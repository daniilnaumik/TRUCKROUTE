<template>
    <div>
        <section class="news-intro">
            <div class="container news-intro__inner">
                <div>
                    <span class="news-intro__eyebrow">Информация в рейсе</span>
                    <h1>Новости</h1>
                    <p>Статьи для водителей и актуальные сообщения о дорожной обстановке в одном разделе.</p>
                </div>
                <nav class="news-intro__links" aria-label="Разделы новостей">
                    <a href="#articles">Статьи</a>
                    <a href="#events">События на дорогах</a>
                </nav>
            </div>
        </section>

        <section class="section-tight" id="articles">
            <div class="container">
                <div class="live-section-head">
                    <h2>Статьи</h2>
                    <div class="live-toolbar">
                        <span>обновлено {{ updatedAgo }}</span>
                        <button type="button" class="btn outline" :disabled="refreshing" @click="refreshFeeds">Обновить</button>
                        <RouterLink v-if="auth.isAdmin" :to="{ name: 'admin-news-new' }" class="btn" style="font-size:13px;">
                            + Написать статью
                        </RouterLink>
                    </div>
                </div>

                <div v-if="articleTags.length" class="article-tag-filter">
                    <button
                        type="button"
                        class="btn outline article-tag-filter__button"
                        @click="tagsPanelOpen = !tagsPanelOpen"
                    >
                        Теги{{ selectedArticleTags.length ? `: ${selectedArticleTags.length}` : '' }}
                    </button>
                    <button v-if="selectedArticleTags.length" type="button" class="btn outline article-tag-filter__reset" @click="clearArticleTags">
                        Сбросить
                    </button>
                    <div v-if="tagsPanelOpen" class="article-tag-panel">
                        <label v-for="tag in articleTags" :key="tag" class="article-tag-option">
                            <input type="checkbox" :checked="selectedArticleTags.includes(tag)" @change="toggleArticleTag(tag)">
                            <span>{{ tag }}</span>
                        </label>
                    </div>
                </div>

                <div v-if="loadingArticles" class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <div class="card skeleton" v-for="i in 3" :key="i" style="height:280px;"></div>
                </div>
                <div v-else-if="!filteredArticles.length" class="card" style="margin-top:36px;">
                    <h3>Статей пока нет</h3>
                    <p>Редакция готовит материалы. Загляните позже.</p>
                </div>
                <div v-else class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <component
                        v-for="(a, index) in visibleArticles"
                        :key="a.id"
                        :is="isArticleMoreCard(index) ? 'button' : 'RouterLink'"
                        :to="isArticleMoreCard(index) ? undefined : { name: 'news-detail', params: { id: a.slug } }"
                        :type="isArticleMoreCard(index) ? 'button' : undefined"
                        class="card feature-card"
                        :class="{ 'article-card--more': isArticleMoreCard(index) }"
                        style="text-decoration:none;"
                        @click="handleArticleCardClick($event, index)"
                    >
                        <div class="feature-image">
                            <img v-if="articleCanShowMedia(a)" :src="articleImageSrc(a)" :alt="a.title" @error="markArticleMediaFailed(a)">
                            <div v-else class="article-media-empty">
                                <span>Пользователь не приложил фото или видео</span>
                            </div>
                            <div v-if="isArticleMoreCard(index)" class="article-card__more-overlay">
                                +{{ hiddenArticleCount }}
                            </div>
                        </div>
                        <div class="feature-body">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
                                <span v-for="tag in (a.tags ?? []).slice(0, 2)" :key="tag" class="badge" style="font-size:10px;">{{ tag }}</span>
                            </div>
                            <h3>{{ a.title }}</h3>
                            <p style="font-size:12px;color:var(--text-2);margin-top:6px;">{{ a.excerpt }}</p>
                            <p style="font-size:11px;color:var(--text-3);font-family:var(--font-m);margin-top:auto;padding-top:8px;">
                                {{ a.author?.name }} · {{ formatDate(a.published_at) }}
                            </p>
                        </div>
                    </component>
                </div>
            </div>
        </section>

        <div v-if="articlesModalOpen" class="articles-modal" @click.self="articlesModalOpen = false">
            <div class="articles-modal__panel">
                <div class="articles-modal__head">
                    <h3>Все статьи</h3>
                    <button type="button" class="articles-modal__close" aria-label="Закрыть" @click="articlesModalOpen = false">×</button>
                </div>
                <div v-if="articleTags.length" class="article-tag-filter article-tag-filter--modal">
                    <button
                        type="button"
                        class="btn outline article-tag-filter__button"
                        @click="modalTagsPanelOpen = !modalTagsPanelOpen"
                    >
                        Теги{{ selectedArticleTags.length ? `: ${selectedArticleTags.length}` : '' }}
                    </button>
                    <button v-if="selectedArticleTags.length" type="button" class="btn outline article-tag-filter__reset" @click="clearArticleTags">
                        Сбросить
                    </button>
                    <div v-if="modalTagsPanelOpen" class="article-tag-panel article-tag-panel--modal">
                        <label v-for="tag in articleTags" :key="`modal-${tag}`" class="article-tag-option">
                            <input type="checkbox" :checked="selectedArticleTags.includes(tag)" @change="toggleArticleTag(tag)">
                            <span>{{ tag }}</span>
                        </label>
                    </div>
                </div>
                <div class="grid-3 equal-card-grid articles-modal__grid">
                    <RouterLink
                        v-for="a in filteredArticles"
                        :key="`all-${a.id}`"
                        :to="{ name: 'news-detail', params: { id: a.slug } }"
                        class="card feature-card"
                        style="text-decoration:none;"
                        @click="articlesModalOpen = false"
                    >
                        <div class="feature-image">
                            <img v-if="articleCanShowMedia(a)" :src="articleImageSrc(a)" :alt="a.title" @error="markArticleMediaFailed(a)">
                            <div v-else class="article-media-empty">
                                <span>Пользователь не приложил фото или видео</span>
                            </div>
                        </div>
                        <div class="feature-body">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
                                <span v-for="tag in (a.tags ?? []).slice(0, 2)" :key="tag" class="badge" style="font-size:10px;">{{ tag }}</span>
                            </div>
                            <h3>{{ a.title }}</h3>
                            <p style="font-size:11px;color:var(--text-3);font-family:var(--font-m);margin-top:auto;padding-top:8px;">
                                {{ a.author?.name }} · {{ formatDate(a.published_at) }}
                            </p>
                        </div>
                    </RouterLink>
                </div>
            </div>
        </div>

        <div v-if="eventsModalOpen" class="articles-modal" @click.self="eventsModalOpen = false">
            <div class="articles-modal__panel">
                <div class="articles-modal__head">
                    <h3>Все события</h3>
                    <button type="button" class="articles-modal__close" aria-label="Закрыть" @click="eventsModalOpen = false">×</button>
                </div>
                <div class="grid-3 equal-card-grid articles-modal__grid">
                    <NewsCard
                        v-for="ev in events"
                        :key="`all-event-${ev.id}`"
                        :event="ev"
                        @click="eventsModalOpen = false"
                    />
                </div>
            </div>
        </div>

        <section class="section-tight dark" id="events">
            <div class="container">
                <div class="live-section-head">
                    <div>
                        <h2>События на дорогах</h2>
                        <p class="lead">Краудсорсинговые сообщения от водителей. Лента обновляется каждый час.</p>
                    </div>
                    <div class="live-toolbar">
                        <span>обновлено {{ updatedAgo }}</span>
                        <button type="button" class="btn outline" :disabled="refreshing" @click="refreshFeeds">Обновить события</button>
                        <button v-if="auth.isAuthenticated" type="button" class="btn" @click="toggleReportForm">
                            {{ reportOpen ? 'Скрыть форму' : 'Сообщить о событии' }}
                        </button>
                    </div>
                </div>

                <MapFallback
                    v-if="eventsMapError"
                    class="events-map-fallback"
                    :retry="retryEventsMap"
                />
                <div
                    v-show="!eventsMapError"
                    ref="mapEl"
                    style="height:320px;border-radius:6px;overflow:hidden;margin-top:24px;border:1px solid var(--border);"
                ></div>

                <form v-if="reportOpen" class="event-report-form" @submit.prevent="submitEvent">
                    <div class="field">
                        <label>Тип</label>
                        <select v-model="eventForm.type" required>
                            <option
                                v-for="item in dictionaries.options('event_types')"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Трасса</label>
                        <input
                            v-model="eventForm.highway"
                            required
                            title="Заполните номер или название трассы"
                            @invalid="eventMessage = 'Заполните поле «Трасса», оно будет заголовком события.'"
                            @input="eventMessage = ''"
                        >
                    </div>
                    <div class="field">
                        <label>Место</label>
                        <input v-model="eventForm.location" required @input="eventMessage = ''">
                    </div>
                    <div class="field">
                        <label>Задержка, мин</label>
                        <input v-model.number="eventForm.delay_minutes" type="number" min="0" max="600">
                    </div>
                    <div class="field event-report-form__wide">
                        <label>Заголовок</label>
                        <input v-model="eventForm.title" required placeholder="Что произошло" @input="eventMessage = ''">
                    </div>
                    <div class="field event-report-form__wide">
                        <label>Описание</label>
                        <textarea v-model="eventForm.description" rows="3" placeholder="Подробности для других водителей"></textarea>
                    </div>
                    <div class="field">
                        <label>Важность</label>
                        <select v-model="eventForm.importance">
                            <option value="low">низко</option>
                            <option value="medium">средне</option>
                            <option value="high">важно</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Фото</label>
                        <input type="file" accept="image/*" multiple @change="uploadEventImages">
                    </div>
                    <div class="field">
                        <label>Видео</label>
                        <input type="file" accept="video/mp4,video/webm,video/quicktime" @change="uploadEventVideo">
                    </div>
                    <div class="event-report-form__map-block">
                        <label>Точка на карте</label>
                        <MapFallback v-if="reportMapError" class="event-report-map" :retry="retryReportMap" />
                        <div v-show="!reportMapError" ref="reportMapEl" class="event-report-map"></div>
                        <p>{{ eventForm.lat && eventForm.lng ? `${eventForm.lat.toFixed(5)}, ${eventForm.lng.toFixed(5)}` : 'Кликните на карте, где произошло событие' }}</p>
                    </div>
                    <div v-if="eventForm.gallery.length || eventForm.video_url" class="event-media-preview">
                        <img v-for="img in eventForm.gallery" :key="img" :src="mediaPreviewUrl(img)" alt="">
                        <span v-if="eventForm.video_url">Видео загружено</span>
                    </div>
                    <div class="actions event-report-form__wide">
                        <button type="submit" :disabled="submittingEvent || uploadingMedia || !eventForm.lat || !eventForm.lng">
                            {{ submittingEvent ? 'Отправка...' : 'Опубликовать событие' }}
                        </button>
                        <span v-if="eventMessage" class="event-report-form__message">{{ eventMessage }}</span>
                    </div>
                </form>

                <div v-if="loadingEvents" class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <div class="card skeleton" v-for="i in 3" :key="i" style="height:200px;"></div>
                </div>
                <div v-else class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <NewsCard
                        v-for="(ev, index) in visibleEvents"
                        :key="ev.id"
                        :event="ev"
                        :clickable="!(index === 5 && hiddenEventsCount > 0)"
                        :more-count="index === 5 ? hiddenEventsCount : 0"
                        @click="handleEventCardClick($event, index)"
                    />
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import NewsCard from '@/components/NewsCard.vue';
import MapFallback from '@/components/MapFallback.vue';
import { loadYandexMaps } from '@/composables/yandexMaps';
import { useAuthStore } from '@/stores/auth';
import { useDictionariesStore } from '@/stores/dictionaries';
import { articleHasAttachedMedia, articleImageSrc } from '@/utils/articleImages';

