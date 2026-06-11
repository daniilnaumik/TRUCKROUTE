@extends('layouts.app')

@section('title', 'TruckRoute - профиль')

@section('content')
    @php
        $truckCatalog = $truckCatalog ?? config('truck_models');
        $truckImages = $truckImages ?? config('truck_images.brands');
        $truckModelImages = $truckModelImages ?? config('truck_images.models', []);
        $fallbackTruckImage = config('truck_images.fallback', 'truck-white.jpg');
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
        $modelExists = function (?string $model) use ($truckCatalog) {
            foreach ($truckCatalog as $models) {
                if ($model && isset($models[$model])) {
                    return true;
                }
            }

            return false;
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
        $firstBrand = array_key_first($truckCatalog);
        $firstModel = array_key_first($truckCatalog[$firstBrand]);
        $currentModel = old('vehicle_model', $modelExists($vehicle?->model) ? $vehicle?->model : $firstModel);
        $currentBrand = $firstBrand;
        $currentSpecs = $truckCatalog[$firstBrand][$firstModel];

        foreach ($truckCatalog as $brand => $models) {
            if (isset($models[$currentModel])) {
                $currentBrand = $brand;
                $currentSpecs = $models[$currentModel];
                break;
            }
        }

        $currentImage = $truckImageForModel($currentModel, $currentBrand);
        $currentCurbWeight = old('vehicle_curb_weight_t', $vehicle?->curb_weight_t ?? $truckCurbWeight($currentSpecs['type'] ?? null));
        $avatarUrl = $user?->avatar ? asset('storage/' . $user->avatar) : null;
    @endphp

    <section class="page-hero">
        <div class="container profile-head">
            <div>
                <h1>Профиль водителя</h1>
                <p class="lead">Личные данные, сохраненные маршруты и транспортный профиль, по которому TruckRoute будет считать расход, запас хода и остановки.</p>
                <div class="actions">
                    <button class="btn" type="button" data-profile-edit-open>Редактировать данные</button>
                    <button class="btn outline" type="button" data-truck-select-open>Выбрать фуру</button>
                    <a class="btn outline" href="{{ route('profile.notifications') }}">
                        Уведомления{{ $unreadNotificationsCount ? ' (' . $unreadNotificationsCount . ')' : '' }}
                    </a>
                </div>
            </div>
            <div style="display: flex; gap: 32px; align-items: end;">
                <form class="avatar-form" method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" data-avatar-form>
                    @csrf
                    <label class="avatar avatar-upload" title="Выбрать аватарку">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="Аватар пользователя">
                        @else
                            <span>Выбрать аватарку</span>
                        @endif
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input>
                    </label>
                </form>
                <div>
                    <h3>{{ $user?->name ?? 'Водитель' }}</h3>
                    <p class="small">роль: {{ $user?->role ?? 'driver' }}</p>
                    <p class="small">статус: {{ $user?->status ?? 'active' }}</p>
                </div>
            </div>
        </div>
    </section>

    @if($errors->any())
        <section class="section-tight">
            <div class="container">
                <div class="card card-error">
                    <h3>Проверьте данные</h3>
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div class="auth-modal profile-edit-modal" id="profileEditModal" aria-hidden="true">
        <div class="auth-modal__backdrop" data-profile-edit-close></div>
        <div class="auth-modal__panel profile-edit-modal__panel" role="dialog" aria-modal="true" aria-labelledby="profileEditTitle">
            <button class="auth-modal__close" type="button" data-profile-edit-close>закрыть</button>
            <span class="badge">профиль</span>
            <h2 id="profileEditTitle">Редактировать данные</h2>
            <form class="profile-edit-form" method="POST" action="{{ route('profile.update') }}">
                @csrf
                <div class="field">
                    <label>Имя</label>
                    <input name="name" value="{{ old('name', $user?->name) }}" required>
                </div>
                <div class="field">
                    <label>Телефон</label>
                    <input name="phone" value="{{ old('phone', $user?->phone) }}">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input name="email" type="email" value="{{ old('email', $user?->email) }}" required>
                </div>
                <div class="actions">
                    <button type="submit">Сохранить</button>
                    <button type="button" class="btn outline" data-profile-edit-close>Отменить</button>
                </div>
            </form>
        </div>
    </div>

    <div class="auth-modal truck-select-modal" id="truckSelectModal" aria-hidden="true">
        <div class="auth-modal__backdrop" data-truck-select-close></div>
        <div class="auth-modal__panel truck-select-modal__panel" role="dialog" aria-modal="true" aria-labelledby="truckSelectTitle">
            <button class="auth-modal__close" type="button" data-truck-select-close>закрыть</button>
            <div class="truck-select-modal__header">
                <span class="badge">транспорт</span>
                <h2 id="truckSelectTitle">Выбрать фуру</h2>
                <p>Слева выберите модель из каталога. Справа сразу появятся фотография и характеристики, которые будут использоваться при расчете маршрута.</p>
            </div>

            <div class="vehicle-builder truck-select-modal__builder">
                <form class="vehicle-builder__catalog" method="POST" action="{{ route('profile.vehicle.store') }}" data-vehicle-profile-form>
                    @csrf

                    <div class="truck-catalog-list">
                        @foreach($truckCatalog as $brand => $models)
                            @php
                                $brandImage = $truckImages[$brand] ?? $fallbackTruckImage;
                            @endphp
                            <div class="truck-brand-section">
                                <div class="truck-brand-section__head">
                                    <span class="truck-brand-section__title">
                                        <span class="truck-brand-section__image">
                                            <img src="{{ asset('assets/images/' . $brandImage) }}" alt="{{ $brand }}">
                                        </span>
                                        <span>{{ $brand }}</span>
                                    </span>
                                    <span>{{ count($models) }} модели</span>
                                </div>
                                <div class="truck-model-list">
                                    @foreach($models as $model => $specs)
                                        @php
                                            $curbWeight = $truckCurbWeight($specs['type'] ?? null);
                                            $modelImage = $truckImageForModel($model, $brand);
                                        @endphp
                                        <label class="truck-model-option @if($currentModel === $model) is-selected @endif">
                                            <input
                                                type="radio"
                                                name="vehicle_model"
                                                value="{{ $model }}"
                                                data-truck-choice
                                                data-brand="{{ $brand }}"
                                                data-image="{{ asset('assets/images/' . $modelImage) }}"
                                                data-type="{{ $specs['type'] }}"
                                                data-fuel="{{ $specs['fuel_type'] }}"
                                                data-allowed-fuel="{{ $specs['allowed_fuel'] }}"
                                                data-tank="{{ $specs['tank_capacity_l'] }}"
                                                data-consumption="{{ $specs['consumption_l_per_100'] }}"
                                                data-speed="{{ $specs['cruise_speed_kmh'] }}"
                                                data-restrictions="{{ $specs['restrictions'] }}"
                                                data-curb-weight="{{ $curbWeight }}"
                                                @checked($currentModel === $model)
                                            >
                                            <span>
                                                <strong>{{ $model }}</strong>
                                                <small>{{ $specs['type'] }} / {{ $specs['tank_capacity_l'] }} л / {{ $specs['consumption_l_per_100'] }} л на 100 км</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <input type="hidden" name="vehicle_type" data-vehicle-type-input value="{{ old('vehicle_type', $currentSpecs['type']) }}">
                    <input type="hidden" name="fuel_type" data-fuel-type-input value="{{ old('fuel_type', $currentSpecs['fuel_type']) }}">
                    <input type="hidden" name="allowed_fuel" data-allowed-fuel-input value="{{ old('allowed_fuel', $currentSpecs['allowed_fuel']) }}">
                    <input type="hidden" name="tank_capacity_l" data-tank-input value="{{ old('tank_capacity_l', $currentSpecs['tank_capacity_l']) }}">
                    <input type="hidden" name="consumption_l_per_100" data-consumption-input value="{{ old('consumption_l_per_100', $currentSpecs['consumption_l_per_100']) }}">
                    <input type="hidden" name="cruise_speed_kmh" data-speed-input value="{{ old('cruise_speed_kmh', $currentSpecs['cruise_speed_kmh']) }}">
                    <input type="hidden" name="vehicle_curb_weight_t" data-curb-weight-input value="{{ $currentCurbWeight }}">
                    <input type="hidden" name="restrictions" data-restrictions-input value="{{ old('restrictions', $currentSpecs['restrictions']) }}">

                    <div class="actions">
                        <button type="submit">Сохранить выбранную фуру</button>
                    </div>
                </form>

                <aside class="vehicle-preview">
                    <div class="vehicle-preview__image">
                        <img data-truck-preview-image src="{{ asset('assets/images/' . $currentImage) }}" alt="Выбранная фура">
                    </div>
                    <span class="badge" data-truck-preview-brand>{{ $currentBrand }}</span>
                    <h3 data-truck-preview-model>{{ $currentModel }}</h3>
                    <p data-truck-preview-main>{{ $currentSpecs['type'] }}. Бак {{ $currentSpecs['tank_capacity_l'] }} л, расход {{ $currentSpecs['consumption_l_per_100'] }} л / 100 км.</p>
                    <dl class="vehicle-spec-list">
                        <div><dt>Двигатель</dt><dd data-truck-preview-fuel>{{ $currentSpecs['fuel_type'] }}</dd></div>
                        <div><dt>Топливо</dt><dd data-truck-preview-allowed>{{ $currentSpecs['allowed_fuel'] }}</dd></div>
                        <div><dt>Скорость</dt><dd data-truck-preview-speed>{{ $currentSpecs['cruise_speed_kmh'] }} км/ч</dd></div>
                        <div><dt>Масса</dt><dd data-truck-preview-weight>{{ $currentCurbWeight }} т</dd></div>
                        <div><dt>Ограничения</dt><dd data-truck-preview-restrictions>{{ $currentSpecs['restrictions'] }}</dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </div>

    <section class="section-tight profile-slide profile-slide--start" id="profile-vehicle">
        <div class="container">
            <h2>Сохраненные автомобили</h2>
            <p class="lead">Здесь остаются автомобили, которые вы уже добавили в профиль.</p>
            @if($vehicles->isNotEmpty())
                <div class="grid-3" style="margin-top: 36px;">
                    @foreach($vehicles as $savedVehicle)
                        @php
                            $savedVehicleImage = $truckImageForModel($savedVehicle->model, null);
                        @endphp
                        <article class="card saved-vehicle-card">
                            <div class="saved-vehicle-card__image">
                                <img src="{{ asset('assets/images/' . $savedVehicleImage) }}" alt="{{ $savedVehicle->model }}">
                            </div>
                            <h3>{{ $savedVehicle->model }}</h3>
                            <p>{{ $savedVehicle->type }}. Бак {{ $savedVehicle->tank_capacity_l }} л, расход {{ $savedVehicle->consumption_l_per_100 }} л / 100 км.</p>
                            <form method="POST" action="{{ route('profile.vehicle.select', $savedVehicle) }}">
                                @csrf
                                <button type="submit" class="{{ $savedVehicle->is_active ? 'btn outline' : '' }}">
                                    {{ $savedVehicle->is_active ? 'Выбрана' : 'Выбрать' }}
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @else
                <article class="card" style="margin-top: 36px;">
                    <h3>Сохраненных автомобилей пока нет</h3>
                    <p>Откройте каталог фур, выберите модель и сохраните ее в профиль.</p>
                    <div class="actions">
                        <button class="btn" type="button" data-truck-select-open>Открыть каталог</button>
                    </div>
                </article>
            @endif
        </div>
    </section>

    <section class="section-tight profile-slide profile-slide--end" id="profile-routes">
        <div class="container">
            <h2>Мои маршруты</h2>
            <div class="grid-3" style="margin-top: 36px;">
                @forelse($routes as $route)
                    <article class="card saved-route-card">
                        <h3>{{ $route->title }}</h3>
                        <p>{{ $route->distance_km }} км, {{ $route->stops_count }} остановки, {{ $route->fuel_needed_l }} л топлива.</p>
                        <div class="route-card-actions">
                            <button class="btn" type="button" data-route-detail-open="{{ $route->id }}">Открыть</button>
                            <a class="btn outline" href="{{ route('routes') }}#route-form">Повторить</a>
                            <form method="POST" action="{{ route('profile.routes.destroy', $route) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn outline danger" type="submit">Удалить</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <article class="card">
                        <h3>Маршрутов пока нет</h3>
                        <p>После построения рейса он появится здесь.</p>
                        <div class="actions">
                            <a class="btn" href="{{ route('routes') }}">Построить маршрут</a>
                        </div>
                    </article>
                @endforelse
            </div>
        </div>
    </section>

    @foreach($routes as $route)
        <div class="auth-modal route-detail-modal" id="routeDetailModal-{{ $route->id }}" data-route-detail-modal aria-hidden="true">
            <div class="auth-modal__backdrop" data-route-detail-close></div>
            <div class="auth-modal__panel route-detail-modal__panel" role="dialog" aria-modal="true" aria-labelledby="routeDetailTitle-{{ $route->id }}">
                <button class="auth-modal__close" type="button" data-route-detail-close>закрыть</button>
                <span class="badge">маршрут</span>
                <h2 id="routeDetailTitle-{{ $route->id }}">{{ $route->title }}</h2>
                <div class="route-detail-plain">
                    <div>
                        <span>Направление</span>
                        <p>{{ $route->origin }} - {{ $route->destination }}{{ $route->via_point ? ', через ' . $route->via_point : '' }}.</p>
                    </div>
                    <div>
                        <span>Время поездки</span>
                        <p>Старт: {{ $route->start_time?->format('d.m.Y H:i') ?? 'не указан' }}. Прибытие: {{ $route->arrival_time?->format('d.m.Y H:i') ?? 'не рассчитано' }}.</p>
                    </div>
                    <div>
                        <span>Транспорт</span>
                        <p>{{ $route->vehicle_type }}. Бак {{ $route->tank_capacity_l }} л, на старте {{ $route->start_fuel_l }} л, скорость {{ $route->cruise_speed_kmh }} км/ч.</p>
                    </div>
                    <div>
                        <span>Груз</span>
                        <p>{{ $route->cargo_type }}. Вес: {{ $route->cargo_weight_t ? $route->cargo_weight_t . ' т' : 'не указан' }}. Масса рейса: {{ $route->gross_weight_t ? $route->gross_weight_t . ' т' : 'после расчета' }}.</p>
                    </div>
                    <div>
                        <span>Расчет</span>
                        <p>{{ $route->distance_km }} км за {{ intdiv($route->drive_time_minutes, 60) }} ч {{ $route->drive_time_minutes % 60 }} мин. Расход: {{ $route->effective_consumption_l_per_100 ?? $route->consumption_l_per_100 }} л / 100 км.</p>
                    </div>
                    <div>
                        <span>Топливо</span>
                        <p>Нужно {{ $route->fuel_needed_l }} л. Резерв: {{ $route->reserve_percent }}%{{ $route->reserve_l ? ' / ' . $route->reserve_l . ' л' : '' }}. Запас хода: {{ $route->range_km }} км.</p>
                    </div>
                    <div>
                        <span>Итог</span>
                        <p>{{ $route->stops_count }} остановки. Режим: {{ $route->planning_mode }}. Стоимость: {{ $route->fuel_cost_rub ? number_format($route->fuel_cost_rub, 0, ',', ' ') . ' руб.' : 'нет данных' }}.</p>
                    </div>
                </div>
                <div class="route-detail-note">
                    <h3>Рекомендации</h3>
                    <p>{{ $route->recommendations }}</p>
                </div>
            </div>
        </div>
    @endforeach
@push('scripts')
<script>
(function () {
    var avatarInput = document.querySelector('[data-avatar-input]');
    var avatarForm = document.querySelector('[data-avatar-form]');
    var avatarLabel = document.querySelector('.avatar-upload');
    if (!avatarInput || !avatarLabel) return;

    avatarInput.addEventListener('change', function () {
        var file = avatarInput.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = avatarLabel.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = 'Аватар пользователя';
                avatarLabel.querySelector('span') && avatarLabel.querySelector('span').remove();
                avatarLabel.prepend(img);
            }
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        // Auto-submit after preview
        setTimeout(function () { avatarForm && avatarForm.submit(); }, 300);
    });
})();
</script>
@endpush
@endsection
