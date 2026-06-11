<template>
    <div>
        <div v-if="loading" style="padding:80px;text-align:center;color:var(--text-3);">Загрузка маршрута...</div>

        <template v-else-if="route">
            <section class="page-hero route-detail-hero">
                <div class="container">
                    <div class="route-summary-card">
                        <div class="route-summary-top">
                            <div class="route-summary-title">
                                <span class="badge">{{ route.planning_mode || 'Маршрут' }}</span>
                                <h1>{{ routeDisplayTitle }}</h1>
                                <p class="lead">{{ route.origin?.label }} → {{ route.destination?.label }}</p>
                            </div>
                            <div class="route-summary-actions">
                                <RouterLink :to="{ name: 'routes' }" class="btn outline">← К маршрутам</RouterLink>
                                <button v-if="!tracking.isCurrentRouteActive.value" class="btn" @click="startTrip" :disabled="tripStarting">
                                    {{ tripStarting ? 'Запускаем...' : 'Начать поездку' }}
                                </button>
                                <button v-else class="btn route-stop-btn" @click="stopTrip">
                                    Завершить поездку
                                </button>
                            </div>
                        </div>

                        <div class="route-stat-grid">
                            <div class="route-stat"><span>Дистанция</span><strong>{{ route.distance_km }} км</strong></div>
                            <div class="route-stat"><span>В пути</span><strong>{{ driveTimeFormatted }}</strong></div>
                            <div class="route-stat"><span>Топливо</span><strong>{{ route.fuel?.needed_l ?? '—' }} л</strong></div>
                            <div class="route-stat"><span>Остановки</span><strong>{{ route.stops_count ?? 0 }}</strong></div>
                        </div>

                        <div v-if="tracking.isTracking.value" class="trip-monitor">
                            <div class="trip-monitor__head">
                                <span class="trip-monitor__dot" :class="{ 'trip-monitor__dot--muted': !tracking.isWatchingLocation.value }"></span>
                                <strong>{{ tracking.isCurrentRouteActive.value ? 'Поездка активна' : 'Активна другая поездка' }}</strong>
                            </div>
                            <p>{{ tripStatusText }}</p>
                            <p v-if="tracking.currentPos.value" class="trip-monitor__coords">
                                {{ tracking.currentPos.value.lat.toFixed(5) }}, {{ tracking.currentPos.value.lng.toFixed(5) }}
                            </p>
                            <p v-if="tracking.locationError.value" class="trip-monitor__warning">
                                {{ tracking.locationError.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Main content: timeline + map -->
            <section class="section-tight">
                <div class="container route-detail-grid">
                    <!-- Timeline -->
                    <div>
                        <h2>Маршрут</h2>
                        <div class="timeline" style="margin-top:24px;">
                            <!-- Departure -->
                            <div class="timeline-item timeline-item--start">
                                <div class="timeline-dot" style="background:var(--green);"></div>
                                <div class="timeline-content">
                                    <strong>Отправление</strong>
                                    <p>{{ route.origin?.label }}</p>
                                    <p v-if="route.start_time" class="timeline-time">{{ formatTime(route.start_time) }}</p>
                                    <p class="timeline-meta">Топливо: {{ route.fuel?.start_l }} л</p>
                                </div>
                            </div>

                            <!-- Stops -->
                            <div
                                v-for="(stop, i) in route.stops"
                                :key="stop.id"
                                class="timeline-item"
                                :class="[`timeline-item--${stop.type}`]"
                                @click="focusStop(stop, i)"
                                style="cursor:pointer;"
                            >
                                <div class="timeline-dot" :style="{ background: stopColor(stop.type) }"></div>
                                <div class="timeline-content">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <span class="badge" :style="`background:${stopColor(stop.type)};color:#fff`">{{ stopTypeLabel(stop.type) }}</span>
                                        <span style="font-family:var(--font-m);font-size:11px;color:var(--text-3);">{{ stop.distance_from_start_km }} км</span>
                                        <span v-if="stop.detour_km > 0" style="font-size:11px;color:var(--text-3);">+{{ stop.detour_km }} км крюк</span>
                                    </div>
                                    <strong>{{ stop.poi?.name ?? stop.note }}</strong>
                                    <p v-if="stop.poi?.services" style="font-size:12px;color:var(--text-2);margin-top:3px;">{{ stop.poi.services }}</p>
                                    <p v-if="stop.fuel_before_l" class="timeline-meta">Топливо до точки: {{ stop.fuel_before_l }} л</p>
                                    <p v-if="stop.suggested_fuel_l" class="timeline-meta" style="color:var(--accent);">Залить: ~{{ stop.suggested_fuel_l }} л</p>
                                    <p v-if="stop.eta_at" class="timeline-time">ETA: {{ formatTime(stop.eta_at) }}</p>
                                </div>
                            </div>

                            <!-- Arrival -->
                            <div class="timeline-item timeline-item--end">
                                <div class="timeline-dot" style="background:var(--red);"></div>
                                <div class="timeline-content">
                                    <strong>Прибытие</strong>
                                    <p>{{ route.destination?.label }}</p>
                                    <p v-if="route.arrival_time" class="timeline-time">{{ formatTime(route.arrival_time) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Recommendations text -->
                        <div v-if="route.recommendations_text" class="card" style="margin-top:24px;">
                            <h3 style="margin-bottom:10px;">Рекомендации</h3>
                            <p style="font-size:13px;line-height:1.6;">{{ route.recommendations_text }}</p>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="route-map-sticky">
                        <div class="route-map-head">
                            <h2>Карта маршрута</h2>
                            <div class="route-map-actions">
                                <button class="btn outline btn-sm" type="button" @click="fitRoute">Маршрут</button>
                                <button class="btn outline btn-sm" type="button" @click="focusLivePosition" :disabled="locatingPosition">
                                    {{ locatingPosition ? 'Ищем...' : 'Моя позиция' }}
                                </button>
                            </div>
                        </div>
                        <MapFallback v-if="mapError" class="route-map-canvas" :retry="initMap" />
                        <div v-show="!mapError" ref="mapEl" class="route-map-canvas"></div>
                    </div>
                </div>
            </section>
        </template>

        <div v-else style="padding:80px;text-align:center;">
            <h2>Маршрут не найден</h2>
            <RouterLink :to="{ name: 'routes' }" class="btn" style="margin-top:16px;">К маршрутам</RouterLink>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';
import { loadYandexMaps } from '@/composables/yandexMaps';
import { useTripTracking } from '@/composables/useTripTracking';
import MapFallback from '@/components/MapFallback.vue';

const vueRoute = useRoute();
const ui       = useUiStore();

const route        = ref(null);
const loading      = ref(true);
const mapEl        = ref(null);
const tripStarting = ref(false);
const locatingPosition = ref(false);
const mapError = ref(false);
let map = null;
let stopPlacemarks = [];
let liveMarker = null;
let routeBounds = [];

const tracking = useTripTracking(computed(() => route.value?.id));

const driveTimeFormatted = computed(() => {
    const m = route.value?.drive_time_minutes;
    if (!m) return '—';
    const h = Math.floor(m / 60);
    const min = m % 60;
    return h ? `${h} ч ${min} мин` : `${min} мин`;
});

const routeDisplayTitle = computed(() => {
    if (!route.value) return '';

    const origin = compactPlaceLabel(route.value.origin?.label);
    const destination = compactPlaceLabel(route.value.destination?.label);

    if (origin && destination) {
        return `${origin} → ${destination}`;
    }

    return route.value.title ?? 'Маршрут';
});

const tripStatusText = computed(() => {
    if (!tracking.isTracking.value) return '';
    if (!tracking.isCurrentRouteActive.value) {
        return 'На сервере уже есть активная поездка по другому маршруту. Начало этой поездки завершит предыдущую.';
    }
    if (tracking.isWatchingLocation.value) {
        return 'Сайт получает геопозицию и обновляет точку водителя на карте.';
    }
    return 'Поездка запущена. Карта маршрута доступна, но текущая позиция пока не обновляется.';
});

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/v1/routes/${vueRoute.params.id}`);
        route.value = data.data ?? data;
        await tracking.loadCurrent();
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
    await nextTick();
    setTimeout(() => initMap(), 100);
});

async function initMap() {
    if (!mapEl.value || !route.value) return;

    mapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        mapError.value = true;
        return;
    }

    if (map) {
        map.destroy();
        map = null;
        stopPlacemarks = [];
        liveMarker = null;
        routeBounds = [];
    }

    map = new ymaps.Map(mapEl.value, {
        center: [53.9023, 27.5619],
        zoom: 5,
        controls: ['zoomControl', 'fullscreenControl', 'typeSelector'],
    }, { suppressMapOpenBlock: true });

    const polyline = route.value.route?.polyline ?? [];
    const bounds = [];

    if (polyline.length) {
        map.geoObjects.add(new ymaps.Polyline(
            polyline, {},
            { strokeColor: '#916400', strokeWidth: 4, strokeOpacity: 0.85 },
        ));
        bounds.push(...polyline);
    }

    const orig = route.value.origin?.point;
    if (orig?.lat) {
        map.geoObjects.add(new ymaps.Placemark(
            [orig.lat, orig.lng],
            { hintContent: route.value.origin.label, balloonContent: route.value.origin.label },
            makeIcon('#2d7a4f', 'A'),
        ));
        bounds.push([orig.lat, orig.lng]);
    }

    (route.value.stops ?? []).forEach((stop, i) => {
        const poi = stop.poi;
        const lat = poi?.coordinates?.lat ?? poi?.lat;
        const lng = poi?.coordinates?.lng ?? poi?.lng;
        if (lat == null || lng == null) return;
        const placemark = new ymaps.Placemark(
            [lat, lng],
            {
                hintContent: poi.name ?? stop.note,
                balloonContentHeader: poi.name ?? stop.note,
                balloonContentBody: `${stop.distance_from_start_km} км от старта`,
            },
            makeIcon(stopColor(stop.type), String(i + 1)),
        );
        map.geoObjects.add(placemark);
        stopPlacemarks[i] = placemark;
        bounds.push([lat, lng]);
    });

    const dest = route.value.destination?.point;
    if (dest?.lat) {
        map.geoObjects.add(new ymaps.Placemark(
            [dest.lat, dest.lng],
            { hintContent: route.value.destination.label, balloonContent: route.value.destination.label },
            makeIcon('#c0312a', 'B'),
        ));
        bounds.push([dest.lat, dest.lng]);
    }

    if (bounds.length > 1) {
        routeBounds = bounds;
        fitRoute();
    } else {
        map.setView?.([53.9023, 27.5619], 6);
    }

    if (tracking.currentPos.value) {
        updateLiveMarker(tracking.currentPos.value);
    }
}

// ── Live position marker ──────────────────────────────────────────────────
function updateLiveMarker(pos) {
    if (!map) return;
    const coords = [pos.lat, pos.lng];
    if (!liveMarker) {
        liveMarker = new window.ymaps.Placemark(
            coords,
            { hintContent: 'Вы здесь', balloonContent: 'Текущая позиция водителя' },
            {
                iconLayout: 'default#image',
                iconImageHref: `data:image/svg+xml;utf8,${encodeURIComponent(`
                    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 34 34">
                        <circle cx="17" cy="17" r="15" fill="#2d7a4f" stroke="#fff" stroke-width="4" opacity=".95"/>
                        <circle cx="17" cy="17" r="5" fill="#fff"/>
                    </svg>`)}`,
                iconImageSize: [34, 34],
                iconImageOffset: [-17, -17],
            },
        );
        map.geoObjects.add(liveMarker);
    } else {
        liveMarker.geometry.setCoordinates(coords);
    }
}

// Watch current position changes for live marker
watch(tracking.currentPos, (pos) => {
    if (pos) updateLiveMarker(pos);
}, { immediate: true });

// ── Trip controls ─────────────────────────────────────────────────────────
async function startTrip() {
    tripStarting.value = true;
    try {
        const result = await tracking.startTracking();
        if (result?.error) {
            ui.error(result.error);
        } else if (result?.warning) {
            ui.warning({
                title: 'Поездка начата',
                body: result.warning,
            });
        } else {
            ui.success('Поездка начата. Геолокация активирована.');
        }
    } finally {
        tripStarting.value = false;
    }
}

async function stopTrip() {
    await tracking.stopTracking();
    if (liveMarker && map) {
        map.geoObjects.remove(liveMarker);
        liveMarker = null;
    }
    ui.info('Поездка завершена.');
}

// ── Waypoint decisions ────────────────────────────────────────────────────
function onStopAccepted({ stop, waypoint }) {
    // Remove from upcoming list
    tracking.upcomingStops.value = tracking.upcomingStops.value
        .filter(s => s.service_object_id !== stop.service_object_id);

    // Add green pin to map
    if (map && waypoint?.lat) {
        const placemark = new window.ymaps.Placemark(
            [waypoint.lat, waypoint.lng],
            { hintContent: waypoint.label, balloonContent: waypoint.label },
            makeIcon('#2d7a4f', '✓'),
        );
        map.geoObjects.add(placemark);
    }

    ui.success(`"${stop.name}" добавлен в маршрут`);

    // Auto-close sheet if nothing left
    if (!tracking.upcomingStops.value.length && !tracking.systemSuggestions.value.length) {
        tracking.showWaypointSheet.value = false;
    }
}

function onStopRejected(stop) {
    tracking.upcomingStops.value = tracking.upcomingStops.value
        .filter(s => s.service_object_id !== stop.service_object_id);
    tracking.systemSuggestions.value = tracking.systemSuggestions.value
        .filter(s => s.id !== stop.service_object_id);

    if (!tracking.upcomingStops.value.length && !tracking.systemSuggestions.value.length) {
        tracking.showWaypointSheet.value = false;
    }
}

// ── Map helpers ───────────────────────────────────────────────────────────
function fitRoute() {
    if (!map || routeBounds.length < 2) return;

    const lats = routeBounds.map((p) => p[0]);
    const lngs = routeBounds.map((p) => p[1]);
    map.setBounds(
        [[Math.min(...lats), Math.min(...lngs)], [Math.max(...lats), Math.max(...lngs)]],
        { checkZoomRange: true, zoomMargin: 48 },
    );
}

async function requestCurrentPosition() {
    if (!navigator.geolocation) {
        tracking.locationError.value = 'Геолокация не поддерживается браузером.';
        return null;
    }

    locatingPosition.value = true;

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude: lat, longitude: lng, accuracy } = position.coords;
                const pos = { lat, lng, accuracy };
                tracking.currentPos.value = pos;
                tracking.locationError.value = '';
                updateLiveMarker(pos);
                locatingPosition.value = false;
                resolve(pos);
            },
            (error) => {
                tracking.locationError.value = error.message || 'Не удалось получить геопозицию.';
                locatingPosition.value = false;
                resolve(null);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 20_000,
                timeout: 12_000,
            },
        );
    });
}

async function focusLivePosition() {
    if (!map) return;

    const pos = tracking.currentPos.value ?? await requestCurrentPosition();
    if (!pos) {
        ui.warning('Не удалось получить текущую позицию. Проверьте разрешение геолокации в браузере.');
        return;
    }

    updateLiveMarker(pos);

    const coords = [pos.lat, pos.lng];
    const zoom = Math.max(map.getZoom?.() ?? 12, 14);
    map.setCenter(coords, zoom, { checkZoomRange: true, duration: 300 });
    liveMarker?.balloon?.open?.();
}

function compactPlaceLabel(label) {
    if (!label) return '';

    const parts = String(label)
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length <= 2) {
        return parts.join(', ') || label;
    }

    return parts.slice(-2).join(', ');
}

function makeIcon(color, label) {
    const svg = `data:image/svg+xml;utf8,${encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
            <circle cx="16" cy="16" r="13" fill="${color}" stroke="#fff" stroke-width="3"/>
            <text x="16" y="20" text-anchor="middle" font-family="monospace" font-size="11" font-weight="700" fill="#fff">${label}</text>
        </svg>`)}`;
    return { iconLayout: 'default#image', iconImageHref: svg, iconImageSize: [32, 32], iconImageOffset: [-16, -16] };
}

function stopColor(type) {
    const m = { fuel: '#c99b3a', rest: '#4a6caa', overnight: '#7a4a9e', food: '#2d7a4f' };
    return m[type] ?? '#6a6762';
}

function stopTypeLabel(type) {
    const m = { fuel: 'АЗС', rest: 'Отдых', overnight: 'Ночлег', food: 'Питание' };
    return m[type] ?? type;
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('ru', { day: '2-digit', month: '2-digit' })
        + ' ' + d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
}

function focusStop(stop, index) {
    const placemark = stopPlacemarks[index];
    const coords = placemark?.geometry?.getCoordinates?.();
    if (!map || !coords) return;
    map.panTo(coords, { flying: true }).then(() => placemark.balloon.open());
}
</script>

<style scoped>
.route-detail-hero {
    padding: 34px 0;
}

.route-detail-hero .container {
    display: block;
}

.route-summary-card {
    display: grid;
    gap: 22px;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.route-summary-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: start;
}

.route-summary-title {
    min-width: 0;
}

.route-summary-title h1 {
    max-width: 900px;
    margin-top: 12px;
    font-size: clamp(34px, 4.2vw, 58px);
    line-height: 1.02;
    letter-spacing: 0;
}

.route-summary-title .lead {
    max-width: 880px;
    margin-top: 14px;
    font-size: 14px;
    line-height: 1.55;
}

.route-summary-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    max-width: 360px;
}

.route-stop-btn {
    border-color: rgba(192, 49, 42, .35);
    background: rgba(192, 49, 42, .12);
    color: var(--red);
}

.route-stop-btn:hover {
    border-color: rgba(192, 49, 42, .55);
    background: rgba(192, 49, 42, .18);
    color: var(--red);
}

.route-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--bg);
}

.route-stat {
    min-width: 0;
    padding: 18px 20px;
    border-right: 1px solid var(--border);
}

.route-stat:last-child {
    border-right: 0;
}

.route-stat span {
    display: block;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.route-stat strong {
    display: block;
    margin-top: 8px;
    color: var(--accent);
    font-family: var(--font-m);
    font-size: clamp(20px, 2.1vw, 30px);
    font-weight: 500;
    line-height: 1.1;
    overflow-wrap: anywhere;
}

.route-detail-grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 32px;
    align-items: start;
}

.route-map-sticky {
    position: sticky;
    top: var(--header-h, 90px);
}

.route-map-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.route-map-head h2 {
    margin: 0;
}

.route-map-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.route-map-canvas {
    height: 520px;
    overflow: hidden;
    margin-top: 18px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s2);
}

