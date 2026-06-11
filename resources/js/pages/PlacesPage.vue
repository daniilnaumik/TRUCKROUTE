<template>
    <div>
        <section class="places-intro">
            <div class="container places-intro__inner">
                <div>
                    <span class="places-intro__eyebrow">Дорожная инфраструктура</span>
                    <h1>Объекты на трассе</h1>
                    <p>Найдите на карте АЗС, стоянки, ночлег, кафе и СТО, подходящие для грузового маршрута.</p>
                </div>
                <button v-if="auth.isAuthenticated" class="btn outline places-intro__action" @click="routeMode = !routeMode">
                    {{ routeMode ? 'Отключить маршрут' : 'По маршруту' }}
                </button>
            </div>
        </section>

        <!-- Route selector (when routeMode active) -->
        <section v-if="routeMode && auth.isAuthenticated" class="section-tight">
            <div class="container">
                <h2>Маршрутный режим</h2>
                <p class="lead" style="margin-top:8px;">Показываем объекты вдоль выбранного сохранённого маршрута.</p>
                <div class="field" style="margin-top:16px;max-width:400px;">
                    <label>Выбрать маршрут</label>
                    <select v-model="selectedRouteId" @change="loadPoiAlongRoute">
                        <option value="">— выберите маршрут —</option>
                        <option v-for="r in savedRoutes" :key="r.id" :value="r.id">{{ r.title }}</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="section-tight">
            <div class="container">
                <form class="form-grid" style="margin-top:0;" @submit.prevent>
                    <div class="field">
                        <label>Тип объекта</label>
                        <select v-model="filters.type">
                            <option value="">Все типы</option>
                            <option
                                v-for="item in dictionaries.options('poi_categories')"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Трасса</label>
                        <select v-model="filters.highway">
                            <option value="">Все трассы</option>
                            <option v-for="hw in highways" :key="hw" :value="hw">{{ hw }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Поиск</label>
                        <input v-model="filters.query" type="text" placeholder="Название, услуги...">
                    </div>
                    <div class="field" style="display:flex;align-items:flex-end;">
                        <button type="button" class="btn outline" @click="resetFilters">Сбросить</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Map view -->
        <section class="section-tight">
            <div class="container">
                <div class="places-map-wrap">
                    <MapFallback v-if="mapError" class="places-map" :retry="retryMap" />
                    <div v-show="!mapError" ref="mapEl" class="places-map"></div>
                    <!-- Selected POI panel -->
                    <div v-if="selectedPoi" class="places-map-panel" :style="{ '--poi-color': selectedTypeColor }">
                        <button class="places-map-panel__close" @click="selectedPoi = null">✕</button>
                        <div class="places-map-panel__badges">
                            <span class="badge" :style="typeBadgeStyle(selectedPoi.type)">{{ selectedPoi.type }}</span>
                            <span v-if="selectedPoi.highway">{{ selectedPoi.highway }}</span>
                            <span v-if="selectedPoi.km_marker">{{ selectedPoi.km_marker }} км</span>
                        </div>
                        <p class="places-map-panel__route">{{ selectedPoiRouteLine }}</p>
                        <h3>{{ selectedPoi.name }}</h3>
                        <p class="places-map-panel__description">{{ selectedPoi.description || selectedPoi.services }}</p>
                        <div class="places-map-panel__facts">
                            <div>
                                <span>Координаты</span>
                                <strong>{{ formatCoord(poiLat(selectedPoi)) }}, {{ formatCoord(poiLng(selectedPoi)) }}</strong>
                            </div>
                            <div v-if="selectedPoi.rating">
                                <span>Рейтинг</span>
                                <strong>★ {{ selectedPoi.rating }}</strong>
                            </div>
                            <div v-if="selectedPoi.fuel_price">
                                <span>Топливо</span>
                                <strong>{{ selectedPoi.fuel_price }} ₽/л</strong>
                            </div>
                            <div v-if="selectedPoi.has_truck_parking">
                                <span>Стоянка</span>
                                <strong>Для фур</strong>
                            </div>
                        </div>
                        <div v-if="selectedPoi.services" class="places-map-panel__services">
                            <span>Услуги</span>
                            <p>{{ selectedPoi.services }}</p>
                        </div>
                        <div v-if="selectedServiceList.length" class="places-map-panel__chips">
                            <span v-for="service in selectedServiceList" :key="service">{{ service }}</span>
                        </div>
                        <div v-if="nearestPois.length" class="places-map-panel__nearby">
                            <span>Ближайшие объекты</span>
                            <button
                                v-for="near in nearestPois"
                                :key="near.id"
                                type="button"
                                @click="selectPoi(near)"
                            >
                                <strong>{{ near.name }}</strong>
                                <small>{{ near.type }} · {{ near.distance_km_display }} км</small>
                            </button>
                        </div>
                        <div class="places-map-panel__actions">
                            <button type="button" class="btn outline places-map-panel__open" @click="focusSelectedPoi">
                                Показать на карте
                            </button>
                            <RouterLink :to="{ name: 'place-detail', params: { id: selectedPoi.id } }"
                                class="btn places-map-panel__open">
                                Подробнее
                            </RouterLink>
                        </div>
                    </div>
                </div>
                <!-- Legend -->
                <div class="poi-legend">
                    <span v-for="(color, type) in TYPE_COLORS" :key="type" class="poi-legend-item">
                        <span class="poi-legend-dot" :style="{ background: color }"></span>{{ type }}
                    </span>
                </div>
            </div>
        </section>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useDictionariesStore } from '@/stores/dictionaries';