const auth = useAuthStore();
const dictionaries = useDictionariesStore();

const articles = ref([]);
const events = ref([]);
const loadingArticles = ref(true);
const loadingEvents = ref(true);
const refreshing = ref(false);
const lastUpdatedAt = ref(null);
const nowTick = ref(Date.now());
const selectedArticleTags = ref([]);
const tagsPanelOpen = ref(false);
const modalTagsPanelOpen = ref(false);
const articlesModalOpen = ref(false);
const eventsModalOpen = ref(false);
const failedArticleMediaIds = ref([]);
const mapEl = ref(null);
const reportMapEl = ref(null);
const eventsMapError = ref(false);
const reportMapError = ref(false);
const reportOpen = ref(false);
const submittingEvent = ref(false);
const uploadingMedia = ref(false);
const eventMessage = ref('');

const eventForm = reactive({
    title: '',
    type: 'Контроль',
    highway: '',
    location: '',
    description: '',
    importance: 'medium',
    delay_minutes: 0,
    lat: null,
    lng: null,
    gallery: [],
    video_url: '',
});

let ymap = null;
let reportMap = null;
let reportPlacemark = null;
let refreshTimer = null;
let clockTimer = null;

const updatedAgo = computed(() => {
    if (!lastUpdatedAt.value) return 'только что';
    const diff = Math.max(0, Math.floor((nowTick.value - lastUpdatedAt.value.getTime()) / 1000));
    if (diff < 10) return 'только что';
    if (diff < 60) return `${diff} сек назад`;
    const minutes = Math.floor(diff / 60);
    return `${minutes} мин назад`;
});

