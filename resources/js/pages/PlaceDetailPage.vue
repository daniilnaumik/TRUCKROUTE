<template>
    <div>
        <div v-if="loading" class="page-state">Загрузка...</div>
        <div v-else-if="!poi" class="page-state">
            <h2>Объект не найден</h2>
            <RouterLink :to="{ name: 'places' }" class="btn">К объектам</RouterLink>
        </div>

        <template v-else>
            <section class="page-hero place-hero">
                <div class="container">
                    <div class="place-hero__copy">
                        <div class="place-meta">
                            <span class="badge">{{ poi.type }}</span>
                            <span v-if="poi.verified" class="verified-label">проверено</span>
                        </div>
                        <h1>{{ poi.name }}</h1>
                        <p class="lead">{{ poi.location }}</p>
                        <div class="place-stats">
                            <div>
                                <strong>{{ ratingText }}</strong>
                                <span>{{ reviewLabel }}</span>
                            </div>
                            <div v-if="poi.detour_km">
                                <strong>{{ poi.detour_km }} км</strong>
                                <span>от маршрута</span>
                            </div>
                            <div v-if="poi.highway">
                                <strong>{{ poi.highway }}</strong>
                                <span>трасса</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="poiImageUrl" class="page-visual place-visual">
                        <img :src="poiImageUrl" :alt="poi.name">
                    </div>
                    <div v-else class="place-detail-summary">
                        <span>Фото не приложено</span>
                        <strong>{{ coordsText }}</strong>
                        <p>Точное расположение и сведения об объекте доступны ниже.</p>
                    </div>
                </div>
            </section>

            <section class="section-tight">
                <div class="container place-layout">
                    <div class="place-information">
                        <section class="detail-block">
                            <div class="detail-title">
                                <span>Информация</span>
                                <h2>Перед заездом</h2>
                            </div>
                            <div class="facts-list">
                                <div v-if="poi.services" class="fact-row">
                                    <span>Услуги</span>
                                    <strong>{{ poi.services }}</strong>
                                </div>
                                <div v-if="workingHours.length" class="fact-row fact-row--stack">
                                    <span>Часы работы</span>
                                    <div class="schedule-list">
                                        <div v-for="item in workingHours" :key="item.label">
                                            <span>{{ item.label }}</span>
                                            <strong>{{ item.value }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="contactRows.length" class="fact-row fact-row--stack">
                                    <span>Контакты</span>
                                    <div class="contact-list">
                                        <a v-for="item in contactRows" :key="item.label" :href="item.href" :target="item.external ? '_blank' : null" rel="noopener">
                                            <span>{{ item.label }}</span>
                                            <strong>{{ item.value }}</strong>
                                        </a>
                                    </div>
                                </div>
                                <div v-if="poi.description" class="fact-row fact-row--stack">
                                    <span>Об объекте</span>
                                    <p>{{ poi.description }}</p>
                                </div>
                            </div>
                        </section>

                        <section v-if="activePromotions.length" class="detail-block promotions-block">
                            <div class="detail-title">
                                <span>Предложения</span>
                                <h2>Акции</h2>
                            </div>
                            <article v-for="promotion in activePromotions" :key="promotion.title" class="promotion-row">
                                <div>
                                    <strong>{{ promotion.title }}</strong>
                                    <p v-if="promotion.description">{{ promotion.description }}</p>
                                </div>
                                <span v-if="promotion.valid_until">до {{ formatShortDate(promotion.valid_until) }}</span>
                            </article>
                        </section>

                        <section v-if="priceRows.length" class="detail-block">
                            <div class="detail-title">
                                <span>Прайс</span>
                                <h2>Цены</h2>
                            </div>
                            <div class="price-list">
                                <div v-for="item in priceRows" :key="`${item.name}-${item.price}`">
                                    <div>
                                        <strong>{{ item.name }}</strong>
                                        <span v-if="item.note">{{ item.note }}</span>
                                    </div>
                                    <b>{{ formatPrice(item) }}</b>
                                </div>
                            </div>
                        </section>

                        <section v-if="hasTruckAccess" class="detail-block">
                            <div class="detail-title">
                                <span>Большегрузный транспорт</span>
                                <h2>Подъезд грузовиков</h2>
                            </div>
                            <div class="truck-grid">
                                <div v-for="item in truckFacts" :key="item.label">
                                    <span>{{ item.label }}</span>
                                    <strong>{{ item.value }}</strong>
                                </div>
                            </div>
                            <p v-if="poi.truck_access?.notes" class="truck-note">{{ poi.truck_access.notes }}</p>
                        </section>

                        <section v-if="poi.content" class="detail-block">
                            <div class="detail-title">
                                <span>Подробнее</span>
                                <h2>Описание</h2>
                            </div>
                            <div class="rich-content" v-html="poi.content"></div>
                        </section>
                    </div>

                    <aside class="place-map-column">
                        <div class="map-heading">
                            <div>
                                <span>Местоположение</span>
                                <h2>На карте</h2>
                            </div>
                            <span>{{ coordsText }}</span>
                        </div>
                        <MapFallback v-if="coords && mapError" class="place-map" :retry="initMap" />
                        <div v-if="coords" v-show="!mapError" ref="mapEl" class="place-map"></div>
                        <div v-else class="map-empty">Координаты объекта не указаны.</div>
                    </aside>
                </div>
            </section>

            <section class="section-tight reviews-section">
                <div class="container reviews-layout">
                    <div class="reviews-heading">
                        <span>Опыт водителей</span>
                        <h2>Отзывы</h2>
                        <div class="reviews-score">
                            <strong>{{ ratingText }}</strong>
                            <span>{{ reviewLabel }}</span>
                        </div>
                    </div>

                    <div class="reviews-content">
                        <form v-if="canReview" class="review-form" @submit.prevent="submitReview">
                            <div>
                                <strong>{{ ownReview ? 'Изменить свой отзыв' : 'Оценить объект' }}</strong>
                                <div class="star-picker" aria-label="Оценка">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        :class="{ active: star <= reviewForm.rating }"
                                        @click="reviewForm.rating = star"
                                    >★</button>
                                </div>
                            </div>
                            <textarea v-model="reviewForm.body" rows="3" maxlength="3000" placeholder="Что важно знать другим водителям?"></textarea>
                            <div class="review-form__actions">
                                <button type="submit" class="btn" :disabled="reviewSaving">
                                    {{ reviewSaving ? 'Сохраняем...' : 'Сохранить отзыв' }}
                                </button>
                                <button v-if="ownReview" type="button" class="text-action danger-text" @click="deleteReview">Удалить</button>
                            </div>
                        </form>
                        <div v-else-if="!auth.isAuthenticated" class="review-login">
                            <span>Чтобы оставить отзыв, войдите в аккаунт.</span>
                            <RouterLink :to="{ name: 'login', query: { redirect: vueRoute.fullPath } }" class="btn outline">Войти</RouterLink>
                        </div>

                        <div v-if="!reviews.length" class="reviews-empty">Отзывов пока нет. Можно стать первым.</div>
                        <article v-for="review in reviews" :key="review.id" class="review-item">
                            <div class="review-item__head">
                                <div>
                                    <strong>{{ review.user?.name || 'Пользователь' }}</strong>
                                    <span>{{ formatDate(review.created_at) }}</span>
                                </div>
                                <span class="review-stars">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</span>
                            </div>
                            <p>{{ review.body || 'Оценка без комментария.' }}</p>
                            <div v-if="review.owner_reply" class="public-owner-reply">
                                <span>Ответ владельца</span>
                                <p>{{ review.owner_reply }}</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { loadYandexMaps } from '@/composables/yandexMaps';
import MapFallback from '@/components/MapFallback.vue';

const vueRoute = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const poi = ref(null);
const loading = ref(true);
const mapEl = ref(null);
const reviewSaving = ref(false);
const reviewForm = ref({ rating: 5, body: '' });
const mapError = ref(false);

const reviews = computed(() => Array.isArray(poi.value?.reviews) ? poi.value.reviews : []);
const ownReview = computed(() => reviews.value.find((review) => review.user?.id === auth.user?.id) ?? null);
const canReview = computed(() => auth.isAuthenticated && poi.value?.provider_id !== auth.user?.id);
const ratingText = computed(() => {
    const count = Number(poi.value?.reviews_count || reviews.value.length);
    return count > 0 ? Number(poi.value?.rating || 0).toFixed(1) : '—';
});
const reviewLabel = computed(() => {
    const count = Number(poi.value?.reviews_count || reviews.value.length);
    return `${count} ${count === 1 ? 'отзыв' : count > 1 && count < 5 ? 'отзыва' : 'отзывов'}`;
});

const coords = computed(() => {
    const lat = poi.value?.coordinates?.lat ?? poi.value?.lat;
    const lng = poi.value?.coordinates?.lng ?? poi.value?.lng;
    return lat != null && lng != null ? { lat: Number(lat), lng: Number(lng) } : null;
});

const poiImageUrl = computed(() => poi.value?.image_url || poi.value?.gallery?.[0] || '');
const coordsText = computed(() => coords.value
    ? `${coords.value.lat.toFixed(5)}, ${coords.value.lng.toFixed(5)}`
    : 'Координаты не указаны');

const workingHours = computed(() => {
    const hours = poi.value?.working_hours ?? {};
    return [
        { label: 'Пн–Пт', value: hours.weekdays },
        { label: 'Суббота', value: hours.saturday },
        { label: 'Воскресенье', value: hours.sunday },
        { label: 'Примечание', value: hours.note },
    ].filter((item) => item.value);
});

const contactRows = computed(() => {
    const contacts = poi.value?.contacts ?? {};
    return [
        { label: 'Телефон', value: contacts.phone, href: contacts.phone ? `tel:${contacts.phone}` : '' },
        { label: 'Email', value: contacts.email, href: contacts.email ? `mailto:${contacts.email}` : '' },
        { label: 'Сайт', value: contacts.website, href: contacts.website, external: true },
        { label: 'Мессенджер', value: contacts.messenger, href: null },
    ].filter((item) => item.value);
});

const priceRows = computed(() => Array.isArray(poi.value?.price_details) ? poi.value.price_details : []);
const activePromotions = computed(() => {
    const items = Array.isArray(poi.value?.promotions) ? poi.value.promotions : [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return items.filter((item) => !item.valid_until || new Date(item.valid_until) >= today);
});

const truckFacts = computed(() => {
    const access = poi.value?.truck_access ?? {};
    return [
        { label: 'Въезд', value: access.allowed === true ? 'разрешён' : access.allowed === false ? 'не указан' : null },
        { label: 'Высота', value: access.max_height_m ? `до ${access.max_height_m} м` : null },
        { label: 'Длина', value: access.max_length_m ? `до ${access.max_length_m} м` : null },
        { label: 'Масса', value: access.max_weight_t ? `до ${access.max_weight_t} т` : null },
        { label: 'Покрытие', value: access.surface },
        { label: 'Разворот', value: access.turnaround ? 'есть площадка' : null },
        { label: 'Парковка', value: access.parking_spaces ? `${access.parking_spaces} мест` : poi.value?.has_truck_parking ? 'есть' : null },
    ].filter((item) => item.value);
});
const hasTruckAccess = computed(() => truckFacts.value.length > 0 || poi.value?.truck_access?.notes);

onMounted(loadPoi);

async function loadPoi() {
    try {
        const { data } = await axios.get(`/api/v1/poi/${vueRoute.params.id}`);
        poi.value = data.data ?? data;
        if (ownReview.value) {
            reviewForm.value = { rating: ownReview.value.rating, body: ownReview.value.body ?? '' };
        }
    } catch {
        poi.value = null;
    } finally {
        loading.value = false;
    }

    if (coords.value) {
        await nextTick();
        setTimeout(initMap, 100);
    }
}

async function submitReview() {
    reviewSaving.value = true;
    try {
        await axios.post(`/api/v1/poi/${poi.value.id}/reviews`, reviewForm.value);
        ui.success(ownReview.value ? 'Отзыв обновлён.' : 'Спасибо за отзыв.');
        await reloadReviews();
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось сохранить отзыв.');
    } finally {
        reviewSaving.value = false;
    }
}

async function deleteReview() {
    if (!ownReview.value || !window.confirm('Удалить свой отзыв?')) return;
    try {
        await axios.delete(`/api/v1/poi/${poi.value.id}/reviews/${ownReview.value.id}`);
        reviewForm.value = { rating: 5, body: '' };
        ui.success('Отзыв удалён.');
        await reloadReviews();
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось удалить отзыв.');
    }
}

async function reloadReviews() {
    const { data } = await axios.get(`/api/v1/poi/${poi.value.id}`);
    poi.value = data.data ?? data;
    if (ownReview.value) {
        reviewForm.value = { rating: ownReview.value.rating, body: ownReview.value.body ?? '' };
    }
}

function formatPrice(item) {
    if (item.price === null || item.price === undefined || item.price === '') return 'уточняется';
    return `${new Intl.NumberFormat('ru-RU').format(item.price)}${item.unit ? ` ${item.unit}` : ''}`;
}
function formatShortDate(value) {
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short' }).format(new Date(value));
}
function formatDate(value) {
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(value));
}

async function initMap() {
    if (!mapEl.value || !coords.value) return;
    mapError.value = false;
    let ymaps;
    try {
        ymaps = await loadYandexMaps();
    } catch {
        mapError.value = true;
        return;
    }
    const map = new ymaps.Map(mapEl.value, {
        center: [coords.value.lat, coords.value.lng],
        zoom: 14,
        controls: ['zoomControl', 'fullscreenControl'],
    }, { suppressMapOpenBlock: true });
    map.geoObjects.add(new ymaps.Placemark(
        [coords.value.lat, coords.value.lng],
        { hintContent: poi.value.name, balloonContent: poi.value.name },
        { preset: 'islands#goldIcon' },
    ));
}
</script>

<style scoped>
.page-state { min-height: 55vh; display: grid; place-content: center; gap: 18px; text-align: center; color: var(--text-3); }
.place-hero h1 { max-width: 720px; margin-top: 14px; font-size: clamp(42px, 6vw, 82px); }
.place-meta { display: flex; align-items: center; gap: 10px; }
.verified-label { color: var(--accent); font: 10px var(--font-m); text-transform: uppercase; }
.place-stats { display: flex; margin-top: 34px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.place-stats > div { min-width: 130px; padding: 16px 24px 16px 0; }
.place-stats > div + div { padding-left: 24px; border-left: 1px solid var(--border); }
.place-stats strong, .place-stats span { display: block; }
.place-stats strong { color: var(--accent); font: 28px var(--font-d); }
.place-stats span { margin-top: 3px; color: var(--text-3); font: 10px var(--font-m); text-transform: uppercase; }
.place-visual img { width: 100%; height: 100%; object-fit: cover; }
.place-detail-summary { min-height: 280px; display: flex; flex-direction: column; justify-content: center; gap: 12px; padding: 28px; border: 1px solid var(--border); border-radius: 8px; background: var(--s1); }
.place-detail-summary > span { color: var(--text-3); font: 10px var(--font-m); text-transform: uppercase; }
.place-detail-summary strong { font: 30px var(--font-d); }
.place-detail-summary p { max-width: 420px; color: var(--text-2); font-size: 13px; }
.place-layout { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr); gap: 56px; align-items: start; }
.place-information { min-width: 0; }
.detail-block { padding: 30px 0; border-bottom: 1px solid var(--border); }
.detail-block:first-child { padding-top: 0; }
.detail-title > span, .map-heading > div > span, .reviews-heading > span { color: var(--text-3); font: 10px var(--font-m); text-transform: uppercase; }
.detail-title h2, .map-heading h2, .reviews-heading h2 { margin-top: 5px; font-size: 28px; }
.facts-list { margin-top: 22px; }
.fact-row { display: grid; grid-template-columns: 150px 1fr; gap: 24px; padding: 13px 0; border-top: 1px solid var(--border); }
.fact-row > span { color: var(--text-3); font-size: 12px; }
.fact-row strong, .fact-row p { color: var(--text-2); font-size: 13px; line-height: 1.55; }
.schedule-list > div, .contact-list a { display: flex; justify-content: space-between; gap: 20px; padding: 6px 0; color: inherit; text-decoration: none; }
.schedule-list span, .contact-list span { color: var(--text-3); font-size: 12px; }
.contact-list a:hover strong { color: var(--accent); }
.promotion-row, .price-list > div { display: flex; justify-content: space-between; gap: 24px; padding: 15px 0; border-top: 1px solid var(--border); }
.promotions-block .promotion-row:first-of-type, .price-list { margin-top: 20px; }
.promotion-row p, .price-list span { margin-top: 4px; color: var(--text-3); font-size: 12px; }
.promotion-row > span { color: var(--accent); font-size: 12px; white-space: nowrap; }
.price-list strong, .price-list span { display: block; }
.price-list b { color: var(--accent); font: 20px var(--font-d); white-space: nowrap; }
.truck-grid { display: grid; grid-template-columns: repeat(3, 1fr); margin-top: 22px; border-top: 1px solid var(--border); border-left: 1px solid var(--border); }
.truck-grid > div { min-height: 82px; padding: 14px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.truck-grid span, .truck-grid strong { display: block; }
.truck-grid span { color: var(--text-3); font-size: 10px; text-transform: uppercase; }
.truck-grid strong { margin-top: 8px; font-size: 13px; }
.truck-note { margin-top: 14px; color: var(--text-2); font-size: 13px; line-height: 1.55; }
.rich-content { margin-top: 20px; color: var(--text-2); line-height: 1.65; }
.place-map-column { position: sticky; top: calc(var(--header-h) + 24px); }
.map-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 16px; }
.map-heading > span { color: var(--text-3); font: 10px var(--font-m); }
.place-map, .map-empty { width: 100%; height: 510px; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; }
.map-empty { display: grid; place-items: center; color: var(--text-3); font-size: 13px; }
.reviews-section { border-top: 1px solid var(--border); }
.reviews-layout { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 64px; }
.reviews-heading { position: sticky; top: calc(var(--header-h) + 24px); align-self: start; }
.reviews-score { margin-top: 28px; padding-top: 18px; border-top: 1px solid var(--border); }
.reviews-score strong, .reviews-score span { display: block; }
.reviews-score strong { color: var(--accent); font: 48px var(--font-d); }
.reviews-score span { color: var(--text-3); font-size: 12px; }
.review-form { padding-bottom: 26px; border-bottom: 1px solid var(--border); }
.review-form > div:first-child { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
.star-picker { display: flex; }
.star-picker button { min-height: 0; padding: 2px; border: 0; background: transparent; color: var(--border-mid); font-size: 24px; box-shadow: none; }
.star-picker button.active { color: var(--accent); }
.star-picker button:hover { transform: none; color: var(--accent); }
.review-form textarea { width: 100%; margin-top: 14px; resize: vertical; }
.review-form__actions { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.text-action { min-height: 0; padding: 0; border: 0; background: transparent; box-shadow: none; }
.text-action:hover { transform: none; }
.danger-text { color: var(--red); }
.review-login, .reviews-empty { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 18px 0; border-bottom: 1px solid var(--border); color: var(--text-3); font-size: 13px; }
.review-item { padding: 24px 0; border-bottom: 1px solid var(--border); }
.review-item__head { display: flex; justify-content: space-between; gap: 20px; }
.review-item__head strong, .review-item__head span { display: block; }
.review-item__head > div > span { margin-top: 3px; color: var(--text-3); font-size: 11px; }
.review-stars { color: var(--accent); letter-spacing: 2px; }
.review-item > p { margin-top: 12px; color: var(--text-2); line-height: 1.6; }
.public-owner-reply { margin-top: 16px; padding: 14px 0 0 16px; border-left: 2px solid var(--accent); }
.public-owner-reply span { color: var(--text-3); font: 10px var(--font-m); text-transform: uppercase; }
.public-owner-reply p { margin-top: 6px; color: var(--text-2); line-height: 1.55; }

@media (max-width: 900px) {
    .place-layout, .reviews-layout { grid-template-columns: 1fr; gap: 34px; }
    .place-map-column, .reviews-heading { position: static; }
    .place-map { height: 390px; }
}
@media (max-width: 600px) {
    .place-stats { overflow-x: auto; }
    .place-stats > div { min-width: 120px; }
    .fact-row { grid-template-columns: 1fr; gap: 7px; }
    .truck-grid { grid-template-columns: repeat(2, 1fr); }
    .place-map { height: 330px; }
    .review-form > div:first-child, .review-login { align-items: flex-start; flex-direction: column; }
}
</style>
