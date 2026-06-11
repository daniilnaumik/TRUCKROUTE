<template>
    <section class="forbidden-page">
        <div class="forbidden-page__content">
            <span>403</span>
            <h1>Нет доступа к этой странице</h1>
            <p>{{ message }}</p>
            <div class="forbidden-page__actions">
                <button type="button" class="btn" @click="goBack">Назад</button>
                <RouterLink :to="{ name: 'home' }" class="btn outline">На главную</RouterLink>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const message = computed(() => route.query.message
    || 'У вашей учётной записи недостаточно прав. Возможно, страница относится к другой роли или другому пользователю.');

function goBack() {
    if (window.history.length > 1) router.back();
    else router.push({ name: 'home' });
}
</script>

<style scoped>
.forbidden-page { min-height: calc(100dvh - var(--header-h)); display: grid; place-items: center; padding: 48px 20px; }
.forbidden-page__content { width: min(620px, 100%); padding: 54px 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.forbidden-page__content > span { color: var(--accent); font: 13px var(--font-m); }
.forbidden-page h1 { max-width: 560px; margin-top: 14px; font-size: clamp(38px, 6vw, 68px); }
.forbidden-page p { max-width: 540px; margin-top: 18px; color: var(--text-2); line-height: 1.65; }
.forbidden-page__actions { display: flex; gap: 10px; margin-top: 30px; }
</style>
