<template>
    <component
        :is="clickable ? 'RouterLink' : 'article'"
        :to="clickable ? { name: 'event-detail', params: { id: event.id } } : undefined"
        class="card feature-card news-card"
        :class="{ 'news-card--clickable': clickable, 'news-card--own': isOwn, 'news-card--more': moreCount > 0 }"
    >
        <div class="feature-image">
            <img
                v-if="imgSrc"
                :src="imgSrc"
                :alt="event.title"
                loading="lazy"
            >
            <div v-else class="news-card__media-empty">
                <span>Пользователь не приложил фото или видео</span>
            </div>
            <span class="news-card__importance" :class="`importance--${importanceKey}`">
                {{ importanceLabel }}
            </span>
            <div v-if="moreCount > 0" class="news-card__more-overlay">
                +{{ moreCount }}
            </div>
        </div>
        <div class="feature-body">
            <div class="news-card__meta">
                <span v-if="isOwn" class="badge news-card__own-badge">РјРѕС‘</span>
                <span class="badge">{{ event.type }}</span>
                <span v-if="event.highway" class="news-card__highway">{{ event.highway }}</span>
            </div>
            <h3>{{ event.title || event.location }}</h3>
            <p>{{ event.description }}</p>
            <div class="news-card__footer">
                <span v-if="event.delay_minutes > 0" class="news-card__delay">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ event.delay_minutes }} мин
                </span>
                <span class="news-card__time">{{ formattedTime }}</span>
                <span class="news-card__confidence" :title="`Доверие: ${event.confidence_score}`">
                    ● {{ event.confidence_score }}/10
                </span>
            </div>
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { eventImageSrc } from '@/utils/eventImages';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const props = defineProps({
    event:     { type: Object,  required: true },
    clickable: { type: Boolean, default: true },
    moreCount: { type: Number,  default: 0 },
});

const imgSrc = computed(() => {
    const hasOwnMedia = !!props.event.image_url
        || (props.event.gallery ?? []).length > 0
        || !!props.event.video_url;

    if (props.event.created_by_user_id && !hasOwnMedia) {
        return '';
    }

    return eventImageSrc(props.event);
});

const isOwn = computed(() => {
    return !!auth.user?.id && Number(props.event.created_by_user_id) === Number(auth.user.id);
});

const importanceKey = computed(() => {
    const map = { 'важно': 'high', 'средне': 'medium', 'низко': 'low' };
    return map[props.event.importance] ?? 'low';
});

const importanceLabel = computed(() => props.event.importance ?? '');

const formattedTime = computed(() => {
    const d = props.event.reported_at ? new Date(props.event.reported_at) : null;
    if (!d) return '';
    return d.toLocaleDateString('ru', { day: '2-digit', month: '2-digit' })
        + ' ' + d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
});
</script>

<style>
.news-card { text-decoration: none; color: inherit; display: flex; flex-direction: column; }
.news-card--clickable { cursor: pointer; }
.news-card--own { border-color: var(--accent); box-shadow: 0 0 0 1px var(--border-a), var(--shadow-md); }
.news-card .feature-image { position: relative; }
.news-card--more {
    cursor: pointer;
}
.news-card--more .feature-image img,
.news-card--more .news-card__media-empty,
.news-card--more .feature-body {
    filter: blur(2px);
    opacity: .48;
}
.news-card__more-overlay {
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
.news-card__media-empty {
    width: 100%;
    height: 100%;
    min-height: 170px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: var(--s2);
    color: var(--text-3);
    font-size: 12px;
    line-height: 1.4;
    text-align: center;
}
.news-card__own-badge { background: var(--accent); color: var(--accent-text); }

.news-card__importance {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-family: var(--font-m);
    letter-spacing: 0.04em;
    font-weight: 500;
}
.importance--high   { background: var(--red);   color: #fff; }
.importance--medium { background: var(--accent); color: var(--accent-text); }
.importance--low    { background: var(--s3);     color: var(--text-2); }

.news-card__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.news-card__highway {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 3px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--s3);
    color: var(--text);
    font-size: 10px;
    font-family: var(--font-m);
    text-transform: uppercase;
}
.news-card__footer {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    font-size: 11px;
    color: var(--text-3);
}
.news-card__delay {
    display: flex;
    align-items: center;
    gap: 4px;
    color: var(--red);
}
.news-card__confidence { margin-left: auto; }
</style>
