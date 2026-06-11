<template>
    <div class="nav-test-switcher" ref="wrap">
        <button class="nav-test-switcher__btn" type="button" title="Dev: быстрый вход" @click="open = !open">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="nav-test-switcher__label">тест</span>
        </button>
        <div v-if="open" class="nav-test-switcher__dropdown">
            <div class="nav-test-switcher__header">тестовые аккаунты</div>
            <a
                v-for="acc in accounts"
                :key="acc.email"
                class="nav-test-switcher__item"
                :class="{ 'is-active': auth.user?.email === acc.email }"
                href="#"
                @click.prevent="switchTo(acc)"
            >
                <span class="nav-test-switcher__name">{{ acc.name }}</span>
                <span class="nav-test-switcher__role">{{ acc.role }}</span>
                <svg v-if="auth.user?.email === acc.email" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--accent)">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </a>
            <div v-if="auth.isAuthenticated" class="nav-test-switcher__footer">
                <button class="nav-test-switcher__logout" type="button" @click="doLogout">выйти из аккаунта</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth   = useAuthStore();
const ui     = useUiStore();
const router = useRouter();
const wrap   = ref(null);
const open   = ref(false);

const accounts = [
    { email: 'driver@truckroute.local',   name: 'Даниил Наумик', role: 'driver',   password: 'password' },
    { email: 'provider@truckroute.local', name: 'АЗС Партнёр',   role: 'provider', password: 'password' },
    { email: 'fleet@truckroute.local',    name: 'Логист Парк',   role: 'fleet',    password: 'password' },
    { email: 'admin@truckroute.local',    name: 'Администратор', role: 'admin',    password: 'password' },
];

function onOutside(e) {
    if (wrap.value && !wrap.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('click', onOutside));
onUnmounted(() => document.removeEventListener('click', onOutside));

async function switchTo(acc) {
    open.value = false;
    if (auth.isAuthenticated) await auth.logout();
    const res = await auth.login(acc.email, acc.password);
    if (res.ok) {
        ui.success(`Вошли как ${acc.name} (${acc.role})`);
        router.push({ name: 'home' });
    } else {
        ui.error(res.message);
    }
}

async function doLogout() {
    open.value = false;
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
