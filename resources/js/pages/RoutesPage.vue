<template>
    <div class="wizard-page">
        <!-- ─── LEFT: form panel ──────────────────────────────────────── -->
        <div class="wizard-panel">
            <!-- Step indicator -->
            <div class="wizard-steps">
                <button
                    v-for="(label, i) in stepLabels"
                    :key="i"
                    type="button"
                    class="wizard-step-btn"
                    :class="{
                        'is-active': wizard.step === i + 1,
                        'is-done':   wizard.step > i + 1,
                    }"
                    @click="wizard.step > i + 1 ? wizard.goTo(i + 1) : null"
                >
                    <span class="wizard-step-num">{{ wizard.step > i + 1 ? '✓' : i + 1 }}</span>
                    <span class="wizard-step-label">{{ label }}</span>
                </button>
            </div>

            <!-- Step content -->
            <div class="wizard-body">

                <!-- ── Step 1: Vehicle ── -->
                <template v-if="wizard.step === 1">
                    <h2>Транспортное средство</h2>
                    <p class="lead">Выберите свой грузовик или заполните параметры.</p>

                    <div v-if="loadingVehicles" style="margin-top:24px;color:var(--text-3);">Загрузка...</div>
                    <div v-else>
                        <div class="vehicle-list" style="margin-top:20px;">
                            <button
                                v-for="v in vehicles"
                                :key="v.id"
                                type="button"
                                class="vehicle-card"
                                :class="{ 'is-selected': wizard.vehicle?.id === v.id }"
                                @click="selectVehicle(v)"
                            >
                                <div class="vehicle-card__info">
                                    <strong>{{ v.title }}</strong>
                                    <span>{{ v.type }}</span>
                                    <span style="font-family:var(--font-m);font-size:11px;color:var(--text-3);">
                                        {{ v.tank_capacity_l }} л · {{ v.consumption_l_per_100 }} л/100 · {{ v.cruise_speed_kmh }} км/ч
                                    </span>
                                </div>
                                <span v-if="wizard.vehicle?.id === v.id" class="vehicle-card__check">✓</span>
                            </button>
                            <button type="button" class="vehicle-card vehicle-card--add" @click="showNewVehicle = !showNewVehicle">
                                + Добавить новое ТС
                            </button>
                        </div>

                        <!-- Inline new vehicle form -->
                        <div v-if="showNewVehicle" class="inline-card" style="margin-top:16px;">
                            <h3>Новое ТС</h3>
                            <div class="wizard-form-2" style="margin-top:12px;">
                                <div class="field"><label>Название</label><input v-model="newV.title" placeholder="Мой Volvo FH"></div>
                                <div class="field"><label>Тип</label>
                                    <select v-model="newV.type">
                                        <option
                                            v-for="item in dictionaries.options('vehicle_types')"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="field"><label>Бак (л)</label><input v-model.number="newV.tank_capacity_l" type="number" min="50" max="2000"></div>
                                <div class="field"><label>Расход (л/100 км)</label><input v-model.number="newV.consumption_l_per_100" type="number" step="0.5" min="5" max="100"></div>
                                <div class="field"><label>Скорость (км/ч)</label><input v-model.number="newV.cruise_speed_kmh" type="number" min="40" max="130"></div>
                                <div class="field"><label>Масса ТС (т)</label><input v-model.number="newV.curb_weight_t" type="number" step="0.1" placeholder="15.5"></div>
                            </div>
                            <div class="actions" style="margin-top:12px;">
                                <button type="button" class="btn" @click="saveNewVehicle" :disabled="savingV">{{ savingV ? 'Сохраняем...' : 'Сохранить и выбрать' }}</button>
                                <button type="button" class="btn outline" @click="showNewVehicle=false">Отмена</button>
                            </div>
                        </div>

                        <!-- Start fuel slider -->
                        <div v-if="wizard.vehicle" class="field" style="margin-top:20px;">
                            <label>Топливо при старте: <strong style="color:var(--accent);font-family:var(--font-m);">{{ wizard.startFuel }} л</strong></label>
                            <input type="range" :min="0" :max="wizard.vehicle.tank_capacity_l" :step="10" v-model.number="wizard.startFuel" style="width:100%;margin-top:8px;">
                            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-3);margin-top:4px;">
                                <span>0 л</span><span>{{ wizard.vehicle.tank_capacity_l }} л (полный)</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ── Step 2: Cargo ── -->
                <template v-else-if="wizard.step === 2">
                    <h2>Груз</h2>
                    <p class="lead">Укажите параметры груза или отметьте, что рейс порожний.</p>

                    <div class="card toggle-row" style="margin-top:24px;">
                        <div><h3>Есть груз</h3><p style="font-size:13px;color:var(--text-2);">Включите, чтобы указать вес и тип.</p></div>
                        <label class="toggle"><input type="checkbox" v-model="wizard.hasCargo"></label>
                    </div>

                    <div v-if="wizard.hasCargo" class="wizard-form-2" style="margin-top:20px;">
                        <div class="field"><label>Тип груза</label>
                            <select v-model="wizard.cargo.flag">
                                <option
                                    v-for="item in dictionaries.options('cargo_types')"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                        </div>
                        <div class="field"><label>Вес груза (т)</label><input v-model.number="wizard.cargo.weight_t" type="number" step="0.1" min="0" max="45" placeholder="0.0"></div>
                        <div class="field" style="grid-column:1/-1;"><label>Особые требования <span style="color:var(--text-3)">(необязательно)</span></label><input v-model="wizard.cargo.requirements" placeholder="Температурный режим, спецупаковка..."></div>
                    </div>
                    <div v-else class="card" style="margin-top:16px;border-style:dashed;"><p style="color:var(--text-3);font-size:13px;">Порожний рейс — груз не учитывается в расчётах.</p></div>
                </template>

                <!-- ── Step 3: Route ── -->
                <template v-else-if="wizard.step === 3">
                    <h2>Маршрут</h2>
                    <p class="lead">Укажите начало, конец и транзитные точки.</p>
                    <div class="wizard-route-fields" style="margin-top:20px;">
                        <div class="field">
                            <label>Откуда</label>
                            <GeoInput input-id="origin" :modelValue="wizard.origin" placeholder="Москва, Красная площадь" @update:modelValue="onOriginChange" />
                            <button type="button" class="pick-map-btn" :class="{ 'is-picking': pickingField === 'origin' }" @click="pickFromMap('origin')">
                                {{ pickingField === 'origin' ? '✕ Отмена' : '📍 Выбрать на карте' }}
                            </button>
                        </div>
                        <div class="field">
                            <label>Куда</label>
                            <GeoInput input-id="dest" :modelValue="wizard.destination" placeholder="Ростов-на-Дону, центр" @update:modelValue="onDestChange" />
                            <button type="button" class="pick-map-btn" :class="{ 'is-picking': pickingField === 'destination' }" @click="pickFromMap('destination')">
                                {{ pickingField === 'destination' ? '✕ Отмена' : '📍 Выбрать на карте' }}
                            </button>
                        </div>
                        <div v-for="(wp, i) in wizard.waypoints" :key="`wp-${i}`" class="field">
                            <label>Транзитная точка {{ i + 1 }}</label>
                            <div class="waypoint-row">
                                <GeoInput :modelValue="wizard.waypoints[i]" :placeholder="`Воронеж, Курск...`" @update:modelValue="(v) => onWaypointChange(i, v)" />
                                <button type="button" class="pick-map-btn pick-map-btn--sm" :class="{ 'is-picking': pickingField === `waypoint-${i}` }" @click="pickFromMap(`waypoint-${i}`)">📍</button>
                                <button type="button" class="btn outline" style="padding:8px 10px;min-height:auto;flex-shrink:0;" @click="removeWaypoint(i)">×</button>
                            </div>
                        </div>
                        <div v-if="wizard.waypoints.length < 8">
                            <button type="button" class="btn outline" style="font-size:13px;width:100%;" @click="addWaypoint">+ Добавить транзитную точку</button>
                        </div>
                        <div class="field">
                            <label>Дата и время отправления <span style="color:var(--text-3)">(необязательно)</span></label>
                            <input type="datetime-local" v-model="wizard.startTime" :min="minDatetime">
                        </div>
                    </div>
                </template>

                <!-- ── Step 4: Preferences ── -->
                <template v-else-if="wizard.step === 4">
                    <h2>Предпочтения</h2>
                    <p class="lead">Настройте заправки, отдых и режим планирования.</p>
                    <div class="preferences-grid">
                        <div class="field"><label>Режим планирования</label>
                            <select v-model="wizard.prefs.planning_mode"><option value="Безопасный">Безопасный (больше резерв)</option><option value="Экономный">Экономный (меньше остановок)</option></select>
                        </div>
                        <div class="field"><label>Предпочтительная сеть АЗС</label>
                            <select v-model="wizard.prefs.fuel_network"><option value="Любые">Любые АЗС</option><option value="Лукойл">Лукойл</option><option value="Газпромнефть">Газпромнефть</option><option value="Роснефть">Роснефть</option><option value="Татнефть">Татнефть</option></select>
                        </div>
                        <div class="field"><label>Тип ночлега</label>
                            <select v-model="wizard.prefs.lodging_type"><option value="Любой">Любой</option><option value="Стоянка">Стоянка для грузовиков</option><option value="Мотель">Мотель / гостиница</option></select>
                        </div>
                        <div class="field preference-range">
                            <div class="preference-range__head">
                                <label>Непрерывное движение</label>
                                <output>{{ wizard.prefs.continuous_drive_hours }} ч</output>
                            </div>
                            <input type="range" min="2" max="8" step="0.5" v-model.number="wizard.prefs.continuous_drive_hours">
                            <div class="preference-range__scale"><span>2 ч</span><span>8 ч</span></div>
                        </div>
                        <div class="field preference-range preference-range--wide">
                            <div class="preference-range__head">
                                <label>Резерв топлива</label>
                                <output>{{ wizard.prefs.reserve_percent }}%</output>
                            </div>
                            <input type="range" min="5" max="40" step="5" v-model.number="wizard.prefs.reserve_percent">
                            <div class="preference-range__scale"><span>5% — минимальный</span><span>40% — безопасный</span></div>
                        </div>
                        <div class="preference-toggle">
                            <div><h3>Включать точки питания</h3><p style="font-size:13px;color:var(--text-2);">Кафе и рестораны вдоль трассы.</p></div>
                            <label class="toggle"><input type="checkbox" v-model="wizard.prefs.include_food"></label>
                        </div>
                        <div class="preference-toggle">
                            <div><h3>Без платных дорог</h3><p style="font-size:13px;color:var(--text-2);">Обходить платные участки.</p></div>
                            <label class="toggle"><input type="checkbox" :checked="wizard.prefs.no_toll_roads === 'Да'" @change="wizard.prefs.no_toll_roads = $event.target.checked ? 'Да' : 'Нет'"></label>
                        </div>
                    </div>
                </template>

                <!-- ── Step 5: POI Selection ── -->
                <template v-else-if="wizard.step === 5">
                    <h2>Объекты на маршруте</h2>
                    <p class="lead">Выберите объекты вдоль маршрута. Рекомендуемые подсвечены ★.</p>

                    <div style="display:flex;gap:8px;margin-top:16px;">
                        <button type="button" class="btn outline" style="font-size:12px;" @click="wizard.togglePoiVisibility()">
                            {{ wizard.showOnlySelected ? '👁 Показать все' : '👁‍🗨 Только выбранные' }}
                        </button>
                        <span style="font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:4px;font-family:var(--font-m);">
                            {{ wizard.selectedPois.length }} выбрано
                        </span>
                        <span v-if="wizard.selectedPois.length" style="font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:4px;font-family:var(--font-m);">
                            {{ wizard.routeTransitCount }}/{{ wizard.routeTransitLimit }} в маршрут
                        </span>
                    </div>

                    <!-- Selected POI list -->
                    <div class="selected-pois-list">
                        <div v-for="(poi, index) in wizard.selectedPois" :key="poi.id" class="selected-poi-card">
                            <span class="badge" :style="poiTypeStyle(poi.type)">{{ poi.type }}</span>
                            <div class="selected-poi-card__info">
                                <strong>{{ poi.name }}</strong>
                                <span>{{ poi.highway }} {{ poi.km_marker ? poi.km_marker + ' км' : '' }}</span>
                            </div>
                            <div class="selected-poi-card__order">
                                <button type="button" :disabled="index === 0" @click="moveSelectedPoi(poi.id, -1)">↑</button>
                                <button type="button" :disabled="index === wizard.selectedPois.length - 1" @click="moveSelectedPoi(poi.id, 1)">↓</button>
                            </div>
                            <label class="selected-poi-card__route-toggle" :class="{ 'is-disabled': !wizard.isRoutePoi(poi.id) && wizard.routeTransitCount >= wizard.routeTransitLimit }">
                                <input
                                    type="checkbox"
                                    :checked="wizard.isRoutePoi(poi.id)"
                                    :disabled="!wizard.isRoutePoi(poi.id) && wizard.routeTransitCount >= wizard.routeTransitLimit"
                                    @change="togglePoiRouteMode(poi)"
                                >
                                <span>Заехать</span>
                            </label>
                            <button type="button" class="selected-poi-card__remove" @click="removeSelectedPoi(poi.id)">✕</button>
                        </div>
                        <div v-if="!wizard.selectedPois.length" class="selected-pois-empty">
                            Кликните на маркер на карте, чтобы добавить объект к маршруту
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="poi-legend" style="margin-top:16px;">
                        <span class="poi-legend-item"><span class="poi-legend-dot" style="background:#c99b3a;"></span>АЗС</span>
                        <span class="poi-legend-item"><span class="poi-legend-dot" style="background:#4a6caa;"></span>Стоянка</span>
                        <span class="poi-legend-item"><span class="poi-legend-dot" style="background:#7a4a9e;"></span>Ночлег</span>
                        <span class="poi-legend-item"><span class="poi-legend-dot" style="background:#e07030;"></span>СТО/Кафе</span>
                        <span class="poi-legend-item"><span class="poi-legend-dot" style="background:#2d7a4f;"></span>Выбрано</span>
                    </div>
                </template>

                <!-- ── Step 6: Summary ── -->
                <template v-else-if="wizard.step === 6">
                    <h2>Итог</h2>
                    <p class="lead">Проверьте данные и запустите построение маршрута.</p>

                    <div class="summary-grid" style="margin-top:20px;">
                        <div class="summary-item"><span class="summary-label">Транспорт</span><span class="summary-value">{{ wizard.vehicle?.title }}</span></div>
                        <div class="summary-item"><span class="summary-label">Топливо при старте</span><span class="summary-value">{{ wizard.startFuel }} л</span></div>
                        <div class="summary-item"><span class="summary-label">Груз</span><span class="summary-value">{{ wizard.hasCargo ? `${wizard.cargo.flag}, ${wizard.cargo.weight_t} т` : 'Порожний' }}</span></div>
                        <div class="summary-item"><span class="summary-label">Откуда</span><span class="summary-value">{{ wizard.origin?.label }}</span></div>
                        <div v-if="wizard.waypoints.filter(Boolean).length" class="summary-item" style="grid-column:1/-1;">
                            <span class="summary-label">Транзит</span><span class="summary-value">{{ wizard.waypoints.filter(Boolean).map(w => w.label).join(' → ') }}</span>
                        </div>
                        <div class="summary-item"><span class="summary-label">Куда</span><span class="summary-value">{{ wizard.destination?.label }}</span></div>
                        <div v-if="wizard.startTime" class="summary-item"><span class="summary-label">Отправление</span><span class="summary-value">{{ formatDatetime(wizard.startTime) }}</span></div>
                        <div class="summary-item"><span class="summary-label">Режим</span><span class="summary-value">{{ wizard.prefs.planning_mode }}</span></div>
                        <div class="summary-item"><span class="summary-label">АЗС</span><span class="summary-value">{{ wizard.prefs.fuel_network }}</span></div>
                        <div class="summary-item"><span class="summary-label">Ночлег</span><span class="summary-value">{{ wizard.prefs.lodging_type }}</span></div>
                        <div class="summary-item"><span class="summary-label">Резерв топлива</span><span class="summary-value">{{ wizard.prefs.reserve_percent }}%</span></div>
                        <div v-if="wizard.selectedPois.length" class="summary-item" style="grid-column:1/-1;">
                            <span class="summary-label">Выбранные объекты ({{ wizard.selectedPois.length }})</span>
                            <span class="summary-value">{{ wizard.selectedPois.map(p => p.name).join(', ') }}</span>
                        </div>
                        <div v-if="wizard.routeTransitPois.length" class="summary-item" style="grid-column:1/-1;">
                            <span class="summary-label">Обязательные заезды ({{ wizard.routeTransitPois.length }})</span>
                            <span class="summary-value">{{ wizard.routeTransitPois.map(p => p.name).join(' → ') }}</span>
                        </div>
                        <div v-if="wizard.optionalPois.length" class="summary-item" style="grid-column:1/-1;">
                            <span class="summary-label">Варианты для водителя ({{ wizard.optionalPois.length }})</span>
                            <span class="summary-value">{{ wizard.optionalPois.map(p => p.name).join(', ') }}</span>
                        </div>
                    </div>

                    <AlertCard
                        v-if="buildError"
                        type="error" :title="buildError.title" :body="buildError.body"
                        :items="buildError.items" :hint="buildError.hint" :dismissible="true"
                        style="margin-top:18px;" @close="buildError = null"
                    >
                        <template v-if="buildError.fixStep" #actions>
                            <button class="btn outline" @click="wizard.goTo(buildError.fixStep); buildError = null">← Вернуться к шагу</button>
                        </template>
                    </AlertCard>
                </template>

            </div><!-- /wizard-body -->

            <!-- ── Navigation ── -->
            <div class="wizard-nav">
                <button v-if="wizard.step > 1" type="button" class="btn outline" @click="wizard.back()">← Назад</button>
                <span v-else></span>

                <button v-if="wizard.step < 6" type="button" class="btn" :disabled="!wizard.canProceed" @click="wizard.next()">Далее →</button>
                <button v-else type="button" class="btn" :disabled="building" @click="buildRoute">{{ building ? 'Строим маршрут...' : 'Построить маршрут' }}</button>
            </div>
        </div>

        <!-- ─── RIGHT: Yandex map ───────────────────────────────────────── -->
        <div class="wizard-map-wrap">
            <MapFallback v-if="mapError" class="wizard-map" :retry="init" />
            <div v-show="!mapError" ref="mapEl" class="wizard-map"></div>
            <div v-if="!mapError" class="wizard-map-hint">
                <template v-if="isPicking">📍 Кликните на карту — точка будет установлена</template>
                <template v-else-if="wizard.step === 1">Выберите транспортное средство</template>
                <template v-else-if="wizard.step === 2">Укажите параметры груза</template>
                <template v-else-if="wizard.step === 3 && (!wizard.origin || !wizard.destination)">Введите адреса или выберите точки на карте</template>
                <template v-else-if="wizard.step === 3">Маршрут отображается на карте</template>
                <template v-else-if="wizard.step === 4">Настройте предпочтения по остановкам</template>
                <template v-else-if="wizard.step === 5">Кликните на маркер для информации об объекте</template>
                <template v-else>Проверьте данные и нажмите «Построить»</template>
            </div>
        </div>

        <!-- ─── POI Popup ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="poiPopup" class="poi-popup-overlay" @click.self="poiPopup = null">
                <div class="poi-popup-panel">
                    <button type="button" class="poi-popup-close" @click="poiPopup = null">✕</button>

                    <!-- Image -->
                    <div v-if="poiPopup.image_url" class="poi-popup-image">
                        <img :src="poiPopup.image_url" :alt="poiPopup.name">
                    </div>

                    <!-- Header -->
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px;">
                        <span class="badge" :style="poiTypeStyle(poiPopup.type)">{{ poiPopup.type }}</span>
                        <span v-if="poiPopup.highway" style="font-size:11px;color:var(--text-3);font-family:var(--font-m);">{{ poiPopup.highway }}</span>
                        <span v-if="poiPopup.rating" style="font-size:12px;color:var(--accent);font-family:var(--font-m);">★ {{ poiPopup.rating }}</span>
                    </div>
                    <h3 style="font-size:18px;margin-bottom:6px;">{{ poiPopup.name }}</h3>
                    <p v-if="poiPopup.location" style="font-size:12px;color:var(--text-2);">{{ poiPopup.location }}</p>

                    <!-- Details -->
                    <div class="poi-popup-details">
                        <span v-if="poiPopup.fuel_price"><strong>{{ poiPopup.fuel_price }}</strong> ₽/л</span>
                        <span v-if="poiPopup.detour_km > 0">+{{ poiPopup.detour_km }} км крюк</span>
                        <span v-if="poiPopup.has_truck_parking" style="color:var(--green);">P Фура</span>
                    </div>

                    <p v-if="poiPopup.services" style="font-size:13px;color:var(--text-2);margin-top:10px;">{{ poiPopup.services }}</p>

                    <!-- Tags -->
                    <div v-if="poiPopup.tags?.length" style="display:flex;gap:4px;flex-wrap:wrap;margin-top:10px;">
                        <span v-for="tag in poiPopup.tags" :key="tag" class="badge" style="font-size:10px;">{{ tag }}</span>
                    </div>

                    <!-- Rich content -->
                    <div v-if="poiPopup.content" class="poi-popup-content tiptap-editor-inner" v-html="poiPopup.content"></div>

                    <!-- Actions -->
                    <div class="poi-popup-actions">
                        <button v-if="!wizard.isPoiSelected(poiPopup.id)" type="button" class="btn" @click="addPoiFromPopup(poiPopup)">
                            + Добавить к маршруту
                        </button>
                        <button v-else type="button" class="btn danger" @click="removeSelectedPoi(poiPopup.id)">
                            Убрать из маршрута
                        </button>
                        <button
                            v-if="wizard.isPoiSelected(poiPopup.id)"
                            type="button"
                            class="btn outline"
                            :disabled="!wizard.isRoutePoi(poiPopup.id) && wizard.routeTransitCount >= wizard.routeTransitLimit"
                            @click="togglePoiRouteMode(poiPopup)"
                        >
                            {{ wizard.isRoutePoi(poiPopup.id) ? 'Оставить вариантом' : 'Включить в дорогу' }}
                        </button>
                        <button type="button" class="btn outline" @click="poiPopup = null">Закрыть</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useWizardStore } from '@/stores/wizard';
