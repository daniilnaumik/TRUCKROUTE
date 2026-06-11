<template>
    <div>
        <div v-if="loading" style="padding:80px;text-align:center;color:var(--text-3);">Загрузка...</div>

        <div v-else-if="!event" style="padding:80px;text-align:center;">
            <h2>Событие не найдено</h2>
            <RouterLink :to="{ name: 'news', hash: '#events' }" class="btn" style="margin-top:16px;">К событиям</RouterLink>
        </div>

        <template v-else>
            <section class="page-hero">
                <div class="container">
                    <div>
                        <div class="event-detail__badges">
                            <span class="badge">{{ event.type }}</span>
                            <span class="badge">{{ importanceLabel }}</span>
                            <span class="badge">{{ statusLabel }}</span>
                        </div>
                        <h1>{{ event.title || event.location }}</h1>
                        <p class="lead">{{ event.description }}</p>
                        <p class="event-detail__meta">
                            {{ event.highway || 'Трасса не указана' }} · {{ event.location || 'Место не указано' }} · {{ formattedTime }}
                        </p>
                        <div class="actions">
                            <button v-if="!isOwnEvent" type="button" :disabled="actionLoading || !auth.isAuthenticated" @click="vote(1)">
                                Подтвердить
                            </button>
                            <button v-if="!isOwnEvent" type="button" class="btn outline" :disabled="actionLoading || !auth.isAuthenticated" @click="report">
                                Пожаловаться
                            </button>
                            <button v-if="auth.isAdmin" type="button" class="btn danger" :disabled="actionLoading" @click="deleteEvent">
                                Удалить
                            </button>
                            <RouterLink :to="{ name: 'news', hash: '#events' }" class="btn outline">Назад</RouterLink>
                        </div>
                        <p v-if="!auth.isAuthenticated && !isOwnEvent" class="event-detail__hint">
                            Войдите, чтобы подтверждать события и отправлять жалобы.
                        </p>
                        <p v-if="message" class="event-detail__hint">{{ message }}</p>
                    </div>

                    <div class="page-visual event-detail__carousel">
                        <button
                            v-if="mediaItems.length > 1"
                            type="button"
                            class="event-detail__media-arrow event-detail__media-arrow--prev"
                            aria-label="Предыдущее медиа"
                            @click.stop="prevMedia"
                        >
                            ‹
                        </button>

                        <div v-if="!activeMedia" class="event-detail__media-main event-detail__media-empty">
                            <span>Пользователь не приложил фото или видео</span>
                        </div>
                        <button
                            v-else-if="activeMedia?.type === 'image'"
                            type="button"
                            class="event-detail__media-main"
                            @click="openLightbox(activeMediaIndex)"
                        >
                            <img :src="activeMedia.src" :alt="event.title || event.type">
                        </button>
                        <div v-else class="event-detail__media-main event-detail__media-main--video" @dblclick="openLightbox(activeMediaIndex)">
                            <video :src="activeMedia?.src" controls preload="metadata"></video>
                        </div>

                        <button
                            v-if="mediaItems.length > 1"
                            type="button"
                            class="event-detail__media-arrow event-detail__media-arrow--next"
                            aria-label="Следующее медиа"
                            @click.stop="nextMedia"
                        >
                            ›
                        </button>

                        <div v-if="mediaItems.length > 1" class="event-detail__media-count">
                            {{ activeMediaIndex + 1 }} / {{ mediaItems.length }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-tight">
                <div class="container event-detail__grid">
                    <article class="card">
                        <h3>Сводка</h3>
                        <dl class="event-detail__facts">
                            <div>
                                <dt>Задержка</dt>
                                <dd>{{ event.delay_minutes || 0 }} мин</dd>
                            </div>
                            <div>
                                <dt>Доверие</dt>
                                <dd>{{ event.confidence_score }}/10</dd>
                            </div>
                            <div>
                                <dt>Голоса</dt>
                                <dd>+{{ event.votes?.up ?? 0 }} / -{{ event.votes?.down ?? 0 }}</dd>
                            </div>
                            <div>
                                <dt>Координаты</dt>
                                <dd>{{ coordinatesText }}</dd>
                            </div>
                            <div>
                                <dt>Истекает</dt>
                                <dd>{{ formattedExpiresAt }}</dd>
                            </div>
                        </dl>
                    </article>

                    <article class="card event-detail__map-card">
                        <h3>На карте</h3>
                        <MapFallback v-if="mapError" class="event-detail__map" :retry="initMap" />
                        <div v-show="!mapError" ref="mapEl" class="event-detail__map"></div>
                    </article>
                </div>
            </section>

            <div v-if="lightboxOpen" class="event-detail__lightbox" @click.self="closeLightbox">
                <button type="button" class="event-detail__lightbox-close" aria-label="Закрыть" @click="closeLightbox">×</button>
                <button
                    v-if="mediaItems.length > 1"
                    type="button"
                    class="event-detail__lightbox-arrow event-detail__lightbox-arrow--prev"
                    aria-label="Предыдущее медиа"
                    @click="prevMedia"
                >
                    ‹
                </button>
                <div class="event-detail__lightbox-media">
                    <img v-if="activeMedia?.type === 'image'" :src="activeMedia.src" :alt="event.title || event.type">
                    <video v-else :src="activeMedia?.src" controls autoplay preload="metadata"></video>
                </div>
                <button
                    v-if="mediaItems.length > 1"
                    type="button"
                    class="event-detail__lightbox-arrow event-detail__lightbox-arrow--next"
                    aria-label="Следующее медиа"
                    @click="nextMedia"
                >
                    ›
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { loadYandexMaps } from '@/composables/yandexMaps';
import { useAuthStore } from '@/stores/auth';
import { eventImageSrc } from '@/utils/eventImages';
import MapFallback from '@/components/MapFallback.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const event = ref(null);
const loading = ref(true);
const actionLoading = ref(false);
const message = ref('');
const mapEl = ref(null);
const activeMediaIndex = ref(0);
const lightboxOpen = ref(false);
const mapError = ref(false);
let ymap = null;

const statusLabels = {
    active: 'Активно',
    checking: 'Проверяется',
    closed: 'Завершено',
    expired: 'Истекло',
    rejected: 'Отклонено',
};

const importanceLabels = {
    high: 'Важно',
    medium: 'Средне',
    low: 'Низко',
    важно: 'Важно',
    средне: 'Средне',
    низко: 'Низко',
};

const statusLabel = computed(() => statusLabels[event.value?.status] ?? event.value?.status ?? '');
const importanceLabel = computed(() => importanceLabels[event.value?.importance] ?? event.value?.importance ?? '');
const isOwnEvent = computed(() =>
    !!auth.user?.id && Number(event.value?.created_by_user_id) === Number(auth.user.id)
);

const mediaItems = computed(() => {
    if (!event.value) return [];

    const items = [];
    const seen = new Set();
    const isUserEvent = !!event.value.created_by_user_id;
    const addImage = (src) => {
        if (!src || seen.has(src)) return;
        seen.add(src);
        items.push({ type: 'image', src });
    };

    if (isUserEvent) {
        addImage(event.value.image_url);
    } else {
        addImage(eventImageSrc(event.value));
    }
    (event.value.gallery ?? []).forEach(addImage);

    if (event.value.video_url && !seen.has(event.value.video_url)) {
        seen.add(event.value.video_url);
        items.push({ type: 'video', src: event.value.video_url });
    }

    return items;
});

const activeMedia = computed(() => mediaItems.value[activeMediaIndex.value] ?? mediaItems.value[0] ?? null);
const formattedTime = computed(() => formatDateTime(event.value?.reported_at));
const formattedExpiresAt = computed(() => formatDateTime(event.value?.expires_at) || 'Не указано');
const coordinatesText = computed(() => {
    const lat = event.value?.coordinates?.lat;
    const lng = event.value?.coordinates?.lng;
    if (!lat || !lng) return 'Не указаны';
    return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
});

function formatDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ru', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function loadEvent() {
    loading.value = true;
    event.value = null;
    message.value = '';
    activeMediaIndex.value = 0;
    lightboxOpen.value = false;
    try {
        const { data } = await axios.get(`/api/v1/events/${route.params.id}`);
        event.value = data.data ?? null;
    } catch {
        event.value = null;
    } finally {
        loading.value = false;
    }

    await nextTick();
    initMap();
}