const articleTags = computed(() => {
    const tags = new Set();
    dictionaries.options('tags').forEach((item) => tags.add(item.value));
    articles.value.forEach((article) => {
        (article.tags ?? []).forEach((tag) => {
            if (tag) tags.add(tag);
        });
    });

    return [...tags].sort((a, b) => a.localeCompare(b, 'ru'));
});

const filteredArticles = computed(() => {
    if (!selectedArticleTags.value.length) return articles.value;

    return articles.value.filter((article) =>
        selectedArticleTags.value.some((tag) => (article.tags ?? []).includes(tag))
    );
});

const visibleArticles = computed(() => filteredArticles.value.slice(0, 6));
const hiddenArticleCount = computed(() => Math.max(0, filteredArticles.value.length - 6));
const visibleEvents = computed(() => events.value.slice(0, 6));
const hiddenEventsCount = computed(() => Math.max(0, events.value.length - 6));

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('ru', { day: '2-digit', month: 'long', year: 'numeric' });
}

function openArticlesModal(event) {
    event.preventDefault();
    event.stopPropagation();
    articlesModalOpen.value = true;
}

function articleCanShowMedia(article) {
    return articleHasAttachedMedia(article) && !failedArticleMediaIds.value.includes(article.id);
}

function markArticleMediaFailed(article) {
    if (!article?.id || failedArticleMediaIds.value.includes(article.id)) return;
    failedArticleMediaIds.value = [...failedArticleMediaIds.value, article.id];
}

