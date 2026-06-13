<template>
    <div>
        <section class="provider-intro">
            <div class="container provider-intro__inner">
                <div class="provider-intro__copy">
                    <span class="provider-intro__eyebrow">Кабинет поставщика</span>
                    <h1>Объекты и их эффективность</h1>
                    <p>Добавляйте точки дорожного сервиса, обновляйте информацию и отслеживайте, как водители находят и выбирают их во время поездок.</p>
                </div>
                <div class="provider-intro__actions">
                    <RouterLink :to="{ name: 'provider-poi-new' }" class="btn">+ Добавить объект</RouterLink>
                    <div class="provider-tabs" aria-label="Разделы кабинета">
                        <button
                            class="provider-tab"
                            :class="{ 'is-active': activeTab === 'analytics' }"
                            @click="activeTab = 'analytics'"
                        >
                            Аналитика
                        </button>
                        <button
                            class="provider-tab"
                            :class="{ 'is-active': activeTab === 'objects' }"
                            @click="activeTab = 'objects'"
                        >
                            Объекты
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Analytics tab -->
        <template v-if="activeTab === 'analytics'">
            <!-- KPI cards -->
            <section class="section-tight">
                <div class="container">
                    <div class="summary-heading">
                        <div>
                            <span>Текущие показатели</span>
                            <h2>Сводка</h2>
                        </div>
                        <p>Данные рассчитаны по вашим объектам, просмотрам карточек, решениям водителей и опубликованным отзывам.</p>
                    </div>
                    <div v-if="analyticsLoading" class="grid-4 equal-card-grid" style="margin-top:28px;">
                        <div class="card skeleton" v-for="i in 4" :key="i" style="height:90px;"></div>
                    </div>
                    <div v-else class="grid-4 equal-card-grid" style="margin-top:28px;">
                        <div class="card kpi-card">
                            <span class="kpi-val">{{ analytics?.summary?.total_poi ?? 0 }}</span>
                            <span class="kpi-lbl">Объектов</span>
                        </div>
                        <div class="card kpi-card">
                            <span class="kpi-val" style="color:var(--green)">{{ analytics?.summary?.total_accepts ?? 0 }}</span>
                            <span class="kpi-lbl">Принятий</span>
                        </div>
                        <div class="card kpi-card">
                            <span class="kpi-val">{{ analytics?.summary?.total_reviews ?? 0 }}</span>
                            <span class="kpi-lbl">Отзывов</span>
                        </div>
                        <div class="card kpi-card">
                            <span class="kpi-val" style="color:var(--accent)">
                                {{ analytics?.summary?.avg_rating ? analytics.summary.avg_rating.toFixed(1) : '—' }}
                            </span>
                            <span class="kpi-lbl">Ср. рейтинг</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Chart -->
            <section class="section-tight">
                <div class="container">
                    <h2>Принятия за 30 дней</h2>
                    <div class="chart-wrap" style="margin-top:24px;">
                        <svg v-if="chartPoints.length" class="line-chart" viewBox="0 0 600 120" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--accent)" stop-opacity=".35"/>
                                    <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <polygon :points="areaPoints" fill="url(#chartGrad)"/>
                            <polyline :points="chartPoints" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round"/>
                            <circle v-for="pt in dotPoints" :key="pt.x" :cx="pt.x" :cy="pt.y" r="3" fill="var(--accent)">
                                <title>{{ pt.label }}</title>
                            </circle>
                        </svg>
                        <div v-else-if="!analyticsLoading" class="card" style="padding:32px;text-align:center;color:var(--text-3);font-size:13px;">
                            Данных за последние 30 дней нет
                        </div>
                        <div v-if="chartPoints.length" class="chart-labels">
                            <span v-for="lbl in chartDateLabels" :key="lbl">{{ lbl }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Per-POI table -->
            <section class="section-tight">
                <div class="container">
                    <h2>По объектам</h2>
                    <div style="margin-top:20px;overflow-x:auto;">
                        <table class="poi-table" v-if="analytics?.poi_stats?.length">
                            <thead>
                                <tr>
                                    <th>Объект</th>
                                    <th>Тип</th>
                                    <th>Просмотры</th>
                                    <th>Принятия</th>
                                    <th>Отказы</th>
                                    <th>Рейтинг</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in analytics.poi_stats" :key="p.id" @click="selectPoiDetail(p)" class="poi-row">
                                    <td><strong>{{ p.name }}</strong></td>
                                    <td><span class="badge" :style="typeBadgeStyle(p.type)">{{ p.type }}</span></td>
                                    <td>{{ p.view_count }}</td>
                                    <td style="color:var(--green)">{{ p.accepts }}</td>
                                    <td style="color:var(--red)">{{ p.rejects }}</td>
                                    <td>{{ p.rating ? '⭐ ' + p.rating : '—' }}</td>
                                    <td>
                                        <span class="badge" :class="p.verified ? 'badge--green' : ''">
                                            {{ p.verified ? 'Верифицирован' : p.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else-if="!analyticsLoading" class="card" style="padding:24px;text-align:center;color:var(--text-3);font-size:13px;">
                            Объектов нет или данных ещё недостаточно
                        </div>
                    </div>
                </div>
            </section>

            <!-- POI Detail modal -->
            <Teleport to="body">
                <div v-if="poiDetail" class="auth-modal is-open">
                    <div class="auth-modal__backdrop" @click="poiDetail = null"></div>
                    <div class="auth-modal__panel provider-detail-modal">
                        <button class="auth-modal__close" @click="poiDetail = null">закрыть</button>
                        <div class="provider-detail-head">
                            <span class="badge" :style="typeBadgeStyle(poiDetail.type)">{{ poiDetail.type }}</span>
                            <span>Статистика рекомендаций</span>
                            <h2>{{ poiDetail.name }}</h2>
                            <p>Как водители реагировали, когда этот объект предлагался им по пути.</p>
                        </div>

                        <div class="provider-detail-total">
                            <span>Всего решений</span>
                            <strong>{{ poiDecisionTotal }}</strong>
                        </div>

                        <div class="provider-detail-stats">
                            <div>
                                <span class="provider-detail-dot is-accepted"></span>
                                <strong>{{ poiDetail.accepts }}</strong>
                                <div>
                                    <b>Выбрали объект</b>
                                    <small>Водитель принял рекомендацию</small>
                                </div>
                            </div>
                            <div>
                                <span class="provider-detail-dot is-rejected"></span>
                                <strong>{{ poiDetail.rejects }}</strong>
                                <div>
                                    <b>Пропустили</b>
                                    <small>Водитель отклонил предложение</small>
                                </div>
                            </div>
                        </div>

                        <div v-if="poiDecisionTotal" class="provider-detail-result">
                            <div class="provider-detail-result__head">
                                <span>Доля принятых рекомендаций</span>
                                <strong>{{ donutAccept }}%</strong>
                            </div>
                            <div class="provider-detail-progress">
                                <span :style="{ width: `${donutAccept}%` }"></span>
                            </div>
                            <p>{{ providerResultText }}</p>
                        </div>
                        <div v-else class="provider-detail-empty">
                            Водители ещё не принимали решений по этому объекту. Статистика появится после первых рекомендаций.
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>

        <!-- Objects tab -->
        <template v-if="activeTab === 'objects'">
            <section class="section-tight">
                <div class="container">
                    <h2>Мои объекты</h2>
                    <div v-if="loading" class="grid-3 equal-card-grid" style="margin-top:36px;">
                        <div class="card skeleton" v-for="i in 3" :key="i" style="height:160px;"></div>
                    </div>
                    <div v-else-if="!pois.length" class="card" style="margin-top:36px;">
                        <h3>Объектов нет</h3>
                        <p>Добавьте первый объект для его публикации в каталоге.</p>
                    </div>
                    <div v-else class="grid-3 equal-card-grid" style="margin-top:36px;">
                        <article v-for="p in pois" :key="p.id" class="card">
                            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                                <span class="badge" :style="typeBadgeStyle(p.type)">{{ p.type }}</span>
                                <span style="font-size:11px;color:var(--text-3);">{{ p.status }}</span>
                                <span v-if="p.verified" class="badge badge--green" style="font-size:10px;">✓</span>
                            </div>
                            <h3>{{ p.name }}</h3>
                            <p style="font-size:12px;color:var(--text-2);">{{ p.location }}</p>
                            <p style="font-size:11px;color:var(--text-3);margin-top:4px;font-family:var(--font-m);">
                                👁 {{ p.view_count }} · ✓ {{ p.selections_count ?? 0 }}
                            </p>
                            <div class="actions" style="margin-top:12px;">
                                <RouterLink :to="{ name: 'provider-poi-edit', params: { id: p.id } }" class="btn outline" style="font-size:12px;padding:4px 10px;">Редактировать</RouterLink>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </template>

        <!-- Create/Edit modal -->
        <Teleport to="body">
            <div v-if="showCreate" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="closeForm"></div>
                <div class="auth-modal__panel" style="max-width:600px;width:90vw;">
                    <button class="auth-modal__close" @click="closeForm">закрыть</button>
                    <span class="badge">{{ editPoi ? 'редактировать' : 'новый объект' }}</span>
                    <h2 style="margin-top:16px;">{{ editPoi ? editPoi.name : 'Добавить объект' }}</h2>
                    <form class="form-grid" style="margin-top:20px;" @submit.prevent="savePoi">
                        <div class="field">
                            <label>Название</label>
                            <input v-model="form.name" required>
                        </div>
                        <div class="field">
                            <label>Тип</label>
                            <select v-model="form.type">
                                <option
                                    v-for="item in dictionaries.options('poi_categories')"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                        </div>
                        <div class="field" style="grid-column:1/-1;">
                            <label>Адрес / Место</label>
                            <input v-model="form.location" required>
                        </div>
                        <div class="field">
                            <label>Широта (lat)</label>
                            <input v-model.number="form.lat" type="number" step="0.000001">
                        </div>
                        <div class="field">
                            <label>Долгота (lng)</label>
                            <input v-model.number="form.lng" type="number" step="0.000001">
                        </div>
                        <div class="field" style="grid-column:1/-1;">
                            <label>Услуги</label>
                            <input v-model="form.services" placeholder="Душ, кафе, парковка, охрана...">
                        </div>
                        <div class="field" style="grid-column:1/-1;">
                            <label>Описание</label>
                            <textarea v-model="form.description" rows="3"></textarea>
                        </div>
                        <div v-if="formError" class="field" style="grid-column:1/-1;">
                            <p style="color:var(--red);font-size:13px;">{{ formError }}</p>
                        </div>
                        <div class="actions" style="grid-column:1/-1;">
                            <button type="submit" class="btn" :disabled="saving">{{ saving ? 'Сохраняем...' : 'Сохранить' }}</button>
                            <button type="button" class="btn outline" @click="closeForm">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';
import { useDictionariesStore } from '@/stores/dictionaries';

const ui = useUiStore();
const dictionaries = useDictionariesStore();

const activeTab = ref('analytics');

// ── Objects ───────────────────────────────────────────────────────────────
const pois        = ref([]);
const loading     = ref(true);
const showCreate  = ref(false);
const editPoi     = ref(null);
const saving      = ref(false);
const formError   = ref('');

const blankForm = () => ({ name: '', type: 'АЗС', location: '', lat: '', lng: '', services: '', description: '' });
const form = ref(blankForm());

watch(editPoi, (p) => {
    form.value = p
        ? { name: p.name, type: p.type, location: p.location, lat: p.lat, lng: p.lng, services: p.services, description: p.description }
        : blankForm();
});

function closeForm() { showCreate.value = false; editPoi.value = null; formError.value = ''; }

// ── Analytics ─────────────────────────────────────────────────────────────
const analytics        = ref(null);
const analyticsLoading = ref(true);
const poiDetail        = ref(null);

const TYPE_COLORS = { 'АЗС': '#c99b3a', 'Стоянка': '#4a6caa', 'Ночлег': '#7a4a9e', 'СТО': '#e07030', 'Кафе': '#e07030' };

function typeBadgeStyle(type) {
    const c = TYPE_COLORS[type] ?? '#6a6762';
    return `background:${c};color:#fff;border-color:${c}`;
}

function selectPoiDetail(p) { poiDetail.value = p; }

const donutAccept = computed(() => {
    if (!poiDetail.value) return 0;
    const total = (poiDetail.value.accepts + poiDetail.value.rejects) || 1;
    return Math.round((poiDetail.value.accepts / total) * 100);
});
const poiDecisionTotal = computed(() => {
    if (!poiDetail.value) return 0;
    return Number(poiDetail.value.accepts || 0) + Number(poiDetail.value.rejects || 0);
});
const providerResultText = computed(() => {
    if (!poiDecisionTotal.value) return '';
    if (donutAccept.value >= 70) return 'Объект хорошо подходит водителям и часто выбирается по пути.';
    if (donutAccept.value >= 40) return 'Рекомендация полезна части водителей. Стоит уточнить услуги, цены и описание объекта.';
    return 'Объект выбирают редко. Проверьте актуальность информации и соответствие маршрутам водителей.';
});

// ── SVG chart ─────────────────────────────────────────────────────────────
const W = 600; const H = 120; const PAD = 10;

const chartData = computed(() => analytics.value?.selections_by_day ?? []);

const chartPoints = computed(() => {
    const d = chartData.value;
    if (!d.length) return '';
    const maxVal = Math.max(1, ...d.map(r => r.count));
    return d.map((r, i) => {
        const x = PAD + (i / Math.max(d.length - 1, 1)) * (W - 2 * PAD);
        const y = H - PAD - (r.count / maxVal) * (H - 2 * PAD);
        return `${x},${y}`;
    }).join(' ');
});

const areaPoints = computed(() => {
    const d = chartData.value;
    if (!d.length) return '';
    const maxVal = Math.max(1, ...d.map(r => r.count));
    const pts = d.map((r, i) => {
        const x = PAD + (i / Math.max(d.length - 1, 1)) * (W - 2 * PAD);
        const y = H - PAD - (r.count / maxVal) * (H - 2 * PAD);
        return `${x},${y}`;
    });
    const firstX = PAD;
    const lastX  = W - PAD;
    return `${firstX},${H - PAD} ${pts.join(' ')} ${lastX},${H - PAD}`;
});

const dotPoints = computed(() => {
    const d = chartData.value;
    if (!d.length) return [];
    const maxVal = Math.max(1, ...d.map(r => r.count));
    return d.map((r, i) => ({
        x: PAD + (i / Math.max(d.length - 1, 1)) * (W - 2 * PAD),
        y: H - PAD - (r.count / maxVal) * (H - 2 * PAD),
        label: `${r.date}: ${r.count}`,
    }));
});

const chartDateLabels = computed(() => {
    const d = chartData.value;
    if (!d.length) return [];
    const step = Math.max(1, Math.floor(d.length / 5));
    return d.filter((_, i) => i % step === 0 || i === d.length - 1)
        .map(r => r.date.slice(5)); // MM-DD
});

// ── Load data ─────────────────────────────────────────────────────────────
onMounted(async () => {
    dictionaries.load();
    await Promise.all([loadPois(), loadAnalytics()]);
});

async function loadPois() {
    try {
        const { data } = await axios.get('/api/v1/provider/poi');
        pois.value = data.data ?? [];
    } catch { /* ignore */ } finally { loading.value = false; }
}

async function loadAnalytics() {
    try {
        const { data } = await axios.get('/api/v1/provider/analytics');
        analytics.value = data.data ?? null;
    } catch { /* ignore */ } finally { analyticsLoading.value = false; }
}

async function savePoi() {
    saving.value = true;
    formError.value = '';
    try {
        if (editPoi.value) {
            const { data } = await axios.put(`/api/v1/provider/poi/${editPoi.value.id}`, form.value);
            const idx = pois.value.findIndex(p => p.id === editPoi.value.id);
            if (idx !== -1) pois.value[idx] = data.data ?? data;
        } else {
            const { data } = await axios.post('/api/v1/provider/poi', form.value);
            pois.value.unshift(data.data ?? data);
        }
        ui.success('Объект сохранён');
        closeForm();
        loadAnalytics();
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Ошибка сохранения';
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.kpi-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 20px 12px;
    text-align: center;
}

.provider-intro {
    padding: 58px 0 18px;
}

.provider-intro__inner {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 48px;
    padding-bottom: 34px;
    border-bottom: 1px solid var(--border);
}

.provider-intro__copy {
    max-width: 720px;
}

.provider-intro__eyebrow,
.summary-heading span {
    display: block;
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.provider-intro h1 {
    max-width: 680px;
    margin: 10px 0 0;
    font-size: clamp(34px, 4vw, 52px);
    line-height: 1.02;
}

.provider-intro__copy p {
    max-width: 650px;
    margin: 18px 0 0;
    color: var(--text-2);
    font-size: 14px;
    line-height: 1.65;
}

.provider-intro__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
}

.provider-tabs {
    display: flex;
    align-items: center;
    padding: 3px;
    border: 1px solid var(--border);
    border-radius: 6px;
}

.provider-tab {
    min-height: 36px;
    padding: 0 14px;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: var(--text-2);
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}

.provider-tab.is-active {
    background: var(--accent);
    color: var(--bg);
}

.summary-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 32px;
}

.summary-heading h2 {
    margin-top: 6px;
}

.summary-heading p {
    max-width: 560px;
    margin: 0;
    color: var(--text-3);
    font-size: 12px;
    line-height: 1.55;
    text-align: right;
}
.kpi-val {
    font-family: var(--font-m);
    font-size: 28px;
    font-weight: 700;
    color: var(--text);
}
.kpi-lbl {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-3);
}

.chart-wrap {
    background: var(--s1);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
}
.line-chart {
    width: 100%;
    height: 120px;
    display: block;
}
.chart-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 10px;
    font-family: var(--font-m);
    color: var(--text-3);
}

.poi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.poi-table th {
    text-align: left;
    padding: 8px 12px;
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--text-3);
    border-bottom: 1px solid var(--border);
    font-weight: 500;
}
.poi-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.poi-row { cursor: pointer; transition: background .12s; }
.poi-row:hover { background: var(--hover-tint); }

