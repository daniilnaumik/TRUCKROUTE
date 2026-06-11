<template>
    <div class="poi-editor-page">
        <!-- Header bar -->
        <div class="editor-topbar">
            <div class="container editor-topbar-inner">
                <div style="display:flex;align-items:center;gap:12px;">
                    <RouterLink :to="{ name: 'provider' }" class="btn outline" style="padding:6px 14px;min-height:auto;">← Назад</RouterLink>
                    <h2 style="margin:0;font-size:20px;">{{ isEdit ? 'Редактировать объект' : 'Новый объект' }}</h2>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn outline" @click="showPreview = true">Предпросмотр</button>
                    <button type="button" class="btn" @click="save" :disabled="saving">
                        {{ saving ? 'Сохраняем...' : 'Сохранить' }}
                    </button>
                    <button v-if="isEdit" type="button" class="btn danger" @click="deletePoi" :disabled="saving">Удалить</button>
                </div>
            </div>
        </div>

        <div class="container editor-body">
            <div v-if="formError" class="editor-error">{{ formError }}</div>

            <!-- 1. Gallery -->
            <div class="editor-section">
                <h3>Фотографии</h3>
                <div class="gallery-slot-row">
                    <!-- Existing images -->
                    <div v-for="(img, idx) in form.gallery" :key="img" class="gallery-slot gallery-slot--filled">
                        <img :src="imgUrl(img)" :alt="`Фото ${idx+1}`">
                        <button type="button" class="gallery-remove" @click="removeGalleryImg(idx)" aria-label="Удалить">✕</button>
                        <span v-if="idx === 0" class="gallery-cover-badge">Обложка</span>
                    </div>
                    <!-- Upload slot -->
                    <label class="gallery-slot gallery-slot--upload" v-if="form.gallery.length < 8">
                        <input type="file" accept="image/*" multiple style="display:none" @change="uploadImages">
                        <span>+ Добавить</span>
                        <span style="font-size:10px;color:var(--text-3);">JPG/PNG до 8 МБ</span>
                    </label>
                </div>
            </div>

            <!-- 2. Main fields -->
            <div class="editor-section">
                <h3>Основная информация</h3>
                <div class="wizard-form-2">
                    <div class="field">
                        <label>Название <span style="color:var(--red)">*</span></label>
                        <input v-model="form.name" placeholder="АЗС Лукойл, Мотель Дорожный...">
                    </div>
                    <div class="field">
                        <label>Тип <span style="color:var(--red)">*</span></label>
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
                        <input v-model="form.location" placeholder="М-4, км 245, Воронежская область">
                    </div>
                    <div class="field">
                        <label>Широта (lat)</label>
                        <input v-model.number="form.lat" type="number" step="0.000001" placeholder="51.6704">
                    </div>
                    <div class="field">
                        <label>Долгота (lng)</label>
                        <input v-model.number="form.lng" type="number" step="0.000001" placeholder="39.2073">
                    </div>
                    <div class="field">
                        <label>Трасса</label>
                        <input v-model="form.highway" placeholder="М-4 Дон">
                    </div>
                    <div class="field">
                        <label>Км-маркер</label>
                        <input v-model.number="form.km_marker" type="number" min="0">
                    </div>
                    <div class="field">
                        <label>Бренд/сеть</label>
                        <input v-model="form.brand" placeholder="Лукойл, Газпромнефть...">
                    </div>
                    <div class="field">
                        <label>Цена топлива (₽/л)</label>
                        <input v-model.number="form.fuel_price" type="number" step="0.01" placeholder="62.50">
                    </div>
                    <div class="field">
                        <label>Крюк (км)</label>
                        <input v-model.number="form.detour_km" type="number" step="0.1" min="0">
                    </div>
                    <div class="field" style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                        <label class="toggle"><input type="checkbox" v-model="form.has_truck_parking"></label>
                        <span>Есть парковка для фур</span>
                    </div>
                </div>
            </div>

            <!-- 3. Tags -->
            <div class="editor-section">
                <h3>Теги</h3>
                <div class="tag-chips">
                    <span v-for="(tag, i) in form.tags" :key="tag" class="tag-chip">
                        {{ tag }}
                        <button type="button" @click="removeTag(i)" class="tag-chip__remove">✕</button>
                    </span>
                    <input
                        class="tag-input"
                        v-model="tagDraft"
                        placeholder="Добавить тег..."
                        @keydown.enter.prevent="addTag"
                        @keydown.comma.prevent="addTag"
                    >
                    <div v-if="suggestedTagOptions.length" class="tag-suggest-inline">
                        <button
                            v-for="tag in suggestedTagOptions"
                            :key="tag.value"
                            type="button"
                            @click="selectSuggestedTag(tag.value)"
                        >
                            {{ tag.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. Services -->
            <div class="editor-section">
                <h3>Услуги (кратко)</h3>
                <input v-model="form.services" placeholder="Душ, кафе, охрана, стоянка 24/7...">
            </div>

            <div class="editor-section">
                <div class="section-heading">
                    <h3>Часы работы и контакты</h3>
                    <p>Водитель сразу увидит, когда объект открыт и как с ним связаться.</p>
                </div>
                <div class="wizard-form-2">
                    <div class="field">
                        <label>Понедельник – пятница</label>
                        <input v-model="form.working_hours.weekdays" placeholder="08:00–22:00 или круглосуточно">
                    </div>
                    <div class="field">
                        <label>Суббота</label>
                        <input v-model="form.working_hours.saturday" placeholder="09:00–20:00">
                    </div>
                    <div class="field">
                        <label>Воскресенье</label>
                        <input v-model="form.working_hours.sunday" placeholder="Выходной">
                    </div>
                    <div class="field">
                        <label>Примечание к графику</label>
                        <input v-model="form.working_hours.note" placeholder="Кафе до 23:00">
                    </div>
                    <div class="field">
                        <label>Телефон</label>
                        <input v-model="form.contacts.phone" type="tel" placeholder="+375 29 000-00-00">
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input v-model="form.contacts.email" type="email" placeholder="info@example.by">
                    </div>
                    <div class="field">
                        <label>Сайт</label>
                        <input v-model="form.contacts.website" type="url" placeholder="https://example.by">
                    </div>
                    <div class="field">
                        <label>Мессенджер</label>
                        <input v-model="form.contacts.messenger" placeholder="Telegram, Viber или WhatsApp">
                    </div>
                </div>
            </div>

            <div class="editor-section">
                <div class="section-heading section-heading--row">
                    <div>
                        <h3>Подробные цены</h3>
                        <p>Топливо, парковка, душ, ремонт и другие услуги.</p>
                    </div>
                    <button type="button" class="btn outline compact-btn" @click="addPrice">+ Позиция</button>
                </div>
                <div v-if="!form.price_details.length" class="empty-line">Прайс пока не заполнен.</div>
                <div v-for="(item, index) in form.price_details" :key="index" class="repeat-row price-row">
                    <input v-model="item.name" placeholder="Услуга или товар">
                    <input v-model.number="item.price" type="number" min="0" step="0.01" placeholder="Цена">
                    <input v-model="item.unit" placeholder="за литр / час">
                    <input v-model="item.note" placeholder="Примечание">
                    <button type="button" class="remove-row" @click="removePrice(index)" aria-label="Удалить">×</button>
                </div>
            </div>

            <div class="editor-section">
                <div class="section-heading section-heading--row">
                    <div>
                        <h3>Акции</h3>
                        <p>Актуальные предложения будут выделены в карточке объекта.</p>
                    </div>
                    <button type="button" class="btn outline compact-btn" @click="addPromotion">+ Акция</button>
                </div>
                <div v-if="!form.promotions.length" class="empty-line">Активных акций нет.</div>
                <div v-for="(item, index) in form.promotions" :key="index" class="repeat-row promotion-row">
                    <input v-model="item.title" placeholder="Название акции">
                    <input v-model="item.valid_until" type="date">
                    <input v-model="item.description" placeholder="Условия и описание">
                    <button type="button" class="remove-row" @click="removePromotion(index)" aria-label="Удалить">×</button>
                </div>
            </div>

            <div class="editor-section">
                <div class="section-heading">
                    <h3>Подъезд грузовиков</h3>
                    <p>Ограничения и условия, которые важны водителю до заезда.</p>
                </div>
                <div class="inline-switches">
                    <label><input v-model="form.truck_access.allowed" type="checkbox"> Въезд грузовиков разрешён</label>
                    <label><input v-model="form.truck_access.turnaround" type="checkbox"> Есть место для разворота</label>
                </div>
                <div class="wizard-form-2">
                    <div class="field">
                        <label>Макс. высота (м)</label>
                        <input v-model.number="form.truck_access.max_height_m" type="number" min="0" step="0.1">
                    </div>
                    <div class="field">
                        <label>Макс. длина (м)</label>
                        <input v-model.number="form.truck_access.max_length_m" type="number" min="0" step="0.1">
                    </div>
                    <div class="field">
                        <label>Макс. масса (т)</label>
                        <input v-model.number="form.truck_access.max_weight_t" type="number" min="0" step="0.1">
                    </div>
                    <div class="field">
                        <label>Покрытие подъезда</label>
                        <input v-model="form.truck_access.surface" placeholder="Асфальт, бетон, грунт">
                    </div>
                    <div class="field">
                        <label>Мест для фур</label>
                        <input v-model.number="form.truck_access.parking_spaces" type="number" min="0">
                    </div>
                    <div class="field">
                        <label>Пояснение</label>
                        <input v-model="form.truck_access.notes" placeholder="Въезд с тыльной стороны">
                    </div>
                </div>
            </div>

            <!-- 5. Video -->
            <div class="editor-section">
                <h3>Видео</h3>
                <input v-model="form.video_url" placeholder="https://www.youtube.com/watch?v=...">
                <div v-if="youtubeEmbedId" class="video-preview">
                    <iframe :src="`https://www.youtube.com/embed/${youtubeEmbedId}`" allowfullscreen></iframe>
                </div>
            </div>

            <!-- 6. Rich text description -->
            <div class="editor-section">
                <h3>Описание</h3>
                <TiptapEditor v-model="form.content" placeholder="Расскажите о вашем объекте подробно. Используйте заголовки, списки, изображения и видео..." min-height="240px" />
            </div>

            <div v-if="isEdit" class="editor-section">
                <div class="section-heading section-heading--row">
                    <div>
                        <h3>Отзывы пользователей</h3>
                        <p>Рейтинг считается автоматически. Ваши ответы видны всем посетителям.</p>
                    </div>
                    <span class="rating-summary">{{ displayRating }} / 5</span>
                </div>
                <div v-if="!providerReviews.length" class="empty-line">Отзывов пока нет.</div>
                <article v-for="review in providerReviews" :key="review.id" class="owner-review">
                    <div class="owner-review__head">
                        <strong>{{ review.user?.name || 'Пользователь' }}</strong>
                        <span>{{ '★'.repeat(review.rating) }} · {{ formatDate(review.created_at) }}</span>
                    </div>
                    <p>{{ review.body || 'Оценка без текста.' }}</p>
                    <div v-if="review.owner_reply" class="owner-reply">
                        <span>Ваш ответ</span>
                        <p>{{ review.owner_reply }}</p>
                        <button type="button" @click="deleteReply(review)">Удалить ответ</button>
                    </div>
                    <div v-else class="reply-form">
                        <textarea v-model="replyDrafts[review.id]" rows="2" placeholder="Ответить от имени объекта"></textarea>
                        <button type="button" class="btn outline compact-btn" @click="saveReply(review)">Ответить</button>
                    </div>
                </article>
            </div>
        </div>

        <!-- Preview modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="showPreview = false"></div>
                <div class="auth-modal__panel" style="max-width:720px;width:94vw;max-height:85dvh;overflow-y:auto;">
                    <button class="auth-modal__close" @click="showPreview = false">закрыть</button>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                        <span class="badge">{{ form.type }}</span>
                        <span style="font-size:12px;color:var(--text-3);font-family:var(--font-m);">{{ form.highway }}</span>
                    </div>
                    <h2 style="font-size:28px;margin-top:8px;">{{ form.name || '—' }}</h2>
                    <p style="color:var(--text-2);margin-top:8px;">{{ form.location }}</p>
                    <div v-if="form.gallery?.length" class="preview-gallery">
                        <img v-for="img in form.gallery" :key="img" :src="imgUrl(img)" :alt="form.name">
                    </div>
                    <div v-if="form.tags?.length" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;">
                        <span v-for="t in form.tags" :key="t" class="badge">{{ t }}</span>
                    </div>
                    <p v-if="form.services" style="font-size:13px;color:var(--text-2);margin-top:12px;">{{ form.services }}</p>
                    <div v-if="form.content" class="tiptap-editor-inner" v-html="form.content" style="margin-top:16px;padding:0;"></div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';
import { useDictionariesStore } from '@/stores/dictionaries';
import TiptapEditor from '@/components/TiptapEditor.vue';

const vueRoute = useRoute();
const router   = useRouter();
const ui       = useUiStore();
const dictionaries = useDictionariesStore();

const isEdit = computed(() => !!vueRoute.params.id);
const saving       = ref(false);
const formError    = ref('');
const showPreview  = ref(false);
const tagDraft     = ref('');
const uploadingImg = ref(false);
const providerReviews = ref([]);
const replyDrafts = ref({});

const form = ref({
    name: '', type: 'АЗС', location: '', lat: '', lng: '',
    highway: '', km_marker: '', brand: '', fuel_price: '',
    detour_km: '', has_truck_parking: false,
    services: '', video_url: '', content: '',
    gallery: [], tags: [],
    working_hours: { weekdays: '', saturday: '', sunday: '', note: '' },
    contacts: { phone: '', email: '', website: '', messenger: '' },
    price_details: [],
    promotions: [],
    truck_access: {
        allowed: false,
        max_height_m: '',
        max_length_m: '',
        max_weight_t: '',
        surface: '',
        turnaround: false,
        parking_spaces: '',
        notes: '',
    },
});

const displayRating = computed(() => {
    if (!providerReviews.value.length) return '0.0';
    const total = providerReviews.value.reduce((sum, review) => sum + Number(review.rating || 0), 0);
    return (total / providerReviews.value.length).toFixed(1);
});

const suggestedTagOptions = computed(() => {
    const query = tagDraft.value.trim().toLowerCase();
    return dictionaries.options('tags')
        .filter((tag) => !form.value.tags.includes(tag.value))
        .filter((tag) => !query || tag.label.toLowerCase().includes(query) || tag.value.toLowerCase().includes(query))
        .slice(0, 10);
});

// Load existing POI for edit
onMounted(async () => {
    dictionaries.load();
    if (!isEdit.value) return;
    try {
        const { data } = await axios.get(`/api/v1/provider/poi/${vueRoute.params.id}`);
        const p = data.data ?? data;
        Object.assign(form.value, {
            name: p.name ?? '',
            type: p.type ?? 'АЗС',
            location: p.location ?? '',
            lat: p.coordinates?.lat ?? p.lat ?? '',
            lng: p.coordinates?.lng ?? p.lng ?? '',
            highway: p.highway ?? '',
            km_marker: p.km_marker ?? '',
            brand: p.brand ?? '',
            fuel_price: p.fuel_price ?? '',
            detour_km: p.detour_km ?? '',
            has_truck_parking: p.has_truck_parking ?? false,
            services: p.services ?? '',
            video_url: p.video_url ?? '',
            content: p.content ?? '',
            gallery: p.gallery ?? [],
            tags: p.tags ?? [],
            working_hours: {
                weekdays: p.working_hours?.weekdays ?? '',
                saturday: p.working_hours?.saturday ?? '',
                sunday: p.working_hours?.sunday ?? '',
                note: p.working_hours?.note ?? '',
            },
            contacts: {
                phone: p.contacts?.phone ?? '',
                email: p.contacts?.email ?? '',
                website: p.contacts?.website ?? '',
                messenger: p.contacts?.messenger ?? '',
            },
            price_details: Array.isArray(p.price_details) ? p.price_details : [],
            promotions: Array.isArray(p.promotions) ? p.promotions : [],
            truck_access: {
                allowed: p.truck_access?.allowed ?? false,
                max_height_m: p.truck_access?.max_height_m ?? '',
                max_length_m: p.truck_access?.max_length_m ?? '',
                max_weight_t: p.truck_access?.max_weight_t ?? '',
                surface: p.truck_access?.surface ?? '',
                turnaround: p.truck_access?.turnaround ?? false,
                parking_spaces: p.truck_access?.parking_spaces ?? '',
                notes: p.truck_access?.notes ?? '',
            },
        });
        providerReviews.value = Array.isArray(p.reviews) ? p.reviews : [];
    } catch { ui.error('Не удалось загрузить объект'); }
});

// Helpers
function imgUrl(path) {
    if (!path) return '/assets/images/road-warm-forest.jpg';
    if (path.startsWith('http')) return path;
    if (path.startsWith('/')) return path;
    return '/storage/' + path;
}

function addPrice() {
    form.value.price_details.push({ name: '', price: '', unit: '', note: '' });
}
function removePrice(index) { form.value.price_details.splice(index, 1); }
function addPromotion() {
    form.value.promotions.push({ title: '', description: '', valid_until: '' });
}
function removePromotion(index) { form.value.promotions.splice(index, 1); }

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

async function saveReply(review) {
    const reply = (replyDrafts.value[review.id] ?? '').trim();
    if (reply.length < 2) {
        ui.warning('Напишите ответ.');
        return;
    }
    try {
        const { data } = await axios.post(`/api/v1/poi/${vueRoute.params.id}/reviews/${review.id}/reply`, { reply });
        const updated = data.data ?? data;
        const index = providerReviews.value.findIndex((item) => item.id === review.id);
        if (index !== -1) providerReviews.value[index] = updated;
        replyDrafts.value[review.id] = '';
        ui.success('Ответ опубликован.');
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось опубликовать ответ.');
    }
}

async function deleteReply(review) {
    if (!window.confirm('Удалить ответ владельца?')) return;
    try {
        await axios.delete(`/api/v1/poi/${vueRoute.params.id}/reviews/${review.id}/reply`);
        review.owner_reply = null;
        review.owner_replied_at = null;
        ui.success('Ответ удалён.');
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось удалить ответ.');
    }
}

const youtubeEmbedId = computed(() => {
    const url = form.value.video_url;
    if (!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
    return m?.[1] ?? null;
});

// Tags
function addTag() {
    const t = tagDraft.value.trim().replace(/,$/, '');
    if (t && !form.value.tags.includes(t)) form.value.tags.push(t);
    tagDraft.value = '';
}
function selectSuggestedTag(tag) {
    if (tag && !form.value.tags.includes(tag)) form.value.tags.push(tag);
    tagDraft.value = '';
}
function removeTag(i) { form.value.tags.splice(i, 1); }

// Gallery
function removeGalleryImg(idx) { form.value.gallery.splice(idx, 1); }

async function uploadImages(e) {
    const files = [...e.target.files];
    uploadingImg.value = true;
    for (const file of files) {
        try {
            const fd = new FormData();
            fd.append('file', file);
            const { data } = await axios.post('/api/v1/media/upload', fd);
            form.value.gallery.push(data.path);
        } catch { ui.error('Не удалось загрузить изображение'); }
    }
    uploadingImg.value = false;
    e.target.value = '';
}

// Save
async function save() {
    if (!form.value.name || !form.value.type) {
        formError.value = 'Заполните обязательные поля: название и тип';
        return;
    }
    saving.value = true;
    formError.value = '';
    try {
        const payload = {
            ...form.value,
            price_details: form.value.price_details.filter((item) => item.name?.trim()),
            promotions: form.value.promotions.filter((item) => item.title?.trim()),
        };
        if (isEdit.value) {
            await axios.put(`/api/v1/provider/poi/${vueRoute.params.id}`, payload);
            ui.success('Объект обновлён');
        } else {
            await axios.post('/api/v1/provider/poi', payload);
            ui.success('Объект создан. Ожидает модерации.');
        }
        router.push({ name: 'provider' });
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Ошибка сохранения';
    } finally {
        saving.value = false;
    }
}

// Delete
async function deletePoi() {
    if (!window.confirm('Удалить объект? Это необратимо.')) return;
    saving.value = true;
    try {
        await axios.delete(`/api/v1/provider/poi/${vueRoute.params.id}`);
        ui.success('Объект удалён');
        router.push({ name: 'provider' });
    } catch { ui.error('Ошибка удаления'); } finally { saving.value = false; }
}
</script>

<style scoped>
.poi-editor-page { min-height: 100dvh; background: var(--bg); }

.editor-topbar {
    position: sticky;
    top: var(--header-h);
    z-index: 100;
    background: var(--glass-nav);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 10px 0;
}

.editor-topbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.editor-body { padding: 32px 0 80px; display: flex; flex-direction: column; gap: 28px; }

.editor-section {
    background: var(--s1);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 24px;
}
.editor-section h3 { margin-bottom: 16px; font-size: 16px; }

.section-heading {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 18px;
}
.section-heading h3 { margin-bottom: 0; }
.section-heading p {
    max-width: 720px;
    color: var(--text-3);
    font-size: 12px;
    line-height: 1.5;
}
.section-heading--row {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}
.compact-btn { min-height: 34px; padding: 7px 12px; white-space: nowrap; }
.empty-line {
    padding: 13px 0;
    border-top: 1px solid var(--border);
    color: var(--text-3);
    font-size: 12px;
}
.repeat-row {
    display: grid;
    gap: 8px;
    align-items: center;
    padding: 10px 0;
    border-top: 1px solid var(--border);
}
.price-row { grid-template-columns: minmax(180px, 1.4fr) .65fr .75fr 1fr 32px; }
.promotion-row { grid-template-columns: minmax(200px, 1fr) 160px minmax(220px, 1.4fr) 32px; }
.repeat-row input { min-width: 0; }
.remove-row {
    width: 32px;
    min-height: 32px;
    padding: 0;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-3);
    box-shadow: none;
}
.remove-row:hover { color: var(--red); border-color: var(--red); transform: none; }
.inline-switches {
    display: flex;
    gap: 22px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    color: var(--text-2);
    font-size: 13px;
}
.inline-switches label { display: flex; align-items: center; gap: 8px; }
.rating-summary {
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 24px;
    white-space: nowrap;
}
.owner-review { padding: 18px 0; border-top: 1px solid var(--border); }
.owner-review__head { display: flex; justify-content: space-between; gap: 12px; }
.owner-review__head span { color: var(--accent); font-size: 12px; }
.owner-review > p { margin-top: 8px; color: var(--text-2); line-height: 1.55; }
.owner-reply {
    margin-top: 14px;
    padding-left: 14px;
    border-left: 2px solid var(--accent);
}
.owner-reply span { color: var(--text-3); font-size: 10px; text-transform: uppercase; }
.owner-reply p { margin-top: 5px; color: var(--text-2); }
.owner-reply button {
    min-height: 0;
    margin-top: 8px;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
    color: var(--text-3);
    font-size: 11px;
}
.owner-reply button:hover { color: var(--red); transform: none; }
.reply-form { display: flex; gap: 10px; align-items: flex-end; margin-top: 12px; }
.reply-form textarea { flex: 1; resize: vertical; }

.editor-error {
    background: rgba(192,49,42,.08);
    border: 1px solid var(--red);
    color: var(--red);
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 13px;
}

/* Gallery */
.gallery-slot-row { display: flex; gap: 10px; flex-wrap: wrap; }

.gallery-slot {
    width: 120px;
    height: 90px;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    border: 1px solid var(--border);
}

.gallery-slot--filled img { width: 100%; height: 100%; object-fit: cover; }

.gallery-slot--upload {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: var(--s2);
    color: var(--text-3);
    font-size: 12px;
    gap: 4px;
    border-style: dashed;
    transition: background .15s, color .15s;
}
.gallery-slot--upload:hover { background: var(--s3); color: var(--accent); }

.gallery-remove {
    position: absolute;
    top: 4px; right: 4px;
    width: 20px; height: 20px;
    background: rgba(0,0,0,.55);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 10px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    min-height: auto; box-shadow: none;
    transition: background .12s;
}
.gallery-remove:hover { background: rgba(192,49,42,.85); transform: none; }

.gallery-cover-badge {
    position: absolute;
    bottom: 4px; left: 4px;
    background: rgba(0,0,0,.55);
    color: #fff;
    font-size: 9px;
    padding: 1px 6px;
    border-radius: 3px;
    font-family: var(--font-m);
    text-transform: uppercase;
    letter-spacing: .04em;
}

/* Tags */
.tag-chips { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

.tag-chip {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--accent-bg);
    border: 1px solid var(--border-a);
    color: var(--accent);
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 12px;
}

.tag-chip__remove {
    background: none; border: none; cursor: pointer;
    color: var(--accent); font-size: 10px;
    min-height: auto; box-shadow: none; padding: 0;
    line-height: 1;
}

.tag-input {
    border: 1px dashed var(--border-mid);
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 12px;
    background: none;
    color: var(--text);
    outline: none;
    min-width: 120px;
}

.tag-suggest-inline {
    display: flex;
    flex-basis: 100%;
    gap: 6px;
    flex-wrap: wrap;
    padding-top: 6px;
}

.tag-suggest-inline button {
    min-height: 0;
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: transparent;
    box-shadow: none;
    color: var(--text-2);
    font-size: 11px;
}

.tag-suggest-inline button:hover {
    border-color: var(--border-a);
    color: var(--accent);
    transform: none;
}

/* Video */
.video-preview { margin-top: 10px; aspect-ratio: 16/9; }
.video-preview iframe { width: 100%; height: 100%; border: none; border-radius: 6px; }

/* Preview gallery */
.preview-gallery {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    margin-top: 14px;
    scroll-snap-type: x mandatory;
}
.preview-gallery img {
    height: 200px;
    min-width: 280px;
    object-fit: cover;
    border-radius: 6px;
    scroll-snap-align: start;
}

@media (max-width: 768px) {
    .editor-body { padding: 16px 0 60px; }
    .editor-topbar-inner { flex-direction: column; align-items: flex-start; }
    .section-heading--row { align-items: flex-start; flex-direction: column; }
    .price-row, .promotion-row { grid-template-columns: 1fr; }
    .remove-row { justify-self: end; }
    .owner-review__head, .reply-form { align-items: flex-start; flex-direction: column; }
}
</style>
