@extends('layouts.app')

@section('title', 'TruckRoute - новости дороги')

@section('content')
    <section class="page-hero">
        <div class="container">
            <div>
                <h1>Новости на дороге</h1>
                <p class="lead">Лента транспортных происшествий для водителей грузового транспорта: аварии, заторы, перекрытые полосы и опасные участки.</p>
                <div class="actions">
                    <a class="btn" href="#events">Смотреть сводку</a>
                    <a class="btn outline" href="#filters">Фильтры</a>
                </div>
            </div>
            <div class="page-visual">
                <img src="{{ asset('assets/images/trucks-night.jpg') }}" alt="Ночная стоянка грузовиков">
            </div>
        </div>
    </section>

    <section class="section-tight" id="news-map-section">
        <div class="container">
            <h2>Карта событий</h2>
            <div id="newsMap"></div>
        </div>
    </section>

    <section class="section-tight" id="events">
        <div class="container">
            <h2>Срочная сводка</h2>
            <div class="grid-3 equal-card-grid news-summary-grid" style="margin-top: 42px;">
                @foreach($events->take(3) as $event)
                    <article
                        class="card"
                        data-event-card
                        data-highway="{{ $event->highway }}"
                        data-type="{{ $event->type }}"
                        data-status="{{ $event->status }}"
                        data-search="{{ $event->title }} {{ $event->type }} {{ $event->highway }} {{ $event->location }} {{ $event->description }}"
                    >
                        <span class="badge">{{ $event->importance }}</span>
                        <h3 style="margin-top: 18px;">{{ $event->title }}</h3>
                        <p>{{ $event->description }}</p>
                        <p>{{ $event->type }} / {{ $event->status }} / {{ optional($event->reported_at)->format('d.m H:i') }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-tight" id="filters">
        <div class="container">
            <h2>Фильтры новостей</h2>
            <p class="lead">Выберите дорогу, чтобы увидеть события именно на этой трассе.</p>
            <form class="form-grid news-filter-grid" data-news-filter-form style="margin-top: 36px;">
                <div class="field">
                    <label>Дорога</label>
                    <select name="highway">
                        <option value="">Все дороги</option>
                        @foreach($highways as $highway)
                            <option value="{{ $highway }}">{{ $highway }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Трасса, город или километр</label>
                    <input name="query" type="text" placeholder="Например: М-4, 328 км, Воронеж">
                </div>
                <div class="field">
                    <label>Тип происшествия</label>
                    <select name="type">
                        <option value="">Все события</option>
                        <option value="ДТП">ДТП</option>
                        <option value="Затор">Затор</option>
                        <option value="Перекрытие">Перекрытие</option>
                    </select>
                </div>
                <div class="field">
                    <label>Статус</label>
                    <select name="status">
                        <option value="">Любой</option>
                        <option value="active">Активные</option>
                        <option value="checking">Проверяются</option>
                        <option value="closed">Завершенные</option>
                    </select>
                </div>
                <div class="actions" style="grid-column: 1 / -1;">
                    <button type="submit">Показать</button>
                    <button type="reset" class="btn outline">Сбросить</button>
                </div>
            </form>
        </div>
    </section>

    <section class="section dark news-details-section">
        <div class="container">
            <h2>Подробности происшествий</h2>
            <div class="grid-3 equal-card-grid news-details-grid" data-news-results style="margin-top: 42px;">
                @foreach($events as $event)
                    <article
                        class="card dark-card feature-card"
                        data-event-card
                        data-highway="{{ $event->highway }}"
                        data-type="{{ $event->type }}"
                        data-status="{{ $event->status }}"
                        data-search="{{ $event->title }} {{ $event->type }} {{ $event->highway }} {{ $event->location }} {{ $event->description }}"
                    >
                        <div class="feature-image">
                            <img src="{{ asset('assets/images/' . ($event->image ?: 'road-dark-forest.jpg')) }}" alt="{{ $event->title }}">
                        </div>
                        <div class="feature-body">
                            <span class="badge">{{ $event->type }}</span>
                            <h3 style="margin-top: 16px;">{{ $event->location }}</h3>
                            <p>{{ $event->description }}</p>
                            <p>{{ $event->highway }} / задержка {{ $event->delay_minutes }} мин / доверие {{ $event->confidence_score }}</p>
                        </div>
                    </article>
                @endforeach
                <article class="card dark-card news-empty-card" data-news-empty hidden>
                    <span class="badge">нет событий</span>
                    <h3 style="margin-top: 16px;">По выбранной дороге пока ничего не найдено</h3>
                    <p>Попробуйте сбросить фильтр или выбрать другую трассу. Когда по направлению появится новое сообщение, оно отобразится в этой сводке.</p>
                </article>
            </div>
        </div>
    </section>
@push('scripts')
<script>
(function () {
    var eventsData = @json($events->filter(fn($e) => $e->lat && $e->lng)->values());
    var mapEl = document.getElementById('newsMap');
    if (!mapEl || !window.L || !eventsData.length) return;

    var bounds = [];
    var map = L.map('newsMap', { zoomControl: true });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com/">CARTO</a>',
        maxZoom: 19,
    }).addTo(map);

    var colorMap = { high: '#c84840', medium: '#c99b3a', low: '#5a5752' };

    eventsData.forEach(function (ev) {
        var color = colorMap[ev.importance] || colorMap.low;
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:14px;height:14px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5)"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7],
        });
        var marker = L.marker([ev.lat, ev.lng], { icon: icon }).addTo(map);
        marker.bindPopup(
            '<strong>' + (ev.title || ev.type) + '</strong>'
            + '<br>' + (ev.location || '')
            + (ev.highway ? '<br>' + ev.highway : '')
            + '<br><a href="#events" style="color:#3498db">Подробнее ↓</a>'
        );
        bounds.push([ev.lat, ev.lng]);
    });

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 10 });
    } else {
        map.setView([53.9023, 27.5619], 6);
    }
})();
</script>
@endpush
@endsection
