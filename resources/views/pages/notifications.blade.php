@extends('layouts.app')

@section('title', 'TruckRoute - уведомления')

@section('content')
    <section class="section-tight notifications-section">
        <div class="container">
            <h2>Дорожные происшествия</h2>
            <div class="grid-2 notification-list" style="margin-top: 36px;">
                @forelse($notifications as $notification)
                    @php($data = $notification->data)
                    <article class="card notification-card @if(!$notification->read_at) is-unread @endif">
                        <span class="badge">{{ $data['type'] ?? 'событие' }}</span>
                        <h3>{{ $data['title'] ?? 'Дорожное событие' }}</h3>
                        <p>{{ $data['highway'] ?? 'дорога не указана' }} / {{ $data['location'] ?? 'место не указано' }}</p>
                        <p>{{ $data['description'] ?? 'Описание пока недоступно.' }}</p>
                        <p class="small">Получено: {{ $notification->created_at->format('d.m.Y H:i') }}</p>
                        <div class="actions">
                            <a class="btn" href="{{ route('profile.notifications.show', $notification) }}">Открыть</a>
                        </div>
                    </article>
                @empty
                    <article class="card">
                        <h3>Уведомлений пока нет</h3>
                        <p>Включите уведомления в настройках. Когда появится активное дорожное событие, оно появится здесь и будет отправлено на вашу почту.</p>
                        <div class="actions">
                            <a class="btn" href="{{ route('settings') }}">Перейти в настройки</a>
                        </div>
                    </article>
                @endforelse
            </div>
        </div>
    </section>
@endsection
