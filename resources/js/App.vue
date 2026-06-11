<template>
    <AppHeader />
    <main>
        <RouterView v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" :key="$route.path" />
            </Transition>
        </RouterView>
    </main>
    <AppFooter />
    <ToastContainer />
</template>

<script setup>
import { onMounted } from 'vue';
import { useUiStore } from '@/stores/ui';
import AppHeader from '@/components/AppHeader.vue';
import AppFooter from '@/components/AppFooter.vue';
import ToastContainer from '@/components/ToastContainer.vue';

const ui = useUiStore();

onMounted(() => {
    // Sync theme from localStorage on mount
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        ui.theme = 'dark';
    }
});
</script>

<style>
.page-enter-active,
.page-leave-active {
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.page-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.page-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