import { useUiStore }     from '@/stores/ui';
import { useDictionariesStore } from '@/stores/dictionaries';
import GeoInput           from '@/components/GeoInput.vue';
import AlertCard          from '@/components/AlertCard.vue';
import MapFallback        from '@/components/MapFallback.vue';
import { useWizardMap }   from '@/composables/useWizardMap';
import { explainError }   from '@/utils/errorHelpers';

const wizard  = useWizardStore();
const ui      = useUiStore();
const dictionaries = useDictionariesStore();
const router  = useRouter();

const stepLabels = ['ТС', 'Груз', 'Маршрут', 'Настройки', 'Объекты', 'Итог'];

const mapEl           = ref(null);
const loadingVehicles = ref(true);
const vehicles        = ref([]);
const showNewVehicle  = ref(false);
const savingV         = ref(false);
const building        = ref(false);
const buildError      = ref(null);
const pickingField    = ref(null);
const poiPopup        = ref(null);   // POI object shown in popup

const newV = ref({
    title: '', type: 'Тягач + полуприцеп',
    tank_capacity_l: 600, consumption_l_per_100: 29,
    cruise_speed_kmh: 85, curb_weight_t: 15.5,
});

const minDatetime = new Date().toISOString().slice(0, 16);

const POI_TYPE_COLORS = {
    'АЗС': '#c99b3a', 'Стоянка': '#4a6caa', 'Ночлег': '#7a4a9e',
    'СТО': '#e07030', 'Кафе': '#e07030', 'Еда': '#e07030',
};

