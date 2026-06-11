<template>
    <div>
        <section class="page-hero profile-hero">
            <div class="container">
                <div>
                    <h1>Профиль</h1>
                    <p class="lead">{{ auth.user?.name }}</p>
                    <div class="actions">
                        <RouterLink :to="{ name: 'settings' }" class="btn outline">Настройки</RouterLink>
                    </div>
                    <div class="profile-summary-strip">
                        <div>
                            <span>Роль</span>
                            <strong>{{ profileRoleLabel }}</strong>
                        </div>
                        <div>
                            <span>ID</span>
                            <strong>{{ auth.user?.id ?? '—' }}</strong>
                        </div>
                        <div>
                            <span>Транспорт</span>
                            <strong>{{ vehicles.length }}</strong>
                        </div>
                        <div>
                            <span>Маршруты</span>
                            <strong>{{ routes.length }}</strong>
                        </div>
                        <div>
                            <span>Поездка</span>
                            <strong>{{ activeTrip ? 'активна' : 'нет' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="activeTrip" class="section-tight active-trip-section">
            <div class="container">
                <div class="card active-trip-card">
                    <div>
                        <span class="badge">Активная поездка</span>
                        <h2>Поездка в процессе</h2>
                        <p class="active-trip-card__route">{{ activeTripRouteLine }}</p>
                        <p v-if="activeTrip.last_lat && activeTrip.last_lng" class="active-trip-card__coords">
                            Последняя позиция: {{ Number(activeTrip.last_lat).toFixed(5) }}, {{ Number(activeTrip.last_lng).toFixed(5) }}
                        </p>
                    </div>
                    <div class="active-trip-card__actions">
                        <RouterLink
                            v-if="activeTrip.route_plan_id"
                            :to="{ name: 'route-detail', params: { id: activeTrip.route_plan_id } }"
                            class="btn"
                        >
                            Открыть поездку
                        </RouterLink>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="fleets.length" class="section-tight profile-fleets-section">
            <div class="container">
                <div class="profile-section-head">
                    <h2>{{ auth.isFleet ? 'Автопарки в управлении' : 'Автопарк, где я сотрудник' }}</h2>
                    <RouterLink v-if="auth.isFleet" :to="{ name: 'fleet' }" class="btn outline">Открыть кабинет</RouterLink>
                </div>
                <div class="profile-fleet-grid">
                    <article v-for="fleet in fleets" :key="fleet.id" class="card profile-fleet-card">
                        <div class="profile-fleet-card__avatar">
                            <img v-if="fleet.avatar_url" :src="fleet.avatar_url" :alt="fleet.name">
                            <span v-else>{{ fleetInitials(fleet) }}</span>
                        </div>
                        <div class="profile-fleet-card__body">
                            <span>{{ fleet.is_owner ? 'владелец' : 'сотрудник' }}</span>
                            <h3>{{ fleet.name }}</h3>
                            <p>{{ fleet.base_city || fleet.address || fleet.description || 'Информация об автопарке пока не заполнена.' }}</p>
                            <div class="profile-fleet-card__meta">
                                <small>{{ fleet.drivers_count ?? 0 }} водителей</small>
                                <small>{{ fleet.assignments_count ?? 0 }} заданий</small>
                                <small>{{ fleet.completed_assignments_count ?? 0 }} выполнено</small>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Vehicles -->
        <section class="section-tight">
            <div class="container">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
                    <h2>Мои транспортные средства</h2>
                    <button class="btn" @click="showAddVehicle = true">+ Добавить ТС</button>
                </div>
                <div v-if="loadingVehicles" class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <div class="card skeleton" v-for="i in 2" :key="i" style="height:160px;"></div>
                </div>
                <div v-else-if="!vehicles.length" class="card" style="margin-top:36px;">
                    <h3>Нет транспортных средств</h3>
                    <p>Добавьте профиль своего грузовика для расчёта маршрутов.</p>
                </div>
                <div v-else class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <article
                        v-for="v in vehicles"
                        :key="v.id"
                        class="card"
                        :class="{ 'card-success': v.is_active }"
                        style="cursor:pointer;"
                        @click="activateVehicle(v)"
                    >
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                            <div>
                                <span v-if="v.is_active" class="badge">активный</span>
                                <h3 style="margin-top:8px;">{{ v.title }}</h3>
                                <p style="font-size:13px;color:var(--text-2);">{{ v.type }}</p>
                                <p style="font-size:12px;color:var(--text-3);margin-top:6px;font-family:var(--font-m);">
                                    {{ v.tank_capacity_l }} л · {{ v.consumption_l_per_100 }} л/100 · {{ v.cruise_speed_kmh }} км/ч
                                </p>
                            </div>
                            <img v-if="v.image" :src="`/assets/images/${v.image}`" alt="" style="width:56px;height:40px;object-fit:contain;border-radius:4px;opacity:.85;">
                        </div>
                    </article>
                </div>

                <!-- Add vehicle form -->
                <div v-if="showAddVehicle" class="card" style="margin-top:28px;border:1px solid var(--border-a);">
                    <h3>Новое ТС</h3>
                    <form class="form-grid" style="margin-top:20px;" @submit.prevent="saveVehicle">
                        <div class="field">
                            <label>Название</label>
                            <input v-model="vForm.title" required placeholder="Например: Мой Volvo">
                        </div>
                        <div class="field">
                            <label>Тип</label>
                            <select v-model="vForm.type">
                                <option>Тягач + полуприцеп</option>
                                <option>Одиночка</option>
                                <option>Фургон</option>
                                <option>Рефрижератор</option>
                                <option>Цистерна</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Объём бака (л)</label>
                            <input v-model.number="vForm.tank_capacity_l" type="number" min="50" max="2000" required>
                        </div>
                        <div class="field">
                            <label>Расход (л/100 км)</label>
                            <input v-model.number="vForm.consumption_l_per_100" type="number" min="5" max="100" step="0.1" required>
                        </div>
                        <div class="field">
                            <label>Крейсерская скорость (км/ч)</label>
                            <input v-model.number="vForm.cruise_speed_kmh" type="number" min="40" max="130" required>
                        </div>
                        <div class="field">
                            <label>Собственная масса (т)</label>
                            <input v-model.number="vForm.curb_weight_t" type="number" step="0.1" placeholder="Необязательно">
                        </div>
                        <div class="actions" style="grid-column:1/-1;">
                            <button type="submit" class="btn" :disabled="savingVehicle">
                                {{ savingVehicle ? 'Сохраняем...' : 'Сохранить' }}
                            </button>
                            <button type="button" class="btn outline" @click="showAddVehicle=false">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Route history -->
        <section class="section-tight">
            <div class="container">
                <h2>История маршрутов</h2>
                <div v-if="loadingRoutes" style="margin-top:36px;color:var(--text-3);">Загрузка...</div>
                <div v-else-if="!routes.length" class="card" style="margin-top:36px;">
                    <h3>Нет сохранённых маршрутов</h3>
                    <p>Постройте первый маршрут — он появится здесь.</p>
                    <div class="actions" style="margin-top:16px;">
                        <RouterLink :to="{ name: 'routes' }" class="btn">Построить маршрут</RouterLink>
                    </div>
                </div>
                <div v-else class="route-history-grid" style="margin-top:36px;">
                    <article v-for="r in routes" :key="r.id" class="card route-history-card">
                        <span class="badge">{{ r.planning_mode || 'маршрут' }}</span>
                        <h3>{{ r.title }}</h3>
                        <p class="route-history-card__line">
                            {{ routeLine(r) }}
                        </p>
                        <div class="route-history-card__meta">
                            <span>{{ r.distance_km }} км</span>
                            <span>{{ r.fuel?.needed_l ?? r.fuel_needed_l ?? '—' }} л</span>
                            <span>{{ r.stops_count ?? 0 }} ост.</span>
                        </div>
                        <div class="actions">
                            <RouterLink :to="{ name: 'route-detail', params: { id: r.id } }" class="btn outline">Открыть</RouterLink>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui   = useUiStore();

const vehicles = ref([]);
const routes   = ref([]);
const fleets   = ref([]);
const activeTrip = ref(null);
const loadingVehicles = ref(true);
const loadingRoutes   = ref(true);
const showAddVehicle  = ref(false);
const savingVehicle   = ref(false);

const vForm = ref({
    title: '', type: 'Тягач + полуприцеп', tank_capacity_l: 600,
    consumption_l_per_100: 29, cruise_speed_kmh: 85, curb_weight_t: '',
});

const activeTripRouteLine = computed(() => {
    const routePlan = activeTrip.value?.route_plan;
    if (!routePlan) return 'Поездка запущена, маршрут можно открыть из карточки.';
    return `${compactLabel(routePlan.origin) || 'Старт'} → ${compactLabel(routePlan.destination) || 'Финиш'}`;
});

const profileRoleLabel = computed(() => {
    const labels = {
        admin: 'Администратор',
        driver: 'Водитель',
        provider: 'Поставщик',
        fleet: 'Автопарк',
    };

    return labels[auth.user?.role] ?? 'Пользователь';
});

onMounted(async () => {
    try {
        const [v, r, trip, fleetRes] = await Promise.all([
            axios.get('/api/v1/vehicles'),
            axios.get('/api/v1/routes?per_page=12'),
            axios.get('/api/v1/trip/current', { silent: true }),
            axios.get('/api/v1/fleets'),
        ]);
        vehicles.value = v.data.data ?? v.data ?? [];
        routes.value   = r.data.data ?? r.data ?? [];
        fleets.value   = fleetRes.data.data ?? [];
        const tripPayload = Object.prototype.hasOwnProperty.call(trip.data ?? {}, 'data')
            ? trip.data.data
            : trip.data;
        activeTrip.value = tripPayload?.status === 'active' ? tripPayload : null;
    } catch { /* ignore */ } finally {
        loadingVehicles.value = false;
        loadingRoutes.value   = false;
    }
});

async function activateVehicle(v) {
    if (v.is_active) return;
    try {
        await axios.post(`/api/v1/vehicles/${v.id}/activate`);
        vehicles.value.forEach(vh => vh.is_active = (vh.id === v.id));
        ui.success('Транспортное средство выбрано');
    } catch { ui.error('Ошибка активации'); }
}

async function saveVehicle() {
    savingVehicle.value = true;
    try {
        const { data } = await axios.post('/api/v1/vehicles', vForm.value);
        vehicles.value.push(data);
        showAddVehicle.value = false;
        vForm.value = { title:'', type:'Тягач + полуприцеп', tank_capacity_l:600, consumption_l_per_100:29, cruise_speed_kmh:85, curb_weight_t:'' };
        ui.success('ТС добавлено');
    } catch (e) {
        ui.error(e.response?.data?.message ?? 'Ошибка сохранения');
    } finally {
        savingVehicle.value = false;
    }
}

function routeLine(route) {
    const origin = route.origin?.label ?? route.origin ?? 'Старт';
    const destination = route.destination?.label ?? route.destination ?? 'Финиш';
    return `${origin} → ${destination}`;
}

function compactLabel(label) {
    const parts = String(label || '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length <= 2) return parts.join(', ') || label;
    return parts.slice(-2).join(', ');
}

function fleetInitials(fleet) {
    return String(fleet?.name || 'TR')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
</script>

<style scoped>
.profile-hero {
    padding: 46px 0;
}

.profile-hero .container {
    display: block;
}

.profile-summary-strip {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    max-width: 900px;
    margin-top: 28px;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    background: var(--s1);
}

.profile-summary-strip div {
    min-width: 0;
    padding: 16px 18px;
    border-right: 1px solid var(--border);
}

.profile-summary-strip div:last-child {
    border-right: 0;
}

.profile-summary-strip span {
    display: block;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
    text-transform: uppercase;
}

.profile-summary-strip strong {
    display: block;
    margin-top: 7px;
    color: var(--text);
    font-size: 20px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-trip-section {
    padding: 34px 0;
}

.active-trip-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 22px;
    align-items: center;
    border-color: var(--border-a);
    background: var(--accent-bg);
    padding: 22px 26px;
}

.active-trip-card h2 {
    margin-top: 10px;
    max-width: none;
    font-family: var(--font-d);
    font-size: clamp(24px, 2.5vw, 34px);
    line-height: 1.05;
}

.active-trip-card h2::after {
    display: none;
}

.active-trip-card__route {
    max-width: 820px;
    margin-top: 10px;
    color: var(--text-2);
    font-size: 14px;
    line-height: 1.45;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-trip-card__coords {
    margin-top: 8px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
}

.active-trip-card__actions {
    display: flex;
    justify-content: flex-end;
}

.profile-fleets-section {
    padding-top: 34px;
}

.profile-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.profile-fleet-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 24px;
}

.profile-fleet-card {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    background: var(--s1);
}

.profile-fleet-card__avatar {
    width: 76px;
    height: 76px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--s1);
    color: var(--accent);
    font-family: var(--font-m);
    font-weight: 700;
}

.profile-fleet-card__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-fleet-card__body {
    min-width: 0;
}

.profile-fleet-card__body > span {
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
    text-transform: uppercase;
}

.profile-fleet-card h3 {
    margin-top: 6px;
    font-size: 22px;
}

.profile-fleet-card p {
    margin-top: 6px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.45;
}

.profile-fleet-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}

.profile-fleet-card__meta small {
    padding: 5px 7px;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
}

.route-history-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.route-history-card {
    display: flex;
    min-height: 220px;
    flex-direction: column;
    gap: 12px;
}

.route-history-card:hover {
    background: var(--s2);
    border-color: var(--border);
}

.route-history-card h3 {
    font-size: 22px;
}

.route-history-card__line {
    margin-top: 0 !important;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.5;
}

.route-history-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: auto;
}

.route-history-card__meta span {
    padding: 5px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 11px;
}

.route-history-card .actions {
    margin-top: 8px;
}

.route-history-card .btn {
    min-height: 36px;
    padding: 0 14px;
    font-size: 12px;
}

@media (max-width: 900px) {
    .active-trip-card,
    .route-history-grid,
    .profile-fleet-grid,
    .profile-summary-strip {
        grid-template-columns: 1fr;
    }
    .profile-summary-strip div {
        border-right: 0;
        border-bottom: 1px solid var(--border);
    }
    .profile-summary-strip div:last-child {
        border-bottom: 0;
    }
    .active-trip-card__actions {
        justify-content: flex-start;
    }
    .active-trip-card__route {
        white-space: normal;
    }
}

@media (max-width: 560px) {
    .profile-fleet-card {
        grid-template-columns: 1fr;
    }
}
</style>
