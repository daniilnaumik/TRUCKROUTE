<template>
    <div class="settings-page">
        <section class="settings-shell">
            <div class="settings-profile-top">
                <label class="settings-avatar settings-avatar--upload" aria-label="Загрузить аватар">
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="saveAvatar">
                    <img v-if="avatarSrc" :src="avatarSrc" :alt="auth.user?.name || 'Аватар'">
                    <svg v-else viewBox="0 0 24 24">
                        <path d="M20 21a8 8 0 0 0-16 0" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <span v-if="savingAvatar" class="settings-avatar__busy"></span>
                </label>
                <div class="settings-profile-copy">
                    <span>Аккаунт TruckRoute</span>
                    <h1>{{ auth.user?.name || 'Пользователь' }}</h1>
                    <p>{{ accountSubtitle }}</p>
                </div>
                <button class="settings-edit-pill" type="button" @click="togglePanel('profile')">
                    {{ activePanel === 'profile' ? 'Закрыть' : 'Изм.' }}
                </button>
            </div>

            <div v-if="successMsg" class="settings-message is-success">
                <strong>Готово</strong>
                <span>{{ successMsg }}</span>
            </div>
            <div v-if="errorMsg" class="settings-message is-error">
                <strong>Ошибка</strong>
                <span>{{ errorMsg }}</span>
            </div>

            <div class="settings-groups">
                <div class="settings-group">
                    <button class="settings-row" type="button" @click="togglePanel('profile')">
                        <span class="settings-row__icon settings-row__icon--profile" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M20 21a8 8 0 0 0-16 0" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Аккаунт</strong>
                            <small>{{ profileForm.phone || auth.user?.email || 'Имя, телефон и фото' }}</small>
                        </span>
                        <span class="settings-row__meta">ID {{ auth.user?.id ?? '—' }}</span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <form v-if="activePanel === 'profile'" class="settings-drawer" @submit.prevent="saveProfile">
                        <div class="settings-avatar-field">
                            <label class="settings-avatar settings-avatar--small settings-avatar--upload">
                                <input type="file" accept="image/jpeg,image/png,image/webp" @change="saveAvatar">
                                <img v-if="avatarSrc" :src="avatarSrc" :alt="auth.user?.name || 'Аватар'">
                                <svg v-else viewBox="0 0 24 24">
                                    <path d="M20 21a8 8 0 0 0-16 0" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </label>
                            <div>
                                <strong>Фотография</strong>
                                <span>JPG, PNG или WEBP до 2 МБ</span>
                            </div>
                        </div>
                        <div class="field">
                            <label>Имя</label>
                            <input v-model="profileForm.name" type="text" autocomplete="name" required>
                        </div>
                        <div class="field">
                            <label>Телефон</label>
                            <input v-model="profileForm.phone" type="tel" autocomplete="tel" placeholder="+375...">
                        </div>
                        <p v-if="profileError" class="settings-error">{{ profileError }}</p>
                        <div class="settings-actions">
                            <button type="submit" class="btn" :disabled="savingProfile">
                                {{ savingProfile ? 'Сохраняем...' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-group">
                    <div class="settings-row" role="button" tabindex="0" @click="togglePanel('notifications')" @keydown.enter="togglePanel('notifications')">
                        <span class="settings-row__icon settings-row__icon--notify" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                                <path d="M10 21h4" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Уведомления</strong>
                            <small>Дорожные события и важные сообщения</small>
                        </span>
                        <label class="settings-switch" @click.stop>
                            <input v-model="settings.incident_notifications" type="checkbox" @change="saveSettings">
                            <span></span>
                        </label>
                    </div>

                    <div v-if="activePanel === 'notifications'" class="settings-drawer">
                        <p class="settings-drawer__text">
                            Сообщения о дорожных происшествиях на сохранённых маршрутах сейчас
                            {{ settings.incident_notifications ? 'включены' : 'выключены' }}.
                        </p>
                        <div class="settings-channel-list">
                            <label>
                                <span><strong>Email</strong><small>Важные дорожные события</small></span>
                                <input v-model="settings.email_notifications" type="checkbox">
                            </label>
                            <label>
                                <span><strong>Push</strong><small>Мобильное приложение и зарегистрированные браузеры</small></span>
                                <input v-model="settings.push_notifications" type="checkbox">
                            </label>
                            <label>
                                <span><strong>Telegram</strong><small>Сообщения через бота TruckRoute</small></span>
                                <input v-model="settings.telegram_notifications" type="checkbox">
                            </label>
                            <div v-if="settings.telegram_notifications" class="field">
                                <label>Telegram Chat ID</label>
                                <input v-model.trim="settings.telegram_chat_id" type="text" placeholder="Например: 123456789">
                            </div>
                        </div>
                        <div class="settings-actions">
                            <button class="btn" type="button" @click="saveSettings" :disabled="savingSettings">
                                {{ savingSettings ? 'Сохраняем...' : 'Сохранить' }}
                            </button>
                            <RouterLink :to="{ name: 'notifications' }" class="btn outline">
                                Открыть уведомления
                            </RouterLink>
                        </div>
                    </div>

                    <div class="settings-row">
                        <span class="settings-row__icon settings-row__icon--fleet" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3 4 7v5c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V7l-8-4Z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>История маршрутов</strong>
                            <small>Разрешить владельцу вашего автопарка видеть историю поездок</small>
                        </span>
                        <label class="settings-switch" @click.stop>
                            <input v-model="settings.share_route_history_with_fleet" type="checkbox" @change="saveSettings">
                            <span></span>
                        </label>
                    </div>

                    <button class="settings-row" type="button" @click="togglePanel('password')">
                        <span class="settings-row__icon settings-row__icon--lock" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Безопасность</strong>
                            <small>Смена пароля аккаунта</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <form v-if="activePanel === 'password'" class="settings-drawer" @submit.prevent="changePassword">
                        <div class="field">
                            <label>Текущий пароль</label>
                            <input v-model="pwForm.current_password" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="field">
                            <label>Новый пароль</label>
                            <input v-model="pwForm.password" type="password" autocomplete="new-password" required>
                        </div>
                        <div class="field">
                            <label>Подтверждение</label>
                            <input v-model="pwForm.password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <p v-if="pwError" class="settings-error">{{ pwError }}</p>
                        <div class="settings-actions">
                            <button type="submit" class="btn light" :disabled="savingPw">
                                {{ savingPw ? 'Меняем...' : 'Изменить пароль' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-group">
                    <button v-if="activeTrip" class="settings-row" type="button" @click="togglePanel('trip')">
                        <span class="settings-row__icon settings-row__icon--trip" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 17a7 7 0 0 1 14 0" />
                                <path d="M12 17V9" />
                                <path d="m9 12 3-3 3 3" />
                                <path d="M4 21h16" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Активная поездка</strong>
                            <small>{{ activeTripRouteLine }}</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <div v-if="activePanel === 'trip' && activeTrip" class="settings-drawer">
                        <div class="settings-mini-card settings-mini-card--accent">
                            <div>
                                <span>В процессе</span>
                                <strong>{{ activeTripRouteLine }}</strong>
                                <small v-if="activeTrip.last_lat && activeTrip.last_lng">
                                    Последняя позиция: {{ Number(activeTrip.last_lat).toFixed(5) }}, {{ Number(activeTrip.last_lng).toFixed(5) }}
                                </small>
                            </div>
                            <RouterLink
                                v-if="activeTrip.route_plan_id"
                                :to="{ name: 'route-detail', params: { id: activeTrip.route_plan_id } }"
                                class="btn outline"
                            >
                                Открыть
                            </RouterLink>
                        </div>
                    </div>

                    <button class="settings-row" type="button" @click="togglePanel('fleets')">
                        <span class="settings-row__icon settings-row__icon--fleet" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="7" width="18" height="12" rx="2" />
                                <path d="M7 7V5h10v2" />
                                <path d="M8 12h8" />
                                <path d="M8 16h4" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Автопарки</strong>
                            <small>{{ fleetSummary }}</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <div v-if="activePanel === 'fleets'" class="settings-drawer">
                        <div v-if="loadingAccountData" class="settings-empty">Загрузка...</div>
                        <div v-else-if="!fleets.length" class="settings-empty">
                            Вы пока не привязаны к автопарку.
                        </div>
                        <div v-else class="settings-list">
                            <button
                                v-for="fleet in fleets"
                                :key="fleet.id"
                                type="button"
                                class="settings-mini-card settings-mini-card--button"
                                @click="selectedFleetInfo = fleet"
                            >
                                <div class="settings-mini-avatar">
                                    <img v-if="fleet.avatar_url" :src="fleet.avatar_url" :alt="fleet.name">
                                    <span v-else>{{ fleetInitials(fleet) }}</span>
                                </div>
                                <div>
                                    <span>{{ fleet.is_owner ? 'Владелец' : 'Сотрудник' }}</span>
                                    <strong>{{ fleet.name }}</strong>
                                    <small>{{ fleet.base_city || fleet.address || fleet.description || 'Данные автопарка пока не заполнены' }}</small>
                                    <div class="settings-mini-meta">
                                        <em>{{ fleet.drivers_count ?? 0 }} вод.</em>
                                        <em>{{ fleet.assignments_count ?? 0 }} зад.</em>
                                        <em>{{ fleet.completed_assignments_count ?? 0 }} вып.</em>
                                    </div>
                                </div>
                                <span class="settings-mini-card__arrow" aria-hidden="true">›</span>
                            </button>
                        </div>
                        <div v-if="auth.isFleet" class="settings-actions">
                            <RouterLink :to="{ name: 'fleet' }" class="btn">Открыть управление</RouterLink>
                        </div>
                    </div>

                    <button class="settings-row" type="button" @click="togglePanel('assignments')">
                        <span class="settings-row__icon settings-row__icon--routes" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="4" y="3" width="16" height="18" rx="2" />
                                <path d="M8 8h8M8 12h8M8 16h5" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Мои задания</strong>
                            <small>{{ assignmentSummary }}</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <div v-if="activePanel === 'assignments'" class="settings-drawer">
                        <div v-if="loadingAccountData" class="settings-empty">Загрузка...</div>
                        <div v-else-if="!driverAssignments.length" class="settings-empty">
                            Автопарк пока не выдавал вам заданий.
                        </div>
                        <div v-else class="settings-list">
                            <RouterLink
                                v-for="assignment in driverAssignments"
                                :key="assignment.id"
                                :to="{ name: 'assignment-detail', params: { id: assignment.id } }"
                                class="settings-mini-card settings-mini-card--button"
                            >
                                <div>
                                    <span>{{ assignmentStatusLabel(assignment.status) }}</span>
                                    <strong>{{ compactLabel(assignment.origin) }} → {{ compactLabel(assignment.destination) }}</strong>
                                    <small>
                                        {{ assignment.fleet?.name || 'Автопарк' }}
                                        <template v-if="assignment.planned_start_at"> · {{ formatAssignmentDate(assignment.planned_start_at) }}</template>
                                    </small>
                                </div>
                                <span class="settings-mini-card__arrow" aria-hidden="true">›</span>
                            </RouterLink>
                        </div>
                    </div>

                    <button class="settings-row" type="button" @click="togglePanel('vehicles')">
                        <span class="settings-row__icon settings-row__icon--vehicle" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 16V9a2 2 0 0 1 2-2h9l5 5v4" />
                                <path d="M15 7v5h5" />
                                <circle cx="7" cy="17" r="2" />
                                <circle cx="17" cy="17" r="2" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Транспорт</strong>
                            <small>{{ vehicleSummary }}</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <div v-if="activePanel === 'vehicles'" class="settings-drawer">
                        <div class="settings-actions">
                            <button class="btn" type="button" @click="showAddVehicle = !showAddVehicle">
                                {{ showAddVehicle ? 'Скрыть форму' : '+ Добавить ТС' }}
                            </button>
                        </div>

                        <form v-if="showAddVehicle" class="settings-inline-form" @submit.prevent="saveVehicle">
                            <div class="field">
                                <label>Название</label>
                                <input v-model="vForm.title" required placeholder="Например: Мой Volvo">
                            </div>
                            <div class="field">
                                <label>Тип</label>
                                <select v-model="vForm.type">
                                    <option
                                        v-for="item in dictionaries.options('vehicle_types')"
                                        :key="item.value"
                                        :value="item.value"
                                    >
                                        {{ item.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Бак, л</label>
                                <input v-model.number="vForm.tank_capacity_l" type="number" min="50" max="2000" required>
                            </div>
                            <div class="field">
                                <label>Расход, л/100 км</label>
                                <input v-model.number="vForm.consumption_l_per_100" type="number" min="5" max="100" step="0.1" required>
                            </div>
                            <div class="field">
                                <label>Скорость, км/ч</label>
                                <input v-model.number="vForm.cruise_speed_kmh" type="number" min="40" max="130" required>
                            </div>
                            <div class="field">
                                <label>Собственная масса, т</label>
                                <input v-model.number="vForm.curb_weight_t" type="number" step="0.1">
                            </div>
                            <div class="settings-actions settings-actions--full">
                                <button type="submit" class="btn" :disabled="savingVehicle">
                                    {{ savingVehicle ? 'Сохраняем...' : 'Сохранить ТС' }}
                                </button>
                            </div>
                        </form>

                        <div v-if="loadingAccountData" class="settings-empty">Загрузка...</div>
                        <div v-else-if="!vehicles.length" class="settings-empty">
                            Личный транспорт пока не добавлен.
                        </div>
                        <div v-else class="settings-list">
                            <button
                                v-for="vehicle in vehicles"
                                :key="vehicle.id"
                                class="settings-mini-card settings-mini-card--button"
                                :class="{ 'is-active': vehicle.is_active }"
                                type="button"
                                @click="activateVehicle(vehicle)"
                            >
                                <div class="settings-mini-avatar settings-mini-avatar--vehicle">
                                    <img v-if="vehicle.image_url" :src="vehicle.image_url" :alt="vehicle.title">
                                    <svg v-else viewBox="0 0 24 24">
                                        <path d="M4 16V9a2 2 0 0 1 2-2h9l5 5v4" />
                                        <path d="M15 7v5h5" />
                                        <circle cx="7" cy="17" r="2" />
                                        <circle cx="17" cy="17" r="2" />
                                    </svg>
                                </div>
                                <div>
                                    <span>{{ vehicle.is_active ? 'Активный' : vehicle.type }}</span>
                                    <strong>{{ vehicle.title }}</strong>
                                    <small>{{ vehicle.tank_capacity_l }} л · {{ vehicle.consumption_l_per_100 }} л/100 · {{ vehicle.cruise_speed_kmh }} км/ч</small>
                                </div>
                            </button>
                        </div>
                    </div>

                    <button class="settings-row" type="button" @click="togglePanel('routes')">
                        <span class="settings-row__icon settings-row__icon--routes" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 6h4a3 3 0 0 1 3 3v6a3 3 0 0 0 3 3h4" />
                                <circle cx="5" cy="6" r="2" />
                                <circle cx="19" cy="18" r="2" />
                                <path d="M13 6h6" />
                                <path d="M16 3v6" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>История маршрутов</strong>
                            <small>{{ routesSummary }}</small>
                        </span>
                        <span class="settings-row__chevron">›</span>
                    </button>

                    <div v-if="activePanel === 'routes'" class="settings-drawer">
                        <div v-if="loadingAccountData" class="settings-empty">Загрузка...</div>
                        <div v-else-if="!routes.length" class="settings-empty">
                            Сохранённых маршрутов пока нет.
                            <div class="settings-actions">
                                <RouterLink :to="{ name: 'routes' }" class="btn">Построить маршрут</RouterLink>
                            </div>
                        </div>
                        <div v-else class="settings-list">
                            <article v-for="route in routes" :key="route.id" class="settings-mini-card">
                                <div>
                                    <span>{{ route.planning_mode || 'Маршрут' }}</span>
                                    <strong>{{ route.title || routeLine(route) }}</strong>
                                    <small>{{ routeLine(route) }}</small>
                                    <div class="settings-mini-meta">
                                        <em>{{ route.distance_km ?? '—' }} км</em>
                                        <em>{{ route.fuel?.needed_l ?? route.fuel_needed_l ?? '—' }} л</em>
                                        <em>{{ route.stops_count ?? 0 }} ост.</em>
                                    </div>
                                </div>
                                <RouterLink :to="{ name: 'route-detail', params: { id: route.id } }" class="btn outline">
                                    Открыть
                                </RouterLink>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="settings-group">
                    <div class="settings-row settings-row--static">
                        <span class="settings-row__icon settings-row__icon--role" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3 4 7v6c0 5 3.5 7.5 8 9 4.5-1.5 8-4 8-9V7l-8-4Z" />
                                <path d="m9 12 2 2 4-5" />
                            </svg>
                        </span>
                        <span class="settings-row__body">
                            <strong>Роль</strong>
                            <small>Уровень доступа в системе</small>
                        </span>
                        <span class="settings-row__meta">{{ roleLabel }}</span>
                    </div>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="selectedFleetInfo" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="selectedFleetInfo = null"></div>
                <section class="auth-modal__panel settings-fleet-modal" aria-modal="true" role="dialog">
                    <button class="auth-modal__close" type="button" @click="selectedFleetInfo = null">закрыть</button>
                    <header class="settings-fleet-modal__head">
                        <div class="settings-fleet-modal__avatar">
                            <img v-if="selectedFleetInfo.avatar_url" :src="selectedFleetInfo.avatar_url" :alt="selectedFleetInfo.name">
                            <span v-else>{{ fleetInitials(selectedFleetInfo) }}</span>
                        </div>
                        <div>
                            <span>{{ selectedFleetInfo.is_owner ? 'Вы владелец' : 'Вы сотрудник' }}</span>
                            <h2>{{ selectedFleetInfo.name }}</h2>
                            <p>{{ selectedFleetInfo.description || 'Описание автопарка пока не заполнено.' }}</p>
                        </div>
                    </header>

                    <div class="settings-fleet-modal__stats">
                        <div><strong>{{ selectedFleetInfo.drivers_count ?? 0 }}</strong><span>водителей</span></div>
                        <div><strong>{{ selectedFleetInfo.assignments_count ?? 0 }}</strong><span>заданий</span></div>
                        <div><strong>{{ selectedFleetInfo.completed_assignments_count ?? 0 }}</strong><span>выполнено</span></div>
                    </div>

                    <dl class="settings-fleet-modal__details">
                        <div v-if="selectedFleetInfo.base_city">
                            <dt>Город базы</dt>
                            <dd>{{ selectedFleetInfo.base_city }}</dd>
                        </div>
                        <div v-if="selectedFleetInfo.address">
                            <dt>Адрес базы</dt>
                            <dd>{{ selectedFleetInfo.address }}</dd>
                        </div>
                        <div v-if="selectedFleetInfo.phone">
                            <dt>Рабочий телефон</dt>
                            <dd>{{ selectedFleetInfo.phone }}</dd>
                        </div>
                        <div v-if="selectedFleetInfo.inn">
                            <dt>ИНН</dt>
                            <dd>{{ selectedFleetInfo.inn }}</dd>
                        </div>
                    </dl>

                    <div v-if="selectedFleetInfo.is_owner || auth.isFleet" class="settings-actions">
                        <RouterLink :to="{ name: 'fleet' }" class="btn" @click="selectedFleetInfo = null">
                            Открыть автопарк
                        </RouterLink>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useDictionariesStore } from '@/stores/dictionaries';

const auth = useAuthStore();
const ui = useUiStore();
const dictionaries = useDictionariesStore();

const settings = ref({
    incident_notifications: true,
    share_route_history_with_fleet: false,
    email_notifications: true,
    push_notifications: true,
    telegram_notifications: false,
    telegram_chat_id: '',
});
const savingSettings = ref(false);
const successMsg = ref('');
const errorMsg = ref('');
const activePanel = ref('');

const profileForm = ref({ name: '', phone: '' });
const savingProfile = ref(false);
const savingAvatar = ref(false);
const profileError = ref('');
const avatarPreview = ref('');

const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const savingPw = ref(false);
const pwError = ref('');

const fleets = ref([]);
const driverAssignments = ref([]);
const vehicles = ref([]);
const routes = ref([]);
const activeTrip = ref(null);
const selectedFleetInfo = ref(null);
const loadingAccountData = ref(true);
const showAddVehicle = ref(false);
const savingVehicle = ref(false);

const vForm = ref({
    title: '',
    type: 'Тягач + полуприцеп',
    tank_capacity_l: 600,
    consumption_l_per_100: 29,
    cruise_speed_kmh: 85,
    curb_weight_t: '',
});

const roleLabel = computed(() => {
    const labels = {
        admin: 'Администратор',
        driver: 'Водитель',
        provider: 'Поставщик',
        fleet: 'Автопарк',
    };

    return labels[auth.user?.role] ?? 'Пользователь';
});

const avatarSrc = computed(() => avatarPreview.value || auth.user?.avatar_url || '');

const accountSubtitle = computed(() => {
    return [auth.user?.phone, auth.user?.email].filter(Boolean).join(' · ') || roleLabel.value;
});

const fleetSummary = computed(() => {
    if (loadingAccountData.value) return 'Загрузка...';
    if (!fleets.value.length) return auth.isFleet ? 'Создайте или откройте автопарк' : 'Нет привязки к автопарку';
    const owned = fleets.value.filter((fleet) => fleet.is_owner).length;
    const employee = fleets.value.length - owned;
    if (owned && employee) return `${owned} в управлении · ${employee} как сотрудник`;
    if (owned) return `${owned} в управлении`;
    return `${employee || fleets.value.length} как сотрудник`;
});

const vehicleSummary = computed(() => {
    if (loadingAccountData.value) return 'Загрузка...';
    const active = vehicles.value.find((vehicle) => vehicle.is_active);
    if (!vehicles.value.length) return 'Нет личных транспортных средств';
    return active ? `${vehicles.value.length} ТС · активно: ${active.title}` : `${vehicles.value.length} ТС`;
});

const assignmentSummary = computed(() => {
    if (loadingAccountData.value) return 'Загрузка...';
    const active = driverAssignments.value.filter((item) => ['issued', 'accepted', 'in_progress'].includes(item.status)).length;
    if (!driverAssignments.value.length) return 'Новых заданий нет';
    return active ? `${active} активных · всего ${driverAssignments.value.length}` : `${driverAssignments.value.length} в истории`;
});

const routesSummary = computed(() => {
    if (loadingAccountData.value) return 'Загрузка...';
    if (!routes.value.length) return 'Сохранённых маршрутов нет';
    return `${routes.value.length} сохранённых маршрутов`;
});

const activeTripRouteLine = computed(() => {
    const routePlan = activeTrip.value?.route_plan;
    if (!routePlan) return 'Поездка запущена';
    return `${compactLabel(routePlan.origin) || 'Старт'} → ${compactLabel(routePlan.destination) || 'Финиш'}`;
});

function togglePanel(panel) {
    activePanel.value = activePanel.value === panel ? '' : panel;
}

onMounted(async () => {
    dictionaries.load();
    profileForm.value = {
        name: auth.user?.name ?? '',
        phone: auth.user?.phone ?? '',
    };

    await Promise.all([loadSettings(), loadAccountData()]);
});

async function loadSettings() {
    try {
        const { data } = await axios.get('/api/v1/settings');
        const payload = data.data ?? data;
        settings.value = {
            ...settings.value,
            incident_notifications: payload.incident_notifications ?? true,
            share_route_history_with_fleet: payload.share_route_history_with_fleet ?? false,
            email_notifications: payload.email_notifications ?? true,
            push_notifications: payload.push_notifications ?? true,
            telegram_notifications: payload.telegram_notifications ?? false,
            telegram_chat_id: payload.telegram_chat_id ?? '',
        };
    } catch { /* ignore */ }
}

async function loadAccountData() {
    loadingAccountData.value = true;
    const [fleetRes, assignmentRes, vehicleRes, routeRes, tripRes] = await Promise.allSettled([
        axios.get('/api/v1/fleets'),
        axios.get('/api/v1/assignments?per_page=50'),
        axios.get('/api/v1/vehicles'),
        axios.get('/api/v1/routes?per_page=12'),
        axios.get('/api/v1/trip/current', { silent: true }),
    ]);

    if (fleetRes.status === 'fulfilled') fleets.value = fleetRes.value.data.data ?? [];
    if (assignmentRes.status === 'fulfilled') driverAssignments.value = assignmentRes.value.data.data ?? [];
    if (vehicleRes.status === 'fulfilled') vehicles.value = vehicleRes.value.data.data ?? vehicleRes.value.data ?? [];
    if (routeRes.status === 'fulfilled') routes.value = routeRes.value.data.data ?? routeRes.value.data ?? [];
    if (tripRes.status === 'fulfilled') {
        const payload = Object.prototype.hasOwnProperty.call(tripRes.value.data ?? {}, 'data')
            ? tripRes.value.data.data
            : tripRes.value.data;
        activeTrip.value = payload?.status === 'active' ? payload : null;
    }

    loadingAccountData.value = false;
}

async function saveProfile() {
    savingProfile.value = true;
    profileError.value = '';
    successMsg.value = '';
    errorMsg.value = '';

    try {
        const { data } = await axios.patch('/api/v1/auth/profile', profileForm.value);
        auth.user = data.user ?? auth.user;
        successMsg.value = 'Аккаунт обновлён.';
    } catch (e) {
        profileError.value = e.response?.data?.message
            ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Ошибка обновления аккаунта.';
    } finally {
        savingProfile.value = false;
    }
}

async function saveAvatar(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        errorMsg.value = 'Файл аватарки должен быть до 2 МБ.';
        return;
    }

    const oldPreview = avatarPreview.value;
    if (oldPreview) URL.revokeObjectURL(oldPreview);
    avatarPreview.value = URL.createObjectURL(file);
    savingAvatar.value = true;
    successMsg.value = '';
    errorMsg.value = '';

    try {
        const form = new FormData();
        form.append('_method', 'PATCH');
        form.append('avatar', file);

        const { data } = await axios.post('/api/v1/auth/profile', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        auth.user = data.user ?? auth.user;
        if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = '';
        successMsg.value = 'Аватар обновлён.';
    } catch (e) {
        if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = '';
        errorMsg.value = e.response?.data?.message
            ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Не удалось загрузить аватар.';
    } finally {
        savingAvatar.value = false;
    }
}

async function saveSettings() {
    savingSettings.value = true;
    successMsg.value = '';
    errorMsg.value = '';
    try {
        await axios.patch('/api/v1/settings', settings.value);
        successMsg.value = 'Настройки сохранены.';
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Ошибка сохранения.';
    } finally {
        savingSettings.value = false;
    }
}

async function changePassword() {
    savingPw.value = true;
    pwError.value = '';
    successMsg.value = '';
    try {
        await axios.patch('/api/v1/settings/password', pwForm.value);
        pwForm.value = { current_password: '', password: '', password_confirmation: '' };
        successMsg.value = 'Пароль изменён. Остальные активные сессии завершены.';
    } catch (e) {
        pwError.value = e.response?.data?.message
            ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Ошибка.';
    } finally {
        savingPw.value = false;
    }
}

async function activateVehicle(vehicle) {
    if (vehicle.is_active) return;
    try {
        const { data } = await axios.post(`/api/v1/vehicles/${vehicle.id}/activate`);
        vehicles.value = vehicles.value.map((item) => ({
            ...item,
            is_active: Number(item.id) === Number(data.id),
        }));
        successMsg.value = 'Транспорт выбран.';
    } catch {
        ui.error('Ошибка выбора транспорта');
    }
}

async function saveVehicle() {
    savingVehicle.value = true;
    try {
        const { data } = await axios.post('/api/v1/vehicles', vForm.value);
        vehicles.value = vehicles.value.map((vehicle) => ({ ...vehicle, is_active: false }));
        vehicles.value.unshift(data);
        showAddVehicle.value = false;
        vForm.value = {
            title: '',
            type: 'Тягач + полуприцеп',
            tank_capacity_l: 600,
            consumption_l_per_100: 29,
            cruise_speed_kmh: 85,
            curb_weight_t: '',
        };
        successMsg.value = 'Транспорт добавлен.';
    } catch (e) {
        errorMsg.value = e.response?.data?.message
            ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Ошибка сохранения транспорта.';
    } finally {
        savingVehicle.value = false;
    }
}

function routeLine(route) {
    const origin = route.origin?.label ?? route.origin ?? 'Старт';
    const destination = route.destination?.label ?? route.destination ?? 'Финиш';
    return `${origin} → ${destination}`;
}

function assignmentStatusLabel(status) {
    return {
        issued: 'Новое задание',
        accepted: 'Принято',
        in_progress: 'В пути',
        completed: 'Выполнено',
        cancelled: 'Отменено',
    }[status] || 'Задание';
}

function formatAssignmentDate(value) {
    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function compactLabel(label) {
    const source = typeof label === 'object' && label !== null ? label.label : label;
    const parts = String(source || '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length <= 2) return parts.join(', ') || source;
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
.settings-page {
    padding: 108px 0 52px;
}

.settings-shell {
    width: min(760px, calc(100% - 32px));
    margin: 0 auto;
    font-family: inherit;
}

.settings-profile-top {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.settings-avatar,
.settings-row__icon {
    display: grid;
    place-items: center;
    flex: none;
    color: var(--text-2);
}

.settings-avatar {
    position: relative;
    width: 58px;
    height: 58px;
    overflow: hidden;
    border: 1px solid var(--border-mid);
    border-radius: 50%;
    background: color-mix(in srgb, var(--accent) 8%, transparent);
    color: var(--accent);
}

.settings-avatar--small {
    width: 48px;
    height: 48px;
}

.settings-avatar--upload {
    cursor: pointer;
}

.settings-avatar--upload:hover {
    border-color: var(--border-a);
}

.settings-avatar input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.settings-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.settings-avatar svg {
    width: 28px;
    height: 28px;
}

.settings-avatar--small svg {
    width: 24px;
    height: 24px;
}

.settings-avatar svg,
.settings-row__icon svg,
.settings-mini-avatar svg {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.settings-avatar__busy {
    position: absolute;
    inset: 0;
    background: color-mix(in srgb, var(--bg) 54%, transparent);
}

.settings-profile-copy {
    min-width: 0;
}

.settings-profile-copy span {
    display: block;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.settings-profile-copy h1 {
    margin: 5px 0 0;
    overflow: hidden;
    color: var(--text);
    font-family: var(--font-d);
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 400;
    letter-spacing: 0;
    line-height: 1.08;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settings-profile-copy h1::after {
    display: none;
}

.settings-profile-copy p {
    margin-top: 4px;
    overflow: hidden;
    color: var(--text-2);
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settings-edit-pill {
    min-height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    background: var(--s2);
    color: var(--text);
    font-size: 13px;
    box-shadow: none;
}

.settings-message {
    display: grid;
    gap: 4px;
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.settings-message strong {
    color: var(--text);
    font-size: 14px;
}

.settings-message span {
    color: var(--text-2);
    font-size: 13px;
}

.settings-message.is-success {
    border-color: color-mix(in srgb, var(--green) 42%, var(--border));
}

.settings-message.is-error {
    border-color: color-mix(in srgb, var(--red) 48%, var(--border));
}

.settings-groups {
    display: grid;
    gap: 14px;
    margin-top: 16px;
}

.settings-group {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.settings-row {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr) auto auto;
    gap: 12px;
    align-items: center;
    width: 100%;
    min-height: 58px;
    padding: 9px 12px;
    border: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    box-shadow: none;
    cursor: pointer;
}

.settings-row:last-child {
    border-bottom: 0;
}

.settings-row:hover {
    transform: none;
    background: color-mix(in srgb, var(--text) 4%, transparent);
}

.settings-row--static {
    cursor: default;
}

.settings-row--static:hover {
    background: transparent;
}

.settings-row__icon {
    width: 32px;
    height: 32px;
}

.settings-row__icon svg {
    width: 22px;
    height: 22px;
}

.settings-row__icon--profile,
.settings-row__icon--notify,
.settings-row__icon--trip,
.settings-row__icon--fleet,
.settings-row__icon--vehicle,
.settings-row__icon--routes {
    color: var(--accent);
}

.settings-row__icon--lock,
.settings-row__icon--role {
    color: var(--text-2);
}

.settings-row__body {
    min-width: 0;
}

.settings-row__body strong,
.settings-row__body small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settings-row__body strong {
    color: var(--text);
    font-size: 16px;
    font-weight: 500;
}

.settings-row__body small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 12px;
}

.settings-row__meta {
    color: var(--text-2);
    font-size: 13px;
    white-space: nowrap;
}

.settings-row__chevron {
    color: var(--text-3);
    font-size: 24px;
    line-height: 1;
}

.settings-switch {
    position: relative;
    display: block;
    width: 42px;
    height: 24px;
    cursor: pointer;
}

.settings-switch input {
    position: absolute;
    inset: 0;
    opacity: 0;
}

.settings-switch span {
    position: absolute;
    inset: 0;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--s2);
    transition: background-color .15s, border-color .15s;
}

.settings-switch span::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--text-3);
    transition: transform .15s, background-color .15s;
}

.settings-switch input:checked + span {
    border-color: var(--border-a);
    background: var(--accent-bg);
}

.settings-switch input:checked + span::after {
    transform: translateX(18px);
    background: var(--accent);
}

.settings-drawer {
    display: grid;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border);
    background: color-mix(in srgb, var(--text) 3%, transparent);
}

.settings-group .settings-drawer:last-child {
    border-bottom: 0;
}

.settings-drawer :deep(.field input),
.settings-drawer :deep(.field select),
.settings-drawer input,
.settings-drawer select {
    height: 40px;
    border-radius: 8px;
}

.settings-drawer__text {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.45;
}

.settings-avatar-field {
    display: flex;
    align-items: center;
    gap: 12px;
}

.settings-avatar-field strong,
.settings-avatar-field span {
    display: block;
}

.settings-avatar-field strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
}

.settings-avatar-field span {
    margin-top: 2px;
    color: var(--text-3);
    font-size: 12px;
}

.settings-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.settings-actions .btn {
    min-height: 36px;
    padding: 0 12px;
    font-size: 12px;
}

.settings-actions--full {
    grid-column: 1 / -1;
}

.settings-error {
    margin: 0;
    color: var(--red);
    font-size: 13px;
}

.settings-empty {
    padding: 14px 0;
    color: var(--text-3);
    font-size: 13px;
}

.settings-list {
    display: grid;
    gap: 8px;
}

.settings-mini-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 36%, transparent);
    text-align: left;
    box-shadow: none;
}

.settings-mini-card:has(.settings-mini-avatar) {
    grid-template-columns: 44px minmax(0, 1fr) auto;
}

.settings-mini-card--button {
    cursor: pointer;
}

.settings-mini-card--button:hover {
    transform: none;
    border-color: var(--border-a);
    background: var(--accent-bg);
}

.settings-mini-card--button.is-active,
.settings-mini-card--accent {
    border-color: var(--border-a);
    background: var(--accent-bg);
}

.settings-mini-card span,
.settings-mini-card small,
.settings-mini-card strong {
    display: block;
}

.settings-mini-card span {
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.settings-mini-card strong {
    margin-top: 4px;
    overflow: hidden;
    color: var(--text);
    font-size: 15px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settings-mini-card small {
    margin-top: 4px;
    overflow: hidden;
    color: var(--text-2);
    font-size: 12px;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settings-mini-card .btn {
    min-height: 34px;
    padding: 0 12px;
    font-size: 12px;
}

.settings-mini-card__arrow {
    color: var(--text-3) !important;
    font-family: var(--font-s) !important;
    font-size: 24px !important;
    letter-spacing: 0 !important;
}

.settings-fleet-modal {
    width: min(620px, calc(100vw - 32px));
    padding: 28px;
}

.settings-fleet-modal__head {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
    padding-right: 48px;
}

.settings-fleet-modal__avatar {
    display: grid;
    place-items: center;
    width: 72px;
    height: 72px;
    overflow: hidden;
    border: 1px solid var(--border-a);
    border-radius: 8px;
    background: var(--accent-bg);
    color: var(--accent);
    font-family: var(--font-m);
    font-weight: 700;
}

.settings-fleet-modal__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.settings-fleet-modal__head span {
    color: var(--accent);
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.settings-fleet-modal__head h2 {
    margin-top: 5px;
    font-family: var(--font-d);
    font-size: clamp(28px, 5vw, 44px);
    font-weight: 400;
    line-height: 1;
    text-transform: none;
}

.settings-fleet-modal__head h2::after {
    display: none;
}

.settings-fleet-modal__head p {
    margin-top: 9px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.5;
}

.settings-fleet-modal__stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 24px;
    border-block: 1px solid var(--border);
}

.settings-fleet-modal__stats div {
    padding: 15px 10px;
    border-right: 1px solid var(--border);
}

.settings-fleet-modal__stats div:last-child {
    border-right: 0;
}

.settings-fleet-modal__stats strong,
.settings-fleet-modal__stats span {
    display: block;
}

.settings-fleet-modal__stats strong {
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 28px;
    font-weight: 400;
}

.settings-fleet-modal__stats span {
    margin-top: 4px;
    color: var(--text-3);
    font-size: 10px;
    text-transform: uppercase;
}

.settings-fleet-modal__details {
    margin: 16px 0 0;
}

.settings-fleet-modal__details div {
    display: grid;
    grid-template-columns: 130px minmax(0, 1fr);
    gap: 18px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.settings-fleet-modal__details dt {
    color: var(--text-3);
    font-size: 11px;
}

.settings-fleet-modal__details dd {
    margin: 0;
    color: var(--text);
    font-size: 13px;
}

.settings-mini-avatar {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 12px;
    font-weight: 600;
}

.settings-mini-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.settings-mini-avatar svg {
    width: 24px;
    height: 24px;
}

.settings-mini-avatar--vehicle img {
    object-fit: contain;
    padding: 4px;
}

.settings-mini-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.settings-mini-meta em {
    padding: 4px 6px;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    font-style: normal;
}

.settings-inline-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 30%, transparent);
}

.settings-channel-list {
    margin: 14px 0;
    border-top: 1px solid var(--border);
}

.settings-channel-list > label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.settings-channel-list strong,
.settings-channel-list small {
    display: block;
}

.settings-channel-list strong {
    color: var(--text);
    font-size: 13px;
}

.settings-channel-list small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 11px;
}

.settings-channel-list input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent);
}

.settings-channel-list .field {
    padding-top: 12px;
}

@media (max-width: 620px) {
    .settings-page {
        padding-top: 92px;
    }

    .settings-profile-top {
        grid-template-columns: 48px minmax(0, 1fr) auto;
        gap: 12px;
    }

    .settings-avatar {
        width: 48px;
        height: 48px;
    }

    .settings-avatar svg {
        width: 24px;
        height: 24px;
    }

    .settings-edit-pill {
        min-height: 36px;
        padding: 0 13px;
    }

    .settings-row {
        grid-template-columns: 30px minmax(0, 1fr) auto;
        gap: 10px;
    }

    .settings-row__icon {
        width: 30px;
        height: 30px;
    }

    .settings-row__icon svg {
        width: 20px;
        height: 20px;
    }

    .settings-row__meta {
        display: none;
    }

    .settings-row__chevron {
        font-size: 22px;
    }

    .settings-inline-form,
    .settings-mini-card,
    .settings-mini-card:has(.settings-mini-avatar) {
        grid-template-columns: 1fr;
    }

    .settings-fleet-modal__head {
        grid-template-columns: 54px minmax(0, 1fr);
    }

    .settings-fleet-modal__avatar {
        width: 54px;
        height: 54px;
    }

    .settings-fleet-modal__details div {
        grid-template-columns: 1fr;
        gap: 3px;
    }

    .settings-mini-card small {
        white-space: normal;
    }
}
</style>