function poiTypeStyle(type) {
    const c = POI_TYPE_COLORS[type] ?? '#6a6762';
    return `background:${c};color:#fff;border-color:${c}`;
}

const {
    ready, isPicking, loadedPois, mapError,
    init, setOrigin, setDestination, setWaypoints,
    fetchAndDrawRoute, clearRoute,
    refreshPoiMarkers, filterPoiMarkers,
    setPoiClickHandler,
    enableClickPicking, disableClickPicking,
} = useWizardMap(mapEl);

// ── Init map ──
onMounted(async () => {
    dictionaries.load();
    await nextTick();
    setTimeout(init, 100);
    loadVehicles();

    // Register POI click handler (opens popup)
    setPoiClickHandler((poi) => {
        poiPopup.value = poi;
    });
});

// ── Step transitions ──
watch(() => wizard.step, (step) => {
    disableClickPicking();
    pickingField.value = null;

    if (step === 3) {
        nextTick(() => {
            if (wizard.origin)      setOrigin(wizard.origin);
            if (wizard.destination) setDestination(wizard.destination);
            setWaypoints(wizard.waypoints.filter(Boolean));
            if (wizard.origin && wizard.destination) tryRoute();
        });
    }

    // Step 5: redraw road-following route + load POI independently
    if (step === 5 && wizard.origin && wizard.destination) {
        nextTick(() => {
            // 1. Build route line with manual waypoints plus selected mandatory POI stops.
            fetchAndDrawRoute(wizard.origin, wizard.destination, wizard.routeViaPoints);
            // 2. Restore point markers
            setOrigin(wizard.origin);
            setDestination(wizard.destination);
            setWaypoints(wizard.waypoints.filter(Boolean));
            // 3. Load POI independently (don't rely on requestsuccess which may not fire on repeat)
            setTimeout(() => refreshPois(), 1500);
        });
    }
});