function isArticleMoreCard(index) {
    return index === 5 && hiddenArticleCount.value > 0;
}

function handleArticleCardClick(event, index) {
    if (isArticleMoreCard(index)) {
        openArticlesModal(event);
    }
}

function openEventsModal(event) {
    event.preventDefault();
    event.stopPropagation();
    eventsModalOpen.value = true;
}

function handleEventCardClick(event, index) {
    if (index === 5 && hiddenEventsCount.value > 0) {
        openEventsModal(event);
    }
}

function toggleArticleTag(tag) {
    selectedArticleTags.value = selectedArticleTags.value.includes(tag)
        ? selectedArticleTags.value.filter((item) => item !== tag)
        : [...selectedArticleTags.value, tag];
}

function clearArticleTags() {
    selectedArticleTags.value = [];
}

async function refreshFeeds() {
    refreshing.value = true;
    try {
        const newsUrl = '/api/v1/news?per_page=100';
        const eventLimit = auth.isAdmin ? 200 : 100;
        const eventStatus = auth.isAdmin ? 'all' : 'feed';
        const [newsResult, eventsResult] = await Promise.allSettled([
            axios.get(newsUrl),
            axios.get(`/api/v1/events?limit=${eventLimit}&status=${eventStatus}`),
        ]);

        if (newsResult.status === 'fulfilled') {
            articles.value = newsResult.value.data.data ?? [];
            loadingArticles.value = false;
        }

        if (eventsResult.status === 'fulfilled') {
            events.value = eventsResult.value.data.data ?? [];
            loadingEvents.value = false;
            await nextTick();
            initEventsMap(events.value);
        }

        lastUpdatedAt.value = new Date();
        nowTick.value = Date.now();
    } finally {
        refreshing.value = false;
    }
}

