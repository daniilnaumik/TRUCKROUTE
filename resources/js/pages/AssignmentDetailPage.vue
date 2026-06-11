<template>
    <div class="assignment-page">
        <section class="assignment-head">
            <div class="container assignment-head__inner">
                <div>
                    <span class="assignment-status" :class="`assignment-status--${assignment?.status || 'issued'}`">
                        {{ statusLabel(assignment?.status) }}
                    </span>
                    <h1>{{ assignment ? `${compact(assignment.origin)} → ${compact(assignment.destination)}` : 'Задание' }}</h1>
                    <p v-if="assignment?.fleet">
                        Задание от автопарка «{{ assignment.fleet.name }}»
                    </p>
                </div>
                <RouterLink :to="{ name: 'settings' }" class="btn outline">Назад</RouterLink>
            </div>
        </section>

        <section class="section-tight">
            <div class="container">
                <div v-if="loading" class="assignment-state">Загружаем задание...</div>
                <div v-else-if="error" class="assignment-state assignment-state--error">{{ error }}</div>
                <div v-else-if="assignment" class="assignment-layout">
                    <article class="assignment-data">
                        <div>
                            <span>Откуда</span>
                            <strong>{{ assignment.origin }}</strong>
                        </div>
                        <div>
                            <span>Куда</span>
                            <strong>{{ assignment.destination }}</strong>
                        </div>
                        <div>
                            <span>Плановый старт</span>
                            <strong>{{ formatDateTime(assignment.planned_start_at) }}</strong>
                        </div>
                        <div>
                            <span>Транспорт</span>
                            <strong>
                                {{ assignment.vehicle_source === 'fleet'
                                    ? (assignment.vehicle?.title || 'Фура автопарка')
                                    : 'Личная фура водителя' }}
                            </strong>
                            <small v-if="assignment.vehicle">
                                {{ assignment.vehicle.type }}
                                <template v-if="assignment.vehicle.model"> · {{ assignment.vehicle.model }}</template>
                            </small>
                        </div>
                        <div v-if="assignment.comment" class="assignment-data__wide">
                            <span>Комментарий</span>
                            <strong>{{ assignment.comment }}</strong>
                        </div>

                        <div class="assignment-actions assignment-data__wide">
                            <button
                                v-if="assignment.status === 'issued'"
                                class="btn"
                                type="button"
                                :disabled="saving"
                                @click="runAction('accept')"
                            >
                                Принять задание
                            </button>
                            <button
                                v-if="['accepted', 'in_progress'].includes(assignment.status)"
                                class="btn"
                                type="button"
                                :disabled="saving"
                                @click="runAction('complete')"
                            >
                                Завершить задание
                            </button>
                            <RouterLink
                                v-if="assignment.route_plan_id"
                                :to="{ name: 'route-detail', params: { id: assignment.route_plan_id } }"
                                class="btn outline"
                            >
                                Открыть готовый маршрут
                            </RouterLink>
                            <button
                                v-if="!['completed', 'cancelled'].includes(assignment.status)"
                                class="btn outline danger"
                                type="button"
                                :disabled="saving"
                                @click="runAction('cancel')"
                            >
                                Отказаться
                            </button>
                        </div>
                    </article>

                    <article class="assignment-map-card">
                        <div class="assignment-map-card__head">
                            <span>Маршрут задания</span>
                            <strong>{{ mapCaption }}</strong>
                        </div>
                        <div v-if="!hasCoordinates" class="assignment-map-empty">
                            Для этого задания не сохранены точные координаты.
                        </div>
                        <div v-else ref="mapEl" class="assignment-map"></div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';
import { useWizardMap } from '@/composables/useWizardMap';
import { explainError } from '@/utils/errorHelpers';