// Watch eye toggle
watch(() => wizard.showOnlySelected, (show) => {
    const selectedIds = wizard.selectedPois.map(p => p.id);
    filterPoiMarkers(selectedIds, show);
});

// ── POI helpers ──
function refreshPois() {
    const acceptedIds = wizard.selectedPois.map(p => p.id);
    const opts = {
        recommendFuel: wizard.fuelRatio < 0.4,
        recommendRest: wizard.prefs.continuous_drive_hours >= 4,
    };
    refreshPoiMarkers(acceptedIds, opts);
}

function addPoiFromPopup(poi) {
    wizard.addPoi(poi);
    poiPopup.value = null;
    refreshPois();
    ui.success(`"${poi.name}" добавлен к маршруту`);
}

function removeSelectedPoi(id) {
    wizard.removePoi(id);
    if (poiPopup.value?.id === id) poiPopup.value = null;
    redrawRouteWithSelectedPois();
}

function moveSelectedPoi(id, direction) {
    if (wizard.moveSelectedPoi(id, direction)) {
        redrawRouteWithSelectedPois();
    }
}

function togglePoiRouteMode(poi) {
    const changed = wizard.toggleRoutePoi(poi.id);
    if (!changed) {
        ui.error(`Можно включить в дорогу не больше ${wizard.routeTransitLimit} объектов: общий лимит транзитных точек — 8.`);
        return;
    }
    redrawRouteWithSelectedPois();
}