.badge--green {
    background: var(--green) !important;
    color: #fff !important;
    border-color: var(--green) !important;
}

.is-active {
    background: var(--accent-bg);
    border-color: var(--accent);
    color: var(--accent);
}

.provider-detail-modal {
    width: min(92vw, 620px);
}

.provider-detail-head {
    padding-right: 58px;
}

.provider-detail-head > span:not(.badge) {
    display: block;
    margin-top: 16px;
    color: var(--text-3);
    font-size: 10px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.provider-detail-head h2 {
    margin: 8px 0 0;
    font-size: clamp(28px, 5vw, 46px);
    line-height: 1;
    overflow-wrap: anywhere;
}

.provider-detail-head p {
    max-width: 480px;
    margin-top: 14px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
}

.provider-detail-total {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-top: 28px;
    padding: 18px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.provider-detail-total span {
    color: var(--text-2);
    font-size: 13px;
}

.provider-detail-total strong {
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 42px;
    font-weight: 400;
    line-height: .8;
}

.provider-detail-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-bottom: 1px solid var(--border);
}

.provider-detail-stats > div {
    display: grid;
    grid-template-columns: 10px auto 1fr;
    gap: 11px;
    align-items: center;
    padding: 18px 0;
}

.provider-detail-stats > div + div {
    padding-left: 20px;
    border-left: 1px solid var(--border);
}

.provider-detail-stats strong {
    min-width: 34px;
    color: var(--text);
    font-family: var(--font-d);
    font-size: 32px;
    font-weight: 400;
}

.provider-detail-stats b,
.provider-detail-stats small {
    display: block;
}

.provider-detail-stats b {
    font-size: 13px;
}

.provider-detail-stats small {
    margin-top: 4px;
    color: var(--text-3);
    font-size: 10px;
    line-height: 1.35;
}

.provider-detail-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.provider-detail-dot.is-accepted {
    background: var(--green);
}

.provider-detail-dot.is-rejected {
    background: var(--red);
}

.provider-detail-result {
    margin-top: 22px;
}

.provider-detail-result__head {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    color: var(--text-2);
    font-size: 12px;
}

.provider-detail-result__head strong {
    color: var(--accent);
}

.provider-detail-progress {
    height: 6px;
    margin-top: 10px;
    overflow: hidden;
    border-radius: 3px;
    background: var(--border);
}

.provider-detail-progress span {
    display: block;
    height: 100%;
    background: var(--green);
}

.provider-detail-result p,
.provider-detail-empty {
    margin-top: 14px;
    color: var(--text-2);
    font-size: 12px;
    line-height: 1.55;
}

.provider-detail-empty {
    padding: 18px 0 0;
}

@media (max-width: 560px) {
    .provider-intro {
        padding-top: 34px;
    }

    .provider-intro__inner,
    .summary-heading {
        align-items: stretch;
        flex-direction: column;
        gap: 22px;
    }

    .provider-intro__actions {
        justify-content: flex-start;
    }

    .summary-heading p {
        text-align: left;
    }

    .provider-detail-stats {
        grid-template-columns: 1fr;
    }

    .provider-detail-stats > div + div {
        padding-left: 0;
        border-top: 1px solid var(--border);
        border-left: 0;
    }
}
</style>