const route = useRoute();
const ui = useUiStore();
const assignment = ref(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const mapEl = ref(null);

const {
    init: initMap,
    setOrigin,
    setDestination,
    setWaypoints,
    fetchAndDrawRoute,
} = useWizardMap(mapEl);

const hasCoordinates = computed(() => {
    return assignment.value?.origin_point?.lat != null
        && assignment.value?.destination_point?.lat != null;
});

const mapCaption = computed(() => {
    if (!assignment.value) return '';
    return `${compact(assignment.value.origin)} → ${compact(assignment.value.destination)}`;
});

onMounted(loadAssignment);

async function loadAssignment() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/api/v1/assignments/${route.params.id}`);
        assignment.value = data.data ?? data;
        loading.value = false;
        await drawMap();
    } catch (requestError) {
        const info = explainError(requestError);
        error.value = info.body || info.title || 'Не удалось открыть задание.';
    } finally {
        loading.value = false;
    }
}

async function drawMap() {
    if (!hasCoordinates.value) return;
    await nextTick();
    await initMap();
    setOrigin(assignment.value.origin_point);
    setDestination(assignment.value.destination_point);
    setWaypoints(assignment.value.via_points ?? []);
    await fetchAndDrawRoute(
        assignment.value.origin_point,
        assignment.value.destination_point,
        assignment.value.via_points ?? [],
    );
}

async function runAction(action) {
    if (!assignment.value || saving.value) return;
    saving.value = true;
    try {
        const { data } = await axios.post(`/api/v1/assignments/${assignment.value.id}/${action}`);
        assignment.value = data.data ?? data;
        ui.success(
            action === 'accept'
                ? 'Задание принято. Маршрут добавлен в ваши маршруты.'
                : action === 'complete'
                    ? 'Задание завершено.'
                    : 'Задание отменено.',
        );
    } catch (requestError) {
        const info = explainError(requestError);
        ui.error(info.body || info.title || 'Не удалось изменить задание.');
    } finally {
        saving.value = false;
    }
}

function compact(value) {
    const parts = String(value || '').split(',').map((part) => part.trim()).filter(Boolean);
    return parts.length > 2 ? parts.slice(-2).join(', ') : parts.join(', ');
}

function formatDateTime(value) {
    if (!value) return 'Не указано';
    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusLabel(status) {
    return {
        issued: 'Выдано',
        accepted: 'Принято',
        in_progress: 'В пути',
        completed: 'Выполнено',
        cancelled: 'Отменено',
    }[status] || 'Задание';
}
</script>

<style scoped>
.assignment-page {
    padding-top: 92px;
    min-height: 75vh;
}

.assignment-head {
    border-bottom: 1px solid var(--border);
}

.assignment-head__inner {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 24px;
    padding-top: 30px;
    padding-bottom: 30px;
}

.assignment-head h1 {
    margin: 12px 0 6px;
    max-width: 900px;
    font-size: clamp(28px, 4vw, 54px);
    line-height: 1;
    font-weight: 400;
}

.assignment-head p {
    color: var(--text-3);
}

.assignment-status {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 4px;
    background: var(--accent);
    color: var(--accent-text);
    font-size: 10px;
    text-transform: uppercase;
}

.assignment-status--accepted,
.assignment-status--in_progress { background: #4a6caa; color: #fff; }
.assignment-status--completed { background: var(--green); color: #fff; }
.assignment-status--cancelled { background: var(--red); color: #fff; }

.assignment-layout {
    display: grid;
    grid-template-columns: minmax(0, .8fr) minmax(420px, 1.2fr);
    align-items: start;
    gap: 18px;
}

.assignment-data,
.assignment-map-card {
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--s1);
}

.assignment-data {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-content: start;
}

.assignment-data > div {
    min-width: 0;
    padding: 18px;
    border-bottom: 1px solid var(--border);
}

.assignment-data > div:nth-child(odd):not(.assignment-data__wide) {
    border-right: 1px solid var(--border);
}

.assignment-data span,
.assignment-data strong,
.assignment-data small {
    display: block;
}

.assignment-data span {
    color: var(--text-3);
    font-size: 10px;
    text-transform: uppercase;
}

.assignment-data strong {
    margin-top: 7px;
    color: var(--text);
    font-size: 14px;
    line-height: 1.45;
}

.assignment-data small {
    margin-top: 4px;
    color: var(--text-3);
}

.assignment-data__wide {
    grid-column: 1 / -1;
}

.assignment-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    border-bottom: 0 !important;
}

.assignment-actions .danger {
    color: var(--red);
}

.assignment-map-card {
    min-width: 0;
    align-self: start;
    padding: 14px;
}

.assignment-map-card__head {
    padding: 2px 2px 12px;
}

.assignment-map-card__head span,
.assignment-map-card__head strong {
    display: block;
}

.assignment-map-card__head span {
    color: var(--text-3);
    font-size: 10px;
    text-transform: uppercase;
}

.assignment-map-card__head strong {
    margin-top: 5px;
    color: var(--text);
    font-size: 16px;
}

.assignment-map,
.assignment-map-empty {
    width: 100%;
    height: clamp(360px, 56vh, 560px);
    min-height: 0;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
}

.assignment-map-empty,
.assignment-state {
    display: grid;
    place-items: center;
    padding: 30px;
    color: var(--text-3);
    text-align: center;
}

.assignment-state--error {
    color: var(--red);
}

@media (max-width: 900px) {
    .assignment-layout {
        grid-template-columns: 1fr;
    }

    .assignment-head__inner {
        align-items: flex-start;
        flex-direction: column;
    }

    .assignment-map,
    .assignment-map-empty {
        height: clamp(320px, 48vh, 440px);
    }
}

@media (max-width: 560px) {
    .assignment-data {
        grid-template-columns: 1fr;
    }

    .assignment-data > div {
        border-right: 0 !important;
    }

    .assignment-map,
    .assignment-map-empty {
        height: 340px;
    }
}
</style>