import { loadYandexMaps } from '@/composables/yandexMaps';
import MapFallback from '@/components/MapFallback.vue';

const auth = useAuthStore();
const dictionaries = useDictionariesStore();

const TYPE_COLORS = {
    'АЗС':     '#c99b3a',
    'Стоянка': '#4a6caa',
    'Ночлег':  '#7a4a9e',
    'СТО':     '#e07030',
    'Кафе':    '#e07030',
};

const allPoi        = ref([]);
const loading       = ref(true);
const routeMode     = ref(false);
const savedRoutes   = ref([]);
const selectedRouteId = ref('');
const activeRoutePolyline = ref([]);
const mapEl         = ref(null);
const mapError      = ref(false);
const selectedPoi   = ref(null);
const filters       = ref({ type: '', highway: '', query: '' });

let ymap = null;
const markerMap = {};   // poi.id → Placemark

const highways = computed(() => {
    const set = new Set(allPoi.value.map(p => p.highway).filter(Boolean));
    return [...set].sort();
});

const filtered = computed(() => {
    let list = allPoi.value;
    const { type, highway, query } = filters.value;
    if (type)    list = list.filter(p => p.type === type);
    if (highway) list = list.filter(p => p.highway === highway);
    if (query) {
        const q = query.toLowerCase();
        list = list.filter(p => [p.name, p.services, p.description].join(' ').toLowerCase().includes(q));
    }
    return list;
});

const nearestPois = computed(() => {
    if (!selectedPoi.value) return [];
    return allPoi.value
        .filter(p => p.id !== selectedPoi.value.id && poiLat(p) && poiLng(p))
        .map(p => ({
            ...p,
            distance_km_display: distanceKm(selectedPoi.value, p).toFixed(1),
        }))
        .sort((a, b) => Number(a.distance_km_display) - Number(b.distance_km_display))
        .slice(0, 4);
});

const selectedTypeColor = computed(() => TYPE_COLORS[selectedPoi.value?.type] ?? '#6a6762');

