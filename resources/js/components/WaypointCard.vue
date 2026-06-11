<template>
    <div
        class="wc"
        :class="{ 'wc--suggestion': isSuggestion, 'wc--swiping': isSwiping }"
        :style="cardStyle"
        @mousedown="onDragStart"
        @touchstart.passive="onDragStart"
    >
        <!-- Swipe hint overlays -->
        <div class="wc-hint wc-hint--reject" :style="{ opacity: rejectOpacity }">Пропустить ✕</div>
        <div class="wc-hint wc-hint--accept" :style="{ opacity: acceptOpacity }">Принять ✓</div>

        <!-- Card body -->
        <div class="wc-body">
            <div class="wc-icon" :style="{ background: typeColor }">{{ typeIcon }}</div>
            <div class="wc-info">
                <strong class="wc-name">{{ stop.name }}</strong>
                <span class="wc-meta">
                    {{ stop.type }}
                    <template v-if="stop.distance_km != null"> · {{ stop.distance_km }} км</template>
                    <template v-if="stop.detour_km > 0"> · +{{ stop.detour_km }} км крюк</template>
                </span>
                <span v-if="stop.eta_at" class="wc-eta">ETA: {{ formatTime(stop.eta_at) }}</span>
                <span v-if="stop.services" class="wc-services">{{ stop.services }}</span>
            </div>
            <div class="wc-actions">
                <button class="wc-btn wc-btn--reject" @click.stop="$emit('reject')" aria-label="Пропустить">✕</button>
                <button class="wc-btn wc-btn--accept" @click.stop="$emit('accept')" aria-label="Принять">✓</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    stop:         { type: Object, required: true },
    isSuggestion: { type: Boolean, default: false },
});

const emit = defineEmits(['accept', 'reject']);

const TYPE_COLORS = {
    'АЗС':     '#c99b3a',
    'Стоянка': '#4a6caa',
    'Ночлег':  '#7a4a9e',
    'СТО':     '#e07030',
    'Кафе':    '#e07030',
};
const TYPE_ICONS = {
    'АЗС': '⛽', 'Стоянка': 'P', 'Ночлег': '🌙', 'СТО': '🔧', 'Кафе': '☕',
};

const typeColor = computed(() => TYPE_COLORS[props.stop.type] ?? '#6a6762');
const typeIcon  = computed(() => TYPE_ICONS[props.stop.type] ?? '●');

// ── Swipe gesture ─────────────────────────────────────────────────────────
const THRESHOLD = 80;

const deltaX     = ref(0);
const isSwiping  = ref(false);
let startX = 0;

const cardStyle = computed(() => ({
    transform: `translateX(${deltaX.value}px)`,
    transition: isSwiping.value ? 'none' : 'transform .3s ease',
}));

const rejectOpacity = computed(() => Math.max(0, Math.min(1, -deltaX.value / THRESHOLD)));
const acceptOpacity = computed(() => Math.max(0, Math.min(1,  deltaX.value / THRESHOLD)));

function onDragStart(e) {
    startX = (e.touches ? e.touches[0].clientX : e.clientX);
    isSwiping.value = true;

    const onMove = (ev) => {
        const x = (ev.touches ? ev.touches[0].clientX : ev.clientX);
        deltaX.value = x - startX;
    };

    const onEnd = () => {
        isSwiping.value = false;
        if (deltaX.value > THRESHOLD)       emit('accept');
        else if (deltaX.value < -THRESHOLD) emit('reject');
        deltaX.value = 0;

        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup',   onEnd);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend',  onEnd);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onEnd);
    document.addEventListener('touchmove', onMove, { passive: true });
    document.addEventListener('touchend',  onEnd);
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.wc {
    position: relative;
    background: var(--s1);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    cursor: grab;
    user-select: none;
    touch-action: pan-y;
}
.wc--suggestion { border-color: var(--border-a); background: var(--accent-bg); }
.wc--swiping    { cursor: grabbing; }

.wc-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    pointer-events: none;
    border-radius: 10px;
    transition: opacity .1s;
}
.wc-hint--reject { background: rgba(192, 49, 42, .15); color: var(--red); }
.wc-hint--accept { background: rgba(45, 122, 79, .15);  color: var(--green); }

.wc-body {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 10px 12px 14px;
}

.wc-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    color: #fff;
    font-weight: 700;
}

.wc-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.wc-name {
    font-size: 13px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wc-meta {
    font-size: 11px;
    color: var(--text-3);
    font-family: var(--font-m);
}

.wc-eta {
    font-size: 11px;
    color: var(--accent);
    font-family: var(--font-m);
}

.wc-services {
    font-size: 11px;
    color: var(--text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wc-actions {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-shrink: 0;
}

.wc-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--s2);
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: auto;
    box-shadow: none;
    transition: background .15s, border-color .15s;
}
.wc-btn--reject { color: var(--red); }
.wc-btn--reject:hover { background: rgba(192, 49, 42, .1); border-color: var(--red); transform: none; }
.wc-btn--accept { color: var(--green); }
.wc-btn--accept:hover { background: rgba(45, 122, 79, .1); border-color: var(--green); transform: none; }
</style>