function redrawRouteWithSelectedPois() {
    if (wizard.origin && wizard.destination) {
        fetchAndDrawRoute(wizard.origin, wizard.destination, wizard.routeViaPoints);
    }
    refreshPois();
}

// ── Vehicles ──
async function loadVehicles() {
    try {
        const { data } = await axios.get('/api/v1/vehicles');
        vehicles.value = data.data ?? [];
        const active = vehicles.value.find(v => v.is_active) ?? vehicles.value[0];
        if (active && !wizard.vehicle) wizard.setVehicle(active);
    } catch { /* ignore */ } finally {
        loadingVehicles.value = false;
    }
}

function selectVehicle(v) { wizard.setVehicle(v); }

async function saveNewVehicle() {
    savingV.value = true;
    try {
        const { data } = await axios.post('/api/v1/vehicles', newV.value);
        const v = data.data ?? data;
        vehicles.value.push(v);
        wizard.setVehicle(v);
        showNewVehicle.value = false;
        ui.success('ТС добавлено');
    } catch (e) {
        ui.error(e.response?.data?.message ?? 'Ошибка сохранения');
    } finally {
        savingV.value = false;
    }
}

// ── Click-to-place ──
async function reverseGeocode(lat, lng) {
    try {
        const { data } = await axios.get('/api/v1/geo/reverse', { params: { lat, lng } });
        return { lat, lng, label: data.label ?? `${lat.toFixed(4)}, ${lng.toFixed(4)}` };
    } catch {
        return { lat, lng, label: `${lat.toFixed(4)}, ${lng.toFixed(4)}` };
    }
}

