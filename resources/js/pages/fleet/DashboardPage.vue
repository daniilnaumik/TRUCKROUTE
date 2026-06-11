<template>
    <div>
        <div class="fleet-shell">
        <section class="page-hero fleet-hero">
            <div class="container">
                <div class="fleet-hero__copy">
                    <span class="badge">Кабинет автопарка</span>
                    <h1>Автопарк</h1>
                    <p class="lead">
                        Управление водителями, заданиями, рейтингами и данными автопарка в одном месте.
                    </p>
                    <div class="actions">
                        <button class="btn" type="button" @click="openCreateFleet">Создать автопарк</button>
                    </div>
                </div>

                <article v-if="selectedFleet" class="fleet-identity-card">
                    <label class="fleet-avatar" :class="{ 'is-editable': canManageSelectedFleet }">
                        <img v-if="selectedFleet.avatar_url" :src="selectedFleet.avatar_url" :alt="selectedFleet.name">
                        <span v-else>{{ fleetInitials(selectedFleet) }}</span>
                        <input
                            v-if="canManageSelectedFleet"
                            type="file"
                            accept="image/*"
                            @change="uploadFleetAvatar"
                        >
                    </label>
                    <div class="fleet-identity-card__body">
                        <span class="fleet-identity-card__eyebrow">
                            {{ selectedFleet.is_owner ? 'Вы владелец' : 'Вы сотрудник' }}
                        </span>
                        <h2>{{ selectedFleet.name }}</h2>
                        <p>{{ selectedFleet.description || 'Описание автопарка пока не заполнено.' }}</p>
                        <div class="fleet-identity-card__meta">
                            <span v-if="selectedFleet.base_city">{{ selectedFleet.base_city }}</span>
                            <span v-if="selectedFleet.inn">ИНН {{ selectedFleet.inn }}</span>
                            <span v-if="selectedFleet.phone">{{ selectedFleet.phone }}</span>
                        </div>
                    </div>
                </article>

                <article v-else class="fleet-identity-card fleet-identity-card--empty">
                    <div class="fleet-avatar"><span>TR</span></div>
                    <div class="fleet-identity-card__body">
                        <span class="fleet-identity-card__eyebrow">Начало работы</span>
                        <h2>Создайте первый автопарк</h2>
                        <p>После создания здесь появятся водители, задания и статистика выполнения.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="section-tight fleet-section">
            <div class="container">
                <div v-if="loading" class="fleet-loading">Загрузка...</div>

                <div v-else-if="!fleets.length" class="fleet-empty">
                    <span class="badge">пусто</span>
                    <h3>Автопарков пока нет</h3>
                    <p>Создайте автопарк, добавьте водителей по ID и выдавайте им задания.</p>
                    <div class="actions">
                        <button class="btn" type="button" @click="openCreateFleet">Создать автопарк</button>
                    </div>
                </div>

                <div v-else class="fleet-workspace">
                    <aside class="fleet-sidebar">
                        <div class="fleet-sidebar__head">
                            <span>Автопарки</span>
                            <button type="button" @click="openCreateFleet">+</button>
                        </div>
                        <button
                            v-for="fleet in fleets"
                            :key="fleet.id"
                            type="button"
                            class="fleet-switcher"
                            :class="{ 'is-active': selectedFleet?.id === fleet.id }"
                            @click="selectFleet(fleet)"
                        >
                            <span class="fleet-switcher__avatar">
                                <img v-if="fleet.avatar_url" :src="fleet.avatar_url" :alt="fleet.name">
                                <span v-else>{{ fleetInitials(fleet) }}</span>
                            </span>
                            <span class="fleet-switcher__body">
                                <strong>{{ fleet.name }}</strong>
                                <small>{{ fleet.drivers_count ?? 0 }} вод. · {{ fleet.assignments_count ?? 0 }} зад.</small>
                            </span>
                        </button>
                    </aside>

                    <main class="fleet-main">
                        <div v-if="workspaceLoading" class="fleet-loading">Обновляем данные...</div>

                        <template v-else-if="selectedFleet">
                            <div class="fleet-toolbar">
                                <div>
                                    <span class="badge">{{ canManageSelectedFleet ? 'управление' : 'просмотр' }}</span>
                                    <h2>{{ selectedFleet.name }}</h2>
                                </div>
                                <div class="fleet-toolbar__actions">
                                    <button class="btn outline" type="button" @click="reloadWorkspace">Обновить</button>
                                </div>
                            </div>

                            <div class="fleet-kpis">
                                <div>
                                    <span>Водители</span>
                                    <strong>{{ drivers.length }}</strong>
                                </div>
                                <div>
                                    <span>Активные задания</span>
                                    <strong>{{ activeAssignmentsCount }}</strong>
                                </div>
                                <div>
                                    <span>Выполнено</span>
                                    <strong>{{ completedAssignments.length }}</strong>
                                </div>
                                <div>
                                    <span>Средний рейтинг</span>
                                    <strong>{{ averageFleetRating || '—' }}</strong>
                                </div>
                            </div>

                            <div class="fleet-tabs">
                                <button type="button" :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">Информация</button>
                                <button type="button" :class="{ active: activeTab === 'drivers' }" @click="activeTab = 'drivers'">Водители</button>
                                <button type="button" :class="{ active: activeTab === 'vehicles' }" @click="activeTab = 'vehicles'">Транспорт</button>
                                <button type="button" :class="{ active: activeTab === 'assignments' }" @click="activeTab = 'assignments'">Задания</button>
                                <button type="button" :class="{ active: activeTab === 'ratings' }" @click="activeTab = 'ratings'">Оценки</button>
                            </div>

                            <section v-if="activeTab === 'overview'" class="fleet-panel-grid">
                                <article class="fleet-form-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Профиль</span>
                                            <h3>Карточка автопарка</h3>
                                        </div>
                                        <button
                                            v-if="canManageSelectedFleet"
                                            class="btn outline fleet-compact-btn"
                                            type="button"
                                            @click="editingFleet ? cancelFleetEdit() : editingFleet = true"
                                        >
                                            {{ editingFleet ? 'Отмена' : 'Изменить' }}
                                        </button>
                                    </div>

                                    <div v-if="!editingFleet" class="fleet-profile-view">
                                        <div>
                                            <span>Название</span>
                                            <strong>{{ selectedFleet.name }}</strong>
                                        </div>
                                        <div v-if="selectedFleet.inn">
                                            <span>ИНН</span>
                                            <strong>{{ selectedFleet.inn }}</strong>
                                        </div>
                                        <div v-if="selectedFleet.base_city">
                                            <span>Город базы</span>
                                            <strong>{{ selectedFleet.base_city }}</strong>
                                        </div>
                                        <div v-if="selectedFleet.phone">
                                            <span>Телефон</span>
                                            <strong>{{ selectedFleet.phone }}</strong>
                                        </div>
                                        <div v-if="selectedFleet.address" class="fleet-profile-view__wide">
                                            <span>Адрес базы</span>
                                            <strong>{{ selectedFleet.address }}</strong>
                                        </div>
                                        <p v-if="selectedFleet.description" class="fleet-profile-view__wide">{{ selectedFleet.description }}</p>
                                    </div>

                                    <form v-else class="fleet-form" @submit.prevent="saveFleet">
                                        <div class="field">
                                            <label>Название</label>
                                            <input v-model="fleetForm.name" :disabled="!canManageSelectedFleet" required>
                                        </div>
                                        <div class="field">
                                            <label>ИНН</label>
                                            <input v-model="fleetForm.inn" :disabled="!canManageSelectedFleet">
                                        </div>
                                        <div class="field">
                                            <label>Телефон</label>
                                            <input v-model="fleetForm.phone" :disabled="!canManageSelectedFleet" placeholder="+375...">
                                        </div>
                                        <div class="field">
                                            <label>Город базы</label>
                                            <input v-model="fleetForm.base_city" :disabled="!canManageSelectedFleet" placeholder="Минск">
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Адрес базы</label>
                                            <input v-model="fleetForm.address" :disabled="!canManageSelectedFleet" placeholder="Адрес стоянки или офиса">
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Описание</label>
                                            <textarea v-model="fleetForm.description" :disabled="!canManageSelectedFleet" rows="4"></textarea>
                                        </div>
                                        <div v-if="canManageSelectedFleet" class="actions fleet-form__wide">
                                            <button class="btn" type="submit" :disabled="savingFleet">
                                                {{ savingFleet ? 'Сохраняем...' : 'Сохранить изменения' }}
                                            </button>
                                        </div>
                                    </form>
                                </article>

                                <article class="fleet-summary-card">
                                    <span class="fleet-card-kicker">Сводка</span>
                                    <h3>Что сейчас происходит</h3>
                                    <div class="fleet-status-list">
                                        <div>
                                            <span class="status-dot status-issued"></span>
                                            <strong>{{ statusCounts.issued }}</strong>
                                            <small>выдано</small>
                                        </div>
                                        <div>
                                            <span class="status-dot status-accepted"></span>
                                            <strong>{{ statusCounts.accepted + statusCounts.in_progress }}</strong>
                                            <small>в работе</small>
                                        </div>
                                        <div>
                                            <span class="status-dot status-completed"></span>
                                            <strong>{{ statusCounts.completed }}</strong>
                                            <small>выполнено</small>
                                        </div>
                                        <div>
                                            <span class="status-dot status-cancelled"></span>
                                            <strong>{{ statusCounts.cancelled }}</strong>
                                            <small>отменено</small>
                                        </div>
                                    </div>
                                    <p>
                                        Водители видят выданные им задания и данные автопарка. Владелец может менять профиль,
                                        добавлять сотрудников, выдавать рейсы и оценивать завершенные задания.
                                    </p>
                                </article>
                            </section>

                            <section v-if="activeTab === 'drivers'" class="fleet-panel-grid">
                                <article v-if="canManageSelectedFleet" class="fleet-form-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Сотрудник</span>
                                            <h3>Добавить водителя</h3>
                                        </div>
                                    </div>
                                    <form class="fleet-form" @submit.prevent="attachDriver">
                                        <div class="field">
                                            <label>ID пользователя</label>
                                            <input v-model.number="driverForm.user_id" type="number" min="1" required>
                                        </div>
                                        <div class="field">
                                            <label>Роль в автопарке</label>
                                            <select v-model="driverForm.role_in_fleet">
                                                <option value="driver">Водитель</option>
                                                <option value="dispatcher">Диспетчер</option>
                                            </select>
                                        </div>
                                        <div class="actions fleet-form__wide">
                                            <button class="btn" type="submit" :disabled="savingDriver">
                                                {{ savingDriver ? 'Добавляем...' : 'Добавить' }}
                                            </button>
                                        </div>
                                    </form>
                                </article>

                                <article class="fleet-list-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Команда</span>
                                            <h3>Список сотрудников</h3>
                                        </div>
                                    </div>
                                    <div v-if="!drivers.length" class="fleet-muted-box">
                                        Сотрудников пока нет.
                                    </div>
                                    <div v-else class="driver-list">
                                        <div v-for="driver in drivers" :key="driver.id" class="driver-row">
                                            <div class="driver-avatar">
                                                <img v-if="driver.avatar_url" :src="driver.avatar_url" :alt="driver.name">
                                                <span v-else>{{ userInitials(driver) }}</span>
                                            </div>
                                            <div class="driver-row__body">
                                                <strong>{{ driver.name }}</strong>
                                                <small>ID {{ driver.id }} · {{ roleInFleetLabel(driver.role_in_fleet) }}</small>
                                                <span>{{ driver.completed_assignments_count }} выполнено · {{ driver.rating_avg ? `★ ${driver.rating_avg}` : 'нет оценок' }}</span>
                                            </div>
                                            <div v-if="canManageSelectedFleet" class="driver-row__actions">
                                                <button
                                                    class="fleet-icon-btn"
                                                    type="button"
                                                    @click="openDriverHistory(driver)"
                                                >
                                                    История
                                                </button>
                                                <button
                                                    class="fleet-icon-btn danger"
                                                    type="button"
                                                    @click="detachDriver(driver)"
                                                >
                                                    Убрать
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </section>

                            <section v-if="activeTab === 'vehicles'" class="fleet-panel-grid">
                                <article v-if="canManageSelectedFleet" class="fleet-form-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Парк техники</span>
                                            <h3>Добавить фуру</h3>
                                        </div>
                                    </div>
                                    <form class="fleet-form" @submit.prevent="saveFleetVehicle">
                                        <div class="field">
                                            <label>Название</label>
                                            <input v-model="fleetVehicleForm.title" placeholder="Например: Volvo FH 460" required>
                                        </div>
                                        <div class="field">
                                            <label>Тип</label>
                                            <input v-model="fleetVehicleForm.type" placeholder="Тягач + полуприцеп" required>
                                        </div>
                                        <div class="field">
                                            <label>Модель</label>
                                            <input v-model="fleetVehicleForm.model" placeholder="Volvo FH">
                                        </div>
                                        <div class="field">
                                            <label>Топливо</label>
                                            <input v-model="fleetVehicleForm.fuel_type" placeholder="Дизель">
                                        </div>
                                        <div class="field">
                                            <label>Бак, л</label>
                                            <input v-model.number="fleetVehicleForm.tank_capacity_l" type="number" min="1" max="2000" required>
                                        </div>
                                        <div class="field">
                                            <label>Расход, л/100 км</label>
                                            <input v-model.number="fleetVehicleForm.consumption_l_per_100" type="number" min="1" max="100" step="0.1" required>
                                        </div>
                                        <div class="field">
                                            <label>Скорость, км/ч</label>
                                            <input v-model.number="fleetVehicleForm.cruise_speed_kmh" type="number" min="30" max="120">
                                        </div>
                                        <div class="field">
                                            <label>Масса, т</label>
                                            <input v-model.number="fleetVehicleForm.curb_weight_t" type="number" min="1" max="40" step="0.1">
                                        </div>
                                        <div class="actions fleet-form__wide">
                                            <button class="btn" type="submit" :disabled="savingFleetVehicle">
                                                {{ savingFleetVehicle ? 'Сохраняем...' : 'Добавить в автопарк' }}
                                            </button>
                                        </div>
                                    </form>
                                </article>

                                <article class="fleet-list-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Доступно для заданий</span>
                                            <h3>{{ fleetVehicles.length }} фур</h3>
                                        </div>
                                    </div>
                                    <div v-if="!fleetVehicles.length" class="fleet-muted-box">
                                        У автопарка пока нет собственного транспорта.
                                    </div>
                                    <div v-else class="fleet-vehicle-list">
                                        <article v-for="vehicle in fleetVehicles" :key="vehicle.id" class="fleet-vehicle-row">
                                            <div class="fleet-vehicle-row__icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M3 7h11v10H3z" />
                                                    <path d="M14 10h4l3 3v4h-7z" />
                                                    <circle cx="7" cy="18" r="2" />
                                                    <circle cx="18" cy="18" r="2" />
                                                </svg>
                                            </div>
                                            <div>
                                                <strong>{{ vehicle.title }}</strong>
                                                <span>{{ vehicle.type }}<template v-if="vehicle.model"> · {{ vehicle.model }}</template></span>
                                                <small>{{ vehicle.tank_capacity_l }} л · {{ vehicle.consumption_l_per_100 }} л/100 км</small>
                                            </div>
                                            <button
                                                v-if="canManageSelectedFleet"
                                                type="button"
                                                class="fleet-icon-btn danger"
                                                title="Удалить фуру"
                                                @click="deleteFleetVehicle(vehicle)"
                                            >
                                                Удалить
                                            </button>
                                        </article>
                                    </div>
                                </article>
                            </section>

                            <section v-show="activeTab === 'assignments'" class="fleet-panel-grid fleet-assignment-grid">
                                <article v-if="canManageSelectedFleet" class="fleet-form-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Маршрут</span>
                                            <h3>Новое задание</h3>
                                        </div>
                                    </div>
                                    <form class="fleet-form fleet-assignment-form" @submit.prevent="openAssignmentDriverPicker">
                                        <div class="field">
                                            <label>Водитель</label>
                                            <select v-model.number="assignmentForm.driver_user_id" @change="clearAssignmentError('driver_user_id')">
                                                <option value="">Выберите водителя</option>
                                                <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                                    {{ driver.name }} · ID {{ driver.id }}
                                                </option>
                                            </select>
                                            <FieldError :error="assignmentErrors.driver_user_id" />
                                        </div>
                                        <div class="field">
                                            <label>Плановый старт</label>
                                            <input
                                                :value="assignmentDateTimeText"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="16"
                                                placeholder="05.06.2026 20:00"
                                                @input="normalizeAssignmentPlannedStart"
                                            >
                                            <FieldError :error="assignmentErrors.planned_start_at" />
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Транспорт для рейса</label>
                                            <div class="fleet-vehicle-source">
                                                <button
                                                    type="button"
                                                    :class="{ active: assignmentForm.vehicle_source === 'driver' }"
                                                    @click="selectAssignmentVehicleSource('driver')"
                                                >
                                                    Личная фура водителя
                                                </button>
                                                <button
                                                    type="button"
                                                    :class="{ active: assignmentForm.vehicle_source === 'fleet' }"
                                                    @click="selectAssignmentVehicleSource('fleet')"
                                                >
                                                    Фура автопарка
                                                </button>
                                            </div>
                                            <select
                                                v-if="assignmentForm.vehicle_source === 'fleet'"
                                                v-model.number="assignmentForm.vehicle_id"
                                                class="fleet-vehicle-select"
                                                @change="clearAssignmentError('vehicle_id')"
                                            >
                                                <option value="">Выберите фуру автопарка</option>
                                                <option v-for="vehicle in fleetVehicles" :key="vehicle.id" :value="vehicle.id">
                                                    {{ vehicle.title }} · {{ vehicle.type }}
                                                </option>
                                            </select>
                                            <small v-else class="fleet-field-note">
                                                При принятии задания будет использована активная личная фура водителя.
                                            </small>
                                            <FieldError :error="assignmentErrors.vehicle_source || assignmentErrors.vehicle_id" />
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Откуда</label>
                                            <div class="fleet-point-field">
                                                <input
                                                    v-model="assignmentForm.origin"
                                                    required
                                                    placeholder="Брест, пункт погрузки"
                                                    @input="clearAssignmentPoint('origin')"
                                                >
                                                <button
                                                    type="button"
                                                    class="fleet-map-pick-btn"
                                                    :class="{ active: pickingField === 'origin' }"
                                                    @click="pickAssignmentPoint('origin')"
                                                >
                                                    A
                                                </button>
                                            </div>
                                            <FieldError :error="assignmentErrors.origin" />
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Куда</label>
                                            <div class="fleet-point-field">
                                                <input
                                                    v-model="assignmentForm.destination"
                                                    required
                                                    placeholder="Минск, пункт выгрузки"
                                                    @input="clearAssignmentPoint('destination')"
                                                >
                                                <button
                                                    type="button"
                                                    class="fleet-map-pick-btn"
                                                    :class="{ active: pickingField === 'destination' }"
                                                    @click="pickAssignmentPoint('destination')"
                                                >
                                                    B
                                                </button>
                                            </div>
                                            <FieldError :error="assignmentErrors.destination" />
                                        </div>
                                        <div class="field fleet-form__wide">
                                            <label>Комментарий</label>
                                            <textarea v-model="assignmentForm.comment" rows="3" placeholder="Документы, контакт, ограничения"></textarea>
                                        </div>
                                        <div class="actions fleet-form__wide">
                                            <button class="btn" type="submit" :disabled="savingAssignment">
                                                {{ savingAssignment ? 'Выдаем...' : 'Выдать задание' }}
                                            </button>
                                        </div>
                                    </form>
                                </article>

                                <article class="fleet-route-map-card">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Карта</span>
                                            <h3>{{ assignmentMapTitle }}</h3>
                                        </div>
                                        <div v-if="canManageSelectedFleet" class="fleet-map-actions">
                                            <button
                                                type="button"
                                                class="fleet-map-pick-btn"
                                                :class="{ active: pickingField === 'origin' }"
                                                @click="pickAssignmentPoint('origin')"
                                            >
                                                A
                                            </button>
                                            <button
                                                type="button"
                                                class="fleet-map-pick-btn"
                                                :class="{ active: pickingField === 'destination' }"
                                                @click="pickAssignmentPoint('destination')"
                                            >
                                                B
                                            </button>
                                        </div>
                                    </div>
                                    <div class="fleet-map-shell">
                                        <div
                                            ref="assignmentMapEl"
                                            class="fleet-route-map"
                                            :class="{ 'is-unavailable': assignmentMapUnavailable }"
                                        ></div>
                                        <div v-if="assignmentMapUnavailable" class="fleet-map-fallback">
                                            <strong>Карта не загрузилась</strong>
                                            <span>Проверьте подключение или ключ Яндекс.Карт и повторите загрузку.</span>
                                            <button type="button" class="btn outline" @click="retryAssignmentMap">
                                                Повторить
                                            </button>
                                        </div>
                                        <div v-if="!assignmentMapUnavailable" class="fleet-map-hint">
                                            {{ pickingField ? `Кликните по карте, чтобы поставить точку ${pickingField === 'origin' ? 'A' : 'B'}` : 'Точки A/B можно поставить кликом по карте' }}
                                        </div>
                                    </div>
                                    <p class="fleet-route-map-card__note">
                                        После выбора двух точек маршрут строится здесь же, а координаты сохраняются в задании.
                                    </p>
                                </article>

                                <article class="fleet-list-card fleet-list-card--wide">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Лента заданий</span>
                                            <h3>{{ assignments.length }} заданий</h3>
                                        </div>
                                        <select v-model="assignmentFilter" class="fleet-filter">
                                            <option value="">Все</option>
                                            <option value="issued">Выдано</option>
                                            <option value="accepted">Принято</option>
                                            <option value="in_progress">В работе</option>
                                            <option value="completed">Выполнено</option>
                                            <option value="cancelled">Отменено</option>
                                        </select>
                                    </div>
                                    <div v-if="!filteredAssignments.length" class="fleet-muted-box">
                                        Заданий с таким фильтром нет.
                                    </div>
                                    <div v-else class="assignment-list">
                                        <article
                                            v-for="assignment in filteredAssignments"
                                            :key="assignment.id"
                                            class="assignment-card"
                                            :class="{ 'is-expanded': expandedAssignmentId === assignment.id }"
                                            @click="openAssignmentDetails(assignment)"
                                        >
                                            <div class="assignment-card__top">
                                                <span class="assignment-status" :class="`assignment-status--${assignment.status}`">
                                                    {{ statusLabel(assignment.status) }}
                                                </span>
                                                <small>{{ formatDateTime(assignment.planned_start_at || assignment.created_at) }}</small>
                                            </div>
                                            <h4>{{ compactRoute(assignment.origin) }} → {{ compactRoute(assignment.destination) }}</h4>
                                            <p>{{ assignment.driver?.name || 'Водитель' }}</p>
                                            <p v-if="assignment.comment" class="assignment-card__comment">{{ assignment.comment }}</p>
                                            <div v-if="expandedAssignmentId === assignment.id" class="assignment-card__details">
                                                <div>
                                                    <span>Водитель</span>
                                                    <strong>{{ assignment.driver?.name || 'Не указан' }}</strong>
                                                </div>
                                                <div>
                                                    <span>Статус</span>
                                                    <strong>{{ statusLabel(assignment.status) }}</strong>
                                                </div>
                                                <div>
                                                    <span>Старт</span>
                                                    <strong>{{ formatDateTime(assignment.planned_start_at || assignment.created_at) }}</strong>
                                                </div>
                                                <div>
                                                    <span>Транспорт</span>
                                                    <strong>
                                                        {{ assignment.vehicle_source === 'fleet'
                                                            ? (assignment.vehicle?.title || 'Фура автопарка')
                                                            : 'Личная фура водителя' }}
                                                    </strong>
                                                </div>
                                                <div v-if="assignment.rating_stars">
                                                    <span>Оценка</span>
                                                    <strong>{{ stars(assignment.rating_stars) }}</strong>
                                                </div>
                                                <p class="assignment-card__route">
                                                    {{ assignment.origin }} → {{ assignment.destination }}
                                                </p>
                                                <p v-if="assignment.origin_point && assignment.destination_point" class="assignment-card__map-note">
                                                    Маршрут построен на карте выше.
                                                </p>
                                            </div>
                                            <div class="assignment-card__actions">
                                                <button
                                                    v-if="assignment.origin_point && assignment.destination_point"
                                                    type="button"
                                                    class="btn outline"
                                                    @click.stop="showAssignmentOnMap(assignment)"
                                                >
                                                    На карте
                                                </button>
                                                <button
                                                    v-if="canManageSelectedFleet && !['completed', 'cancelled'].includes(assignment.status)"
                                                    type="button"
                                                    class="btn outline"
                                                    @click.stop="cancelAssignment(assignment)"
                                                >
                                                    Отменить
                                                </button>
                                                <button
                                                    v-if="canManageSelectedFleet && assignment.status === 'completed'"
                                                    type="button"
                                                    class="btn"
                                                    @click.stop="openRating(assignment)"
                                                >
                                                    {{ assignment.rating_stars ? 'Изменить оценку' : 'Оценить' }}
                                                </button>
                                            </div>
                                        </article>
                                    </div>
                                </article>
                            </section>

                            <section v-if="activeTab === 'ratings'" class="fleet-panel-grid">
                                <article class="fleet-list-card fleet-list-card--wide">
                                    <div class="fleet-card-head">
                                        <div>
                                            <span class="fleet-card-kicker">Рейтинг</span>
                                            <h3>Выполненные задания</h3>
                                        </div>
                                    </div>
                                    <div v-if="!completedAssignments.length" class="fleet-muted-box">
                                        Выполненных заданий пока нет.
                                    </div>
                                    <div v-else class="rating-list">
                                        <article v-for="assignment in completedAssignments" :key="`rating-${assignment.id}`" class="rating-row">
                                            <div>
                                                <strong>{{ assignment.driver?.name || 'Водитель' }}</strong>
                                                <span>{{ compactRoute(assignment.origin) }} → {{ compactRoute(assignment.destination) }}</span>
                                            </div>
                                            <button
                                                v-if="canManageSelectedFleet"
                                                type="button"
                                                class="star-button"
                                                @click="openRating(assignment)"
                                            >
                                                {{ assignment.rating_stars ? stars(assignment.rating_stars) : 'Поставить оценку' }}
                                            </button>
                                            <span v-else class="star-readonly">{{ assignment.rating_stars ? stars(assignment.rating_stars) : 'Без оценки' }}</span>
                                        </article>
                                    </div>
                                </article>
                            </section>
                        </template>
                    </main>
                </div>
            </div>
        </section>
        </div>

        <Teleport to="body">
            <div v-if="showCreate" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="showCreate=false"></div>
                <div class="auth-modal__panel fleet-modal-panel">
                    <button class="auth-modal__close" @click="showCreate=false">закрыть</button>
                    <span class="badge">новый автопарк</span>
                    <h2>Создать автопарк</h2>
                    <form class="fleet-form" @submit.prevent="createFleet">
                        <div class="field">
                            <label>Название</label>
                            <input v-model="createForm.name" required>
                        </div>
                        <div class="field">
                            <label>ИНН</label>
                            <input v-model="createForm.inn">
                        </div>
                        <div class="field">
                            <label>Телефон</label>
                            <input v-model="createForm.phone">
                        </div>
                        <div class="field">
                            <label>Город базы</label>
                            <input v-model="createForm.base_city">
                        </div>
                        <div class="field fleet-form__wide">
                            <label>Адрес базы</label>
                            <input v-model="createForm.address">
                        </div>
                        <div class="field fleet-form__wide">
                            <label>Описание</label>
                            <textarea v-model="createForm.description" rows="3"></textarea>
                        </div>
                        <div class="actions fleet-form__wide">
                            <button type="submit" class="btn" :disabled="savingCreate">
                                {{ savingCreate ? 'Создаем...' : 'Создать' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showAssignmentDriverPicker" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="showAssignmentDriverPicker=false"></div>
                <div class="auth-modal__panel fleet-modal-panel fleet-driver-modal">
                    <button class="auth-modal__close" @click="showAssignmentDriverPicker=false">закрыть</button>
                    <span class="badge">выдача задания</span>
                    <h2>Кому выдать задание</h2>
                    <p class="fleet-modal-route">
                        {{ compactRoute(assignmentForm.origin) }} → {{ compactRoute(assignmentForm.destination) }}
                    </p>

                    <div class="fleet-driver-picker">
                        <button
                            v-for="driver in assignmentDriverOptions"
                            :key="driver.id"
                            type="button"
                            class="fleet-driver-option"
                            :class="{ 'is-selected': Number(assignmentForm.driver_user_id) === Number(driver.id) }"
                            @click="assignmentForm.driver_user_id = driver.id"
                        >
                            <span class="driver-avatar">
                                <img v-if="driver.avatar_url" :src="driver.avatar_url" :alt="driver.name">
                                <span v-else>{{ userInitials(driver) }}</span>
                            </span>
                            <span class="fleet-driver-option__body">
                                <strong>{{ driver.name }}</strong>
                                <small>ID {{ driver.id }} · {{ roleInFleetLabel(driver.role_in_fleet) }}</small>
                            </span>
                            <span class="fleet-driver-option__stats">
                                <strong>{{ driver.rating_avg ? `★ ${driver.rating_avg}` : 'нет рейтинга' }}</strong>
                                <small>{{ driver.completed_assignments_count || 0 }} выполнено</small>
                            </span>
                        </button>
                    </div>

                    <div class="actions fleet-driver-modal__actions">
                        <button class="btn outline" type="button" @click="showAssignmentDriverPicker=false">Назад</button>
                        <button
                            class="btn"
                            type="button"
                            :disabled="savingAssignment || !assignmentForm.driver_user_id"
                            @click="createAssignment"
                        >
                            {{ savingAssignment ? 'Выдаем...' : 'Выдать выбранному' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="ratingTarget" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="ratingTarget=null"></div>
                <div class="auth-modal__panel fleet-modal-panel">
                    <button class="auth-modal__close" @click="ratingTarget=null">закрыть</button>
                    <span class="badge">оценка задания</span>
                    <h2>{{ ratingTarget.driver?.name || 'Водитель' }}</h2>
                    <p class="fleet-modal-route">
                        {{ compactRoute(ratingTarget.origin) }} → {{ compactRoute(ratingTarget.destination) }}
                    </p>
                    <div class="fleet-rating-picker">
                        <button
                            v-for="n in 5"
                            :key="n"
                            type="button"
                            :class="{ active: ratingForm.rating_stars >= n }"
                            @click="ratingForm.rating_stars = n"
                        >
                            ★
                        </button>
                    </div>
                    <form class="fleet-form" @submit.prevent="saveRating">
                        <div class="field fleet-form__wide">
                            <label>Комментарий</label>
                            <textarea v-model="ratingForm.rating_comment" rows="3" placeholder="Как водитель выполнил задание"></textarea>
                        </div>
                        <div class="actions fleet-form__wide">
                            <button class="btn" type="submit" :disabled="savingRating">
                                {{ savingRating ? 'Сохраняем...' : 'Сохранить оценку' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="historyDriver" class="auth-modal is-open">
                <div class="auth-modal__backdrop" @click="closeDriverHistory"></div>
                <div class="auth-modal__panel fleet-modal-panel fleet-history-modal">
                    <button class="auth-modal__close" @click="closeDriverHistory">закрыть</button>
                    <span class="badge">история маршрутов</span>
                    <h2>{{ historyDriver.name }}</h2>
                    <p class="fleet-modal-route">
                        Маршруты доступны только с явного согласия водителя в настройках.
                    </p>

                    <div v-if="driverHistoryLoading" class="fleet-muted-box">Загружаем историю...</div>
                    <div v-else-if="!driverRoutes.length" class="fleet-muted-box">
                        У водителя пока нет сохраненных маршрутов.
                    </div>
                    <div v-else class="fleet-history-list">
                        <article v-for="route in driverRoutes" :key="route.id" class="fleet-history-row">
                            <div>
                                <strong>{{ compactRoute(route.origin?.label) }} → {{ compactRoute(route.destination?.label) }}</strong>
                                <span>
                                    {{ Math.round(Number(route.distance_km || 0)) }} км
                                    · {{ formatDateTime(route.start_time || route.created_at) }}
                                </span>
                            </div>
                            <button type="button" class="fleet-icon-btn" @click="openSharedRoute(route.id)">
                                Открыть
                            </button>
                        </article>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useWizardMap } from '@/composables/useWizardMap';
import FieldError from '@/components/FieldError.vue';
import { explainError } from '@/utils/errorHelpers';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();

const fleets = ref([]);
const selectedFleet = ref(null);
const drivers = ref([]);
const assignments = ref([]);
const fleetVehicles = ref([]);
const loading = ref(true);
const workspaceLoading = ref(false);
const activeTab = ref('overview');
const assignmentFilter = ref('');
const assignmentMapEl = ref(null);
const pickingField = ref(null);
const assignmentMapUnavailable = ref(false);
const assignmentMapTitle = ref('Маршрут задания');
const editingFleet = ref(false);
const expandedAssignmentId = ref(null);
const assignmentDateTimeText = ref('');

const showCreate = ref(false);
const showAssignmentDriverPicker = ref(false);
const savingCreate = ref(false);
const savingFleet = ref(false);
const savingDriver = ref(false);
const savingAssignment = ref(false);
const savingFleetVehicle = ref(false);
const savingRating = ref(false);
const ratingTarget = ref(null);
const historyDriver = ref(null);
const driverRoutes = ref([]);
const driverHistoryLoading = ref(false);

const createForm = reactive(emptyFleetForm());
const fleetForm = reactive(emptyFleetForm());
const driverForm = reactive({ user_id: '', role_in_fleet: 'driver' });
const assignmentForm = reactive({
    driver_user_id: '',
    vehicle_source: 'driver',
    vehicle_id: '',
    origin: '',
    origin_point: null,
    destination: '',
    destination_point: null,
    via_points: [],
    planned_start_at: '',
    comment: '',
});
const assignmentErrors = reactive({});
const fleetVehicleForm = reactive(emptyFleetVehicleForm());
const ratingForm = reactive({ rating_stars: 5, rating_comment: '' });

const {
    init: initAssignmentMap,
    mapError: assignmentMapError,
    refresh: refreshAssignmentMap,
    setOrigin: setAssignmentMapOrigin,
    setDestination: setAssignmentMapDestination,
    fetchAndDrawRoute: drawAssignmentRoute,
    clearRoute: clearAssignmentMapRoute,
    enableClickPicking,
    disableClickPicking,
} = useWizardMap(assignmentMapEl);

const canManageSelectedFleet = computed(() => {
    return !!selectedFleet.value && (selectedFleet.value.is_owner || auth.isAdmin);
});

const statusCounts = computed(() => {
    return assignments.value.reduce((acc, assignment) => {
        acc[assignment.status] = (acc[assignment.status] ?? 0) + 1;
        return acc;
    }, { issued: 0, accepted: 0, in_progress: 0, completed: 0, cancelled: 0 });
});

const activeAssignmentsCount = computed(() => {
    return assignments.value.filter((assignment) => ['issued', 'accepted', 'in_progress'].includes(assignment.status)).length;
});

const completedAssignments = computed(() => {
    return assignments.value.filter((assignment) => assignment.status === 'completed');
});

const averageFleetRating = computed(() => {
    const ratings = completedAssignments.value
        .map((assignment) => Number(assignment.rating_stars))
        .filter(Number.isFinite);

    if (!ratings.length) return '';
    const average = ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length;
    return average.toFixed(1);
});

const assignmentDriverOptions = computed(() => {
    return [...drivers.value].sort((a, b) => {
        const ratingDiff = Number(b.rating_avg || 0) - Number(a.rating_avg || 0);
        if (ratingDiff !== 0) return ratingDiff;

        const completedDiff = Number(b.completed_assignments_count || 0) - Number(a.completed_assignments_count || 0);
        if (completedDiff !== 0) return completedDiff;

        return String(a.name || '').localeCompare(String(b.name || ''), 'ru');
    });
});

const filteredAssignments = computed(() => {
    if (!assignmentFilter.value) return assignments.value;
    return assignments.value.filter((assignment) => assignment.status === assignmentFilter.value);
});

const expandedAssignment = computed(() => {
    return assignments.value.find((assignment) => assignment.id === expandedAssignmentId.value) ?? null;
});

onMounted(async () => {
    await loadFleets();
});

watch(activeTab, async (tab) => {
    if (tab === 'assignments') {
        await ensureAssignmentMap();
        syncDraftAssignmentRoute();
        return;
    }

    disableClickPicking();
    pickingField.value = null;
});

function emptyFleetForm() {
    return {
        name: '',
        inn: '',
        phone: '',
        base_city: '',
        address: '',
        description: '',
        avatar: '',
    };
}

function emptyFleetVehicleForm() {
    return {
        title: '',
        type: 'Тягач + полуприцеп',
        model: '',
        fuel_type: 'Дизель',
        allowed_fuel: 'Дизель + AdBlue',
        tank_capacity_l: 600,
        consumption_l_per_100: 29,
        cruise_speed_kmh: 85,
        curb_weight_t: 15.5,
        restrictions: '',
        is_active: true,
    };
}

function assignForm(target, source = {}) {
    Object.assign(target, {
        name: source.name ?? '',
        inn: source.inn ?? '',
        phone: source.phone ?? '',
        base_city: source.base_city ?? '',
        address: source.address ?? '',
        description: source.description ?? '',
        avatar: source.avatar ?? '',
    });
}

async function loadFleets() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/fleets');
        fleets.value = data.data ?? [];
        if (fleets.value.length) {
            await selectFleet(fleets.value[0]);
        }
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось загрузить автопарки');
    } finally {
        loading.value = false;
    }
}

async function selectFleet(fleet) {
    selectedFleet.value = fleet;
    editingFleet.value = false;
    expandedAssignmentId.value = null;
    assignForm(fleetForm, fleet);
    resetAssignmentForm();
    clearAssignmentMap();
    await reloadWorkspace();
}

async function reloadWorkspace() {
    if (!selectedFleet.value) return;
    workspaceLoading.value = true;
    try {
        const [fleetRes, driversRes, assignmentsRes, vehiclesRes] = await Promise.all([
            axios.get(`/api/v1/fleets/${selectedFleet.value.id}`),
            axios.get(`/api/v1/fleets/${selectedFleet.value.id}/drivers`),
            axios.get(`/api/v1/fleets/${selectedFleet.value.id}/assignments`),
            axios.get(`/api/v1/fleets/${selectedFleet.value.id}/vehicles`),
        ]);
        const freshFleet = fleetRes.data.data ?? fleetRes.data;
        selectedFleet.value = freshFleet;
        assignForm(fleetForm, freshFleet);
        replaceFleet(freshFleet);
        drivers.value = driversRes.data.data ?? [];
        assignments.value = assignmentsRes.data.data ?? [];
        fleetVehicles.value = vehiclesRes.data.data ?? [];
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось обновить автопарк');
    } finally {
        workspaceLoading.value = false;
    }
}

function replaceFleet(fleet) {
    const index = fleets.value.findIndex((item) => item.id === fleet.id);
    if (index >= 0) fleets.value.splice(index, 1, fleet);
    else fleets.value.unshift(fleet);
}

function openCreateFleet() {
    assignForm(createForm, {});
    showCreate.value = true;
}

function cancelFleetEdit() {
    assignForm(fleetForm, selectedFleet.value ?? {});
    editingFleet.value = false;
}

async function createFleet() {
    savingCreate.value = true;
    try {
        const { data } = await axios.post('/api/v1/fleets', normalizedFleetPayload(createForm));
        const fleet = data.data ?? data;
        fleets.value.unshift(fleet);
        showCreate.value = false;
        ui.success('Автопарк создан');
        await selectFleet(fleet);
    } catch (error) {
        ui.error(error.response?.data?.message ?? firstError(error) ?? 'Не удалось создать автопарк');
    } finally {
        savingCreate.value = false;
    }
}

async function saveFleet() {
    if (!selectedFleet.value || !canManageSelectedFleet.value) return;
    savingFleet.value = true;
    try {
        const { data } = await axios.patch(`/api/v1/fleets/${selectedFleet.value.id}`, normalizedFleetPayload(fleetForm));
        const fleet = data.data ?? data;
        selectedFleet.value = fleet;
        editingFleet.value = false;
        assignForm(fleetForm, fleet);
        replaceFleet(fleet);
        ui.success('Автопарк обновлен');
    } catch (error) {
        ui.error(error.response?.data?.message ?? firstError(error) ?? 'Не удалось сохранить автопарк');
    } finally {
        savingFleet.value = false;
    }
}

function normalizedFleetPayload(form) {
    return {
        name: form.name,
        inn: form.inn || null,
        phone: form.phone || null,
        base_city: form.base_city || null,
        address: form.address || null,
        description: form.description || null,
        avatar: form.avatar || null,
    };
}

async function uploadFleetAvatar(event) {
    const file = event.target.files?.[0];
    if (!file || !selectedFleet.value || !canManageSelectedFleet.value) return;

    try {
        const fd = new FormData();
        fd.append('file', file);
        const { data } = await axios.post('/api/v1/media/upload', fd);
        fleetForm.avatar = data.path;
        await saveFleet();
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось загрузить аватар автопарка');
    } finally {
        event.target.value = '';
    }
}

async function attachDriver() {
    if (!selectedFleet.value) return;
    savingDriver.value = true;
    try {
        await axios.post(`/api/v1/fleets/${selectedFleet.value.id}/drivers`, driverForm);
        driverForm.user_id = '';
        driverForm.role_in_fleet = 'driver';
        ui.success('Водитель добавлен');
        await reloadWorkspace();
    } catch (error) {
        ui.error(error.response?.data?.message ?? firstError(error) ?? 'Не удалось добавить водителя');
    } finally {
        savingDriver.value = false;
    }
}

async function detachDriver(driver) {
    if (!selectedFleet.value) return;
    try {
        await axios.delete(`/api/v1/fleets/${selectedFleet.value.id}/drivers/${driver.id}`);
        drivers.value = drivers.value.filter((item) => item.id !== driver.id);
        ui.success('Водитель удален из автопарка');
        await reloadWorkspace();
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось убрать водителя');
    }
}

async function saveFleetVehicle() {
    if (!selectedFleet.value || !canManageSelectedFleet.value) return;

    savingFleetVehicle.value = true;
    try {
        const payload = {
            ...fleetVehicleForm,
            model: fleetVehicleForm.model || null,
            fuel_type: fleetVehicleForm.fuel_type || null,
            allowed_fuel: fleetVehicleForm.allowed_fuel || null,
            curb_weight_t: fleetVehicleForm.curb_weight_t || null,
            restrictions: fleetVehicleForm.restrictions || null,
        };
        const { data } = await axios.post(`/api/v1/fleets/${selectedFleet.value.id}/vehicles`, payload);
        fleetVehicles.value.unshift(data.data ?? data);
        Object.assign(fleetVehicleForm, emptyFleetVehicleForm());
        ui.success('Фура добавлена в автопарк');
        await reloadWorkspace();
    } catch (error) {
        const info = explainError(error);
        ui.error(info.body || info.title || 'Не удалось добавить фуру');
    } finally {
        savingFleetVehicle.value = false;
    }
}

async function deleteFleetVehicle(vehicle) {
    if (!selectedFleet.value || !canManageSelectedFleet.value) return;

    try {
        await axios.delete(`/api/v1/fleets/${selectedFleet.value.id}/vehicles/${vehicle.id}`);
        fleetVehicles.value = fleetVehicles.value.filter((item) => item.id !== vehicle.id);
        if (Number(assignmentForm.vehicle_id) === Number(vehicle.id)) {
            assignmentForm.vehicle_id = '';
        }
        ui.success('Фура удалена из автопарка');
    } catch (error) {
        const info = explainError(error);
        ui.error(info.body || info.title || 'Не удалось удалить фуру');
    }
}

async function ensureAssignmentMap() {
    await nextTick();
    if (!assignmentMapEl.value) return;

    try {
        await initAssignmentMap();
        assignmentMapUnavailable.value = assignmentMapError.value;
        if (assignmentMapUnavailable.value) return;
        await nextTick();
        requestAnimationFrame(() => {
            refreshAssignmentMap();
            setTimeout(refreshAssignmentMap, 180);
        });
    } catch (error) {
        assignmentMapUnavailable.value = true;
        console.warn('assignment map unavailable:', error);
        return;
    }
}

async function retryAssignmentMap() {
    assignmentMapUnavailable.value = false;
    await ensureAssignmentMap();
}

async function openDriverHistory(driver) {
    if (!selectedFleet.value) return;

    historyDriver.value = driver;
    driverHistoryLoading.value = true;
    driverRoutes.value = [];

    try {
        const { data } = await axios.get(
            `/api/v1/fleets/${selectedFleet.value.id}/drivers/${driver.id}/routes`,
            { params: { per_page: 50 } },
        );
        driverRoutes.value = data.data ?? [];
    } catch (error) {
        closeDriverHistory();
        ui.error(error.response?.data?.message ?? 'Не удалось загрузить историю маршрутов');
    } finally {
        driverHistoryLoading.value = false;
    }
}

function closeDriverHistory() {
    historyDriver.value = null;
    driverRoutes.value = [];
}

function openSharedRoute(routeId) {
    closeDriverHistory();
    router.push({ name: 'route-detail', params: { id: routeId } });
}

async function reverseGeocode(lat, lng) {
    try {
        const { data } = await axios.get('/api/v1/geo/reverse', { params: { lat, lng } });
        return {
            lat,
            lng,
            label: data.label ?? `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
        };
    } catch {
        return { lat, lng, label: `${lat.toFixed(5)}, ${lng.toFixed(5)}` };
    }
}

async function pickAssignmentPoint(field) {
    await ensureAssignmentMap();

    if (pickingField.value === field) {
        disableClickPicking();
        pickingField.value = null;
        return;
    }

    pickingField.value = field;
    enableClickPicking(async (lat, lng) => {
        const point = await reverseGeocode(lat, lng);
        setAssignmentPoint(field, point);
        pickingField.value = null;
    });
}

function setAssignmentPoint(field, point) {
    if (field === 'origin') {
        assignmentForm.origin = point.label;
        assignmentForm.origin_point = point;
        delete assignmentErrors.origin;
        setAssignmentMapOrigin(point);
    } else {
        assignmentForm.destination = point.label;
        assignmentForm.destination_point = point;
        delete assignmentErrors.destination;
        setAssignmentMapDestination(point);
    }

    previewAssignmentRoute();
}

function clearAssignmentPoint(field) {
    if (field === 'origin') {
        delete assignmentErrors.origin;
        assignmentForm.origin_point = null;
        setAssignmentMapOrigin(null);
    } else {
        delete assignmentErrors.destination;
        assignmentForm.destination_point = null;
        setAssignmentMapDestination(null);
    }

    clearAssignmentMapRoute();
    assignmentMapTitle.value = 'Маршрут задания';
}

function pointPayload(point) {
    if (point?.lat == null || point?.lng == null) return null;
    return { lat: Number(point.lat), lng: Number(point.lng) };
}

let assignmentRouteDebounce = null;
function previewAssignmentRoute() {
    clearTimeout(assignmentRouteDebounce);
    assignmentRouteDebounce = setTimeout(syncDraftAssignmentRoute, 350);
}

function syncDraftAssignmentRoute() {
    if (!assignmentForm.origin_point || !assignmentForm.destination_point) return;
    assignmentMapTitle.value = 'Черновой маршрут';
    drawAssignmentRoute(assignmentForm.origin_point, assignmentForm.destination_point, assignmentForm.via_points ?? []);
}

async function showAssignmentOnMap(assignment) {
    activeTab.value = 'assignments';

    const origin = assignmentPointFrom(assignment.origin_point, assignment.origin);
    const destination = assignmentPointFrom(assignment.destination_point, assignment.destination);

    if (!origin || !destination) {
        ui.warning('У этого задания нет точных координат A/B.');
        return;
    }

    assignmentMapTitle.value = `${compactRoute(assignment.origin)} → ${compactRoute(assignment.destination)}`;
    await ensureAssignmentMap();
    setAssignmentMapOrigin(origin);
    setAssignmentMapDestination(destination);
    drawAssignmentRoute(origin, destination, assignment.via_points ?? []);
}

async function openAssignmentDetails(assignment) {
    const isSame = expandedAssignmentId.value === assignment.id;
    expandedAssignmentId.value = isSame ? null : assignment.id;

    if (!isSame && assignment.origin_point && assignment.destination_point) {
        await showAssignmentOnMap(assignment);
    }
}

function assignmentPointFrom(point, label) {
    if (point?.lat == null || point?.lng == null) return null;
    return {
        lat: Number(point.lat),
        lng: Number(point.lng),
        label: label || `${Number(point.lat).toFixed(5)}, ${Number(point.lng).toFixed(5)}`,
    };
}

function resetAssignmentForm() {
    Object.assign(assignmentForm, {
        driver_user_id: '',
        vehicle_source: 'driver',
        vehicle_id: '',
        origin: '',
        origin_point: null,
        destination: '',
        destination_point: null,
        via_points: [],
        planned_start_at: '',
        comment: '',
    });
    assignmentDateTimeText.value = '';
    clearAssignmentErrors();
}

function clearAssignmentMap() {
    disableClickPicking();
    pickingField.value = null;
    assignmentMapTitle.value = 'Маршрут задания';
    setAssignmentMapOrigin(null);
    setAssignmentMapDestination(null);
    clearAssignmentMapRoute();
}

function normalizeAssignmentPlannedStart(event) {
    const formatted = formatAssignmentDateTimeInput(event?.target?.value ?? assignmentDateTimeText.value);
    assignmentDateTimeText.value = formatted;
    if (event?.target) {
        event.target.value = formatted;
    }
    assignmentForm.planned_start_at = parseAssignmentDateTimeInput(formatted);
    delete assignmentErrors.planned_start_at;
}

function selectAssignmentVehicleSource(source) {
    assignmentForm.vehicle_source = source;
    if (source === 'driver') assignmentForm.vehicle_id = '';
    delete assignmentErrors.vehicle_source;
    delete assignmentErrors.vehicle_id;
}

function clearAssignmentErrors() {
    Object.keys(assignmentErrors).forEach((key) => delete assignmentErrors[key]);
}

function setAssignmentError(field, message) {
    assignmentErrors[field] = message;
}

function clearAssignmentError(field) {
    delete assignmentErrors[field];
}

function formatAssignmentDateTimeInput(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 12);
    const day = digits.slice(0, 2);
    const month = digits.slice(2, 4);
    const year = digits.slice(4, 8);
    const hour = digits.slice(8, 10);
    const minute = digits.slice(10, 12);

    let result = day;
    if (month) result += `.${month}`;
    if (year) result += `.${year}`;
    if (hour) result += ` ${hour}`;
    if (minute) result += `:${minute}`;
    return result;
}

function parseAssignmentDateTimeInput(value) {
    const match = String(value ?? '').match(/^(\d{2})\.(\d{2})\.(\d{4})\s(\d{2}):(\d{2})$/);
    if (!match) return '';

    const [, day, month, year, hour, minute] = match;
    const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
    const valid = date.getFullYear() === Number(year)
        && date.getMonth() === Number(month) - 1
        && date.getDate() === Number(day)
        && date.getHours() === Number(hour)
        && date.getMinutes() === Number(minute);

    return valid ? `${year}-${month}-${day}T${hour}:${minute}` : '';
}

function openAssignmentDriverPicker() {
    if (!selectedFleet.value) return;
    clearAssignmentErrors();

    if (!drivers.value.length) {
        ui.error('Сначала добавьте водителя в автопарк');
        activeTab.value = 'drivers';
        return;
    }

    if (!assignmentForm.origin?.trim()) {
        setAssignmentError('origin', 'Укажите точку отправления.');
    }
    if (!assignmentForm.destination?.trim()) {
        setAssignmentError('destination', 'Укажите точку назначения.');
    }
    if (!assignmentDateTimeText.value) {
        setAssignmentError('planned_start_at', 'Укажите дату и время начала задания.');
    } else if (!assignmentForm.planned_start_at) {
        setAssignmentError('planned_start_at', 'Введите дату и время в формате ДД.ММ.ГГГГ ЧЧ:ММ.');
    }
    if (assignmentForm.vehicle_source === 'fleet' && !assignmentForm.vehicle_id) {
        setAssignmentError(
            'vehicle_id',
            fleetVehicles.value.length
                ? 'Выберите фуру автопарка.'
                : 'Сначала добавьте фуру во вкладке «Транспорт».',
        );
    }

    const hasSelectedDriver = drivers.value.some((driver) => Number(driver.id) === Number(assignmentForm.driver_user_id));
    if (!hasSelectedDriver) {
        assignmentForm.driver_user_id = assignmentDriverOptions.value[0]?.id ?? '';
    }

    if (!assignmentForm.driver_user_id) {
        setAssignmentError('driver_user_id', 'Выберите водителя.');
    }
    if (Object.keys(assignmentErrors).length) return;

    showAssignmentDriverPicker.value = true;
}

async function createAssignment() {
    if (!selectedFleet.value) return;
    if (!assignmentForm.driver_user_id) {
        showAssignmentDriverPicker.value = true;
        setAssignmentError('driver_user_id', 'Выберите водителя для задания.');
        return;
    }

    savingAssignment.value = true;
    try {
        const payload = {
            driver_user_id: assignmentForm.driver_user_id,
            origin: assignmentForm.origin,
            origin_point: pointPayload(assignmentForm.origin_point),
            destination: assignmentForm.destination,
            destination_point: pointPayload(assignmentForm.destination_point),
            via_points: assignmentForm.via_points?.length ? assignmentForm.via_points.map(pointPayload).filter(Boolean) : [],
            planned_start_at: assignmentForm.planned_start_at,
            vehicle_source: assignmentForm.vehicle_source,
            vehicle_id: assignmentForm.vehicle_source === 'fleet' ? assignmentForm.vehicle_id : null,
            comment: assignmentForm.comment || null,
        };
        const { data } = await axios.post(`/api/v1/fleets/${selectedFleet.value.id}/assignments`, payload);
        const assignment = data.data ?? data;
        assignments.value.unshift(assignment);
        resetAssignmentForm();
        showAssignmentDriverPicker.value = false;
        ui.success('Задание выдано водителю');
        await reloadWorkspace();
        const freshAssignment = assignments.value.find((item) => item.id === assignment.id) ?? assignment;
        await showAssignmentOnMap(freshAssignment);
    } catch (error) {
        const info = explainError(error);
        Object.entries(info.fields ?? {}).forEach(([field, message]) => {
            assignmentErrors[field] = message;
        });
        showAssignmentDriverPicker.value = false;
        if (!Object.keys(info.fields ?? {}).length) {
            ui.error(info.body || info.title || 'Не удалось выдать задание');
        }
    } finally {
        savingAssignment.value = false;
    }
}

async function cancelAssignment(assignment) {
    if (!selectedFleet.value) return;
    try {
        const { data } = await axios.patch(`/api/v1/fleets/${selectedFleet.value.id}/assignments/${assignment.id}`, {
            status: 'cancelled',
        });
        updateAssignment(data.data ?? data);
        ui.success('Задание отменено');
    } catch (error) {
        ui.error(error.response?.data?.message ?? 'Не удалось отменить задание');
    }
}

function openRating(assignment) {
    ratingTarget.value = assignment;
    ratingForm.rating_stars = assignment.rating_stars || 5;
    ratingForm.rating_comment = assignment.rating_comment || '';
}

async function saveRating() {
    if (!selectedFleet.value || !ratingTarget.value) return;
    savingRating.value = true;
    try {
        const { data } = await axios.post(
            `/api/v1/fleets/${selectedFleet.value.id}/assignments/${ratingTarget.value.id}/rating`,
            ratingForm
        );
        updateAssignment(data.data ?? data);
        ratingTarget.value = null;
        ui.success('Оценка сохранена');
        await reloadWorkspace();
    } catch (error) {
        ui.error(error.response?.data?.message ?? firstError(error) ?? 'Не удалось сохранить оценку');
    } finally {
        savingRating.value = false;
    }
}

function updateAssignment(assignment) {
    const index = assignments.value.findIndex((item) => item.id === assignment.id);
    if (index >= 0) assignments.value.splice(index, 1, assignment);
    if (expandedAssignmentId.value === assignment.id) {
        expandedAssignmentId.value = assignment.id;
    }
}

function firstError(error) {
    return Object.values(error.response?.data?.errors ?? {})[0]?.[0];
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

function userInitials(user) {
    return String(user?.name || 'U')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function statusLabel(status) {
    const labels = {
        issued: 'выдано',
        accepted: 'принято',
        in_progress: 'в работе',
        completed: 'выполнено',
        cancelled: 'отменено',
    };

    return labels[status] ?? status;
}

function roleInFleetLabel(role) {
    return role === 'dispatcher' ? 'диспетчер' : 'водитель';
}

function compactRoute(value) {
    const parts = String(value || '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length <= 2) return parts.join(', ') || 'точка';
    return parts.slice(-2).join(', ');
}

function formatDateTime(iso) {
    if (!iso) return 'без даты';
    return new Date(iso).toLocaleString('ru', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function stars(count) {
    return '★'.repeat(Number(count) || 0) + '☆'.repeat(Math.max(0, 5 - (Number(count) || 0)));
}
</script>

<style scoped>
.fleet-shell {
    width: min(1180px, calc(100% - 36px));
    margin: 108px auto 26px;
    padding: 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    box-shadow: none;
    font-family: inherit;
}

[data-theme="dark"] .fleet-shell {
    background: transparent;
    box-shadow: none;
}

.fleet-hero {
    width: 100%;
    margin: 0;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}

.fleet-section {
    width: 100%;
    margin: 16px 0 0;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}

.fleet-hero .container {
    display: grid;
    grid-template-columns: minmax(240px, .58fr) minmax(0, 1fr);
    gap: 20px;
    align-items: center;
    padding: 22px;
}

.fleet-hero__copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    min-width: 0;
}

.fleet-hero__copy h1 {
    display: none;
}

.fleet-hero .badge,
.fleet-toolbar .badge,
.fleet-empty .badge {
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-3);
}

.fleet-hero__copy .lead {
    max-width: 360px;
    margin-top: 10px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
}

.fleet-hero__copy .actions {
    margin-top: 18px;
}

.fleet-hero h1 {
    margin-top: 10px;
    font-family: var(--font-d);
    font-size: clamp(42px, 4.4vw, 60px);
    font-weight: 400;
    letter-spacing: 0;
}

.fleet-identity-card {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    min-height: 118px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

[data-theme="dark"] .fleet-identity-card {
    background: var(--s1);
}

.fleet-identity-card--empty {
    opacity: .9;
}

.fleet-avatar,
.fleet-switcher__avatar,
.driver-avatar {
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--s1);
    color: var(--accent);
    font-family: var(--font-m);
    font-weight: 700;
    letter-spacing: .02em;
}

.fleet-avatar {
    position: relative;
    width: 64px;
    height: 64px;
    border-radius: 8px;
    font-size: 18px;
}

.fleet-avatar img,
.fleet-switcher__avatar img,
.driver-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.fleet-avatar input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.fleet-avatar.is-editable::after {
    content: 'сменить';
    position: absolute;
    inset: auto 6px 6px;
    padding: 4px 6px;
    border-radius: 4px;
    background: rgba(0, 0, 0, .62);
    color: #fff;
    font-size: 9px;
    text-align: center;
    text-transform: uppercase;
    opacity: 0;
    transition: opacity .15s;
}

.fleet-avatar.is-editable:hover::after {
    opacity: 1;
}

.fleet-identity-card__eyebrow,
.fleet-card-kicker,
.fleet-sidebar__head span,
.fleet-kpis span,
.fleet-status-list small {
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.fleet-identity-card h2 {
    margin-top: 7px;
    font-family: var(--font-d);
    font-size: clamp(23px, 2.4vw, 30px);
    font-weight: 400;
    line-height: 1.08;
    letter-spacing: 0;
}

.fleet-identity-card h2::after {
    display: none;
}

.fleet-identity-card p {
    margin-top: 8px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.5;
}

.fleet-identity-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 14px;
}

.fleet-identity-card__meta span {
    padding-right: 10px;
    border-right: 1px solid var(--border);
    color: var(--text-2);
    font-size: 11px;
}

.fleet-identity-card__meta span:last-child {
    border-right: 0;
}

.fleet-loading {
    color: var(--text-3);
    padding: 22px;
}

.fleet-empty {
    max-width: 640px;
    padding: 24px;
}

.fleet-workspace {
    display: grid;
    grid-template-columns: 230px minmax(0, 1fr);
    gap: 0;
    min-height: 560px;
}

.fleet-sidebar {
    position: sticky;
    top: 108px;
    display: flex;
    height: fit-content;
    flex-direction: column;
    gap: 6px;
    padding: 16px;
    border-right: 1px solid var(--border);
    background: color-mix(in srgb, var(--text) 2%, transparent);
}

.fleet-sidebar__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0 10px;
    border-bottom: 1px solid var(--border);
}

.fleet-sidebar__head button,
.fleet-icon-btn {
    min-height: auto;
    border: 0;
    border-radius: 5px;
    background: transparent;
    color: var(--text);
    box-shadow: none;
}

.fleet-sidebar__head button {
    width: 28px;
    height: 28px;
    padding: 0;
    color: var(--accent);
}

.fleet-switcher {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
    min-height: auto;
    padding: 9px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: inherit;
    text-align: left;
    box-shadow: none;
}

.fleet-switcher:hover,
.fleet-switcher.is-active {
    transform: none;
    color: var(--text);
    border-color: var(--border-a);
    background: color-mix(in srgb, var(--accent) 9%, transparent);
}

.fleet-switcher.is-active .fleet-switcher__body strong {
    color: var(--accent);
}

.fleet-switcher.is-active {
    box-shadow: inset 2px 0 0 var(--accent);
}

.fleet-switcher__avatar {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--s1);
    font-size: 11px;
}

.fleet-switcher__body {
    min-width: 0;
}

.fleet-switcher__body strong,
.fleet-switcher__body small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fleet-switcher__body strong {
    color: var(--text);
    font-size: 13px;
}

.fleet-switcher__body small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 11px;
}

.fleet-main {
    min-width: 0;
    padding: 16px;
}

.fleet-toolbar,
.fleet-card-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
}

.fleet-toolbar {
    align-items: center;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.fleet-toolbar > div:first-child {
    display: block;
    min-width: 0;
}

.fleet-toolbar h2 {
    margin-top: 5px;
    overflow: hidden;
    font-family: var(--font-d);
    font-size: clamp(24px, 3vw, 34px);
    font-weight: 400;
    letter-spacing: 0;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fleet-card-head h3,
.fleet-summary-card h3,
.fleet-form-card h3,
.fleet-list-card h3,
.fleet-route-map-card h3 {
    font-family: var(--font-d);
    font-weight: 400;
    letter-spacing: 0;
}

.fleet-compact-btn {
    min-height: 34px;
    padding: 0 12px;
    font-size: 12px;
}

.fleet-toolbar__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.fleet-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
}

.fleet-kpis div {
    display: flex;
    min-width: 0;
    min-height: 76px;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
    overflow: hidden;
}

.fleet-kpis div:last-child {
    border-right: 1px solid var(--border);
}

.fleet-kpis strong {
    display: block;
    margin-top: 8px;
    color: var(--accent);
    font-family: var(--font-d);
    font-size: 28px;
    font-weight: 400;
    line-height: 1;
}

.fleet-kpis span {
    display: block;
    max-width: 100%;
    line-height: 1.25;
    letter-spacing: .045em;
    overflow-wrap: anywhere;
}

.fleet-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding: 5px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.fleet-tabs button {
    min-height: 34px;
    padding: 0 12px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--text-2);
    font-size: 12px;
    box-shadow: none;
}

.fleet-tabs button.active {
    border-bottom-color: transparent;
    background: var(--accent);
    color: var(--accent-text);
}

[data-theme="dark"] .fleet-tabs button.active {
    color: var(--accent-text);
}

.fleet-panel-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, .75fr);
    gap: 12px;
    margin-top: 12px;
}

.fleet-assignment-grid {
    grid-template-columns: minmax(320px, .78fr) minmax(420px, 1fr);
}

.fleet-form-card,
.fleet-list-card,
.fleet-summary-card {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.fleet-list-card--wide {
    grid-column: 1 / -1;
}

.fleet-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.fleet-assignment-form > .field:first-child {
    display: none;
}

.fleet-profile-view {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.fleet-profile-view div,
.fleet-profile-view p {
    min-width: 0;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 32%, transparent);
}

.fleet-profile-view span {
    display: block;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.fleet-profile-view strong {
    display: block;
    margin-top: 6px;
    overflow: hidden;
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fleet-profile-view p {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.5;
}

.fleet-profile-view__wide {
    grid-column: 1 / -1;
}

.fleet-shell :deep(.field input),
.fleet-shell :deep(.field select) {
    height: 40px;
    border-radius: 8px;
    background-color: color-mix(in srgb, var(--bg) 36%, transparent);
    transition: border-color .15s, background-color .15s, box-shadow .15s;
}

.fleet-shell :deep(.field input:focus),
.fleet-shell :deep(.field select:focus) {
    background-color: color-mix(in srgb, var(--bg) 46%, transparent);
}

.fleet-form textarea {
    width: 100%;
    min-height: 94px;
    resize: vertical;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 36%, transparent);
    color: var(--text);
    padding: 10px 12px;
    font: inherit;
    font-size: 13px;
    line-height: 1.45;
}

.fleet-form textarea:focus {
    outline: none;
    border-color: var(--border-a);
    box-shadow: 0 0 0 3px var(--accent-bg);
}

.fleet-form__wide {
    grid-column: 1 / -1;
}

.fleet-vehicle-source {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.fleet-vehicle-source button {
    min-height: 44px;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--s1);
    color: var(--text-2);
    font: inherit;
    font-size: 12px;
    cursor: pointer;
    box-shadow: none;
    transform: none;
}

.fleet-vehicle-source button:hover,
.fleet-vehicle-source button.active {
    border-color: var(--accent);
    color: var(--text);
    background: color-mix(in srgb, var(--accent) 10%, var(--s1));
    transform: none;
    box-shadow: none;
}

.fleet-vehicle-select {
    margin-top: 8px;
}

.fleet-field-note {
    display: block;
    margin-top: 8px;
    color: var(--text-3);
    font-size: 11px;
    line-height: 1.45;
}

.fleet-vehicle-list {
    display: grid;
}

.fleet-vehicle-row {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
}

.fleet-vehicle-row:last-child {
    border-bottom: 0;
}

.fleet-vehicle-row__icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--accent);
}

.fleet-vehicle-row__icon svg {
    width: 23px;
    height: 23px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.fleet-vehicle-row strong,
.fleet-vehicle-row span,
.fleet-vehicle-row small {
    display: block;
}

.fleet-vehicle-row strong {
    color: var(--text);
    font-size: 13px;
}

.fleet-vehicle-row span,
.fleet-vehicle-row small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 11px;
}

.fleet-point-field {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 40px;
    gap: 8px;
}

.fleet-map-pick-btn {
    display: inline-grid;
    place-items: center;
    min-height: 40px;
    min-width: 40px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text-2);
    font-family: var(--font-m);
    font-size: 13px;
    font-weight: 500;
    box-shadow: none;
}

.fleet-map-pick-btn:hover,
.fleet-map-pick-btn.active {
    transform: none;
    border-color: var(--border-a);
    background: var(--accent-bg);
    color: var(--accent);
    box-shadow: none;
}

.fleet-route-map-card {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}

.fleet-map-actions {
    display: flex;
    gap: 8px;
}

.fleet-map-shell {
    position: relative;
    height: 360px;
    margin-top: 14px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 36%, transparent);
}

.fleet-route-map {
    width: 100%;
    height: 100%;
}

.fleet-route-map.is-unavailable {
    opacity: .18;
}

.fleet-map-fallback {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 24px;
    background: color-mix(in srgb, var(--bg) 70%, transparent);
    text-align: center;
}

.fleet-map-fallback strong {
    color: var(--text);
    font-size: 18px;
}

.fleet-map-fallback span {
    max-width: 360px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.45;
}

.fleet-map-fallback .btn {
    min-height: 36px;
    padding: 0 14px;
}

.fleet-map-hint {
    position: absolute;
    z-index: 3;
    left: 12px;
    bottom: 12px;
    max-width: calc(100% - 28px);
    padding: 7px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--glass-modal);
    color: var(--text-2);
    font-size: 12px;
    pointer-events: none;
}

.fleet-route-map-card__note {
    margin-top: 10px;
    color: var(--text-3);
    font-size: 12px;
    line-height: 1.45;
}

.fleet-summary-card p {
    margin-top: 12px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
}

.fleet-status-list {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.fleet-status-list div {
    display: grid;
    grid-template-columns: 9px minmax(0, 1fr);
    grid-template-rows: 24px 18px;
    column-gap: 8px;
    row-gap: 5px;
    align-items: center;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 30%, transparent);
    overflow: hidden;
}

.fleet-status-list div:last-child {
    border-right: 1px solid var(--border);
}

.fleet-status-list strong {
    grid-column: 2;
    grid-row: 1;
    color: var(--text);
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.fleet-status-list small {
    grid-column: 2;
    grid-row: 2;
    display: block;
    max-width: 100%;
    line-height: 1.2;
    letter-spacing: .045em;
    overflow-wrap: anywhere;
}

.status-dot {
    grid-column: 1;
    grid-row: 1;
    align-self: center;
    justify-self: start;
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

.status-issued { background: var(--accent); }
.status-accepted { background: #4a6caa; }
.status-completed { background: var(--green); }
.status-cancelled { background: var(--red); }

.fleet-muted-box {
    margin-top: 14px;
    padding: 14px;
    border: 1px dashed var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 25%, transparent);
    color: var(--text-3);
    font-size: 13px;
}

.driver-list,
.assignment-list,
.rating-list {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.assignment-list,
.rating-list {
    padding-top: 0;
}

.driver-row {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 30%, transparent);
}

.driver-avatar {
    width: 42px;
    height: 42px;
    border-radius: 9px;
    font-size: 12px;
}

.driver-row__body {
    min-width: 0;
}

.driver-row__body strong,
.driver-row__body small,
.driver-row__body span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.driver-row__body strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
}

.driver-row__body small {
    margin-top: 3px;
    color: var(--text-3);
    font-size: 11px;
}

.driver-row__body span {
    margin-top: 4px;
    color: var(--text-2);
    font-size: 12px;
}

.fleet-icon-btn {
    padding: 7px 9px;
    color: var(--text-2);
    font-size: 12px;
}

.fleet-icon-btn.danger {
    color: var(--red);
}

.driver-row__actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.fleet-filter {
    width: 164px;
    height: 36px;
    padding: 0 34px 0 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    appearance: none;
    background-color: var(--s1);
    color: var(--text);
    font: inherit;
    font-size: 13px;
    box-shadow: none;
    cursor: pointer;
}

.fleet-filter:focus {
    outline: none;
    border-color: var(--border-a);
    box-shadow: 0 0 0 3px var(--accent-bg);
}

.assignment-card,
.rating-row {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--bg) 30%, transparent);
}

.assignment-card {
    padding: 14px;
    cursor: pointer;
    transition: border-color .15s, background-color .15s;
}

.assignment-card:hover,
.assignment-card.is-expanded {
    border-color: var(--border-a);
    background: color-mix(in srgb, var(--accent) 7%, transparent);
}

.assignment-card__top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
}

.assignment-card__top small {
    color: var(--text-3);
    font-size: 11px;
}

.assignment-status {
    padding: 4px 8px;
    border-radius: 4px;
    color: #fff;
    font-family: var(--font-m);
    font-size: 10px;
    text-transform: uppercase;
}

.assignment-status--issued { background: var(--accent); color: var(--accent-text); }
.assignment-status--accepted,
.assignment-status--in_progress { background: #4a6caa; }
.assignment-status--completed { background: var(--green); }
.assignment-status--cancelled { background: var(--red); }

.assignment-card h4 {
    margin-top: 10px;
    color: var(--text);
    font-size: 16px;
    font-weight: 500;
    line-height: 1.2;
}

.assignment-card p {
    margin-top: 6px;
    color: var(--text-2);
    font-size: 13px;
}

.assignment-card__comment {
    color: var(--text-3) !important;
}

.assignment-card__details {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.assignment-card__details span {
    display: block;
    color: var(--text-3);
    font-family: var(--font-m);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.assignment-card__details strong {
    display: block;
    margin-top: 5px;
    overflow: hidden;
    color: var(--text);
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assignment-card__route,
.assignment-card__map-note {
    grid-column: 1 / -1;
    margin: 0 !important;
}

.assignment-card__route {
    color: var(--text-2) !important;
    line-height: 1.45;
}

.assignment-card__map-note {
    color: var(--accent) !important;
    font-size: 12px !important;
}

.assignment-card__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 14px;
}

.assignment-card__actions .btn {
    min-height: 34px;
    padding: 0 12px;
    font-size: 12px;
}

.rating-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 14px;
}

.rating-row strong,
.rating-row span {
    display: block;
}

.rating-row strong {
    color: var(--text);
}

.rating-row span {
    margin-top: 4px;
    color: var(--text-2);
    font-size: 13px;
}

.star-button,
.star-readonly {
    color: var(--accent);
    font-size: 14px;
    letter-spacing: .08em;
}

.star-button {
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid var(--border-a);
    border-radius: 5px;
    background: var(--accent-bg);
    box-shadow: none;
}

.auth-modal {
    z-index: 1200;
}

.fleet-modal-panel {
    padding: 30px;
}

.fleet-modal-panel h2 {
    margin-top: 14px;
    max-width: 520px;
    font-size: clamp(28px, 4vw, 42px);
    line-height: 1.05;
}

.fleet-modal-route {
    margin-top: 10px;
    color: var(--text-2);
    font-size: 13px;
}

.fleet-driver-modal {
    width: min(620px, calc(100vw - 32px));
    max-height: 84dvh;
    overflow-y: auto;
}

.fleet-driver-picker {
    display: grid;
    gap: 8px;
    margin-top: 18px;
}

.fleet-driver-option {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    width: 100%;
    min-height: 68px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
    color: var(--text);
    text-align: left;
    box-shadow: none;
}

.fleet-driver-option:hover,
.fleet-driver-option.is-selected {
    border-color: var(--border-a);
    background: color-mix(in srgb, var(--accent) 12%, var(--s1));
}

.fleet-driver-option__body,
.fleet-driver-option__stats {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
}

.fleet-driver-option__body strong,
.fleet-driver-option__stats strong {
    overflow: hidden;
    font-size: 14px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fleet-driver-option__body small,
.fleet-driver-option__stats small {
    color: var(--text-3);
    font-size: 11px;
}

.fleet-driver-option__stats {
    align-items: flex-end;
    text-align: right;
}

.fleet-driver-option__stats strong {
    color: var(--accent);
}

.fleet-driver-modal__actions {
    justify-content: flex-end;
    margin-top: 18px;
}

.fleet-history-modal {
    width: min(720px, calc(100vw - 32px));
    max-height: 84dvh;
    overflow-y: auto;
}

.fleet-history-list {
    display: grid;
    gap: 8px;
    margin-top: 18px;
}

.fleet-history-row {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
}

.fleet-history-row:last-child {
    border-bottom: 0;
}

.fleet-history-row strong,
.fleet-history-row span {
    display: block;
}

.fleet-history-row strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 600;
}

.fleet-history-row span {
    margin-top: 5px;
    color: var(--text-3);
    font-size: 11px;
}

.fleet-rating-picker {
    display: flex;
    gap: 6px;
    margin-top: 18px;
}

.fleet-rating-picker button {
    min-height: 42px;
    width: 42px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--s2);
    color: var(--text-3);
    font-size: 24px;
    box-shadow: none;
}

.fleet-rating-picker button.active {
    border-color: var(--border-a);
    background: var(--accent-bg);
    color: var(--accent);
}

@media (max-width: 1000px) {
    .fleet-hero .container,
    .fleet-workspace,
    .fleet-panel-grid {
        grid-template-columns: 1fr;
    }

    .fleet-sidebar {
        position: static;
        border-right: 0;
        border-bottom: 1px solid var(--border);
    }

    .fleet-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 620px) {
    .fleet-identity-card,
    .driver-row,
    .rating-row,
    .fleet-toolbar {
        grid-template-columns: 1fr;
    }

    .fleet-identity-card {
        justify-items: start;
    }

    .fleet-form,
    .fleet-kpis,
    .fleet-status-list {
        grid-template-columns: 1fr;
    }

    .fleet-toolbar,
    .fleet-card-head {
        display: grid;
    }
}
</style>
