import { ref, onUnmounted } from 'vue';
import axios from 'axios';
import { loadYandexMaps } from './yandexMaps';

/**
 * Wizard map на Яндекс JS API 2.1.
 * init/setOrigin/setDestination/setWaypoints/fetchAndDrawRoute/
 * drawResultStops/clearRoute/enableClickPicking/destroy
 * + POI along route with click handling, filtering, recommendations.
 */

const ICON_COLORS = {
    origin:      '#2d7a4f',
    destination: '#c0312a',
    waypoint:    '#916400',
    fuel:        '#c99b3a',
    rest:        '#4a6caa',
    overnight:   '#7a4a9e',
    service:     '#e07030',
    accepted:    '#2d7a4f',
};

const POI_TYPE_COLORS = {
    'АЗС':     '#c99b3a',
    'Стоянка': '#4a6caa',
    'Ночлег':  '#7a4a9e',
    'СТО':     '#e07030',
    'Кафе':    '#e07030',
    'Еда':     '#e07030',
};

const POI_TYPE_LABELS = {
    'АЗС':     '⛽',
    'Стоянка': 'P',
    'Ночлег':  '🌙',
    'СТО':     '🔧',
    'Кафе':    '☕',
    'Еда':     '☕',
};

function makeIconPreset(color, label) {
    const svg = `data:image/svg+xml;utf8,${encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <circle cx="16" cy="16" r="13" fill="${color}" stroke="#fff" stroke-width="3"/>
            <text x="16" y="20" text-anchor="middle" font-family="monospace" font-size="11" font-weight="700" fill="#fff">${label}</text>
        </svg>`)}`;
    return { iconLayout: 'default#image', iconImageHref: svg, iconImageSize: [32, 32], iconImageOffset: [-16, -16] };
}

/**
 * Interpolate points between reference points every ~30 km.
 * Ensures POI search covers entire corridor, not just endpoints.
 */
function interpolatePoints(points, stepKm = 50) {
    if (points.length < 2) return points;
    const result = [];
    for (let i = 0; i < points.length - 1; i++) {
        const a = points[i];
        const b = points[i + 1];
        result.push(a);

        // Approximate distance in km (1° lat ≈ 111 km)
        const dLat = (b.lat - a.lat);
        const dLng = (b.lng - a.lng);
        const distKm = Math.sqrt(dLat * dLat + dLng * dLng) * 111;

        const segments = Math.max(1, Math.ceil(distKm / stepKm));
        for (let s = 1; s < segments; s++) {
            const t = s / segments;
            result.push({
                lat: a.lat + dLat * t,
                lng: a.lng + dLng * t,
            });
        }
    }
    result.push(points[points.length - 1]);
    return result;
}

function normalizePolyline(points) {
    return (points ?? [])
        .map((point) => {
            if (Array.isArray(point) && point.length >= 2) {
                return { lat: Number(point[0]), lng: Number(point[1]) };
            }
            if (point && point.lat != null && point.lng != null) {
                return { lat: Number(point.lat), lng: Number(point.lng) };
            }
            return null;
        })
        .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
}

export function useWizardMap(mapEl) {
    const ready      = ref(false);
    const isPicking  = ref(false);
    const loadedPois = ref([]);     // POI loaded along route
    const mapError   = ref(false);

    let map              = null;
    let markers          = {};       // key → ymaps.Placemark
    let poiMarkers       = [];       // { placemark, poi } objects
    let routeLine        = null;
    let clickHandler     = null;
    let lastPolyline     = null;
    let lastRefPoints    = null;     // raw reference points for fallback
    let poiClickCb       = null;     // callback when POI marker clicked
    let resizeObserver   = null;

    async function init() {
        if (!mapEl.value) return;
        if (map) {
            refresh();
            return;
        }
        mapError.value = false;
        let ymaps;
        try {
            ymaps = await loadYandexMaps();
        } catch {
            ready.value = false;
            mapError.value = true;
            return;
        }

        map = new ymaps.Map(mapEl.value, {
            center: [53.9023, 27.5619],
            zoom: 5,
            controls: ['zoomControl', 'fullscreenControl', 'typeSelector'],
        }, { suppressMapOpenBlock: true });

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => {
                if (!map || !mapEl.value?.offsetWidth || !mapEl.value?.offsetHeight) return;
                map.container.fitToViewport();
            });
            resizeObserver.observe(mapEl.value);
        }

        ready.value = true;
        refresh();
    }

    function refresh() {
        if (!map) return;
        map.container.fitToViewport();
        fitBounds();
    }

    // ── Click-to-place ────────────────────────────────────────────────────

    function enableClickPicking(callback) {
        if (!map) return;
        disableClickPicking();
        isPicking.value = true;
        if (mapEl.value) mapEl.value.style.cursor = 'crosshair';

        clickHandler = (e) => {
            const [lat, lng] = e.get('coords');
            disableClickPicking();
            callback(lat, lng);
        };
        map.events.add('click', clickHandler);
    }

    function disableClickPicking() {
        if (!map) return;
        isPicking.value = false;
        if (mapEl.value) mapEl.value.style.cursor = '';
        if (clickHandler) {
            map.events.remove('click', clickHandler);
            clickHandler = null;
        }
    }

    // ── Markers ───────────────────────────────────────────────────────────

    function removeMarker(key) {
        if (markers[key]) {
            map.geoObjects.remove(markers[key]);
            delete markers[key];
        }
    }

    function setPoint(key, point, label, color) {
        if (!map) return;
        removeMarker(key);
        if (!point?.lat || !point?.lng) return;
        const placemark = new window.ymaps.Placemark(
            [point.lat, point.lng],
            { hintContent: point.label ?? label, balloonContent: point.label ?? label },
            makeIconPreset(color, label),
        );
        map.geoObjects.add(placemark);
        markers[key] = placemark;
        fitBounds();
    }

    function setOrigin(pt)      { setPoint('origin', pt, 'A', ICON_COLORS.origin); }
    function setDestination(pt) { setPoint('dest',   pt, 'Б', ICON_COLORS.destination); }

    function setWaypoints(wps) {
        Object.keys(markers).filter(k => k.startsWith('wp_')).forEach(removeMarker);
        (wps ?? []).forEach((wp, i) => {
            if (wp?.lat && wp?.lng) {
                setPoint(`wp_${i}`, wp, String(i + 1), ICON_COLORS.waypoint);
            }
        });
        fitBounds();
    }

    // ── Route ─────────────────────────────────────────────────────────────

    async function fetchAndDrawRoute(origin, destination, via = []) {
        if (!map || !origin?.lat || !destination?.lat) return;

        clearRoute();

        const referencePoints = [
            [origin.lat, origin.lng],
            ...via.filter(Boolean).filter(p => p.lat && p.lng).map(p => [p.lat, p.lng]),
            [destination.lat, destination.lng],
        ];
        lastRefPoints = referencePoints;

        const fallbackPolyline = interpolatePoints(normalizePolyline(referencePoints));
        drawRouteLine(fallbackPolyline);
        fetchAndShowPoi(fallbackPolyline);

        try {
            const { data } = await axios.post('/api/v1/geo/route', {
                from: { lat: origin.lat, lng: origin.lng },
                to: { lat: destination.lat, lng: destination.lng },
                via: via.filter(Boolean).filter(p => p.lat && p.lng).map(p => ({ lat: p.lat, lng: p.lng })),
            }, { silent: true });

            const roadPolyline = normalizePolyline(data.polyline);
            if (roadPolyline.length >= 2) {
                drawRouteLine(roadPolyline);
                fetchAndShowPoi(roadPolyline);
            }
        } catch (e) {
            console.warn('backend route failed, using fallback polyline:', e);
        }
    }

    function drawRouteLine(points) {
        if (!map || !points?.length) return;
        if (routeLine) {
            map.geoObjects.remove(routeLine);
            routeLine = null;
        }
        routeLine = new window.ymaps.Polyline(
            points.map(point => [point.lat, point.lng]), {},
            { strokeColor: '#916400', strokeWidth: 4, strokeOpacity: 0.85 },
        );
        map.geoObjects.add(routeLine);
        map.setBounds(routeLine.geometry.getBounds(), { checkZoomRange: true, zoomMargin: 40 });
    }

    function drawPolyline(coords) {
        const points = normalizePolyline(coords);
        if (!points.length) return;
        clearRoute();
        drawRouteLine(points);
    }

    function drawResultStops(stops) {
        if (!map) return;
        Object.keys(markers).filter(k => k.startsWith('stop_')).forEach(removeMarker);

        (stops ?? []).forEach((stop, i) => {
            const poi = stop.poi;
            const lat = poi?.coordinates?.lat ?? poi?.lat;
            const lng = poi?.coordinates?.lng ?? poi?.lng;
            if (lat == null || lng == null) return;
            const color = stop.type === 'fuel' ? ICON_COLORS.fuel
                : stop.type === 'overnight'    ? ICON_COLORS.overnight
                : ICON_COLORS.rest;
            const placemark = new window.ymaps.Placemark(
                [lat, lng],
                {
                    hintContent: poi.name,
                    balloonContentHeader: poi.name,
                    balloonContentBody: `${stop.type} · ${stop.distance_from_start_km} км от старта`,
                },
                makeIconPreset(color, String(i + 1)),
            );
            map.geoObjects.add(placemark);
            markers[`stop_${i}`] = placemark;
        });
    }

    function fitBounds() {
        const coords = Object.values(markers).map(m => m.geometry.getCoordinates()).filter(Boolean);
        if (coords.length >= 2) {
            const lats = coords.map(c => c[0]);
            const lngs = coords.map(c => c[1]);
            const bounds = [[Math.min(...lats), Math.min(...lngs)], [Math.max(...lats), Math.max(...lngs)]];
            map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 60 });
        } else if (coords.length === 1) {
            map.setCenter(coords[0], 10);
        }
    }

    function clearRoute() {
        if (routeLine) {
            map.geoObjects.remove(routeLine);
            routeLine = null;
        }
        Object.keys(markers).filter(k => k.startsWith('stop_')).forEach(removeMarker);
    }

    // ── POI along route ───────────────────────────────────────────────

    function clearPoiMarkers() {
        poiMarkers.forEach(({ placemark }) => { if (map) map.geoObjects.remove(placemark); });
        poiMarkers = [];
    }

    function makePinSvg(color, label, size = 28, h = 36) {
        return `data:image/svg+xml;utf8,${encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${h}" viewBox="0 0 ${size} ${h}">
                <path d="M${size/2} 0C${size*0.224} 0 0 ${size*0.224} 0 ${size/2}c0 ${h*0.58} ${size/2} ${h*0.67} ${size/2} ${h*0.67}s${size/2}-${h*0.09} ${size/2}-${h*0.67}C${size} ${size*0.224} ${size*0.776} 0 ${size/2} 0z"
                      fill="${color}" stroke="#fff" stroke-width="2"/>
                <text x="${size/2}" y="${size*0.64}" text-anchor="middle" font-family="system-ui" font-size="${Math.round(size*0.43)}"
                      font-weight="700" fill="#fff">${label}</text>
            </svg>`)}`;
    }

    /** Recommended marker: larger with gold border */
    function makePinSvgRecommended(color, label) {
        return `data:image/svg+xml;utf8,${encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 34 44">
                <path d="M17 0C7.6 0 0 7.6 0 17c0 12 17 27 17 27s17-15 17-27C34 7.6 26.4 0 17 0z"
                      fill="${color}" stroke="#c99b3a" stroke-width="3"/>
                <text x="17" y="22" text-anchor="middle" font-family="system-ui" font-size="14"
                      font-weight="700" fill="#fff">${label}</text>
                <text x="28" y="10" font-size="10" fill="#c99b3a">★</text>
            </svg>`)}`;
    }

    /**
     * Set callback for when user clicks a POI marker on the map.
     */
    function setPoiClickHandler(callback) {
        poiClickCb = callback;
    }

    /**
     * Fetch and display POI along the route polyline.
     * Returns the loaded POI array for external use.
     * @param {Object} opts — { recommendFuel, recommendRest } for highlighting
     */
    async function fetchAndShowPoi(polylinePoints, acceptedIds = [], opts = {}) {
        if (!map || !polylinePoints?.length) {
            console.warn('[WizardMap] fetchAndShowPoi skipped: map=', !!map, 'points=', polylinePoints?.length);
            return [];
        }

        clearPoiMarkers();
        lastPolyline = polylinePoints;

        try {
            // POST to avoid URL length limits with many polyline points
            const { data } = await axios.post('/api/v1/poi/along-route', {
                polyline: JSON.stringify(polylinePoints),
                corridor_km: 30,
            });
            const items = data.data ?? [];
            loadedPois.value = items;

            items.forEach(poi => {
                const coords = poi.coordinates;
                if (!coords?.lat) return;

                const isAccepted = acceptedIds.includes(poi.id);
                const isRecommended = checkRecommended(poi, opts);

                const color = isAccepted ? ICON_COLORS.accepted
                    : (POI_TYPE_COLORS[poi.type] ?? '#6a6762');
                const label = isAccepted ? '✓' : (POI_TYPE_LABELS[poi.type] ?? '●');

                const href = isRecommended && !isAccepted
                    ? makePinSvgRecommended(color, label)
                    : makePinSvg(color, label);

                const iconSize = isRecommended && !isAccepted ? [34, 44] : [28, 36];
                const iconOffset = isRecommended && !isAccepted ? [-17, -44] : [-14, -36];

                const placemark = new window.ymaps.Placemark(
                    [coords.lat, coords.lng],
                    {
                        hintContent: poi.name + (isRecommended ? ' ★ Рекомендуем' : ''),
                    },
                    {
                        iconLayout: 'default#image',
                        iconImageHref: href,
                        iconImageSize: iconSize,
                        iconImageOffset: iconOffset,
                    },
                );

                // Click handler → popup
                placemark.events.add('click', () => {
                    if (poiClickCb) poiClickCb(poi);
                });

                map.geoObjects.add(placemark);
                poiMarkers.push({ placemark, poi, isRecommended });
            });

            return items;
        } catch { /* ignore */ return []; }
    }

    /** Check if POI should be highlighted as recommended */
    function checkRecommended(poi, opts = {}) {
        if (poi.rating && poi.rating >= 4.5) return true;
        if (poi.type === 'АЗС' && opts.recommendFuel) return true;
        if ((poi.type === 'Стоянка' || poi.type === 'Ночлег') && opts.recommendRest) return true;
        return false;
    }

    /**
     * Show/hide POI markers based on selection and eye toggle.
     */
    function filterPoiMarkers(selectedIds, showOnlySelected) {
        poiMarkers.forEach(({ placemark, poi }) => {
            const isSelected = selectedIds.includes(poi.id);
            if (showOnlySelected && !isSelected) {
                placemark.options.set('visible', false);
            } else {
                placemark.options.set('visible', true);
            }
        });
    }

    /**
     * Refresh markers to reflect accepted state (green pins for selected).
     * Falls back to interpolated reference points if no polyline stored.
     */
    function refreshPoiMarkers(acceptedIds = [], opts = {}) {
        if (lastPolyline && lastPolyline.length >= 2) {
            fetchAndShowPoi(lastPolyline, acceptedIds, opts);
        } else if (lastRefPoints && lastRefPoints.length >= 2) {
            const poly = interpolatePoints(lastRefPoints.map(p => ({ lat: p[0], lng: p[1] })));
            fetchAndShowPoi(poly, acceptedIds, opts);
        }
    }

    function destroy() {
        disableClickPicking();
        clearPoiMarkers();
        resizeObserver?.disconnect();
        resizeObserver = null;
        if (map) { map.destroy(); map = null; }
        markers      = {};
        routeLine    = null;
        lastPolyline = null;
        poiClickCb   = null;
        loadedPois.value = [];
        ready.value     = false;
        isPicking.value = false;
    }

    onUnmounted(destroy);

    return {
        ready, isPicking, loadedPois, mapError,
        init,
        refresh,
        setOrigin, setDestination, setWaypoints,
        fetchAndDrawRoute, drawPolyline, drawResultStops,
        fetchAndShowPoi, clearPoiMarkers, refreshPoiMarkers,
        filterPoiMarkers, setPoiClickHandler,
        clearRoute, destroy,
        enableClickPicking, disableClickPicking,
    };
}
