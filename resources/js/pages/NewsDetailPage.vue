<template>
    <div>
        <div v-if="loading" style="padding:80px;text-align:center;color:var(--text-3);">Загрузка...</div>

        <div v-else-if="!article" style="padding:80px;text-align:center;">
            <h2>Статья не найдена</h2>
            <RouterLink :to="{ name: 'news' }" class="btn" style="margin-top:16px;">К новостям</RouterLink>
        </div>

        <template v-else>
            <section class="article-detail-hero">
                <div class="container article-detail-hero__inner">
                    <div class="article-detail-hero__copy">
                        <RouterLink :to="{ name: 'news' }" class="article-detail-back">← Все статьи</RouterLink>
                        <div class="article-detail-tags">
                            <span v-for="tag in (article.tags ?? []).slice(0, 4)" :key="tag" class="badge">{{ tag }}</span>
                        </div>
                        <h1>{{ article.title }}</h1>
                        <p v-if="article.excerpt" class="article-detail-lead">{{ article.excerpt }}</p>
                        <div class="article-detail-byline">
                            <span>{{ article.author?.name || 'TruckRoute' }}</span>
                            <span>{{ formatDate(article.published_at) }}</span>
                        </div>
                        <RouterLink
                            v-if="auth.isAdmin"
                            :to="{ name: 'admin-news-edit', params: { id: article.id } }"
                            class="btn outline article-detail-edit"
                        >
                            Редактировать
                        </RouterLink>
                    </div>
                    <div class="article-detail-cover">
                        <img
                            v-if="articleCanShowMedia"
                            :src="articleImageSrc(article)"
                            :alt="article.title"
                            @error="articleMediaFailed = true"
                        >
                        <div v-else class="article-detail-media-empty">
                            <span>Пользователь не приложил фото или видео</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gallery slider -->
            <section v-if="articleGallery.length > 1" class="section-tight">
                <div class="container">
                    <div class="article-gallery">
                        <img v-for="img in articleGallery" :key="img" :src="img" :alt="article.title">
                    </div>
                </div>
            </section>

            <section class="section-tight article-detail-body">
                <div class="container article-detail-body__inner">
                    <aside class="article-detail-aside">
                        <span>Опубликовано</span>
                        <strong>{{ formatDate(article.published_at) }}</strong>
                        <span v-if="article.author?.name">Автор</span>
                        <strong v-if="article.author?.name">{{ article.author.name }}</strong>
                    </aside>
                    <div class="article-content tiptap-editor-inner" v-html="cleanArticleContent"></div>
                </div>
            </section>

            <section class="section-tight">
                <div class="container article-detail-footer">
                    <RouterLink :to="{ name: 'news' }" class="btn outline">← К новостям</RouterLink>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { articleGalleryImages, articleHasAttachedMedia, articleImageSrc } from '@/utils/articleImages';

const vueRoute = useRoute();
const auth     = useAuthStore();

const article = ref(null);
const loading = ref(true);
const articleMediaFailed = ref(false);
const articleGallery = computed(() => article.value && articleHasAttachedMedia(article.value) ? articleGalleryImages(article.value) : []);
const articleCanShowMedia = computed(() => article.value && articleHasAttachedMedia(article.value) && !articleMediaFailed.value);
const cleanArticleContent = computed(() => {
    const content = article.value?.content ?? '';
    const title = article.value?.title?.trim().toLocaleLowerCase('ru') ?? '';
    if (!content || !title || typeof DOMParser === 'undefined') return content;

    const document = new DOMParser().parseFromString(content, 'text/html');
    const firstHeading = document.body.querySelector('h1, h2, h3');
    if (firstHeading?.textContent?.trim().toLocaleLowerCase('ru') === title) {
        firstHeading.remove();
    }

    return document.body.innerHTML;
});

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('ru', { day: '2-digit', month: 'long', year: 'numeric' });
}

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/v1/news/${vueRoute.params.id}`);
        article.value = data.data ?? null;
        articleMediaFailed.value = false;
    } catch { /* not found */ } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.article-detail-hero {
    padding: 42px 0 30px;
}

.article-detail-hero__inner {
    display: grid;
    grid-template-columns: minmax(0, .9fr) minmax(380px, 1.1fr);
    gap: clamp(34px, 6vw, 84px);
    align-items: center;
}

.article-detail-hero__copy {
    min-width: 0;
}

.article-detail-back {
    display: inline-flex;
    margin-bottom: 28px;
    color: var(--text-2);
    font-size: 13px;
}

.article-detail-back:hover {
    color: var(--accent);
}

.article-detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}

.article-detail-hero h1 {
    max-width: 760px;
    margin: 0;
    font-size: clamp(38px, 5.4vw, 70px);
    line-height: .96;
    overflow-wrap: anywhere;
}

.article-detail-lead {
    max-width: 620px;
    margin-top: 22px;
    color: var(--text-2);
    font-size: 15px;
    line-height: 1.65;
}

.article-detail-byline {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    margin-top: 22px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
}

.article-detail-edit {
    margin-top: 20px;
    font-size: 12px;
}

.article-detail-cover {
    min-width: 0;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.article-detail-cover img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.article-gallery {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    border-radius: 8px;
}
.article-gallery img {
    height: 300px;
    min-width: 420px;
    object-fit: cover;
    border-radius: 8px;
    scroll-snap-align: start;
    flex-shrink: 0;
}

.article-detail-media-empty {
    width: 100%;
    height: 100%;
    min-height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    border: 1px dashed var(--border);
    border-radius: 8px;
    background: #1a1f1b;
    color: var(--text-3);
    font-size: 14px;
    line-height: 1.5;
    text-align: center;
}

.article-content {
    min-width: 0;
    padding: 0 !important;
    font-size: 16px;
    line-height: 1.75;
    color: var(--text);
}

.article-detail-body {
    padding-top: 42px;
}

.article-detail-body__inner {
    display: grid;
    grid-template-columns: 150px minmax(0, 760px);
    gap: 48px;
    justify-content: center;
    align-items: start;
}

.article-detail-aside {
    display: grid;
    gap: 6px;
    padding-top: 4px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    line-height: 1.5;
    text-transform: uppercase;
}

.article-detail-aside strong {
    margin-bottom: 12px;
    color: var(--text-2);
    font-family: var(--font-b);
    font-size: 12px;
    font-weight: 500;
    text-transform: none;
}

.article-detail-footer {
    max-width: 958px;
}

@media (max-width: 860px) {
    .article-detail-hero__inner {
        grid-template-columns: 1fr;
    }

    .article-detail-cover {
        order: -1;
    }

    .article-detail-body__inner {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .article-detail-aside {
        grid-template-columns: auto 1fr;
        gap: 5px 14px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border);
    }

    .article-detail-aside strong {
        margin-bottom: 0;
    }
}

@media (max-width: 560px) {
    .article-detail-hero {
        padding-top: 24px;
    }

    .article-detail-hero h1 {
        font-size: 38px;
    }

    .article-gallery img {
        min-width: 88vw;
        height: 240px;
    }
}
</style>
