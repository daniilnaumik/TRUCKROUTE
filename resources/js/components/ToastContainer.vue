<template>
    <Teleport to="body">
        <div class="toast-stack" aria-live="polite" aria-atomic="true">
            <TransitionGroup name="toast" tag="div" class="toast-stack__inner">
                <div
                    v-for="t in ui.toasts"
                    :key="t.id"
                    class="toast"
                    :class="`toast--${t.type}`"
                    role="alert"
                >
                    <!-- Severity stripe (left edge) -->
                    <span class="toast__stripe" aria-hidden="true"></span>

                    <!-- Icon -->
                    <div class="toast__icon" aria-hidden="true">
                        <!-- success ✓ -->
                        <svg v-if="t.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <!-- error ✕ -->
                        <svg v-else-if="t.type === 'error'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        <!-- warning ⚠ -->
                        <svg v-else-if="t.type === 'warning'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <!-- info ⓘ -->
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                    </div>

                    <!-- Body -->
                    <div class="toast__body">
                        <div class="toast__title">{{ t.title || defaultTitle(t.type) }}</div>
                        <div v-if="t.body" class="toast__text">{{ t.body }}</div>
                        <div v-if="t.hint" class="toast__hint">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            {{ t.hint }}
                        </div>
                        <button
                            v-if="t.action"
                            type="button"
                            class="toast__action"
                            @click="invokeAction(t)"
                        >{{ t.action.label }}</button>
                    </div>

                    <!-- Dismiss -->
                    <button
                        type="button"
                        class="toast__close"
                        aria-label="Закрыть"
                        @click="ui.dismissToast(t.id)"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>

                    <!-- Progress bar -->
                    <span
                        v-if="t.duration > 0"
                        class="toast__progress"
                        :style="{ animationDuration: `${t.duration}ms` }"
                    ></span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useUiStore } from '@/stores/ui';
const ui = useUiStore();

function defaultTitle(type) {
    return {
        success: 'Готово',
        error:   'Ошибка',
        warning: 'Внимание',
        info:    'Информация',
    }[type] ?? '';
}

function invokeAction(t) {
    try { t.action?.handler?.(); } catch { /* ignore */ }
    ui.dismissToast(t.id);
}
</script>

<style>
/* ── Stack container ─────────────────────────────────────────────────── */
.toast-stack {
    position: fixed;
    z-index: 3000;
    bottom: 28px;
    right: 28px;
    pointer-events: none;
    max-width: calc(100vw - 56px);
}
.toast-stack__inner {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}
@media (max-width: 600px) {
    .toast-stack { right: 12px; left: 12px; bottom: 12px; max-width: none; }
    .toast-stack__inner { align-items: stretch; }
}

/* ── Card ─────────────────────────────────────────────────────────────── */
.toast {
    pointer-events: auto;
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: flex-start;
    gap: 12px;
    min-width: 320px;
    max-width: 420px;
    padding: 14px 16px 14px 22px;
    background: var(--glass-modal);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-mid);
    border-radius: 10px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    color: var(--text);
}

/* Severity left stripe */
.toast__stripe {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 4px 0 0 4px;
}
.toast--success .toast__stripe { background: var(--green); }
.toast--error   .toast__stripe { background: var(--red); }
.toast--warning .toast__stripe { background: var(--accent); }
.toast--info    .toast__stripe { background: var(--text-3); }

/* Icon */
.toast__icon {
    width: 22px;
    height: 22px;
    margin-top: 1px;
    flex-shrink: 0;
}
.toast__icon svg { width: 100%; height: 100%; }
.toast--success .toast__icon { color: var(--green); }
.toast--error   .toast__icon { color: var(--red); }
.toast--warning .toast__icon { color: var(--accent); }
.toast--info    .toast__icon { color: var(--text-2); }

/* Body */
.toast__body { min-width: 0; }
.toast__title {
    font-family: var(--font-d, inherit);
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    line-height: 1.3;
}
.toast__text {
    font-size: 13px;
    color: var(--text-2);
    margin-top: 4px;
    line-height: 1.45;
    word-break: break-word;
}
.toast__hint {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--text-3);
    margin-top: 8px;
    line-height: 1.4;
    font-style: italic;
}
.toast__hint svg { flex-shrink: 0; opacity: 0.6; }

.toast__action {
    display: inline-block;
    background: none;
    border: 1px solid currentColor;
    border-radius: 5px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 10px;
    min-height: auto;
    box-shadow: none;
    transition: background .14s;
}
.toast--success .toast__action { color: var(--green); }
.toast--error   .toast__action { color: var(--red); }
.toast--warning .toast__action { color: var(--accent); }
.toast--info    .toast__action { color: var(--text-2); }
.toast__action:hover {
    background: var(--hover-tint);
    transform: none;
    box-shadow: none;
}

/* Close button */
.toast__close {
    background: none;
    border: none;
    padding: 4px;
    color: var(--text-3);
    cursor: pointer;
    align-self: flex-start;
    margin-top: -2px;
    min-height: auto;
    box-shadow: none;
    border-radius: 4px;
    transition: color .14s, background .14s;
}
.toast__close:hover {
    color: var(--text);
    background: var(--hover-tint);
    transform: none;
    box-shadow: none;
}
.toast__close svg { display: block; }

/* Progress bar at bottom */
.toast__progress {
    position: absolute;
    left: 0;
    bottom: 0;
    height: 2px;
    width: 100%;
    transform-origin: left center;
    animation: toast-progress linear forwards;
    opacity: 0.5;
}
.toast--success .toast__progress { background: var(--green); }
.toast--error   .toast__progress { background: var(--red); }
.toast--warning .toast__progress { background: var(--accent); }
.toast--info    .toast__progress { background: var(--text-3); }

@keyframes toast-progress {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
}

/* Hover pause: stop progress + extend display */
.toast:hover .toast__progress {
    animation-play-state: paused;
}

/* ── Transitions ─────────────────────────────────────────────────────── */
.toast-enter-active { transition: transform .28s cubic-bezier(.2,.9,.3,1.2), opacity .22s ease; }
.toast-leave-active { transition: transform .2s ease, opacity .18s ease; }
.toast-enter-from {
    opacity: 0;
    transform: translateX(40px) scale(.95);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(40px) scale(.95);
}
.toast-move { transition: transform .25s ease; }
</style>
