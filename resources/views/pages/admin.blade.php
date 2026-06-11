@extends('layouts.app')

@section('title', 'TruckRoute - админ-панель')

@section('content')
    <section class="page-hero">
        <div class="container">
            <div>
                <h1>Панель администратора</h1>
                <p class="lead">Управление пользователями, дорожными событиями, объектами, справочниками и статистикой системы.</p>
                <div class="actions">
                    <a class="btn" href="#moderation">Открыть модерацию</a>
                </div>
            </div>
            <div class="page-visual">
                <img src="{{ asset('assets/images/road-warm-forest.jpg') }}" alt="Дорога в лесу">
            </div>
        </div>
    </section>

    <section class="section-tight">
        <div class="container">
            <h2>Статистика</h2>
            <div class="stats stats-4 mt-36">
                <div class="stat">
                    <strong>{{ $usersCount }}</strong>
                    <span>пользователей</span>
                </div>
                <div class="stat">
                    <strong>{{ $routesCount }}</strong>
                    <span>маршрутов</span>
                </div>
                <div class="stat">
                    <strong>{{ $eventsCount }}</strong>
                    <span>активных событий</span>
                </div>
                <div class="stat">
                    <strong>{{ $objectsModerationCount }}</strong>
                    <span>объектов на проверке</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section-tight" id="moderation">
        <div class="container">
            <h2>Модерация событий</h2>
            <table class="table" style="margin-top: 36px;">
                <thead>
                    <tr>
                        <th>Тип</th>
                        <th>Описание</th>
                        <th>Место</th>
                        <th>Статус</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                        <tr id="event-row-{{ $event->id }}">
                            <td>{{ $event->type }}</td>
                            <td>{{ $event->title }}</td>
                            <td>{{ $event->location }}</td>
                            <td class="event-status-{{ $event->id }}">{{ $event->status }}</td>
                            <td>
                                <div class="table-actions">
                                    <button type="button"
                                        data-admin-action="approve-event"
                                        data-id="{{ $event->id }}">Подтвердить</button>
                                    <button type="button" class="btn outline"
                                        data-admin-action="reject-event"
                                        data-id="{{ $event->id }}">Отклонить</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="section dark">
        <div class="container">
            <h2>Объекты и пользователи</h2>
            <div class="grid-3" style="margin-top: 42px;">
                @foreach($objects->take(3) as $object)
                    <article class="card dark-card">
                        <h3>{{ $object->name }}</h3>
                        <p>{{ $object->type }} / {{ $object->location }} / рейтинг {{ $object->rating }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@push('scripts')
<script>
(function () {
    function getCsrf() {
        var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    }

    var endpoints = {
        'approve-event': function (id) { return ['/api/v1/admin/events/' + id + '/approve', 'POST']; },
        'reject-event':  function (id) { return ['/api/v1/admin/events/' + id + '/reject', 'POST']; },
        'approve-poi':   function (id) { return ['/api/v1/admin/poi/' + id + '/approve', 'POST']; },
        'reject-poi':    function (id) { return ['/api/v1/admin/poi/' + id + '/reject', 'POST']; },
    };

    var labels = {
        'approve-event': 'active',
        'reject-event':  'rejected',
        'approve-poi':   'active',
        'reject-poi':    'rejected',
    };

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-admin-action]');
        if (!btn) return;
        var action = btn.dataset.adminAction;
        var id = btn.dataset.id;
        if (!action || !id || !endpoints[action]) return;

        btn.disabled = true;
        var pair = endpoints[action](id);

        fetch(pair[0], {
            method: pair[1],
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var statusCell = document.querySelector('.event-status-' + id) || document.querySelector('.poi-status-' + id);
            if (statusCell) statusCell.textContent = labels[action];
            var row = document.getElementById('event-row-' + id) || document.getElementById('poi-row-' + id);
            if (row) {
                row.style.opacity = '.4';
                row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            }
            if (window.TruckToast) TruckToast.success(data.message || 'Готово');
        })
        .catch(function () {
            btn.disabled = false;
            if (window.TruckToast) TruckToast.error('Ошибка. Попробуйте ещё раз.');
        });
    });
})();
</script>
@endpush
@endsection