.btn-sm {
    min-height: 34px;
    padding: 8px 12px;
    font-size: 12px;
}

.trip-monitor {
    margin-top: 16px;
    max-width: 520px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.trip-monitor__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.trip-monitor__dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 4px rgba(45, 122, 79, .14);
}

.trip-monitor__dot--muted {
    background: var(--accent);
    box-shadow: 0 0 0 4px rgba(201, 155, 58, .14);
}

.trip-monitor p {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.45;
}

.trip-monitor__coords {
    margin-top: 6px !important;
    color: var(--text-3) !important;
    font-family: var(--font-m);
    font-size: 12px !important;
}

.trip-monitor__warning {
    margin-top: 8px !important;
    color: var(--red) !important;
}

@media (max-width: 900px) {
    .route-summary-top {
        grid-template-columns: 1fr;
    }
    .route-summary-actions {
        justify-content: flex-start;
        max-width: none;
    }
    .route-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .route-stat:nth-child(2n) {
        border-right: 0;
    }
    .route-stat:nth-child(-n+2) {
        border-bottom: 1px solid var(--border);
    }
    .route-detail-grid {
        grid-template-columns: 1fr;
    }
    .route-map-sticky {
        position: static;
        order: -1;
    }
    .route-map-head {
        align-items: flex-start;
        flex-direction: column;
    }
    .route-map-canvas {
        height: 430px;
    }
}

