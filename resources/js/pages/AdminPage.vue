<template>
    <div>
        <section class="admin-hero">
            <div class="container admin-hero__inner">
                <div class="admin-hero__copy">
                    <span>Системное управление</span>
                    <h1>Панель администратора</h1>
                    <p>Пользователи, контент, дорожные события, справочники и аналитика в одном рабочем пространстве.</p>
                </div>
                <div class="admin-hero__scope" aria-label="Разделы управления">
                    <span>Пользователи</span>
                    <span>Контент</span>
                    <span>События</span>
                    <span>Аналитика</span>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="section-tight">
            <div class="container">
                <div class="stats stats-4 mt-36" style="margin-top:0;">
                    <div class="stat"><strong>{{ stats.users_total ?? '—' }}</strong><span>пользователей</span></div>
                    <div class="stat"><strong>{{ stats.poi_total ?? '—' }}</strong><span>объектов</span></div>
                    <div class="stat"><strong>{{ stats.events_active ?? '—' }}</strong><span>событий</span></div>
                    <div class="stat"><strong>{{ stats.news_published ?? '—' }}</strong><span>новостей</span></div>
                </div>
                <div class="stats stats-4" style="margin-top:0;">
                    <div class="stat"><strong style="color:var(--red)">{{ stats.poi_pending ?? '—' }}</strong><span>POI на проверке</span></div>
                    <div class="stat"><strong style="color:var(--red)">{{ stats.events_pending ?? '—' }}</strong><span>событий на проверке</span></div>
                    <div class="stat"><strong>{{ stats.routes_today ?? '—' }}</strong><span>маршрутов сегодня</span></div>
                    <div class="stat"><strong>—</strong><span>жалоб</span></div>
                </div>
            </div>
        </section>

        <!-- Tabs -->
        <section class="section-tight">
            <div class="container">
                <div class="admin-tabs">
                    <button v-for="tab in tabs" :key="tab.key" type="button"
                        class="admin-tab" :class="{ 'is-active': activeTab === tab.key }"
                        @click="activeTab = tab.key">
                        {{ tab.label }}
                        <span v-if="tab.badge" class="tab-badge">{{ tab.badge }}</span>
                    </button>
                </div>

                <div v-if="activeTab === 'analytics'" class="analytics-panel">
                    <div class="analytics-toolbar">
                        <div>
                            <span class="analytics-eyebrow">Состояние продукта</span>
                            <h2>Расширенная аналитика</h2>
                            <p>Активность пользователей, маршруты, рекомендации и результат поездок.</p>
                        </div>
                        <div class="analytics-periods" aria-label="Период аналитики">
                            <button
                                v-for="days in [7, 14, 30, 90]"
                                :key="days"
                                type="button"
                                :class="{ 'is-active': analyticsDays === days }"
                                @click="setAnalyticsPeriod(days)"
                            >
                                {{ days }} дней
                            </button>
                        </div>
                    </div>

                    <div v-if="loadingAnalytics" class="analytics-loading">Собираем показатели...</div>

                    <template v-else-if="analytics">
                        <div class="analytics-kpis">
                            <div>
                                <span class="analytics-metric-label">
                                    DAU
                                    <button
                                        class="analytics-info"
                                        type="button"
                                        aria-label="Что означает DAU"
                                        data-tooltip="DAU — количество уникальных пользователей, которые были активны сегодня."
                                    >!</button>
                                </span>
                                <strong>{{ analytics.audience.dau }}</strong>
                                <small>активны сегодня</small>
                            </div>
                            <div>
                                <span class="analytics-metric-label">
                                    MAU
                                    <button
                                        class="analytics-info"
                                        type="button"
                                        aria-label="Что означает MAU"
                                        data-tooltip="MAU — количество уникальных пользователей, которые были активны за последние 30 дней."
                                    >!</button>
                                </span>
                                <strong>{{ analytics.audience.mau }}</strong>
                                <small>активны за 30 дней</small>
                            </div>
                            <div>
                                <span>Маршрут → поездка</span>
                                <strong>{{ formatPercent(analytics.routes.start_conversion_percent) }}</strong>
                                <small>{{ analytics.routes.started }} из {{ analytics.routes.built }}</small>
                            </div>
                            <div>
                                <span>Принято рекомендаций</span>
                                <strong>{{ formatPercent(analytics.recommendations.acceptance_rate_percent) }}</strong>
                                <small>{{ analytics.recommendations.accepted }} решений</small>
                            </div>
                            <div>
                                <span>Прочитано уведомлений</span>
                                <strong>{{ formatPercent(analytics.notifications.read_rate_percent) }}</strong>
                                <small>{{ analytics.notifications.read }} из {{ analytics.notifications.sent }}</small>
                            </div>
                        </div>

                        <div class="analytics-grid">
                            <section class="analytics-section">
                                <div class="analytics-section-head">
                                    <div>
                                        <span>Аудитория</span>
                                        <h3>Активность по дням</h3>
                                    </div>
                                    <b class="analytics-ratio">
                                        {{ formatPercent(analytics.audience.stickiness_percent) }} DAU/MAU
                                        <button
                                            class="analytics-info"
                                            type="button"
                                            aria-label="Что означает отношение DAU к MAU"
                                            data-tooltip="DAU/MAU показывает долю месячной аудитории, которая пользуется сервисом сегодня. Чем выше процент, тем чаще пользователи возвращаются."
                                        >!</button>
                                    </b>
                                </div>
                                <div class="analytics-chart" aria-label="График активных пользователей">
                                    <div
                                        v-for="point in analytics.audience.series"
                                        :key="point.date"
                                        class="analytics-chart-column"
                                        :title="`${formatAnalyticsDate(point.date)}: ${point.value}`"
                                    >
                                        <div class="analytics-chart-bar">
                                            <em v-if="point.value">{{ point.value }}</em>
                                            <span
                                                :class="{ 'is-empty': !point.value }"
                                                :style="{ height: chartHeight(point.value, analyticsActivityMax) }"
                                            ></span>
                                        </div>
                                        <small>{{ shortAnalyticsDate(point.date) }}</small>
                                    </div>
                                </div>
                                <div class="analytics-platforms">
                                    <span v-for="(count, platform) in analytics.audience.platforms" :key="platform">
                                        {{ platformLabel(platform) }} <b>{{ count }}</b>
                                    </span>
                                    <span v-if="!Object.keys(analytics.audience.platforms).length">Данных по платформам пока нет</span>
                                </div>
                            </section>

                            <section class="analytics-section">
                                <div class="analytics-section-head">
                                    <div>
                                        <span>Воронка</span>
                                        <h3>Путь маршрута</h3>
                                    </div>
                                    <b>{{ formatPercent(analytics.routes.completion_conversion_percent) }} завершено</b>
                                </div>
                                <div class="analytics-funnel">
                                    <div>
                                        <span>Построено</span>
                                        <strong>{{ analytics.routes.built }}</strong>
                                        <i style="width:100%"></i>
                                    </div>
                                    <div>
                                        <span>Начато</span>
                                        <strong>{{ analytics.routes.started }}</strong>
                                        <i :style="{ width: funnelWidth(analytics.routes.started, analytics.routes.built) }"></i>
                                    </div>
                                    <div>
                                        <span>Завершено</span>
                                        <strong>{{ analytics.routes.completed }}</strong>
                                        <i :style="{ width: funnelWidth(analytics.routes.completed, analytics.routes.built) }"></i>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="analytics-grid analytics-grid--compact">
                            <section class="analytics-section">
                                <div class="analytics-section-head">
                                    <div>
                                        <span>Рекомендации</span>
                                        <h3>Полезность подсказок</h3>
                                    </div>
                                    <b>{{ formatPercent(analytics.recommendations.response_rate_percent) }} реакций</b>
                                </div>
                                <div class="analytics-split">
                                    <div><strong>{{ analytics.recommendations.shown }}</strong><span>показано</span></div>
                                    <div><strong>{{ analytics.recommendations.accepted }}</strong><span>принято</span></div>
                                    <div><strong>{{ analytics.recommendations.rejected }}</strong><span>отклонено</span></div>
                                </div>
                            </section>

                            <section class="analytics-section">
                                <div class="analytics-section-head">
                                    <div>
                                        <span>Уведомления</span>
                                        <h3>Вовлечённость</h3>
                                    </div>
                                    <b>{{ formatReadTime(analytics.notifications.average_read_minutes) }}</b>
                                </div>
                                <div class="analytics-split">
                                    <div><strong>{{ analytics.notifications.sent }}</strong><span>отправлено</span></div>
                                    <div><strong>{{ analytics.notifications.read }}</strong><span>прочитано</span></div>
                                    <div><strong>{{ formatPercent(analytics.notifications.read_rate_percent) }}</strong><span>доля прочтений</span></div>
                                </div>
                            </section>
                        </div>

                        <section class="analytics-savings">
                            <div>
                                <span>Результат поездок</span>
                                <h3>Экономия топлива и времени</h3>
                                <p>{{ analytics.notes.time }} {{ analytics.notes.fuel }}</p>
                            </div>
                            <div class="analytics-savings-values">
                                <div>
                                    <strong>{{ formatDuration(analytics.savings.time_minutes) }}</strong>
                                    <span>сэкономлено времени</span>
                                    <small>{{ analytics.savings.time_compared_trips }} завершённых поездок</small>
                                </div>
                                <div>
                                    <strong>{{ analytics.savings.fuel_liters }} л</strong>
                                    <span>сэкономлено топлива</span>
                                    <small v-if="analytics.savings.fuel_measured_trips">
                                        {{ analytics.savings.fuel_measured_trips }} поездок с фактическим расходом
                                    </small>
                                    <small v-else>Фактический расход пока не передавался</small>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>

                <!-- ── Users ── -->
                <div v-if="activeTab === 'users'">
                    <div class="admin-toolbar">
                        <input v-model="userSearch" placeholder="Поиск по имени или email..." class="admin-search">
                        <select v-model="userRoleFilter">
                            <option value="">Все роли</option>
                            <option value="driver">Водитель</option>
                            <option value="provider">Поставщик</option>
                            <option value="fleet">Автопарк</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    <div v-if="loadingUsers" style="color:var(--text-3);padding:24px;">Загрузка...</div>
                    <table v-else class="admin-table">
                        <thead><tr>
                            <th>Имя</th><th>Email</th><th>Роль</th><th>Статус</th><th>Создан</th><th>Действия</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id" :class="{ 'is-banned': u.status === 'banned' }">
                                <td><strong>{{ u.name }}</strong></td>
                                <td style="font-size:12px;color:var(--text-2);">{{ u.email }}</td>
                                <td>
                                    <select :value="u.role" @change="changeRole(u, $event.target.value)" class="inline-select">
                                        <option value="driver">Водитель</option>
                                        <option value="provider">Поставщик</option>
                                        <option value="fleet">Автопарк</option>
                                        <option value="admin">Администратор</option>
                                    </select>
                                </td>
                                <td>
                                    <span class="badge" :style="u.status === 'banned' ? 'background:var(--red);color:#fff' : ''">{{ u.status ?? 'active' }}</span>
                                </td>
                                <td style="font-size:11px;color:var(--text-3);font-family:var(--font-m);">{{ fmtDate(u.created_at) }}</td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button v-if="u.status !== 'banned'" type="button" class="btn danger" style="font-size:11px;padding:4px 8px;min-height:auto;" @click="banUser(u)">Бан</button>
                                        <button v-else type="button" class="btn outline" style="font-size:11px;padding:4px 8px;min-height:auto;" @click="unbanUser(u)">Разбан</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── POI ── -->
                <div v-if="activeTab === 'poi'">
                    <div class="admin-toolbar">
                        <select v-model="poiStatusFilter" @change="loadPoi">
                            <option value="">Все</option>
                            <option value="moderation">На проверке</option>
                            <option value="active">Активные</option>
                            <option value="rejected">Отклонённые</option>
                        </select>
                    </div>
                    <div v-if="loadingPoi" style="color:var(--text-3);padding:24px;">Загрузка...</div>
                    <table v-else class="admin-table">
                        <thead><tr>
                            <th>Название</th><th>Тип</th><th>Провайдер</th><th>Статус</th><th>Действия</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="p in pois" :key="p.id" :class="{ 'is-done': p._done }">
                                <td><strong>{{ p.name }}</strong><br><span style="font-size:11px;color:var(--text-3);">{{ p.location }}</span></td>
                                <td><span class="badge">{{ p.type }}</span></td>
                                <td style="font-size:12px;">{{ p.provider_id ?? '—' }}</td>
                                <td>
                                    <span class="badge" :style="p.verified ? 'background:var(--green);color:#fff' : ''">
                                        {{ p.status }}{{ p.verified ? ' ✓' : '' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button v-if="p.status === 'moderation'" type="button" class="btn" style="font-size:11px;padding:4px 10px;min-height:auto;" @click="approvePoi(p)">Одобрить</button>
                                        <button v-if="p.status === 'moderation'" type="button" class="btn outline" style="font-size:11px;padding:4px 10px;min-height:auto;" @click="rejectPoi(p)">Отклонить</button>
                                        <button type="button" class="btn danger" style="font-size:11px;padding:4px 8px;min-height:auto;" @click="deletePoi(p)">Удалить</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── News ── -->
                <div v-if="activeTab === 'news'">
                    <div class="admin-toolbar">
                        <RouterLink :to="{ name: 'admin-news-new' }" class="btn" style="font-size:13px;">+ Новая статья</RouterLink>
                        <select v-model="newsStatusFilter" @change="loadNews">
                            <option value="">Все</option>
                            <option value="published">Опубликованные</option>
                            <option value="draft">Черновики</option>
                        </select>
                    </div>
                    <div v-if="loadingNews" style="color:var(--text-3);padding:24px;">Загрузка...</div>
                    <table v-else class="admin-table">
                        <thead><tr>
                            <th>Заголовок</th><th>Статус</th><th>Автор</th><th>Дата</th><th>Действия</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="a in newsArticles" :key="a.id">
                                <td><strong>{{ a.title }}</strong></td>
                                <td><span class="badge" :style="a.status === 'published' ? 'background:var(--green);color:#fff' : ''">{{ a.status }}</span></td>
                                <td style="font-size:12px;">{{ a.author?.name }}</td>
                                <td style="font-size:11px;font-family:var(--font-m);color:var(--text-3);">{{ fmtDate(a.published_at ?? a.created_at) }}</td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <RouterLink :to="{ name: 'admin-news-edit', params: { id: a.id } }" class="btn outline" style="font-size:11px;padding:4px 10px;min-height:auto;">Ред.</RouterLink>
                                        <button type="button" class="btn danger" style="font-size:11px;padding:4px 8px;min-height:auto;" @click="deleteNews(a)">Удалить</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── Events ── -->
                <div v-if="activeTab === 'events'">
                    <div class="admin-toolbar">
                        <select v-model="eventStatusFilter" @change="loadEvents">
                            <option value="">Все</option>
                            <option value="active">Активные</option>
                            <option value="checking">На проверке</option>
                            <option value="rejected">Отклонённые</option>
                            <option value="expired">Истекшие</option>
                        </select>
                    </div>
                    <div v-if="loadingEvents" style="color:var(--text-3);padding:24px;">Загрузка...</div>
                    <div v-else-if="!pendingEvents.length" class="card" style="margin-top:20px;">
                        <h3>Нет событий</h3>
                    </div>
                    <table v-else class="admin-table">
                        <thead><tr><th>Тип</th><th>Описание</th><th>Место</th><th>Действия</th></tr></thead>
                        <tbody>
                            <tr v-for="ev in pendingEvents" :key="ev.id" :class="{ 'is-done': ev._done }">
                                <td>{{ ev.type }}</td>
                                <td>{{ ev.title }}</td>
                                <td>{{ ev.location }}</td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button type="button" class="btn" style="font-size:11px;padding:4px 10px;min-height:auto;" @click="moderate(ev, 'approve')" :disabled="ev._done">Одобрить</button>
                                        <button type="button" class="btn outline" style="font-size:11px;padding:4px 10px;min-height:auto;" @click="moderate(ev, 'reject')" :disabled="ev._done">Отклонить</button>
                                        <button type="button" class="btn danger" style="font-size:11px;padding:4px 8px;min-height:auto;" @click="deleteEvent(ev)" :disabled="ev._done">Удалить</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="activeTab === 'dictionaries'" class="dictionary-panel">
                    <div class="dictionary-layout">
                        <aside class="dictionary-groups">
                            <button
                                v-for="group in dictionaryGroups"
                                :key="group.key"
                                type="button"
                                class="dictionary-group"
                                :class="{ 'is-active': dictionaryGroup === group.key }"
                                @click="selectDictionaryGroup(group.key)"
                            >
                                <strong>{{ group.label }}</strong>
                                <span>{{ group.hint }}</span>
                            </button>
                        </aside>

                        <div class="dictionary-main">
                            <div class="dictionary-head">
                                <div>
                                    <span class="badge">{{ currentDictionaryMeta.owner }}</span>
                                    <h2>{{ currentDictionaryMeta.label }}</h2>
                                    <p>{{ currentDictionaryMeta.description }}</p>
                                </div>
                            </div>

                            <form class="dictionary-form" @submit.prevent="saveDictionaryItem">
                                <input v-model="dictionaryForm.label" required placeholder="Новое значение">
                                <input v-model="dictionaryForm.description" placeholder="Короткое пояснение">
                                <input v-model.number="dictionaryForm.sort_order" type="number" min="0" max="65535" placeholder="Порядок">
                                <label class="dictionary-toggle">
                                    <input type="checkbox" v-model="dictionaryForm.is_active">
                                    <span>Активно</span>
                                </label>
                                <button type="submit" class="btn" :disabled="savingDictionary">
                                    {{ savingDictionary ? 'Сохраняем...' : (editingDictionaryItem ? 'Сохранить' : 'Добавить') }}
                                </button>
                                <button v-if="editingDictionaryItem" type="button" class="btn outline" @click="resetDictionaryForm">Отмена</button>
                            </form>

                            <div v-if="loadingDictionaries" style="color:var(--text-3);padding:24px;">Загрузка...</div>
                            <table v-else class="admin-table dictionary-table">
                                <thead>
                                    <tr>
                                        <th>Значение</th>
                                        <th>Где используется</th>
                                        <th>Порядок</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in currentDictionaryItems" :key="item.id">
                                        <td>
                                            <strong>{{ item.label }}</strong>
                                            <span v-if="item.value !== item.label" class="dictionary-value">{{ item.value }}</span>
                                        </td>
                                        <td style="font-size:12px;color:var(--text-2);">{{ item.description || currentDictionaryMeta.hint }}</td>
                                        <td style="font-family:var(--font-m);">{{ item.sort_order ?? 0 }}</td>
                                        <td>
                                            <span class="badge" :style="item.is_active ? 'background:var(--green);color:#fff' : ''">
                                                {{ item.is_active ? 'активно' : 'скрыто' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                <button type="button" class="btn outline dictionary-action" @click="editDictionaryItem(item)">Ред.</button>
                                                <button type="button" class="btn outline dictionary-action" @click="toggleDictionaryItem(item)">
                                                    {{ item.is_active ? 'Скрыть' : 'Вернуть' }}
                                                </button>
                                                <button type="button" class="btn danger dictionary-action" @click="deleteDictionaryItem(item)">Удалить</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();

const stats        = ref({});
const activeTab    = ref('analytics');

// Analytics
const analytics = ref(null);
const analyticsDays = ref(30);
const loadingAnalytics = ref(false);

// Users
const users         = ref([]);
const loadingUsers  = ref(true);
const userSearch    = ref('');
const userRoleFilter = ref('');
let userDebounce = null;

// POI
const pois          = ref([]);
const loadingPoi    = ref(false);
const poiStatusFilter = ref('moderation');

// News
const newsArticles   = ref([]);
const loadingNews    = ref(false);
const newsStatusFilter = ref('');

// Events
const pendingEvents  = ref([]);
const loadingEvents  = ref(true);
const eventStatusFilter = ref('');

// Dictionaries
const dictionaryItems = ref([]);
const loadingDictionaries = ref(false);
const savingDictionary = ref(false);
const dictionaryGroup = ref('vehicle_types');
const editingDictionaryItem = ref(null);
const dictionaryForm = ref({
    label: '',
    value: '',
    description: '',
    sort_order: 10,
    is_active: true,
});

const dictionaryGroups = [
    {
        key: 'vehicle_types',
        label: 'Типы транспорта',
        owner: 'Водители',
        hint: 'Профиль транспорта и построение маршрута',
        description: 'Варианты, которые водитель выбирает при добавлении транспортного средства.',
    },
    {
        key: 'cargo_types',
        label: 'Типы грузов',
        owner: 'Водители и логисты',
        hint: 'Груз маршрута и задания автопарка',
        description: 'Единые типы груза для расчёта ограничений и условий перевозки.',
    },
    {
        key: 'event_types',
        label: 'Типы событий',
        owner: 'Все пользователи',
        hint: 'Публикация и фильтрация дорожных событий',
        description: 'Категории происшествий, по которым работают лента, фильтры и уведомления.',
    },
    {
        key: 'poi_categories',
        label: 'Категории объектов',
        owner: 'Поставщики',
        hint: 'Карточки объектов и рекомендации по маршруту',
        description: 'Категории АЗС, стоянок, ночлега, сервиса и питания.',
    },
    {
        key: 'tags',
        label: 'Теги',
        owner: 'Редакция и поставщики',
        hint: 'Статьи и карточки объектов',
        description: 'Общие метки для поиска и группировки материалов и дорожных объектов.',
    },
];

const currentDictionaryMeta = computed(() =>
    dictionaryGroups.find((group) => group.key === dictionaryGroup.value) ?? dictionaryGroups[0]
);

const currentDictionaryItems = computed(() =>
    dictionaryItems.value.filter((item) => item.dictionary === dictionaryGroup.value)
);

const analyticsActivityMax = computed(() =>
    Math.max(1, ...(analytics.value?.audience?.series ?? []).map((point) => Number(point.value) || 0))
);

const tabs = computed(() => [
    { key: 'analytics', label: 'Аналитика' },
    { key: 'users',  label: 'Пользователи' },
    { key: 'poi',    label: 'Объекты', badge: stats.value.poi_pending || null },
    { key: 'news',   label: 'Новости' },
    { key: 'events', label: 'События', badge: stats.value.events_pending || null },
    { key: 'dictionaries', label: 'Справочники' },
]);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/v1/admin/stats');
        stats.value = data.data ?? data;
    } catch { /* ignore */ }
    await Promise.all([loadAnalytics(), loadUsers(), loadEvents()]);
});

watch(activeTab, (tab) => {
    if (tab === 'analytics' && !analytics.value) loadAnalytics();
    if (tab === 'poi' && !pois.value.length) loadPoi();
    if (tab === 'news' && !newsArticles.value.length) loadNews();
    if (tab === 'dictionaries' && !dictionaryItems.value.length) loadDictionaries();
});

async function loadAnalytics() {
    loadingAnalytics.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/analytics', {
            params: { days: analyticsDays.value },
        });
        analytics.value = data.data ?? data;
    } catch {
        ui.error('Не удалось загрузить аналитику');
    } finally {
        loadingAnalytics.value = false;
    }
}

