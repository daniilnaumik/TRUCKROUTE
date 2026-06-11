<template>
    <Transition name="field-err">
        <p v-if="error" class="field-error">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            {{ error }}
        </p>
    </Transition>
</template>

<script setup>
defineProps({
    error: { type: String, default: '' },
});
</script>

<style>
.field-error {
    display: flex;
    align-items: center;
    gap: 4px;
    color: var(--red);
    font-size: 11px;
    margin-top: 5px;
    line-height: 1.3;
}
.field-error svg { flex-shrink: 0; }

.field-err-enter-active,
.field-err-leave-active { transition: opacity .14s, transform .14s; }
.field-err-enter-from {
    opacity: 0;
    transform: translateY(-3px);
}
.field-err-leave-to {
    opacity: 0;
    transform: translateY(-2px);
}
</style>