function pickFromMap(field) {
    if (pickingField.value === field) {
        disableClickPicking();
        pickingField.value = null;
        return;
    }
    pickingField.value = field;
    enableClickPicking(async (lat, lng) => {
        pickingField.value = null;
        const point = await reverseGeocode(lat, lng);
        if (field === 'origin') onOriginChange(point);
        else if (field === 'destination') onDestChange(point);
        else if (field.startsWith('waypoint-')) {
            onWaypointChange(parseInt(field.split('-')[1], 10), point);
        }
    });
}

// ── Route fields ──
function onOriginChange(pt) { wizard.origin = pt; if (pt) setOrigin(pt); if (wizard.origin && wizard.destination) tryRoute(); }
function onDestChange(pt)   { wizard.destination = pt; if (pt) setDestination(pt); if (wizard.origin && wizard.destination) tryRoute(); }
function onWaypointChange(i, pt) { wizard.setWaypoint(i, pt); setWaypoints(wizard.waypoints.filter(Boolean)); if (wizard.origin && wizard.destination) tryRoute(); }
function addWaypoint() { wizard.addWaypoint(); }
function removeWaypoint(i) {
    wizard.removeWaypoint(i);
    if (pickingField.value === `waypoint-${i}`) { disableClickPicking(); pickingField.value = null; }
    setWaypoints(wizard.waypoints.filter(Boolean));
    if (wizard.origin && wizard.destination) tryRoute();
}

let routeDebounce = null;
function tryRoute() {
    clearTimeout(routeDebounce);
    routeDebounce = setTimeout(() => {
        fetchAndDrawRoute(wizard.origin, wizard.destination, wizard.waypoints.filter(Boolean));
    }, 600);
}

// ── Build ──
function preflightCheck() {
    if (!wizard.vehicle) return { title: 'Не выбран транспорт', body: 'Перед расчётом маршрута нужно выбрать или создать профиль грузовика.', fixStep: 1 };
    if (!wizard.origin)  return { title: 'Не указана точка отправления', body: 'Введите адрес начала маршрута или выберите его на карте.', fixStep: 3 };
    if (!wizard.destination) return { title: 'Не указана точка назначения', body: 'Введите конечный адрес или выберите его на карте.', fixStep: 3 };
    return null;
}