function prevMedia() {
    if (!mediaItems.value.length) return;
    activeMediaIndex.value = (activeMediaIndex.value - 1 + mediaItems.value.length) % mediaItems.value.length;
}

function nextMedia() {
    if (!mediaItems.value.length) return;
    activeMediaIndex.value = (activeMediaIndex.value + 1) % mediaItems.value.length;
}

function openLightbox(index = 0) {
    if (!mediaItems.value.length) return;
    activeMediaIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox() {
    lightboxOpen.value = false;
}

async function vote(value) {
    if (isOwnEvent.value) return;
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    actionLoading.value = true;
    message.value = '';
    try {
        const { data } = await axios.post(`/api/v1/events/${event.value.id}/vote`, { vote: value });
        event.value = data.data ?? event.value;
        message.value = 'Спасибо, событие подтверждено.';
    } catch (error) {
        message.value = error.response?.data?.message ?? 'Не удалось отправить голос.';
    } finally {
        actionLoading.value = false;
    }
}

async function report() {
    if (isOwnEvent.value) return;
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    actionLoading.value = true;
    message.value = '';
    try {
        const { data } = await axios.post(`/api/v1/events/${event.value.id}/report`);
        event.value = data.data ?? event.value;
        message.value = data.message ?? 'Жалоба отправлена.';
    } catch (error) {
        message.value = error.response?.data?.message ?? 'Не удалось отправить жалобу.';
    } finally {
        actionLoading.value = false;
    }
}

async function deleteEvent() {
    if (!auth.isAdmin || !event.value?.id) return;
    if (!window.confirm('Удалить событие?')) return;

    actionLoading.value = true;
    message.value = '';
    try {
        await axios.delete(`/api/v1/admin/events/${event.value.id}`);
        router.push({ name: 'news', hash: '#events' });
    } catch (error) {
        message.value = error.response?.data?.message ?? 'Не удалось удалить событие.';
    } finally {
        actionLoading.value = false;
    }
}

async function initMap() {
    const lat = event.value?.coordinates?.lat;
    const lng = event.value?.coordinates?.lng;
    if (!mapEl.value || !lat || !lng) return;

    mapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        mapError.value = true;
        return;
    }
    if (ymap) ymap.destroy();
    ymap = new ymaps.Map(mapEl.value, { center: [lat, lng], zoom: 9, controls: ['zoomControl'] }, { suppressMapOpenBlock: true });
    const color = eventColor(event.value.importance);
    const svg = `data:image/svg+xml;utf8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="24" height="32" viewBox="0 0 24 32"><path d="M12 31s9-10.3 9-19A9 9 0 1 0 3 12c0 8.7 9 19 9 19z" fill="${color}" stroke="#fff" stroke-width="2"/><circle cx="12" cy="12" r="3.5" fill="#fff"/></svg>`)}`;
    ymap.geoObjects.add(new ymaps.Placemark([lat, lng], {
        hintContent: event.value.title || event.value.type,
        balloonContent: event.value.location || event.value.description,
    }, {
        iconLayout: 'default#image',
        iconImageHref: svg,
        iconImageSize: [24, 32],
        iconImageOffset: [-12, -32],
    }));
    ymap.container.fitToViewport();
}

function eventColor(importance) {
    const value = String(importance || '').toLowerCase();
    if (value === 'low' || value.includes('низ')) return '#5a5752';
    if (value === 'medium' || value.includes('сред')) return '#c99b3a';
    return '#c84840';
}

onMounted(loadEvent);
watch(() => route.params.id, loadEvent);
onUnmounted(() => {
    if (ymap) ymap.destroy();
});
</script>

<style>
.event-detail__badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.event-detail__meta,
.event-detail__hint {
    margin-top: 14px;
    color: var(--text-3);
    font-size: 13px;
}

.event-detail__carousel {
    position: relative;
    overflow: hidden;
    background: var(--s1);
}

.event-detail__media-main {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 320px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    overflow: hidden;
    background: transparent;
    cursor: zoom-in;
    box-shadow: none;
}

.event-detail__media-main img,
.event-detail__media-main video {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 320px;
    object-fit: cover;
}

.event-detail__media-main--video {
    position: relative;
    cursor: default;
}

.event-detail__media-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    border: 1px dashed var(--border);
    background: var(--s1);
    color: var(--text-3);
    font-size: 14px;
    line-height: 1.5;
    text-align: center;
    cursor: default;
}

.event-detail__media-arrow,
.event-detail__lightbox-arrow {
    position: absolute;
    z-index: 3;
    top: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    min-height: 0;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 6px;
    background: rgba(0, 0, 0, .42);
    color: #fff;
    font-size: 32px;
    line-height: 1;
    transform: translateY(-50%);
    opacity: 0;
    transition: none;
}

.event-detail__carousel:hover .event-detail__media-arrow,
.event-detail__lightbox:hover .event-detail__lightbox-arrow {
    opacity: 1;
}

.event-detail__media-arrow:hover,
.event-detail__lightbox-arrow:hover {
    background: rgba(0, 0, 0, .68);
    transform: translateY(-50%);
    box-shadow: none;
}

.event-detail__media-arrow:focus,
.event-detail__media-arrow:active,
.event-detail__lightbox-arrow:focus,
.event-detail__lightbox-arrow:active {
    transform: translateY(-50%);
    box-shadow: none;
}

.event-detail__media-arrow--prev,
.event-detail__lightbox-arrow--prev {
    left: 16px;
}

.event-detail__media-arrow--next,
.event-detail__lightbox-arrow--next {
    right: 16px;
}

.event-detail__media-count {
    position: absolute;
    right: 12px;
    top: 12px;
    z-index: 4;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 6px;
    background: rgba(0, 0, 0, .48);
    color: #fff;
    font-size: 12px;
}

.event-detail__media-count {
    padding: 6px 9px;
}

.event-detail__grid {
    display: grid;
    grid-template-columns: minmax(280px, 0.8fr) minmax(320px, 1.2fr);
    gap: 24px;
    align-items: stretch;
}

.event-detail__facts {
    display: grid;
    gap: 14px;
    margin: 20px 0 0;
}

.event-detail__facts div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 10px;
}

.event-detail__facts dt {
    color: var(--text-3);
    font-size: 12px;
}

.event-detail__facts dd {
    margin: 0;
    text-align: right;
    color: var(--text);
}

.event-detail__map-card {
    min-height: 420px;
}

.event-detail__map {
    height: 340px;
    margin-top: 18px;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
}

.event-detail__lightbox {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 72px;
    background: rgba(0, 0, 0, .88);
}

.event-detail__lightbox-media {
    max-width: min(1120px, 100%);
    max-height: 86vh;
}

.event-detail__lightbox-media img,
.event-detail__lightbox-media video {
    display: block;
    max-width: 100%;
    max-height: 86vh;
    border-radius: 8px;
    object-fit: contain;
    background: #000;
}

.event-detail__lightbox-close {
    position: absolute;
    top: 18px;
    right: 18px;
    width: 44px;
    height: 44px;
    min-height: 0;
    padding: 0;
    border-radius: 6px;
    background: rgba(0, 0, 0, .5);
    color: #fff;
    font-size: 30px;
    line-height: 1;
    box-shadow: none;
}

@media (max-width: 820px) {
    .event-detail__grid {
        grid-template-columns: 1fr;
    }

    .event-detail__lightbox {
        padding: 56px 14px 24px;
    }

    .event-detail__media-arrow,
    .event-detail__lightbox-arrow {
        opacity: 1;
        width: 40px;
        height: 40px;
    }
}
</style>
