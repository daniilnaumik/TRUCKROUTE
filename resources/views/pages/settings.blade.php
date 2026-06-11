@extends('layouts.app')

@section('title', 'TruckRoute - настройки')

@section('content')
    <section class="page-hero">
        <div class="container">
            <div>
                <h1>Настройки аккаунта</h1>
                <p class="lead">Здесь находятся уведомления приложения, безопасность аккаунта, документы сервиса и настройки конфиденциальности.</p>
                <div class="actions">
                    <button type="submit" form="settings-form" id="settingsSaveBtn" class="btn settings-save-btn">
                        <svg class="btn-spinner" viewBox="0 0 16 16" width="16" height="16" style="display:none;vertical-align:middle;margin-right:6px;animation:spin .7s linear infinite"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="28" stroke-dashoffset="10"/></svg>
                        <span>Сохранить</span>
                    </button>
                    <button type="reset" form="settings-form" class="btn outline">Отменить</button>
                </div>
            </div>
            <div class="page-visual">
                <img src="{{ asset('assets/images/road-warm-forest.jpg') }}" alt="Настройки аккаунта">
            </div>
        </div>
    </section>

    <section class="section-tight">
        <div class="container">
            @if(session('success'))
                <div class="card card-success" style="margin-bottom: 28px;">
                    <h3>Готово</h3>
                    <p>{{ session('success') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="card card-error" style="margin-bottom: 28px;">
                    <h3>Проверьте данные</h3>
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <h2>Уведомления</h2>
            <form id="settings-form" class="card toggle-row" method="POST" action="{{ route('settings.update') }}" style="margin-top: 36px;">
                @csrf
                <div>
                    <h3>Уведомления о дорожных происшествиях</h3>
                    <p>Если ползунок включен, TruckRoute будет сохранять уведомления в профиле и отправлять письма на email {{ $user?->email }}. Если выключен - новые уведомления приходить не будут.</p>
                    <div class="actions">
                        <a class="btn outline" href="{{ route('profile.notifications') }}">Открыть уведомления</a>
                    </div>
                </div>
                <div>
                    <label class="toggle">
                        <input type="checkbox" name="incident_notifications" value="1" @checked($settings?->incident_notifications)>
                    </label>
                    <p class="small" style="margin-top:8px; color: {{ $settings?->incident_notifications ? 'var(--accent)' : 'var(--text-3)' }}">
                        {{ $settings?->incident_notifications ? 'включено' : 'выключено' }}
                    </p>
                    <input type="hidden" name="privacy_policy_accepted" value="{{ $settings?->privacy_policy_accepted ? 1 : 0 }}">
                    <input type="hidden" name="data_processing_accepted" value="{{ $settings?->data_processing_accepted ? 1 : 0 }}">
                </div>
            </form>
        </div>
    </section>

    <section class="section-tight">
        <div class="container">
            <h2>Безопасность</h2>
            <div class="grid-3 equal-card-grid settings-security-grid" style="margin-top: 36px;">
                <form class="card security-action-card" method="POST" action="{{ route('settings.password') }}">
                    @csrf
                    <h3>Пароль</h3>
                    <p>Изменение пароля для входа в аккаунт TruckRoute.</p>
                    <div class="field">
                        <label>Текущий пароль</label>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label>Новый пароль</label>
                        <input name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label>Повторите пароль</label>
                        <input name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    @if($settings?->last_password_change_at)
                        <p class="small">Последнее изменение: {{ $settings->last_password_change_at->format('d.m.Y H:i') }}</p>
                    @endif
                    <div class="actions">
                        <button type="submit" class="btn light">Изменить пароль</button>
                    </div>
                </form>

                <div class="card security-action-card">
                    <h3>Активные сеансы</h3>
                    <p>Просмотр устройств, с которых выполнен вход.</p>
                    <div class="session-list">
                        @forelse($sessions as $session)
                            <div class="session-item">
                                <div>
                                    <strong>{{ $session->browser }}</strong>
                                    <span>{{ $session->ip_address }} / {{ $session->last_activity->format('d.m.Y H:i') }}</span>
                                    @if($session->is_current)
                                        <span class="badge">текущий</span>
                                    @endif
                                </div>
                                @unless($session->is_current)
                                    <form method="POST" action="{{ route('settings.sessions.destroy', $session->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn outline">Завершить</button>
                                    </form>
                                @endunless
                            </div>
                        @empty
                            <p class="small">Активных сеансов пока нет.</p>
                        @endforelse
                    </div>
                </div>

                <form class="card security-action-card" method="POST" action="{{ route('settings.logout-other-devices') }}">
                    @csrf
                    <h3>Выход со всех устройств</h3>
                    <p>Завершение всех активных сессий пользователя.</p>
                    <div class="field">
                        <label>Текущий пароль</label>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <p class="small">Текущий браузер останется активным, остальные устройства будут отключены.</p>
                    <div class="actions">
                        <button type="submit" class="btn light">Выйти со всех устройств</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="section-tight" id="service-documents">
        <div class="container">
            <h2>Конфиденциальность</h2>
            <div class="grid-3" style="margin-top: 36px;">
                @foreach($documents as $document)
                    <div class="card">
                        <h3>{{ $document->title }}</h3>
                        <p>{{ $document->summary }}</p>
                    </div>
                @endforeach
            </div>
            <div class="actions">
                <a class="btn" href="#service-documents">Открыть документы</a>
            </div>
        </div>
    </section>
@push('scripts')
<script>
(function () {
    var form = document.getElementById('settings-form');
    var btn = document.getElementById('settingsSaveBtn');
    if (!form || !btn) return;
    var spinner = btn.querySelector('.btn-spinner');
    form.addEventListener('submit', function () {
        btn.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
    });
})();
</script>
@endpush
@endsection
