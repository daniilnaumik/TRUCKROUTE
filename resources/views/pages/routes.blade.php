@extends('layouts.app')

@section('title', 'TruckRoute - маршруты')

@push('styles')
<style>
.route-split { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start; }
@media (max-width: 900px) { .route-split { grid-template-columns: 1fr; } }
#routeMap { height: 520px; border-radius: 8px; overflow: hidden; position: sticky; top: 80px; }
#routeEventsPanel { margin-top: 20px; }
.route-submit-btn .spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: 6px; }
.route-submit-btn.is-loading .spinner { display: inline-block; }
.route-submit-btn.is-loading span { opacity: .7; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
    @php
        $field = fn (string $name, mixed $default = '') => old($name, $routePlan?->{$name} ?? $default);
        $selected = fn (string $name, string $value, mixed $default = '') => (string) old($name, $routePlan?->{$name} ?? $default) === $value;
        $truckCatalog = $truckCatalog ?? config('truck_models');
        $truckImages = config('truck_images.brands');
        $truckModelImages = $truckModelImages ?? config('truck_images.models', []);
        $fallbackTruckImage = config('truck_images.fallback', 'truck-white.jpg');
        $firstBrand = array_key_first($truckCatalog);
        $firstModel = array_key_first($truckCatalog[$firstBrand]);
        $modelExists = function (?string $model) use ($truckCatalog) {
            foreach ($truckCatalog as $models) {
                if ($model && isset($models[$model])) {
                    return true;
                }
            }

            return false;
        };
        $currentVehicleModel = old('vehicle_model', $modelExists($vehicle?->model) ? $vehicle?->model : $firstModel);
        $currentVehicleBrand = $firstBrand;
        $findTruck = function (string $model) use ($truckCatalog) {
            foreach ($truckCatalog as $brand => $models) {
                if (isset($models[$model])) {
                    return ['brand' => $brand, 'specs' => $models[$model]];
                }
            }

            $firstModels = collect($truckCatalog)->first();

            return $firstModels ? ['brand' => array_key_first($truckCatalog), 'specs' => $firstModels[array_key_first($firstModels)]] : ['brand' => null, 'specs' => []];
        };
        $truckImageForModel = function (?string $model, ?string $brand = null) use ($truckCatalog, $truckImages, $truckModelImages, $fallbackTruckImage) {
            if ($model && isset($truckModelImages[$model])) {
                return $truckModelImages[$model];
            }

            if (!$brand && $model) {
                foreach ($truckCatalog as $candidateBrand => $models) {
                    if (isset($models[$model])) {
                        $brand = $candidateBrand;
                        break;
                    }
                }
            }

            return ($brand && isset($truckImages[$brand])) ? $truckImages[$brand] : $fallbackTruckImage;
        };
        $truckCurbWeight = function (?string $type) {
            $type = mb_strtolower((string) $type);

            return match (true) {
                str_contains($type, 'фургон') => 7.5,
                str_contains($type, 'одиночка') => 12.0,
                str_contains($type, 'реф') || str_contains($type, 'рефрижератор') => 17.0,
                str_contains($type, 'цистерна') => 18.0,
                default => 15.5,
            };
        };
        $initialTruckData = $findTruck($currentVehicleModel);
        $currentVehicleBrand = $initialTruckData['brand'] ?? $currentVehicleBrand;
        $initialTruck = $initialTruckData['specs'] ?? [];
        $initialTruckImage = $truckImageForModel($currentVehicleModel, $currentVehicleBrand);
        $initialCurbWeight = old('vehicle_curb_weight_t', $vehicle?->curb_weight_t ?? $routePlan?->vehicle_curb_weight_t ?? $truckCurbWeight($initialTruck['type'] ?? null));
        $startTime = old('start_time', optional($routePlan?->start_time)->format('Y-m-d\TH:i'));
        $routeImage = $routePlan?->image ?: 'road-green-forest.jpg';
        $tripMinutes = (int) ($routePlan?->drive_time_minutes ?? 560);
        $routePoints = collect($routePlan?->recommendationsList ?? []);
        $recommendations = $routePlan?->recommendations
            ?: 'Введите маршрут, выберите модель фуры и нажмите "Построить маршрут", чтобы получить расчет топлива, времени и точек остановки.';
        $navigationData = [
            'routes' => collect($routePresets ?? config('truck_routes'))->values(),
            'restObjects' => collect($restObjects ?? [])->map(fn ($object) => [
                'id' => $object->id,
                'name' => $object->name,
                'type' => $object->type,
                'highway' => $object->highway,
                'km_marker' => $object->km_marker,
                'location' => $object->location,
                'services' => $object->services,
                'rating' => $object->rating,
                'detour_km' => $object->detour_km,
                'has_truck_parking' => $object->has_truck_parking,
            ])->values(),
        ];
    @endphp

    <datalist id="route-cities">
        <option value="Москва">
        <option value="Воронеж">
        <option value="Ростов-на-Дону">
        <option value="Краснодар">
        <option value="Казань">
        <option value="Ульяновск">
        <option value="Самара">
        <option value="Тамбов">
        <option value="Волгоград">
    </datalist>

    <section class="page-hero">
        <div class="container">
            <div>
                <h1>Построение маршрута</h1>
                <p class="lead">Выберите направление и модель фуры. Характеристики транспорта подставятся автоматически, а система рассчитает расход, прибытие, АЗС и остановки.</p>
                <div class="actions">
                    <a class="btn requires-auth" href="#route-form">Построить</a>
                    <a class="btn outline" href="#calculations">Расчеты</a>
                </div>
            </div>
            <div class="page-visual">
                <img src="{{ asset('assets/images/' . $routeImage) }}" alt="Дорога для маршрута">
            </div>
        </div>
    </section>

    <section class="section-tight" id="route-form">
        <div class="container">
            <h2>Расчет маршрута</h2>
            <div class="route-split" style="margin-top: 36px;">
            <div class="route-form-col">

            @if(session('success') || $errors->any())
                <div class="grid-2" style="margin-top: 26px;">
                    @if(session('success'))
                        <div class="card">
                            <h3>Маршрут готов</h3>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="card">
                            <h3>Проверьте данные</h3>
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <form id="route-calculation-form" class="form-grid" method="POST" action="{{ route('routes.calculate') }}">
                @csrf
                <input type="hidden" name="via_point" data-route-via value="{{ $field('via_point', 'Воронеж') }}">
                <input type="hidden" name="distance_km" data-route-distance value="{{ $field('distance_km', '') }}">
                <input type="hidden" name="include_rest_stop" data-include-rest value="">
                <input type="hidden" name="selected_rest_object_id" data-selected-rest value="">
                <input type="hidden" name="continuous_drive_hours" data-rest-hours value="4">
                <input type="hidden" name="lodging_type" data-lodging-type value="Стоянка">
                <input type="hidden" name="night_budget_rub" value="{{ old('night_budget_rub', 3000) }}">

                <h3 style="grid-column: 1 / -1;">Куда едем</h3>
                <div class="field">
                    <label>Точка A</label>
                    <input name="origin" list="route-cities" value="{{ $field('origin', 'Москва') }}" required>
                </div>
                <div class="field">
                    <label>Точка B</label>
                    <input name="destination" list="route-cities" value="{{ $field('destination', 'Ростов-на-Дону') }}" required>
                </div>
                <div class="field">
                    <label>Дата и время старта</label>
                    <input name="start_time" type="datetime-local" value="{{ $startTime }}">
                </div>
                <div class="field">
                    <label>Топливо на старте, л</label>
                    <input name="start_fuel_l" type="number" min="0" max="2000" step="1" value="{{ $field('start_fuel_l', 420) }}" required>
                </div>

                <input type="hidden" name="vehicle_model" value="{{ $currentVehicleModel }}">
                <input type="hidden" name="vehicle_type" value="{{ old('vehicle_type', $vehicle?->type ?? $initialTruck['type'] ?? 'Тягач + полуприцеп') }}">
                <input type="hidden" name="fuel_type" value="{{ old('fuel_type', $vehicle?->fuel_type ?? $initialTruck['fuel_type'] ?? 'Дизель') }}">
                <input type="hidden" name="allowed_fuel" value="{{ old('allowed_fuel', $vehicle?->allowed_fuel ?? $initialTruck['allowed_fuel'] ?? 'Дизель + AdBlue') }}">
                <input type="hidden" name="tank_capacity_l" value="{{ old('tank_capacity_l', $vehicle?->tank_capacity_l ?? $initialTruck['tank_capacity_l'] ?? 600) }}">
                <input type="hidden" name="consumption_l_per_100" value="{{ old('consumption_l_per_100', $vehicle?->consumption_l_per_100 ?? $initialTruck['consumption_l_per_100'] ?? 29) }}">
                <input type="hidden" name="cruise_speed_kmh" value="{{ old('cruise_speed_kmh', $vehicle?->cruise_speed_kmh ?? $initialTruck['cruise_speed_kmh'] ?? 85) }}">
                <input type="hidden" name="restrictions" value="{{ old('restrictions', $vehicle?->restrictions ?? $initialTruck['restrictions'] ?? 'Стандартная еврофура') }}">
                <input type="hidden" name="vehicle_curb_weight_t" value="{{ $initialCurbWeight }}">

                <div class="route-vehicle-summary">
                    <div class="route-vehicle-summary__image">
                        <img src="{{ asset('assets/images/' . $initialTruckImage) }}" alt="Выбранная фура">
                    </div>
                    <div>
                        <span class="badge">выбранная фура</span>
                        <h3>{{ $currentVehicleModel }}</h3>
                        <p>
                            {{ $vehicle?->type ?? $initialTruck['type'] ?? 'Тягач + полуприцеп' }}.
                            Бак {{ $vehicle?->tank_capacity_l ?? $initialTruck['tank_capacity_l'] ?? 600 }} л,
                            расход {{ $vehicle?->consumption_l_per_100 ?? $initialTruck['consumption_l_per_100'] ?? 29 }} л / 100 км,
                            масса {{ $initialCurbWeight }} т.
                        </p>
                        <p class="small">Поменять фуру можно в профиле. Этот маршрут будет рассчитан по сохраненным характеристикам выбранного автомобиля.</p>
                        <div class="actions">
                            <a class="btn outline" href="{{ route('profile') }}#profile-vehicle">Сменить автомобиль</a>
                        </div>
                    </div>
                </div>

                <h3 style="grid-column: 1 / -1; margin-top: 18px;">Груз и остановки</h3>
                <div class="field">
                    <label>Вес груза, т</label>
                    <input name="cargo_weight_t" type="number" min="0" max="45" step="0.1" value="{{ old('cargo_weight_t', $routePlan?->cargo_weight_t ?? 12) }}" required>
                </div>
                <div class="field">
                    <label>Особенность груза</label>
                    <select name="cargo_flag" required>
                        @foreach(['Нет', 'Опасный', 'Негабарит'] as $flag)
                            <option value="{{ $flag }}" @selected(old('cargo_flag', 'Нет') === $flag)>{{ $flag }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Требования груза</label>
                    <select name="cargo_requirements" required>
                        @foreach(['Нет', 'Температурный режим', 'Усиленный контроль', 'Охраняемая стоянка'] as $requirement)
                            <option value="{{ $requirement }}" @selected(old('cargo_requirements', 'Нет') === $requirement)>{{ $requirement }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Режим расчета</label>
                    <select name="planning_mode" required>
                        @foreach(['Безопасный', 'Экономный'] as $mode)
                            <option value="{{ $mode }}" @selected($selected('planning_mode', $mode, 'Безопасный'))>{{ $mode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Минимальный остаток</label>
                    <select name="reserve_percent" required>
                        @foreach([10, 15, 20, 25, 30] as $reserve)
                            <option value="{{ $reserve }}" @selected((int) old('reserve_percent', $routePlan?->reserve_percent ?? 15) === $reserve)>{{ $reserve }}%</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>АЗС</label>
                    <select name="preferred_fuel_brand" required>
                        @foreach(['Любые', 'Лукойл', 'Газпромнефть', 'Роснефть', 'Татнефть'] as $brand)
                            <option value="{{ $brand }}" @selected(old('preferred_fuel_brand', 'Любые') === $brand)>{{ $brand }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Платные дороги</label>
                    <select name="no_toll_roads" required>
                        <option value="Нет" @selected(old('no_toll_roads', 'Нет') === 'Нет')>Разрешены</option>
                        <option value="Да" @selected(old('no_toll_roads', 'Нет') === 'Да')>Запрещены</option>
                    </select>
                </div>
            </form>

            <div class="actions">
                <button type="submit" form="route-calculation-form" class="btn requires-auth route-submit-btn" id="routeSubmitBtn">
                    <svg class="spinner" viewBox="0 0 16 16"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="28" stroke-dashoffset="10"/></svg>
                    <span>Построить маршрут</span>
                </button>
                <button type="reset" form="route-calculation-form" class="btn outline">Очистить</button>
            </div>
            </div>{{-- /route-form-col --}}

            <div class="route-map-col">
                <div id="routeMap"></div>
                <div id="routeEventsPanel" hidden>
                    <h3 style="margin-top:16px;">События на маршруте</h3>
                    <ul id="routeEventsList" style="list-style:none;padding:0;margin:12px 0 0;display:flex;flex-direction:column;gap:8px;"></ul>
                </div>
            </div>
            </div>{{-- /route-split --}}
        </div>
    </section>

    <div class="auth-modal" id="restPlanningModal" aria-hidden="true">
        <div class="auth-modal__backdrop" data-rest-close></div>
        <div class="auth-modal__panel" role="dialog" aria-modal="true" aria-labelledby="restPlanningTitle">
            <button class="auth-modal__close" type="button" data-rest-close>закрыть</button>
            <span class="badge">остановка в пути</span>
            <h2 id="restPlanningTitle">Запланировать отдых?</h2>
            <p class="lead">TruckRoute может подобрать места отдыха по вашему направлению и показать, через сколько вы будете рядом с каждой точкой.</p>

            <div class="actions">
                <button type="button" data-rest-show>Подобрать остановку</button>
                <button type="button" class="btn outline" data-rest-skip>Пропустить</button>
            </div>

            <div data-rest-list hidden style="margin-top: 28px;">
                <h3>Подходящие места</h3>
                <div data-rest-choices style="display: grid; gap: 14px; margin-top: 18px;"></div>
                <div class="actions">
                    <button type="button" data-rest-submit>Выбрать и построить</button>
                    <button type="button" class="btn outline" data-rest-skip>Без остановки</button>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="routeNavigatorData">{!! json_encode($navigationData, JSON_UNESCAPED_UNICODE) !!}</script>

    <section class="section" id="calculations">
        <div class="container">
            <h2>Главные расчеты</h2>
            <div class="calculation-summary">
                <div>
                    <span>Дистанция</span>
                    <strong>{{ $routePlan?->distance_km ?? 740 }} км</strong>
                </div>
                <div>
                    <span>Время</span>
                    <strong>{{ intdiv($tripMinutes, 60) }} ч {{ $tripMinutes % 60 }} мин</strong>
                </div>
                <div>
                    <span>Прибытие</span>
                    <strong>{{ $routePlan?->arrival_time?->format('d.m.Y H:i') ?? 'после расчета' }}</strong>
                </div>
                <div>
                    <span>Расход</span>
                    <strong>{{ $routePlan?->effective_consumption_l_per_100 ?? $routePlan?->consumption_l_per_100 ?? 29 }} л / 100 км</strong>
                </div>
                <div>
                    <span>Масса рейса</span>
                    <strong>{{ $routePlan?->gross_weight_t ? number_format((float) $routePlan->gross_weight_t, 1, '.', ' ') . ' т' : 'после расчета' }}</strong>
                </div>
                <div>
                    <span>Топливо</span>
                    <strong>{{ $routePlan?->fuel_needed_l ?? 215 }} л</strong>
                </div>
                <div>
                    <span>Стоимость</span>
                    <strong>{{ $routePlan?->fuel_cost_rub ? number_format((float) $routePlan->fuel_cost_rub, 0, '.', ' ') . ' руб.' : 'после расчета' }}</strong>
                </div>
                <div>
                    <span>Запас хода</span>
                    <strong>{{ $routePlan?->range_km ?? 410 }} км</strong>
                </div>
                <div>
                    <span>Точки</span>
                    <strong>{{ $routePlan?->stops_count ?? 4 }}</strong>
                </div>
            </div>

            <div class="grid-2" style="margin-top: 26px;">
                <div class="card">
                    <h3>Рекомендации по рейсу</h3>
                    <p>{!! nl2br(e($recommendations)) !!}</p>
                </div>
                <div class="card">
                    <h3>События рядом</h3>
                    @foreach($events as $event)
                        <p>{{ $event->title }} - {{ $event->location }}</p>
                    @endforeach
                </div>
            </div>

            <h2 style="margin-top: 72px;">АЗС и остановки</h2>
            @if($routePoints->isNotEmpty())
                <table class="table" style="margin-top: 36px;">
                    <thead>
                        <tr>
                            <th>Тип</th>
                            <th>Точка</th>
                            <th>Км</th>
                            <th>ETA</th>
                            <th>Топливо</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($routePoints as $point)
                            <tr>
                                <td>{{ $point->type }}</td>
                                <td>
                                    {{ $point->serviceObject?->name ?? 'Точка маршрута' }}
                                    <br>
                                    <span class="small">{{ $point->serviceObject?->location }} / detour {{ $point->detour_km }} км</span>
                                </td>
                                <td>{{ $point->distance_from_start_km }} км</td>
                                <td>{{ $point->eta_at?->format('H:i') ?? '-' }}</td>
                                <td>
                                    @if($point->fuel_before_l !== null)
                                        остаток {{ $point->fuel_before_l }} л
                                        @if($point->suggested_fuel_l)
                                            <br>залить {{ $point->suggested_fuel_l }} л
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $point->note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="card" style="margin-top: 36px;">
                    <h3>Точки появятся после расчета</h3>
                    <p>После построения маршрута здесь будут АЗС, стоянки и ночлег с километром маршрута, временем прибытия и остатком топлива.</p>
                </div>
            @endif

            <div class="actions">
                <button type="submit" form="route-calculation-form" class="requires-auth">Сохранить маршрут</button>
            </div>
        </div>
    </section>
@push('scripts')
<script>
(function () {
    var mapEl = document.getElementById('routeMap');
    if (!mapEl || !window.L) return;

    var map = L.map('routeMap');
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com/">CARTO</a>',
        maxZoom: 19,
    }).addTo(map);
    map.setView([53.9023, 27.5619], 6);

    var routeLayer = null;
    var poiLayer = L.layerGroup().addTo(map);
    var eventLayer = L.layerGroup().addTo(map);

    var poiColors = { 'АЗС': '#2ecc71', 'Стоянка': '#3498db', 'Ночлег': '#e67e22', 'СТО': '#95a5a6' };

    function getCsrf() {
        var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    }

    // Рисуем маршрут если уже есть polyline_json
    @if($routePlan && $routePlan->polyline_json)
    try {
        var coords = JSON.parse(@json($routePlan->polyline_json));
        if (coords && coords.length) {
            routeLayer = L.polyline(coords, { color: '#3498db', weight: 4, opacity: .85 }).addTo(map);
            map.fitBounds(routeLayer.getBounds(), { padding: [30, 30] });
        }
    } catch(e) {}
    @else
    map.setView([53.9023, 27.5619], 6);
    @endif

    // POI маркеры из navigationData
    try {
        var navData = JSON.parse(document.getElementById('routeNavigatorData').textContent);
        (navData.restObjects || []).forEach(function (obj) {
            if (!obj.lat && !obj.lng) return;
            var color = poiColors[obj.type] || '#aaa';
            var icon = L.divIcon({
                className: '',
                html: '<div style="width:12px;height:12px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>',
                iconSize: [12, 12], iconAnchor: [6, 6],
            });
            L.marker([obj.lat, obj.lng], { icon: icon })
                .bindPopup('<strong>' + obj.name + '</strong><br>' + obj.type + (obj.location ? '<br>' + obj.location : ''))
                .addTo(poiLayer);
        });
    } catch(e) {}

    // Клик по карте → reverse geocode → заполнить поле
    var clickMode = 'origin';
    map.on('click', function (e) {
        var lat = e.latlng.lat.toFixed(6), lng = e.latlng.lng.toFixed(6);
        var fieldName = clickMode === 'origin' ? 'origin' : 'destination';
        var input = document.querySelector('[name="' + fieldName + '"]');
        if (!input) return;
        input.value = 'Загрузка...';
        fetch('/api/v1/geo/reverse?lat=' + lat + '&lng=' + lng, {
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            input.value = (data.data && data.data.display_name) ? data.data.display_name : lat + ', ' + lng;
        })
        .catch(function () { input.value = lat + ', ' + lng; });
        clickMode = clickMode === 'origin' ? 'destination' : 'origin';
    });

    // Кнопка submit — спиннер
    var submitBtn = document.getElementById('routeSubmitBtn');
    var form = document.getElementById('route-calculation-form');
    if (submitBtn && form) {
        form.addEventListener('submit', function () {
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
        });
    }

    // Geocode autocomplete на полях origin/destination
    ['origin', 'destination'].forEach(function (name) {
        var input = document.querySelector('[name="' + name + '"]');
        if (input && window.GeocodeAutocomplete) {
            new GeocodeAutocomplete(input);
        }
    });
})();
</script>
@endpush
@endsection
