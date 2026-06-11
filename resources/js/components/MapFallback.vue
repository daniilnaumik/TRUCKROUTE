<template>
    <div class="map-fallback" role="status">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Z" />
            <path d="M9 3v15M15 6v15" />
        </svg>
        <div>
            <strong>Карта временно недоступна</strong>
            <p>Текстовые данные, координаты и расчёт маршрута продолжают работать.</p>
        </div>
        <button v-if="retry" type="button" class="btn outline" @click="retry">Повторить</button>
    </div>
</template>

<script setup>
defineProps({
    retry: {
        type: Function,
        default: null,
    },
});
</script>

<style scoped>
.map-fallback {
    width: 100%;
    min-height: 220px;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 14px;
    padding: 28px;
    text-align: center;
    border: 1px solid var(--border);
    background: var(--s1);
    color: var(--text-2);
}
.map-fallback svg {
    width: 34px;
    height: 34px;
    fill: none;
    stroke: var(--accent);
    stroke-width: 1.5;
}
.map-fallback strong { color: var(--text-1); font-size: 16px; }
.map-fallback p { max-width: 360px; margin-top: 5px; color: var(--text-3); font-size: 12px; line-height: 1.5; }
.map-fallback .btn { margin-top: 4px; }
</style>