const selectedPoiRouteLine = computed(() => {
    if (!selectedPoi.value) return '';
    const parts = [
        selectedPoi.value.highway,
        selectedPoi.value.km_marker ? `${selectedPoi.value.km_marker} км` : '',
        selectedPoi.value.location,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : 'Точное место отмечено на карте';
});

const selectedServiceList = computed(() => {
    const raw = selectedPoi.value?.services ?? '';
    return raw
        .split(/[,;•\n]+/)
        .map(item => item.trim())
        .filter(Boolean)
        .slice(0, 6);
});

function resetFilters() { filters.value = { type: '', highway: '', query: '' }; }

function typeBadgeStyle(type) {
    const c = TYPE_COLORS[type] ?? '#6a6762';
    return `background:${c};color:#fff;border-color:${c}`;
}

function poiLat(poi) {
    return poi?.coordinates?.lat ?? poi?.lat ?? null;
}

function poiLng(poi) {
    return poi?.coordinates?.lng ?? poi?.lng ?? null;
}

function formatCoord(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n.toFixed(6) : '—';
}

function distanceKm(a, b) {
    const lat1 = Number(poiLat(a));
    const lng1 = Number(poiLng(a));
    const lat2 = Number(poiLat(b));
    const lng2 = Number(poiLng(b));
    if (![lat1, lng1, lat2, lng2].every(Number.isFinite)) return 999999;
    const toRad = deg => deg * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const s1 = Math.sin(dLat / 2);
    const s2 = Math.sin(dLng / 2);
    const h = s1 * s1 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * s2 * s2;
    return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function selectPoi(poi) {
    selectedPoi.value = poi;
    const lat = poiLat(poi);
    const lng = poiLng(poi);
    if (ymap && lat && lng) {
        ymap.setCenter([lat, lng], Math.max(ymap.getZoom(), 12), { duration: 250 });
    }
}

function focusSelectedPoi() {
    if (!selectedPoi.value) return;
    selectPoi(selectedPoi.value);

    const marker = markerMap[selectedPoi.value.id];
    if (marker?.balloon) {
        marker.balloon.open();
    }
}

// ── Map ──────────────────────────────────────────────────────────────────

function makePinSvg(color) {
    return `data:image/svg+xml;utf8,${encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="34" viewBox="0 0 26 34">
            <path d="M13 0C5.82 0 0 5.82 0 13c0 9.5 13 21 13 21s13-11.5 13-21C26 5.82 20.18 0 13 0z"
                  fill="${color}" stroke="#fff" stroke-width="2"/>
            <circle cx="13" cy="13" r="5" fill="#fff" opacity=".85"/>
    </svg>`)}`;
}

function normalizeRoutePolyline(polyline) {
    if (!Array.isArray(polyline)) return [];

    return polyline
        .map((point) => {
            if (Array.isArray(point)) {
                return [Number(point[0]), Number(point[1])];
            }

            return [Number(point?.lat), Number(point?.lng)];
        })
        .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));
}

async function initMap(pois) {
    if (!mapEl.value) return;
    mapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        mapError.value = true;
        return;
    }
    if (ymap) { ymap.destroy(); ymap = null; }
    Object.keys(markerMap).forEach(k => delete markerMap[k]);

    ymap = new ymaps.Map(mapEl.value, {
        center: [53.9023, 27.5619], zoom: 6,
        controls: ['zoomControl', 'fullscreenControl', 'typeSelector'],
    }, { suppressMapOpenBlock: true });

    const bounds = [];
    const routeCoordinates = normalizeRoutePolyline(activeRoutePolyline.value);

    if (routeCoordinates.length > 1) {
        const routeOutline = new ymaps.Polyline(routeCoordinates, {}, {
            strokeColor: '#171814',
            strokeWidth: 8,
            strokeOpacity: .72,
            zIndex: 120,
        });
        const routeLine = new ymaps.Polyline(routeCoordinates, {}, {
            strokeColor: '#d2a32f',
            strokeWidth: 5,
            strokeOpacity: .96,
            zIndex: 121,
        });

        ymap.geoObjects.add(routeOutline);
        ymap.geoObjects.add(routeLine);
        bounds.push(...routeCoordinates);
    }

    pois.forEach(poi => {
        const lat = poi.coordinates?.lat ?? poi.lat;
        const lng = poi.coordinates?.lng ?? poi.lng;
        if (!lat || !lng) return;

        const color = TYPE_COLORS[poi.type] ?? '#6a6762';
        const placemark = new ymaps.Placemark(
            [lat, lng],
            { hintContent: poi.name, balloonContent: poi.name },
            {
                iconLayout: 'default#image',
                iconImageHref: makePinSvg(color),
                iconImageSize: [26, 34],
                iconImageOffset: [-13, -34],
            },
        );
        placemark.events.add('click', () => {
            selectPoi(poi);
        });
        ymap.geoObjects.add(placemark);
        markerMap[poi.id] = placemark;
        bounds.push([lat, lng]);
    });

    if (bounds.length > 1) {
        const lats = bounds.map(p => p[0]);
        const lngs = bounds.map(p => p[1]);
        ymap.setBounds([[Math.min(...lats), Math.min(...lngs)], [Math.max(...lats), Math.max(...lngs)]],
            { checkZoomRange: true, zoomMargin: 40 });
    }
}

async function retryMap() {
    await nextTick();
    await initMap(filtered.value);
}

// ── Data loading ──────────────────────────────────────────────────────────

onMounted(async () => {
    dictionaries.load();
    try {
        const { data } = await axios.get('/api/v1/poi', {
            params: { bbox: '20,40,80,75', limit: 200 },
        });
        allPoi.value = data.data ?? [];
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
    if (auth.isAuthenticated) {
        try {
            const { data } = await axios.get('/api/v1/routes');
            savedRoutes.value = data.data ?? [];
        } catch { /* ignore */ }
    }
    await nextTick();
    setTimeout(() => initMap(allPoi.value), 100);
});

async function loadPoiAlongRoute() {
    if (!selectedRouteId.value) {
        activeRoutePolyline.value = [];
        await loadDefaultPoi();
        return;
    }

    loading.value = true;
    activeRoutePolyline.value = [];
    try {
        const { data: rData } = await axios.get(`/api/v1/routes/${selectedRouteId.value}`);
        const polyline = normalizeRoutePolyline(rData.data?.route?.polyline ?? rData.route?.polyline ?? []);
        if (!polyline.length) {
            await initMap(filtered.value);
            return;
        }

        activeRoutePolyline.value = polyline;
        const { data } = await axios.get('/api/v1/poi/along-route', {
            params: { polyline: JSON.stringify(polyline) },
        });
        allPoi.value = data.data ?? [];
        await nextTick();
        await initMap(filtered.value);
    } catch { /* ignore */ } finally { loading.value = false; }
}

async function loadDefaultPoi() {
    loading.value = true;
    activeRoutePolyline.value = [];
    try {
        // Wide bbox covering Russia / CIS road network
        const { data } = await axios.get('/api/v1/poi', {
            params: { bbox: '20,40,80,75', limit: 200 },
        });
        allPoi.value = data.data ?? [];
    } catch { /* ignore */ } finally { loading.value = false; }
}

// Re-init map when filters change (update markers)
watch(filtered, (pois) => {
    if (ymap) initMap(pois);
});

watch(routeMode, async (enabled) => {
    if (enabled) return;

    selectedRouteId.value = '';
    selectedPoi.value = null;
    await loadDefaultPoi();
});
</script>

<style scoped>
.places-intro {
    padding: 112px 0 24px;
}

.places-intro__inner {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 36px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
}

.places-intro__eyebrow {
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.places-intro h1 {
    margin: 10px 0 0;
    font-size: clamp(38px, 5vw, 68px);
    line-height: .95;
}

.places-intro p {
    max-width: 720px;
    margin-top: 15px;
    color: var(--text-2);
    font-size: 14px;
    line-height: 1.6;
}

.places-intro__action {
    flex: 0 0 auto;
}

/* Map */
.places-map-wrap {
    position: relative;
    height: 480px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.places-map { width: 100%; height: 100%; }

.places-map-panel {
    position: absolute;
    top: 12px;
    right: 12px;
    width: min(380px, calc(100% - 24px));
    max-height: calc(100% - 24px);
    overflow: auto;
    background: linear-gradient(180deg, rgba(26, 30, 26, .94), rgba(15, 18, 15, .96));
    backdrop-filter: blur(16px);
    border: 1px solid var(--border-mid);
    border-left: 3px solid var(--poi-color, var(--accent));
    border-radius: 10px;
    padding: 16px;
    box-shadow: var(--shadow-lg);
    z-index: 10;
}

.places-map-panel__badges {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    margin: 0 28px 8px 0;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
}

.places-map-panel__badges span:not(.badge) {
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: rgba(255, 255, 255, .035);
}

.places-map-panel__route {
    margin: 0 28px 10px 0;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
    line-height: 1.45;
}

.places-map-panel h3 {
    margin: 0;
    max-width: 310px;
    font-size: 22px;
    line-height: 1.1;
}

.places-map-panel__description {
    margin: 8px 0 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.5;
}

.places-map-panel__facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
}

.places-map-panel__facts div,
.places-map-panel__services,
.places-map-panel__nearby button {
    border: 1px solid var(--border);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.035);
    padding: 9px;
}

.places-map-panel__facts span,
.places-map-panel__services span,
.places-map-panel__nearby > span {
    display: block;
    color: var(--text-3);
    font-size: 10px;
    font-family: var(--font-m);
    text-transform: uppercase;
}

.places-map-panel__facts strong {
    display: block;
    margin-top: 4px;
    color: var(--text);
    font-size: 13px;
    line-height: 1.25;
}

.places-map-panel__services {
    margin-top: 10px;
}

.places-map-panel__services p {
    margin: 5px 0 0;
    color: var(--text-2);
    font-size: 12px;
    line-height: 1.45;
}

.places-map-panel__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.places-map-panel__chips span {
    padding: 5px 8px;
    border: 1px solid color-mix(in srgb, var(--poi-color, var(--accent)) 45%, var(--border));
    border-radius: 4px;
    background: color-mix(in srgb, var(--poi-color, var(--accent)) 12%, transparent);
    color: var(--text-2);
    font-size: 11px;
}

.places-map-panel__nearby {
    display: grid;
    gap: 7px;
    margin-top: 12px;
}

.places-map-panel__nearby button {
    display: block;
    width: 100%;
    min-height: auto;
    color: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: none;
}

.places-map-panel__nearby button:hover {
    border-color: var(--border-mid);
}

.places-map-panel__nearby strong,
.places-map-panel__nearby small {
    display: block;
}

.places-map-panel__nearby strong {
    color: var(--text);
    font-size: 12px;
}

.places-map-panel__nearby small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 11px;
}

.places-map-panel__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 12px;
}

.places-map-panel__open {
    justify-content: center;
    font-size: 13px;
}

.places-map-panel__close {
    position: absolute;
    top: 8px; right: 10px;
    background: none; border: none;
    color: var(--text-3); font-size: 14px; cursor: pointer;
    min-height: auto; box-shadow: none;
}

.poi-type-badge {
    position: absolute;
    top: 8px; left: 8px;
    font-size: 10px;
    padding: 2px 8px;
}

/* Legend */
.poi-legend {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 10px;
    font-size: 12px;
    color: var(--text-2);
}
.poi-legend-item { display: flex; align-items: center; gap: 5px; }
.poi-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

@media (max-width: 768px) {
    .places-intro {
        padding-top: 94px;
    }

    .places-intro__inner {
        display: grid;
        gap: 18px;
    }

    .places-intro__action {
        justify-self: start;
    }

    .places-map-wrap { height: 620px; }
    .places-map-panel {
        top: auto;
        right: 8px;
        bottom: 8px;
        left: 8px;
        width: auto;
        max-height: 330px;
    }
    .places-map-panel__facts { grid-template-columns: 1fr; }
    .places-map-panel__actions { grid-template-columns: 1fr; }
}
</style>
