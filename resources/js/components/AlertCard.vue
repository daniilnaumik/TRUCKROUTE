<template>
    <Transition name="alert-fade">
        <div
            v-if="visible"
            class="alert-card"
            :class="`alert-card--${type}`"
            role="alert"
        >
            <div class="alert-card__icon" aria-hidden="true">
                <svg v-if="type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else-if="type === 'error'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <svg v-else-if="type === 'warning'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div class="alert-card__body">
                <h3 v-if="title" class="alert-card__title">{{ title }}</h3>
                <p v-if="body"  class="alert-card__text">{{ body }}</p>
                <ul v-if="items?.length" class="alert-card__list">
                    <li v-for="(item, i) in items" :key="i">{{ item }}</li>
                </ul>
                <p v-if="hint" class="alert-card__hint">{{ hint }}</p>
                <div v-if="$slots.actions" class="alert-card__actions">
                    <slot name="actions" />
                </div>
            </div>
            <button
                v-if="dismissible"
                type="button"
                class="alert-card__close"
                aria-label="Закрыть"
                @click="$emit('close')"
            >×</button>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    type:        { type: String,  default: 'info' },     // success | error | warning | info
    title:       { type: String,  default: '' },
    body:        { type: String,  default: '' },
    items:       { type: Array,   default: () => [] },
    hint:        { type: String,  default: '' },
    visible:     { type: Boolean, default: true },
    dismissible: { type: Boolean, default: false },
});
defineEmits(['close']);
</script>

<style>
.alert-card {
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 14px;
    padding: 16px 18px;
    border: 1px solid var(--border-mid);
    border-left-width: 4px;
    border-radius: 8px;
    background: var(--s1);
    box-shadow: var(--shadow-sm);
}
.alert-card--success { border-left-color: var(--green);  background: rgba(45,122,79,.06); }
.alert-card--error   { border-left-color: var(--red);    background: rgba(192,49,42,.06); }
.alert-card--warning { border-left-color: var(--accent); background: var(--accent-bg); }
.alert-card--info    { border-left-color: var(--text-3); background: var(--s1); }

.alert-card__icon { width: 22px; height: 22px; margin-top: 2px; flex-shrink: 0; }
.alert-card__icon svg { width: 100%; height: 100%; }
.alert-card--success .alert-card__icon { color: var(--green); }
.alert-card--error   .alert-card__icon { color: var(--red); }
.alert-card--warning .alert-card__icon { color: var(--accent); }
.alert-card--info    .alert-card__icon { color: var(--text-2); }

.alert-card__body  { min-width: 0; }
.alert-card__title { font-size: 14px; font-weight: 600; color: var(--text); margin: 0 0 4px; line-height: 1.3; }
.alert-card__text  { font-size: 13px; color: var(--text-2); margin: 0; line-height: 1.5; }
.alert-card__list  { font-size: 13px; color: var(--text-2); margin: 8px 0 0; padding-left: 18px; }
.alert-card__list li { line-height: 1.5; margin-bottom: 2px; }
.alert-card__hint  { font-size: 12px; color: var(--text-3); margin-top: 8px; font-style: italic; }
.alert-card__actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }

.alert-card__close {
    background: none;
    border: none;
    color: var(--text-3);
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
    padding: 0 4px;
    align-self: flex-start;
    min-height: auto;
    box-shadow: none;
    transition: color .14s;
}
.alert-card__close:hover {
    color: var(--text);
    transform: none;
    box-shadow: none;
}

.alert-fade-enter-active, .alert-fade-leave-active { transition: all .2s; }
.alert-fade-enter-from { opacity: 0; transform: translateY(-4px); }
.alert-fade-leave-to   { opacity: 0; transform: translateY(-2px); }
</style>