@media (max-width: 560px) {
    .route-summary-card {
        padding: 20px;
    }
    .route-stat-grid {
        grid-template-columns: 1fr;
    }
    .route-stat,
    .route-stat:nth-child(2n) {
        border-right: 0;
    }
    .route-stat:not(:last-child),
    .route-stat:nth-child(-n+2) {
        border-bottom: 1px solid var(--border);
    }
}
</style>

<style>
/* ── Timeline ── */
.timeline { display: flex; flex-direction: column; gap: 0; }

.timeline-item {
    display: flex;
    gap: 16px;
    position: relative;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: 13px;
    top: 28px;
    bottom: 0;
    width: 2px;
    background: var(--border);
}
.timeline-item:last-child::before { display: none; }

.timeline-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 2px solid var(--bg);
    box-shadow: 0 0 0 2px var(--border-mid);
    margin-top: 2px;
}

.timeline-content { flex: 1; padding-top: 2px; }
.timeline-content strong { font-size: 13px; color: var(--text); display: block; margin-bottom: 3px; }
.timeline-content p { font-size: 12px; color: var(--text-2); margin: 2px 0 0; }
.timeline-time { font-family: var(--font-m); font-size: 11px; color: var(--accent) !important; }
.timeline-meta { color: var(--text-3) !important; font-size: 11px !important; font-family: var(--font-m); }
</style>