function toggleReportForm() {
    reportOpen.value = !reportOpen.value;
    if (reportOpen.value) {
        nextTick(initReportMap);
    }
}

async function uploadEventImages(event) {
    const files = [...(event.target.files ?? [])].slice(0, 8 - eventForm.gallery.length);
    if (!files.length) return;
    uploadingMedia.value = true;
    eventMessage.value = '';
    try {
        for (const file of files) {
            const uploaded = await uploadMedia(file);
            eventForm.gallery.push(uploaded.path);
        }
    } catch (error) {
        eventMessage.value = error.response?.data?.message ?? 'Не удалось загрузить фото.';
    } finally {
        uploadingMedia.value = false;
        event.target.value = '';
    }
}

async function uploadEventVideo(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadingMedia.value = true;
    eventMessage.value = '';
    try {
        const uploaded = await uploadMedia(file);
        eventForm.video_url = uploaded.path;
    } catch (error) {
        eventMessage.value = error.response?.data?.message ?? 'Не удалось загрузить видео.';
    } finally {
        uploadingMedia.value = false;
        event.target.value = '';
    }
}

async function uploadMedia(file) {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await axios.post('/api/v1/media/upload', fd);
    return data;
}

function mediaPreviewUrl(path) {
    if (!path) return '';
    if (path.startsWith('http') || path.startsWith('/storage/')) return path;
    return `/storage/${path}`;
}