function setAnalyticsPeriod(days) {
    if (analyticsDays.value === days && analytics.value) return;
    analyticsDays.value = days;
    loadAnalytics();
}

function formatPercent(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 1 })}%`;
}

function chartHeight(value, max) {
    if (!value) return '4px';
    return `${Math.max(18, Math.round((Number(value) / Math.max(1, max)) * 100))}%`;
}

function funnelWidth(value, total) {
    if (!total || !value) return '0%';
    return `${Math.max(4, Math.min(100, (Number(value) / Number(total)) * 100))}%`;
}

function formatDuration(minutes) {
    const total = Math.max(0, Number(minutes) || 0);
    const hours = Math.floor(total / 60);
    const rest = Math.round(total % 60);
    if (!hours) return `${rest} мин`;
    return rest ? `${hours} ч ${rest} мин` : `${hours} ч`;
}

function formatReadTime(minutes) {
    if (minutes === null || minutes === undefined) return 'нет данных';
    return `в среднем ${formatDuration(minutes)}`;
}

function formatAnalyticsDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: 'long',
    });
}

function shortAnalyticsDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
    });
}

function platformLabel(platform) {
    return { web: 'Сайт', ios: 'iOS', android: 'Android' }[platform] ?? platform;
}

// ── Users ──────────────────────────────────────────────────────────────
async function loadUsers() {
    loadingUsers.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/users', {
            params: { search: userSearch.value, role: userRoleFilter.value },
        });
        users.value = data.data ?? [];
    } catch { /* ignore */ } finally { loadingUsers.value = false; }
}

watch([userSearch, userRoleFilter], () => {
    clearTimeout(userDebounce);
    userDebounce = setTimeout(loadUsers, 350);
});

async function changeRole(user, role) {
    try {
        await axios.patch(`/api/v1/admin/users/${user.id}`, { role });
        user.role = role;
        ui.success('Роль обновлена');
    } catch { ui.error('Ошибка'); }
}

async function banUser(user) {
    if (!window.confirm(`Заблокировать ${user.name}?`)) return;
    try {
        await axios.post(`/api/v1/admin/users/${user.id}/ban`);
        user.status = 'banned';
        ui.success('Пользователь заблокирован');
    } catch { ui.error('Ошибка'); }
}

async function unbanUser(user) {
    try {
        await axios.post(`/api/v1/admin/users/${user.id}/unban`);
        user.status = 'active';
        ui.success('Пользователь разблокирован');
    } catch { ui.error('Ошибка'); }
}

// ── POI ────────────────────────────────────────────────────────────────
async function loadPoi() {
    loadingPoi.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/poi', {
            params: { status: poiStatusFilter.value },
        });
        pois.value = (data.data ?? []).map(p => ({ ...p, _done: false }));
    } catch { /* ignore */ } finally { loadingPoi.value = false; }
}

async function approvePoi(p) {
    try {
        await axios.post(`/api/v1/admin/poi/${p.id}/approve`);
        p.status = 'active'; p.verified = true; p._done = true;
        ui.success('Объект одобрен');
    } catch { ui.error('Ошибка'); }
}

async function rejectPoi(p) {
    try {
        await axios.post(`/api/v1/admin/poi/${p.id}/reject`);
        p.status = 'rejected'; p._done = true;
        ui.success('Объект отклонён');
    } catch { ui.error('Ошибка'); }
}

async function deletePoi(p) {
    if (!window.confirm('Удалить объект?')) return;
    try {
        await axios.delete(`/api/v1/admin/poi/${p.id}`);
        pois.value = pois.value.filter(x => x.id !== p.id);
        ui.success('Удалён');
    } catch { ui.error('Ошибка'); }
}

// ── News ───────────────────────────────────────────────────────────────
async function loadNews() {
    loadingNews.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/news', {
            params: { status: newsStatusFilter.value },
        });
        newsArticles.value = data.data ?? [];
    } catch { /* ignore */ } finally { loadingNews.value = false; }
}

async function deleteNews(a) {
    if (!window.confirm('Удалить статью?')) return;
    try {
        await axios.delete(`/api/v1/news/${a.id}`);
        newsArticles.value = newsArticles.value.filter(x => x.id !== a.id);
        ui.success('Статья удалена');
    } catch { ui.error('Ошибка'); }
}

// ── Events ─────────────────────────────────────────────────────────────
async function loadEvents() {
    loadingEvents.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/events', {
            params: { status: eventStatusFilter.value, per_page: 100 },
        });
        pendingEvents.value = (data.data ?? data ?? []).map(e => ({ ...e, _done: false }));
    } catch { /* ignore */ } finally { loadingEvents.value = false; }
}

async function moderate(ev, action) {
    try {
        const { data } = await axios.post(`/api/v1/admin/events/${ev.id}/${action}`);
        ev._done = true; ev.status = action === 'approve' ? 'active' : 'rejected';
        ui.success(data.message ?? 'Готово');
    } catch { ui.error('Ошибка'); }
}

async function deleteEvent(ev) {
    if (!window.confirm('Удалить событие?')) return;
    try {
        await axios.delete(`/api/v1/admin/events/${ev.id}`);
        pendingEvents.value = pendingEvents.value.filter(x => x.id !== ev.id);
        ui.success('Событие удалено');
    } catch { ui.error('Ошибка'); }
}

async function loadDictionaries() {
    loadingDictionaries.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/dictionaries');
        dictionaryItems.value = data.data ?? [];
    } catch {
        ui.error('Не удалось загрузить справочники');
    } finally {
        loadingDictionaries.value = false;
    }
}

function selectDictionaryGroup(group) {
    dictionaryGroup.value = group;
    resetDictionaryForm();
}

function resetDictionaryForm() {
    editingDictionaryItem.value = null;
    const nextOrder = Math.max(
        0,
        ...currentDictionaryItems.value.map((item) => Number(item.sort_order) || 0)
    ) + 10;
    dictionaryForm.value = {
        label: '',
        value: '',
        description: '',
        sort_order: nextOrder,
        is_active: true,
    };
}

function editDictionaryItem(item) {
    editingDictionaryItem.value = item;
    dictionaryForm.value = {
        label: item.label,
        value: item.value,
        description: item.description ?? '',
        sort_order: item.sort_order ?? 0,
        is_active: item.is_active,
    };
}

async function saveDictionaryItem() {
    savingDictionary.value = true;
    try {
        const payload = {
            dictionary: dictionaryGroup.value,
            label: dictionaryForm.value.label.trim(),
            value: editingDictionaryItem.value
                ? dictionaryForm.value.value
                : dictionaryForm.value.label.trim(),
            description: dictionaryForm.value.description.trim() || null,
            sort_order: Number(dictionaryForm.value.sort_order) || 0,
            is_active: Boolean(dictionaryForm.value.is_active),
        };

        if (editingDictionaryItem.value) {
            const { data } = await axios.patch(
                `/api/v1/admin/dictionaries/${editingDictionaryItem.value.id}`,
                payload
            );
            const updated = data.data ?? data;
            dictionaryItems.value = dictionaryItems.value.map((item) =>
                item.id === updated.id ? updated : item
            );
            ui.success('Значение обновлено');
        } else {
            const { data } = await axios.post('/api/v1/admin/dictionaries', payload);
            dictionaryItems.value.push(data.data ?? data);
            ui.success('Значение добавлено');
        }

        resetDictionaryForm();
    } catch (error) {
        ui.error(
            error.response?.data?.message
            ?? Object.values(error.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Не удалось сохранить значение'
        );
    } finally {
        savingDictionary.value = false;
    }
}

async function toggleDictionaryItem(item) {
    try {
        const { data } = await axios.patch(`/api/v1/admin/dictionaries/${item.id}`, {
            is_active: !item.is_active,
        });
        const updated = data.data ?? data;
        dictionaryItems.value = dictionaryItems.value.map((entry) =>
            entry.id === updated.id ? updated : entry
        );
    } catch {
        ui.error('Не удалось изменить статус');
    }
}

async function deleteDictionaryItem(item) {
    if (!window.confirm(`Удалить «${item.label}» из справочника? Ранее созданные записи останутся без изменений.`)) return;

    try {
        await axios.delete(`/api/v1/admin/dictionaries/${item.id}`);
        dictionaryItems.value = dictionaryItems.value.filter((entry) => entry.id !== item.id);
        if (editingDictionaryItem.value?.id === item.id) resetDictionaryForm();
        ui.success('Значение удалено');
    } catch {
        ui.error('Не удалось удалить значение');
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
</script>

<style scoped>
.admin-hero {
    padding: 112px 0 24px;
}

.admin-hero__inner {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border);
}

.admin-hero__copy > span {
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.admin-hero h1 {
    margin: 10px 0 0;
    font-size: clamp(38px, 5vw, 68px);
    line-height: .95;
}

.admin-hero p {
    max-width: 660px;
    margin-top: 16px;
    color: var(--text-2);
    font-size: 14px;
    line-height: 1.6;
}

.admin-hero__scope {
    display: grid;
    grid-template-columns: repeat(2, auto);
    gap: 8px 24px;
    padding-left: 28px;
    border-left: 1px solid var(--border);
    color: var(--text-3);
    font-size: 11px;
}

.admin-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 0; }

.admin-tab {
    padding: 8px 16px;
    background: none;
    border: 1px solid transparent;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-2);
    min-height: auto;
    box-shadow: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color .15s, background .15s;
    position: relative;
    bottom: -1px;
}
.admin-tab:hover { color: var(--text); background: var(--hover-tint); transform: none; }
.admin-tab.is-active { background: var(--bg); border-color: var(--border); color: var(--text); font-weight: 500; }

.tab-badge {
    background: var(--red);
    color: #fff;
    border-radius: 10px;
    font-size: 10px;
    font-family: var(--font-m);
    padding: 1px 6px;
    min-width: 18px;
    text-align: center;
}

.admin-toolbar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.admin-search {
    flex: 1;
    min-width: 200px;
    max-width: 360px;
}

.admin-toolbar input,
.admin-toolbar select,
.admin-search,
.inline-select {
    height: 40px;
    min-height: 40px;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    outline: none;
    background: var(--s1);
    color: var(--text);
    font: inherit;
    font-size: 12px;
    box-shadow: none;
}

.admin-toolbar input::placeholder,
.admin-search::placeholder {
    color: var(--text-3);
}

.admin-toolbar input:focus,
.admin-toolbar select:focus,
.admin-search:focus,
.inline-select:focus {
    border-color: var(--border-a);
    box-shadow: 0 0 0 3px var(--accent-bg);
}

.admin-toolbar select,
.inline-select {
    min-width: 130px;
    cursor: pointer;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.admin-table th {
    text-align: left;
    padding: 8px 12px;
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--text-3);
    border-bottom: 2px solid var(--border);
    font-weight: 500;
}
.admin-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.admin-table tr:hover td { background: var(--hover-tint); }
.admin-table .is-done { opacity: .4; }
.admin-table .is-banned td { color: var(--text-3); }

.inline-select {
    height: 34px;
    min-height: 34px;
    padding: 0 9px;
}

.analytics-panel {
    display: grid;
    gap: 18px;
}

.analytics-toolbar,
.analytics-section,
.analytics-savings {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.analytics-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: flex-start;
    padding: 18px;
}

.analytics-eyebrow,
.analytics-section-head span,
.analytics-savings span,
.analytics-kpis span,
.analytics-split span {
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .07em;
    text-transform: uppercase;
}

.analytics-toolbar h2,
.analytics-section h3,
.analytics-savings h3 {
    margin: 6px 0 0;
    color: var(--text);
    font-family: var(--font-d);
    font-weight: 400;
    letter-spacing: 0;
}

.analytics-toolbar h2 { font-size: clamp(26px, 3vw, 38px); }
.analytics-section h3,
.analytics-savings h3 { font-size: 24px; }

.analytics-toolbar p,
.analytics-savings p {
    margin: 8px 0 0;
    max-width: 560px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
}

.analytics-periods {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.analytics-periods button {
    min-height: 34px;
    padding: 0 12px;
    border-radius: 6px;
    background: transparent;
    color: var(--text-2);
    font-size: 12px;
    box-shadow: none;
}

.analytics-periods button:hover {
    transform: none;
    background: var(--hover-tint);
    color: var(--text);
}

.analytics-periods button.is-active {
    border-color: var(--border-a);
    background: var(--accent);
    color: var(--accent-text);
}

.analytics-loading {
    padding: 26px;
    color: var(--text-3);
}

.analytics-kpis {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: visible;
}

.analytics-kpis div {
    position: relative;
    min-width: 0;
    padding: 16px;
    border-right: 1px solid var(--border);
    background: color-mix(in srgb, var(--text) 2%, transparent);
}

.analytics-kpis div:last-child { border-right: 0; }

.analytics-metric-label,
.analytics-ratio {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.analytics-info {
    position: relative;
    display: inline-grid;
    width: 17px;
    height: 17px;
    min-width: 17px;
    min-height: 17px;
    max-width: 17px;
    max-height: 17px;
    flex: 0 0 17px;
    place-items: center;
    padding: 0;
    border: 1px solid color-mix(in srgb, var(--text-2) 60%, transparent);
    border-radius: 50%;
    background: transparent;
    color: var(--text-2);
    font-family: var(--font-m);
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    cursor: help;
    box-shadow: none;
    box-sizing: border-box;
}

.analytics-info:hover,
.analytics-info:focus-visible {
    border-color: var(--accent);
    background: transparent;
    color: var(--accent);
    transform: none;
}

.analytics-info::after {
    position: absolute;
    z-index: 20;
    bottom: calc(100% + 9px);
    left: 50%;
    width: max-content;
    max-width: min(300px, 72vw);
    padding: 9px 11px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--s2);
    color: var(--text);
    content: attr(data-tooltip);
    font-family: var(--font-s);
    font-size: 12px;
    font-weight: 400;
    line-height: 1.45;
    letter-spacing: 0;
    text-align: left;
    text-transform: none;
    white-space: normal;
    opacity: 0;
    pointer-events: none;
    transform: translate(-50%, 4px);
    visibility: hidden;
}

.analytics-info:hover::after,
.analytics-info:focus-visible::after {
    opacity: 1;
    transform: translate(-50%, 0);
    visibility: visible;
}

.analytics-metric-label .analytics-info::after {
    right: auto;
    left: 0;
    transform: translateY(4px);
}

.analytics-metric-label .analytics-info:hover::after,
.analytics-metric-label .analytics-info:focus-visible::after {
    transform: translateY(0);
}

.analytics-ratio .analytics-info::after {
    right: 0;
    left: auto;
    transform: translateY(4px);
}

.analytics-ratio .analytics-info:hover::after,
.analytics-ratio .analytics-info:focus-visible::after {
    transform: translateY(0);
}

.analytics-kpis strong {
    display: block;
    margin-top: 9px;
    color: var(--accent);
    font-family: var(--font-d);
    font-size: clamp(26px, 3.2vw, 42px);
    font-weight: 400;
    line-height: 1;
}

.analytics-kpis small {
    display: block;
    margin-top: 7px;
    color: var(--text-2);
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(320px, .8fr);
    gap: 14px;
}

.analytics-grid--compact {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.analytics-section,
.analytics-savings {
    min-width: 0;
    padding: 18px;
}

.analytics-section-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.analytics-section-head b {
    color: var(--accent);
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}

.analytics-chart {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12px, 1fr));
    gap: 5px;
    height: 230px;
    align-items: stretch;
    padding-top: 20px;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0,
        transparent 49px,
        color-mix(in srgb, var(--border) 58%, transparent) 50px
    );
}

.analytics-chart-column {
    display: grid;
    grid-template-rows: minmax(0, 1fr) 30px;
    gap: 7px;
    min-width: 0;
    height: 100%;
}

.analytics-chart-bar {
    display: flex;
    min-height: 0;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
}

.analytics-chart-bar em {
    margin-bottom: 5px;
    color: var(--text-2);
    font-family: var(--font-m);
    font-size: 10px;
    font-style: normal;
    line-height: 1;
}

.analytics-chart-bar span {
    width: 100%;
    max-width: 22px;
    min-height: 4px;
    border-radius: 999px 999px 2px 2px;
    background: var(--accent);
    opacity: .92;
}

.analytics-chart-bar span.is-empty {
    background: color-mix(in srgb, var(--text-3) 42%, transparent);
}

.analytics-chart-column small {
    align-self: start;
    justify-self: center;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 9px;
    writing-mode: vertical-rl;
}

.analytics-platforms {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.analytics-platforms span {
    padding: 5px 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-2);
    font-size: 11px;
}

.analytics-platforms b { color: var(--text); }

.analytics-funnel {
    display: grid;
    gap: 14px;
    padding-top: 18px;
}

.analytics-funnel div {
    display: grid;
    gap: 7px;
}

.analytics-funnel span {
    color: var(--text-3);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.analytics-funnel strong {
    color: var(--text);
    font-family: var(--font-d);
    font-size: 32px;
    font-weight: 400;
    line-height: 1;
}

.analytics-funnel i {
    display: block;
    height: 6px;
    border-radius: 999px;
    background: var(--accent);
}

.analytics-split {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    padding-top: 16px;
}

.analytics-split div {
    min-width: 0;
    padding: 12px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
}

.analytics-split strong {
    display: block;
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 30px;
    font-weight: 400;
}

.analytics-split span {
    display: block;
    margin-top: 5px;
}

.analytics-savings {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, .9fr);
    gap: 18px;
    align-items: center;
}

.analytics-savings-values {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.analytics-savings-values div {
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 34%, transparent);
}

.analytics-savings-values strong {
    display: block;
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 34px;
    font-weight: 400;
    line-height: 1;
}

.analytics-savings-values span,
.analytics-savings-values small {
    display: block;
}

.analytics-savings-values span {
    margin-top: 8px;
    color: var(--text);
    font-size: 13px;
    letter-spacing: 0;
    text-transform: none;
    font-family: inherit;
}

.analytics-savings-values small {
    margin-top: 6px;
    color: var(--text-3);
    font-size: 11px;
    line-height: 1.4;
}

.dictionary-layout {
    display: grid;
    grid-template-columns: minmax(190px, 230px) minmax(0, 1fr);
    gap: 34px;
}

.dictionary-groups {
    border-right: 1px solid var(--border);
    padding-right: 18px;
}

.dictionary-group {
    width: 100%;
    min-height: 0;
    padding: 12px 10px;
    border: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    color: var(--text-2);
    text-align: left;
    white-space: normal;
    overflow: hidden;
}

.dictionary-group:hover {
    background: var(--hover-tint);
    color: var(--text);
    transform: none;
}

.dictionary-group.is-active {
    color: var(--text);
    box-shadow: inset 2px 0 0 var(--accent);
    background: var(--hover-tint);
}

.dictionary-group strong,
.dictionary-group span {
    display: block;
}

.dictionary-group strong {
    font-size: 13px;
    font-weight: 600;
}

.dictionary-group span {
    margin-top: 4px;
    color: var(--text-3);
    font-size: 10px;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.dictionary-head {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
}

.dictionary-head h2 {
    margin: 10px 0 5px;
    font-size: 24px;
}

.dictionary-head p {
    max-width: 720px;
    color: var(--text-2);
    font-size: 13px;
}

.dictionary-form {
    display: grid;
    grid-template-columns: minmax(150px, 1fr) minmax(220px, 1.5fr) 90px auto auto auto;
    gap: 8px;
    align-items: center;
    padding: 18px 0;
    border-bottom: 1px solid var(--border);
}

.dictionary-form > input {
    min-width: 0;
    height: 40px;
    padding: 0 11px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--s1);
    color: var(--text);
    font: inherit;
    font-size: 12px;
}

.dictionary-form > input::placeholder {
    color: var(--text-3);
}

.dictionary-form > input:focus {
    outline: none;
    border-color: var(--border-a);
    box-shadow: 0 0 0 3px var(--accent-bg);
}

.dictionary-toggle {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 4px;
    color: var(--text-2);
    font-size: 12px;
    white-space: nowrap;
}

.dictionary-value {
    display: block;
    margin-top: 3px;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
}

.dictionary-action {
    min-height: 0;
    padding: 4px 8px;
    font-size: 11px;
}

@media (max-width: 980px) {
    .analytics-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .analytics-kpis div {
        border-bottom: 1px solid var(--border);
    }

    .analytics-grid,
    .analytics-grid--compact,
    .analytics-savings {
        grid-template-columns: 1fr;
    }

    .dictionary-layout {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .dictionary-groups {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-right: 0;
        padding-right: 0;
    }

    .dictionary-form {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .admin-hero {
        padding-top: 94px;
    }

    .admin-hero__inner {
        display: grid;
    }

    .admin-hero__scope {
        padding-top: 18px;
        padding-left: 0;
        border-top: 1px solid var(--border);
        border-left: 0;
    }

    .analytics-toolbar {
        display: grid;
    }

    .analytics-periods {
        justify-content: flex-start;
    }

    .analytics-kpis,
    .analytics-savings-values,
    .analytics-split {
        grid-template-columns: 1fr;
    }

    .analytics-kpis div {
        border-right: 0;
    }

    .analytics-section-head {
        display: grid;
    }

    .dictionary-groups,
    .dictionary-form {
        grid-template-columns: 1fr;
    }

    .dictionary-table {
        display: block;
        overflow-x: auto;
    }
}
</style>
