<template>
    <header class="site-header">
        <RouterLink class="logo" :to="{ name: 'home' }">TRUCKROUTE</RouterLink>

        <nav class="main-nav" aria-label="Основная навигация">
            <!-- Nav Drawer -->
            <div class="nav-drawer" id="navDrawer">
                <RouterLink :to="{ name: 'home' }" active-class="active">главная</RouterLink>
                <RouterLink :to="{ name: 'news' }" active-class="active">новости</RouterLink>
                <RouterLink :to="{ name: 'routes' }" active-class="active">маршруты</RouterLink>
                <RouterLink :to="{ name: 'places' }" active-class="active">объекты</RouterLink>

                <template v-if="auth.isAuthenticated">
                    <RouterLink :to="{ name: 'settings' }" active-class="active">настройки</RouterLink>
                    <RouterLink v-if="auth.isAdmin"    :to="{ name: 'admin' }"    active-class="active">админ</RouterLink>
                    <RouterLink v-if="auth.isProvider" :to="{ name: 'provider' }" active-class="active">кабинет</RouterLink>
                    <RouterLink v-if="auth.isFleet"    :to="{ name: 'fleet' }"    active-class="active">автопарк</RouterLink>

                    <!-- Notification bell -->
                    <div class="nav-notifications" ref="notifWrap">
                        <button
                            class="nav-notifications__btn"
                            type="button"
                            aria-label="Уведомления"
                            @click="toggleNotifications"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <span v-if="unreadCount > 0" class="nav-notifications__badge">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>
                        <div v-if="notifOpen" class="nav-notifications__dropdown">
                            <div class="nav-notifications__header">
                                <span>Уведомления</span>
                                <button type="button" @click="markAllRead">отметить все</button>
                            </div>
                            <ul class="nav-notifications__list">
                                <li v-if="!notifications.length" class="nav-notifications__empty">
                                    Нет уведомлений
                                </li>
                                <li
                                    v-for="n in notifications"
                                    :key="n.id"
                                    class="nav-notifications__item"
                                    :class="{ 'is-read': n.read_at }"
                                    @click="markRead(n)"
                                >
                                    <strong>{{ n.data?.title ?? 'Уведомление' }}</strong>
                                    <span v-if="n.data?.body">{{ n.data.body }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Logout -->
                    <button class="nav-logout-btn" type="button" @click="handleLogout">выйти</button>
                </template>

                <template v-else>
                    <RouterLink :to="{ name: 'login' }" active-class="active">войти</RouterLink>
                </template>
            </div>

            <!-- Dev test-account switcher -->
            <DevSwitcher v-if="isDebug" />

            <!-- Theme toggle -->
            <button class="nav-theme-toggle" type="button" aria-label="Переключить тему" @click="ui.toggleTheme()">
                <svg v-if="ui.theme === 'dark'" class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg v-else class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>

            <!-- Hamburger -->
            <button
                class="nav-hamburger"
                id="navHamburger"
                type="button"
                aria-label="Меню"
                :aria-expanded="menuOpen"
                aria-controls="navDrawer"
                @click="toggleMenu"
            >
                <svg v-if="!menuOpen" class="icon-menu" width="20" height="14" viewBox="0 0 20 14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M0 1h20M0 7h20M0 13h20"/>
                </svg>
                <svg v-else class="icon-close" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M2 2l14 14M16 2L2 16"/>
                </svg>
            </button>
        </nav>
    </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import DevSwitcher from '@/components/DevSwitcher.vue';

const auth  = useAuthStore();
const ui    = useUiStore();
const router = useRouter();

const menuOpen    = ref(false);
const notifOpen   = ref(false);
const notifications = ref([]);
const notifWrap   = ref(null);

const isDebug = computed(() => window.__APP_DEBUG__ === true);
const unreadCount = computed(() => notifications.value.length);

function toggleMenu() { menuOpen.value = !menuOpen.value; }
function toggleNotifications() {
    notifOpen.value = !notifOpen.value;
    if (notifOpen.value) loadNotifications();
}

// Close menu on route change
router.afterEach(() => { menuOpen.value = false; });

// Close notif dropdown on outside click
function onClickOutside(e) {
    if (notifWrap.value && !notifWrap.value.contains(e.target)) {
        notifOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));

// Sync header open class on site-header element
watch(menuOpen, (open) => {
    const header = document.querySelector('.site-header');
    if (header) header.classList.toggle('nav-open', open);
});

async function loadNotifications() {
    if (!auth.isAuthenticated) return;
    try {
        const { data } = await axios.get('/api/v1/notifications?per_page=8');
        notifications.value = (data.data ?? []).filter((notification) => !notification.read_at);
    } catch { /* ignore */ }
}

async function markRead(n) {
    if (!n.read_at) {
        try {
            await axios.post(`/api/v1/notifications/${n.id}/read`);
        } catch { /* ignore */ }
    }

    notifications.value = notifications.value.filter((notification) => notification.id !== n.id);
    notifOpen.value = false;
    if (n.data?.url) {
        router.push(n.data.url);
    } else if (n.data?.assignment_id) {
        router.push({ name: 'assignment-detail', params: { id: n.data.assignment_id } });
    } else if (n.data?.road_event_id) {
        router.push({ name: 'event-detail', params: { id: n.data.road_event_id } });
    }
}

async function markAllRead() {
    try {
        await axios.post('/api/v1/notifications/read-all');
        notifications.value = [];
    } catch { /* ignore */ }
}

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}

// Poll notifications every 60s when authenticated
let pollInterval = null;
watch(() => auth.isAuthenticated, (authed) => {
    if (authed) {
        loadNotifications();
        pollInterval = setInterval(loadNotifications, 60000);
    } else {
        clearInterval(pollInterval);
        notifications.value = [];
    }
}, { immediate: true });

onUnmounted(() => clearInterval(pollInterval));
</script>

<style scoped>
.nav-logout-btn {
    background: none;
    border: 1px solid var(--border-mid);
    border-radius: 6px;
    color: var(--text-2);
    font-size: 13px;
    padding: 7px 14px;
    cursor: pointer;
    transition: color .15s, border-color .15s;
    min-height: auto;
    box-shadow: none;
}
.nav-logout-btn:hover {
    color: var(--text);
    border-color: var(--border-hover);
    transform: none;
    box-shadow: none;
}
</style>
