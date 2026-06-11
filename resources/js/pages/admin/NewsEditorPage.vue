<template>
    <div class="poi-editor-page">
        <div class="editor-topbar">
            <div class="container editor-topbar-inner">
                <div style="display:flex;align-items:center;gap:12px;">
                    <RouterLink :to="{ name: 'admin' }" class="btn outline" style="padding:6px 14px;min-height:auto;">← Назад</RouterLink>
                    <h2 style="margin:0;font-size:20px;">{{ isEdit ? 'Редактировать статью' : 'Новая статья' }}</h2>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn outline" @click="form.status = 'draft'; save()">Сохранить черновик</button>
                    <button type="button" class="btn" @click="form.status = 'published'; save()" :disabled="saving">
                        {{ saving ? 'Публикуем...' : 'Опубликовать' }}
                    </button>
                    <button v-if="isEdit" type="button" class="btn danger" @click="deleteArticle">Удалить</button>
                </div>
            </div>
        </div>

        <div class="container editor-body">
            <div v-if="formError" class="editor-error">{{ formError }}</div>

            <!-- Cover image -->
            <div class="editor-section">
                <h3>Обложка</h3>
                <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                    <div v-if="form.image" class="cover-preview">
                        <img :src="coverImgUrl" style="width:100%;height:100%;object-fit:cover;" alt="Обложка">
                        <button type="button" class="gallery-remove" @click="removeCover">✕</button>
                    </div>
                    <label class="gallery-slot gallery-slot--upload" style="width:200px;height:130px;">
                        <input type="file" accept="image/*" style="display:none" @change="uploadCover">
                        <span>{{ form.image ? '⟳ Заменить' : '+ Загрузить' }}</span>
                        <span style="font-size:10px;color:var(--text-3);">JPG/PNG до 8 МБ</span>
                    </label>
                </div>
            </div>

            <!-- Main fields -->
            <div class="editor-section">
                <h3>Основное</h3>
                <div class="field" style="margin-bottom:14px;">
                    <label>Заголовок <span style="color:var(--red)">*</span></label>
                    <input v-model="form.title" placeholder="Заголовок статьи" style="font-size:18px;font-weight:500;">
                </div>
                <div class="field">
                    <label>Теги</label>
                    <div class="tag-chips" ref="tagBoxRef">
                        <span v-for="(tag, i) in form.tags" :key="tag" class="tag-chip">
                            {{ tag }}
                            <button type="button" @click="removeTag(i)" class="tag-chip__remove">✕</button>
                        </span>
                        <input class="tag-input" v-model="tagDraft" placeholder="Добавить тег..."
                            @focus="tagsSuggestOpen = true"
                            @keydown.enter.prevent="addTag" @keydown.comma.prevent="addTag">
                        <div v-if="tagsSuggestOpen && suggestedTags.length" class="tag-suggest">
                            <button
                                v-for="tag in suggestedTags"
                                :key="tag"
                                type="button"
                                class="tag-suggest__item"
                                @mousedown.prevent="selectSuggestedTag(tag)"
                            >
                                {{ tag }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gallery -->
            <div class="editor-section">
                <h3>Галерея</h3>
                <div class="gallery-slot-row">
                    <div v-for="(img, idx) in form.gallery" :key="img" class="gallery-slot gallery-slot--filled">
                        <img :src="galleryImgUrl(img)" alt="">
                        <button type="button" class="gallery-remove" @click="form.gallery.splice(idx, 1)">✕</button>
                    </div>
                    <label class="gallery-slot gallery-slot--upload" v-if="form.gallery.length < 8">
                        <input type="file" accept="image/*" multiple style="display:none" @change="uploadGallery">
                        <span>+ Фото</span>
                    </label>
                </div>
            </div>

            <!-- Rich content -->
            <div class="editor-section">
                <h3>Содержание</h3>
                <p style="font-size:12px;color:var(--text-3);margin-bottom:12px;">Заголовки, списки, цитаты, изображения, видео, ссылки</p>
                <TiptapEditor v-model="form.content" placeholder="Расскажите подробно. Используйте заголовки, списки, цитаты, изображения и видео..." min-height="320px" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';
import TiptapEditor from '@/components/TiptapEditor.vue';

const vueRoute = useRoute();
const router   = useRouter();
const ui       = useUiStore();

const isEdit   = computed(() => !!vueRoute.params.id);
const saving   = ref(false);
const formError = ref('');
const tagDraft = ref('');
const tagBoxRef = ref(null);
const tagsSuggestOpen = ref(false);
const availableTags = ref([]);

const form = ref({
    title: '', content: '', image: '',
    gallery: [], tags: [], status: 'draft',
});

const coverImgUrl = computed(() => {
    const img = form.value.image;
    if (!img) return '';
    if (img.startsWith('http') || img.startsWith('/')) return img;
    if (img.startsWith('uploads/')) return `/storage/${img}`;
    return `/assets/images/${img}`;
});

const suggestedTags = computed(() => {
    const query = tagDraft.value.trim().toLowerCase();

    return availableTags.value
        .filter((tag) => !form.value.tags.includes(tag))
        .filter((tag) => !query || tag.toLowerCase().includes(query))
        .slice(0, 30);
});

function galleryImgUrl(img) {
    if (!img) return '';
    if (img.startsWith('http') || img.startsWith('/')) return img;
    if (img.startsWith('uploads/')) return `/storage/${img}`;
    return `/assets/images/${img}`;
}

onMounted(async () => {
    document.addEventListener('mousedown', closeTagsSuggestOutside);
    await loadAvailableTags();
    if (!isEdit.value) return;
    try {
        const { data } = await axios.get(`/api/v1/admin/news/${vueRoute.params.id}`);
        const a = data.data ?? data;
        Object.assign(form.value, {
            title: a.title ?? '',
            content: a.content ?? '',
            image: a.image ?? '',
            gallery: a.gallery ?? [],
            tags: a.tags ?? [],
            status: a.status ?? 'draft',
        });
    } catch { ui.error('Не удалось загрузить статью'); }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', closeTagsSuggestOutside);
});

async function loadAvailableTags() {
    try {
        const [dictionaryResponse, newsResponse] = await Promise.all([
            axios.get('/api/v1/dictionaries', { params: { dictionary: 'tags' } }),
            axios.get('/api/v1/admin/news', { params: { per_page: 100 } }),
        ]);
        const tags = new Set();

        (dictionaryResponse.data.data?.tags ?? []).forEach((tag) => {
            if (tag.value) tags.add(String(tag.value).trim());
        });

        (newsResponse.data.data ?? []).forEach((article) => {
            (article.tags ?? []).forEach((tag) => {
                const cleanTag = String(tag).trim();
                if (cleanTag) tags.add(cleanTag);
            });
        });

        availableTags.value = [...tags].sort((a, b) => a.localeCompare(b, 'ru'));
    } catch {
        availableTags.value = [];
    }
}

function addTag() {
    const t = tagDraft.value.trim().replace(/,$/, '');
    if (t && !form.value.tags.includes(t)) {
        form.value.tags.push(t);
        if (!availableTags.value.includes(t)) {
            availableTags.value = [...availableTags.value, t].sort((a, b) => a.localeCompare(b, 'ru'));
        }
    }
    tagDraft.value = '';
    tagsSuggestOpen.value = false;
}

function selectSuggestedTag(tag) {
    if (!form.value.tags.includes(tag)) form.value.tags.push(tag);
    tagDraft.value = '';
    tagsSuggestOpen.value = false;
}

function closeTagsSuggestOutside(event) {
    if (!tagBoxRef.value?.contains(event.target)) {
        tagsSuggestOpen.value = false;
    }
}
function removeTag(i) { form.value.tags.splice(i, 1); }

async function uploadCover(e) {
    const file = e.target.files[0];
    if (!file) return;
    const fd = new FormData(); fd.append('file', file);
    try {
        const { data } = await axios.post('/api/v1/media/upload', fd);
        form.value.image = data.url;
    } catch { ui.error('Ошибка загрузки'); }
    e.target.value = '';
}

function removeCover() {
    form.value.image = '';
}

async function uploadGallery(e) {
    for (const file of [...e.target.files]) {
        const fd = new FormData(); fd.append('file', file);
        try {
            const { data } = await axios.post('/api/v1/media/upload', fd);
            form.value.gallery.push(data.url);
        } catch { /* skip */ }
    }
    e.target.value = '';
}

async function save() {
    if (!form.value.title) { formError.value = 'Заполните заголовок'; return; }
    if (form.value.status === 'published' && !form.value.tags.length) {
        formError.value = 'Для публикации добавьте хотя бы один тег';
        return;
    }
    saving.value = true; formError.value = '';
    try {
        const payload = { ...form.value, excerpt: '' };
        if (isEdit.value) {
            const id = vueRoute.params.id;
            await axios.put(`/api/v1/news/${id}`, payload);
            ui.success('Статья обновлена');
        } else {
            await axios.post('/api/v1/news', payload);
            ui.success(form.value.status === 'published' ? 'Статья опубликована' : 'Черновик сохранён');
        }
        router.push({ name: 'admin' });
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Ошибка';
    } finally { saving.value = false; }
}

async function deleteArticle() {
    if (!window.confirm('Удалить статью?')) return;
    try {
        await axios.delete(`/api/v1/news/${vueRoute.params.id}`);
        ui.success('Статья удалена');
        router.push({ name: 'admin' });
    } catch { ui.error('Ошибка удаления'); }
}
</script>

<style scoped>
/* Reuse poi-editor styles via global classes */
.poi-editor-page {
    padding-top: calc(var(--header-h, 90px) + 22px);
}

.editor-topbar {
    position: relative;
    z-index: 100;
    margin-top: 0;
    background: transparent;
    border-bottom: 0;
    padding: 0;
}
.editor-topbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 18px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}
.editor-body { padding: 24px 0 80px; display: flex; flex-direction: column; gap: 28px; }
.editor-section { background: var(--s1); border: 1px solid var(--border); border-radius: 10px; padding: 24px; }
.editor-section h3 { margin-bottom: 16px; font-size: 16px; }
.editor-error { background: rgba(192,49,42,.08); border: 1px solid var(--red); color: var(--red); padding: 10px 16px; border-radius: 6px; font-size: 13px; }
.cover-preview { width: 200px; height: 130px; overflow: hidden; border-radius: 8px; border: 1px solid var(--border); position: relative; }
.gallery-slot-row { display: flex; gap: 10px; flex-wrap: wrap; }
.gallery-slot { width: 120px; height: 90px; border-radius: 8px; overflow: hidden; position: relative; border: 1px solid var(--border); }
.gallery-slot--filled img { width: 100%; height: 100%; object-fit: cover; }
.gallery-slot--upload { display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; background: var(--s2); color: var(--text-3); font-size: 12px; gap: 4px; border-style: dashed; }
.gallery-slot--upload:hover { background: var(--s3); color: var(--accent); }
.gallery-remove { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; background: rgba(0,0,0,.55); color: #fff; border: none; border-radius: 50%; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: auto; box-shadow: none; }
.tag-chips { position: relative; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.tag-chip { display: flex; align-items: center; gap: 4px; background: var(--accent-bg); border: 1px solid var(--border-a); color: var(--accent); padding: 3px 8px; border-radius: 20px; font-size: 12px; }
.tag-chip__remove { background: none; border: none; cursor: pointer; color: var(--accent); font-size: 10px; min-height: auto; box-shadow: none; padding: 0; }
.tag-input { border: 1px dashed var(--border-mid); border-radius: 20px; padding: 3px 10px; font-size: 12px; background: none; color: var(--text); outline: none; min-width: 120px; }
.tag-suggest {
    position: absolute;
    left: 0;
    top: calc(100% + 8px);
    z-index: 20;
    width: min(320px, 100%);
    max-height: 190px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s2);
    box-shadow: var(--shadow-md);
}
.tag-suggest__item {
    min-height: auto;
    padding: 8px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--text);
    box-shadow: none;
    text-align: left;
    cursor: pointer;
}
.tag-suggest__item:hover {
    background: var(--s3);
    color: var(--accent);
}
</style>