async function buildRoute() {
    if (building.value) return;
    buildError.value = null;
    const pre = preflightCheck();
    if (pre) { buildError.value = pre; return; }

    building.value = true;
    try {
        const { data } = await axios.post('/api/v1/routes', wizard.apiPayload, { silent: true });
        const id = data.data?.id ?? data.id;
        ui.success({ title: 'Маршрут построен', body: `${data.data?.distance_km ?? data.distance_km ?? ''} км — открываем детали...` });
        wizard.reset();
        router.push({ name: 'route-detail', params: { id } });
    } catch (e) {
        const info = explainError(e);
        const fields = info.fields ?? {};
        const items = [];
        if (fields.origin) items.push(`Откуда: ${fields.origin}`);
        if (fields.destination) items.push(`Куда: ${fields.destination}`);
        if (fields.start_fuel_l) items.push(`Топливо при старте: ${fields.start_fuel_l}`);
        if (fields.vehicle || fields.vehicle_id) items.push('Не выбран профиль ТС');
        let fixStep = null;
        if (fields.vehicle || fields.vehicle_id || fields.start_fuel_l) fixStep = 1;
        else if (fields.cargo) fixStep = 2;
        else if (fields.origin || fields.destination || Object.keys(fields).some(k => k.startsWith('via'))) fixStep = 3;
        buildError.value = { title: info.title, body: items.length ? '' : info.body, items, hint: info.hint || 'Проверьте, что адреса введены корректно и доступен сервис маршрутов.', fixStep };
    } finally {
        building.value = false;
    }
}

function formatDatetime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('ru') + ' ' + d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
}
</script>

<style>
/* ── Wizard page layout — mobile first ── */
.wizard-page { display: grid; grid-template-columns: 1fr; align-items: stretch; overflow-x: hidden; }
.wizard-panel { display: flex; flex-direction: column; padding: 28px 24px 20px; border-right: 1px solid var(--border); overflow-y: auto; }
.wizard-map-wrap { position: relative; height: 300px; order: -1; overflow: hidden; background: var(--s1); }

@media (min-width: 901px) {
    .wizard-page { grid-template-columns: 460px 1fr; min-height: calc(100dvh - var(--header-h)); padding-top: var(--header-h); overflow-x: unset; }
    .wizard-panel { max-height: calc(100dvh - var(--header-h)); position: sticky; top: var(--header-h); }
    .wizard-map-wrap { position: sticky; top: var(--header-h); height: calc(100dvh - var(--header-h)); order: 0; overflow: visible; }
}
.wizard-map { width: 100%; height: 100%; }

.wizard-map-hint { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--glass-modal); backdrop-filter: blur(16px); border: 1px solid var(--border-mid); border-radius: 20px; padding: 7px 16px; font-size: 12px; color: var(--text-2); pointer-events: none; white-space: nowrap; box-shadow: var(--shadow-md); transition: opacity .2s; }

/* ── Steps ── */
.wizard-steps { display: flex; gap: 4px; margin-bottom: 24px; flex-shrink: 0; }
.wizard-step-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; background: none; border: 1px solid var(--border); border-radius: 8px; padding: 7px 4px; cursor: default; min-height: auto; box-shadow: none; transition: background .15s, border-color .15s; }
.wizard-step-btn.is-done  { border-color: var(--green); background: rgba(45,122,79,.07); cursor: pointer; }
.wizard-step-btn.is-active { border-color: var(--accent); background: var(--accent-bg); }
.wizard-step-num   { font-family: var(--font-m); font-size: 13px; font-weight: 600; color: var(--text-3); }
.wizard-step-btn.is-active .wizard-step-num { color: var(--accent); }
.wizard-step-btn.is-done   .wizard-step-num { color: var(--green); }
.wizard-step-label { font-size: 10px; color: var(--text-3); letter-spacing: .03em; }

.wizard-body { flex: 1; overflow-y: auto; padding-bottom: 12px; }
.wizard-body h2 { font-size: 20px; margin-bottom: 6px; }
.wizard-form-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 600px) { .wizard-form-2 { grid-template-columns: 1fr; } }
.preferences-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 22px;
}
.preferences-grid .field {
    min-width: 0;
}
.preferences-grid .field > label {
    min-height: 32px;
    display: flex;
    align-items: flex-end;
}
.preference-range {
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s1);
}
.preference-range--wide {
    grid-column: 1 / -1;
}
.preference-range__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.preference-range__head label {
    margin: 0;
}
.preference-range output {
    flex: 0 0 auto;
    padding: 3px 7px;
    border-radius: 4px;
    background: var(--accent-bg);
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 12px;
}
.preference-range input[type="range"] {
    width: 100%;
    margin: 14px 0 7px;
    accent-color: var(--accent);
}
.preference-range__scale {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-3);
    font-size: 10px;
}
.preference-toggle {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 13px 14px;
    border-top: 1px solid var(--border);
}
.preference-toggle h3 {
    font-family: var(--font-s);
    font-size: 14px;
    font-weight: 600;
}
.preference-toggle p {
    margin-top: 3px !important;
}
@media (max-width: 600px) {
    .preferences-grid { grid-template-columns: 1fr; }
    .preference-range--wide { grid-column: auto; }
}
.wizard-route-fields { display: flex; flex-direction: column; gap: 14px; }
.wizard-nav { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--border); margin-top: 8px; flex-shrink: 0; }

