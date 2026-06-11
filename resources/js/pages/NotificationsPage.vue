<template>
    <div>
        <section class="page-hero">
            <div class="container">
                <div>
                    <h1>Уведомления</h1>
                    <p class="lead">Оповещения о дорожных событиях на ваших маршрутах.</p>
                    <div class="actions">
                        <button class="btn outline" @click="markAll">Прочитать все</button>
                    </div>
                </div>
                <div class="page-visual">
                    <img src="/assets/images/trucks-night.jpg" alt="Уведомления">
                </div>
            </div>
        </section>

        <section class="section-tight">
            <div class="container">
                <div v-if="loading" style="margin-top:36px;">
                    <div class="card skeleton" v-for="i in 4" :key="i" style="height:80px;margin-bottom:12px;"></div>
                </div>
                <div v-else-if="!notifications.length" class="card" style="margin-top:36px;">
                    <span class="badge">пусто</span>
                    <h3 style="margin-top:16px;">Уведомлений нет</h3>
                    <p>Они появятся, когда на ваших маршрутах произойдут события.</p>
                </div>
                <div v-else style="margin-top:36px;display:flex;flex-direction:column;gap:8px;">
                    <div
                        v-for="n in notifications"
                        :key="n.id"
                        class="card notification-card"
                        :class="{ 'is-unread': !n.read_at }"
                        @click="open(n)"
                        style="cursor:pointer;"
                    >
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span class="badge">{{ n.data?.event_type ?? 'событие' }}</span>
                            <strong style="flex:1;font-size:14px;">{{ n.data?.title ?? 'Уведомление' }}</strong>
                            <span style="font-size:11px;color:var(--text-3);">{{ relativeTime(n.created_at) }}</span>
                        </div>
                        <p v-if="n.data?.body" style="margin-top:6px;font-size:13px;color:var(--text-2);">{{ n.data.body }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const notifications = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/v1/notifications?per_page=50');
        notifications.value = (data.data ?? []).filter((notification) => !notification.read_at);
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
});

async function open(n) {
    if (!n.read_at) {
        try {
            await axios.post(`/api/v1/notifications/${n.id}/read`);
        } catch { /* ignore */ }
    }

    notifications.value = notifications.value.filter((notification) => notification.id !== n.id);

    if (n.data?.url) {
        router.push(n.data.url);
    } else if (n.data?.assignment_id) {
        router.push({ name: 'assignment-detail', params: { id: n.data.assignment_id } });
    } else if (n.data?.road_event_id) {
        router.push({ name: 'event-detail', params: { id: n.data.road_event_id } });
    }
}

async function markAll() {
    try {
        await axios.post('/api/v1/notifications/read-all');
        notifications.value = [];
    } catch { /* ignore */ }
}

function relativeTime(iso) {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1)  return 'только что';
    if (mins < 60) return `${mins} мин назад`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24)  return `${hrs} ч назад`;
    return new Date(iso).toLocaleDateString('ru');
}
</script>