async function submitEvent() {
    submittingEvent.value = true;
    eventMessage.value = '';
    try {
        const highwayTitle = eventForm.highway.trim();
        if (!highwayTitle) {
            eventMessage.value = 'Укажите трассу, это обязательное поле события.';
            return;
        }

        const payload = {
            title: eventForm.title.trim(),
            type: eventForm.type,
            highway: highwayTitle,
            location: eventForm.location,
            description: eventForm.description || '',
            importance: eventForm.importance,
            delay_minutes: eventForm.delay_minutes || 0,
            lat: eventForm.lat,
            lng: eventForm.lng,
            image: eventForm.gallery[0] ?? null,
            gallery: eventForm.gallery,
            video_url: eventForm.video_url || null,
        };
        await axios.post('/api/v1/events', payload);
        eventMessage.value = 'Событие опубликовано. Карточка выделена как ваша.';
        resetEventForm();
        reportOpen.value = false;
        await refreshFeeds();
    } catch (error) {
        eventMessage.value = error.response?.data?.message
            ?? Object.values(error.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Не удалось опубликовать событие.';
    } finally {
        submittingEvent.value = false;
    }
}

function resetEventForm() {
    Object.assign(eventForm, {
        title: '',
        type: 'Контроль',
        highway: '',
        location: '',
        description: '',
        importance: 'medium',
        delay_minutes: 0,
        lat: null,
        lng: null,
        gallery: [],
        video_url: '',
    });
    reportPlacemark = null;
}

async function initEventsMap(evs) {
    if (!mapEl.value) return;
    eventsMapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        eventsMapError.value = true;
        return;
    }
    if (ymap) {
        ymap.destroy();
        ymap = null;
    }
    const belarusCenter = [53.7098, 27.9534];
    ymap = new ymaps.Map(mapEl.value, { center: belarusCenter, zoom: 6, controls: ['zoomControl'] }, { suppressMapOpenBlock: true });
    const colorMap = { высокий: '#c84840', важно: '#c84840', средне: '#c99b3a', низко: '#5a5752', high: '#c84840', medium: '#c99b3a', low: '#5a5752' };
    evs.forEach((ev) => {
        const lat = ev.coordinates?.lat ?? ev.lat;
        const lng = ev.coordinates?.lng ?? ev.lng;
        if (!lat || !lng) return;
        const color = colorMap[ev.importance] ?? '#5a5752';
        const svg = `data:image/svg+xml;utf8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="8" cy="8" r="5" fill="${color}" stroke="#fff" stroke-width="2"/></svg>`)}`;
        ymap.geoObjects.add(new ymaps.Placemark([lat, lng], { hintContent: ev.title || ev.type },
            { iconLayout: 'default#image', iconImageHref: svg, iconImageSize: [16, 16], iconImageOffset: [-8, -8] }));
    });
    ymap.setCenter(belarusCenter, 6);
}

async function initReportMap() {
    if (!reportMapEl.value) return;
    reportMapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        reportMapError.value = true;
        return;
    }
    if (reportMap) {
        reportMap.destroy();
        reportMap = null;
        reportPlacemark = null;
    }
    reportMap = new ymaps.Map(reportMapEl.value, { center: [53.7098, 27.9534], zoom: 6, controls: ['zoomControl'] }, { suppressMapOpenBlock: true });
    reportMap.events.add('click', async (e) => {
        const coords = e.get('coords');
        eventForm.lat = Number(coords[0]);
        eventForm.lng = Number(coords[1]);
        eventForm.location = `${eventForm.lat.toFixed(5)}, ${eventForm.lng.toFixed(5)}`;
        if (reportPlacemark) {
            reportPlacemark.geometry.setCoordinates(coords);
        } else {
            reportPlacemark = new ymaps.Placemark(coords, { hintContent: 'Место события' });
            reportMap.geoObjects.add(reportPlacemark);
        }
    });
}

async function retryEventsMap() {
    await nextTick();
    await initEventsMap(events.value);
}

async function retryReportMap() {
    await nextTick();
    await initReportMap();
}

onMounted(async () => {
    dictionaries.load();
    await refreshFeeds();
    refreshTimer = setInterval(refreshFeeds, 60 * 60 * 1000);
    clockTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 10 * 1000);
});

watch([articlesModalOpen, eventsModalOpen], ([articlesOpen, eventsOpen]) => {
    document.body.style.overflow = articlesOpen || eventsOpen ? 'hidden' : '';
}, { immediate: true });

onUnmounted(() => {
    document.body.style.overflow = '';
    if (refreshTimer) clearInterval(refreshTimer);
    if (clockTimer) clearInterval(clockTimer);
    if (ymap) ymap.destroy();
    if (reportMap) reportMap.destroy();
});
</script>

<style>
.news-intro {
    padding: 112px 0 24px;
}

.news-intro__inner {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 40px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
}

.news-intro__eyebrow {
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.news-intro h1 {
    margin: 10px 0 0;
    font-size: clamp(38px, 5vw, 68px);
    line-height: .95;
}

.news-intro p {
    max-width: 680px;
    margin-top: 15px;
    color: var(--text-2);
    font-size: 14px;
    line-height: 1.6;
}

.news-intro__links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 24px;
    padding-left: 28px;
    border-left: 1px solid var(--border);
}

.news-intro__links a {
    color: var(--text-2);
    font-size: 12px;
    text-decoration: none;
}

.news-intro__links a::after {
    content: ' ↓';
    color: var(--accent);
}

.news-intro__links a:hover {
    color: var(--text);
}

.live-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
}

.live-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.live-toolbar span {
    color: var(--text-3);
    font-size: 12px;
}

.article-tag-filter {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.article-tag-filter--modal {
    margin-top: 0;
    margin-bottom: 18px;
}

.article-tag-filter__button,
.article-tag-filter__reset {
    min-height: 38px;
}

.article-tag-panel {
    position: absolute;
    z-index: 20;
    top: calc(100% + 8px);
    left: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    width: min(520px, calc(100vw - 40px));
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
    box-shadow: var(--shadow-md);
}

.article-tag-panel--modal {
    top: calc(100% + 8px);
}

.article-tag-option {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 7px 9px;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-2);
    cursor: pointer;
}

.article-tag-option:hover {
    border-color: var(--accent);
    color: var(--text);
    transform: none;
}

.article-card--more {
    position: relative;
    cursor: pointer;
}

.article-card--more .feature-image img,
.article-card--more .article-media-empty,
.article-card--more .feature-body {
    filter: blur(2px);
    opacity: .48;
}

.article-media-empty {
    width: 100%;
    height: 100%;
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: #1a1f1b;
    color: var(--text-3);
    font-size: 11px;
    line-height: 1.4;
    text-align: center;
}

.article-card__more-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, .38);
    color: #fff;
    font-family: var(--font-h);
    font-size: clamp(42px, 7vw, 76px);
}

.articles-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    justify-content: center;
    padding: 72px 20px 32px;
    background: rgba(0, 0, 0, .76);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.articles-modal__panel {
    width: min(1180px, 100%);
    min-height: min-content;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg);
    padding: 24px;
    overscroll-behavior: contain;
}

.articles-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.articles-modal__head h3 {
    margin: 0;
    font-size: 28px;
}

.articles-modal__close {
    width: 40px;
    height: 40px;
    min-height: 0;
    padding: 0;
    border-radius: 6px;
    font-size: 28px;
    line-height: 1;
    box-shadow: none;
}

.articles-modal__close:hover {
    transform: none;
}

.articles-modal__grid {
    margin-top: 18px;
}

.event-report-form {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 24px;
    padding: 18px;
    border: 1px solid var(--border-mid);
    border-radius: 8px;
    background: var(--s1);
}

.event-report-form__wide,
.event-report-form__map-block {
    grid-column: 1 / -1;
}

.event-report-form textarea {
    resize: vertical;
}

.event-report-map {
    height: 260px;
    margin-top: 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
}

.event-report-form__map-block p,
.event-report-form__message {
    margin-top: 8px;
    color: var(--text-3);
    font-size: 12px;
}

.event-media-preview {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.event-media-preview img {
    width: 82px;
    height: 58px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid var(--border);
}

.event-media-preview span {
    color: var(--text-2);
    font-size: 12px;
}

@media (max-width: 700px) {
    .news-intro {
        padding-top: 94px;
    }

    .news-intro__inner {
        display: grid;
        gap: 18px;
    }

    .news-intro__links {
        padding-top: 16px;
        padding-left: 0;
        border-top: 1px solid var(--border);
        border-left: 0;
    }
}

@media (max-width: 900px) {
    .event-report-form {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 620px) {
    .event-report-form {
        grid-template-columns: 1fr;
    }
}
</style>