/* ── Vehicle cards ── */
.vehicle-list { display: flex; flex-direction: column; gap: 8px; }
.vehicle-card { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--s1); cursor: pointer; text-align: left; transition: border-color .15s, background .15s; min-height: auto; box-shadow: none; }
.vehicle-card:hover { border-color: var(--border-mid); background: var(--s2); transform: none; }
.vehicle-card.is-selected { border-color: var(--accent); background: var(--accent-bg); }
.vehicle-card--add { justify-content: center; border-style: dashed; color: var(--accent); font-size: 13px; }
.vehicle-card__info { display: flex; flex-direction: column; gap: 2px; }
.vehicle-card__info strong { font-size: 13px; color: var(--text); }
.vehicle-card__info span   { font-size: 12px; color: var(--text-2); }
.vehicle-card__check { color: var(--accent); font-size: 18px; }
.inline-card { border: 1px solid var(--border-a); border-radius: 8px; padding: 14px; background: var(--s1); }
.waypoint-row { display: flex; align-items: stretch; gap: 6px; margin-top: 6px; }
.waypoint-row .geo-input { flex: 1; }
.pick-map-btn { display: inline-flex; align-items: center; gap: 4px; background: none; border: 1px dashed var(--border-mid); color: var(--text-3); border-radius: 5px; font-size: 11px; padding: 5px 10px; cursor: pointer; margin-top: 5px; min-height: auto; box-shadow: none; transition: color .15s, border-color .15s, background .15s; }
.pick-map-btn:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-bg); transform: none; box-shadow: none; }
.pick-map-btn.is-picking { color: var(--red); border-color: var(--red); background: rgba(192,49,42,.07); border-style: solid; }
.pick-map-btn--sm { padding: 8px 10px; margin-top: 0; font-size: 14px; align-self: stretch; }
.summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.summary-item { padding: 9px 12px; background: var(--s1); border: 1px solid var(--border); border-radius: 6px; display: flex; flex-direction: column; gap: 3px; }
.summary-label { font-size: 10px; color: var(--text-3); letter-spacing: .04em; text-transform: uppercase; }
.summary-value { font-size: 13px; color: var(--text); font-weight: 500; word-break: break-word; }

/* ── Step 5: Selected POI list ── */
.selected-pois-list { margin-top: 14px; display: flex; flex-direction: column; gap: 6px; }

.selected-poi-card {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border: 1px solid var(--green); border-radius: 8px;
    background: rgba(45,122,79,.06);
}
.selected-poi-card__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.selected-poi-card__info strong { font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.selected-poi-card__info span { font-size: 11px; color: var(--text-3); font-family: var(--font-m); }
.selected-poi-card__order { display: flex; flex-direction: column; gap: 3px; flex-shrink: 0; }
.selected-poi-card__order button {
    width: 24px; height: 22px; min-height: 0; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); border-radius: 5px;
    background: var(--s1); color: var(--text-2);
    box-shadow: none; transform: none; cursor: pointer;
}
.selected-poi-card__order button:hover { border-color: var(--border-mid); background: var(--s2); transform: none; box-shadow: none; }
.selected-poi-card__order button:disabled { opacity: .35; cursor: not-allowed; }
.selected-poi-card__route-toggle {
    display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;
    font-size: 11px; color: var(--text-2); cursor: pointer; user-select: none;
}
.selected-poi-card__route-toggle input { width: 14px; height: 14px; accent-color: var(--accent); }
.selected-poi-card__route-toggle.is-disabled { opacity: .45; cursor: not-allowed; }
.selected-poi-card__remove {
    background: none; border: none; color: var(--red); cursor: pointer;
    font-size: 14px; padding: 4px; min-height: auto; box-shadow: none; flex-shrink: 0;
}
.selected-poi-card__remove:hover { transform: none; box-shadow: none; }

.selected-pois-empty { font-size: 13px; color: var(--text-3); text-align: center; padding: 20px 0; border: 1px dashed var(--border); border-radius: 8px; }

/* ── POI Legend ── */
.poi-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 11px; color: var(--text-2); }
.poi-legend-item { display: flex; align-items: center; gap: 4px; }
.poi-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

/* ── POI Popup ── */
.poi-popup-overlay {
    position: fixed; inset: 0; z-index: 500;
    background: var(--glass-backdrop); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.poi-popup-panel {
    background: var(--glass-modal); border: 1px solid var(--border-mid);
    border-radius: 12px; box-shadow: var(--shadow-xl);
    max-width: 480px; width: 100%; max-height: 80dvh; overflow-y: auto;
    padding: 20px; position: relative;
}
.poi-popup-close {
    position: absolute; top: 10px; right: 12px;
    background: none; border: none; color: var(--text-3); font-size: 16px;
    cursor: pointer; min-height: auto; box-shadow: none; padding: 4px;
}
.poi-popup-close:hover { color: var(--text); transform: none; }
.poi-popup-image { height: 180px; border-radius: 8px; overflow: hidden; margin-bottom: 14px; }
.poi-popup-image img { width: 100%; height: 100%; object-fit: cover; }
.poi-popup-details { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; font-size: 12px; font-family: var(--font-m); color: var(--text-3); }
.poi-popup-content { margin-top: 14px; padding: 0 !important; font-size: 13px; line-height: 1.6; }
.poi-popup-actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
</style>
