@extends('layouts.app')

@section('title', 'TruckRoute - уведомление')

@section('content')
    @php
        $data = $notification->data;
        $title = $event?->title ?? ($data['title'] ?? 'Дорожное событие');
        $description = $event?->description ?? ($data['description'] ?? 'Описание пока недоступно.');
        $image = $event?->image ?: 'road-dark-forest.jpg';
    @endphp

    <section class="page-hero">
        <div class="container">
            <div>
                <h1>{{ $title }}</h1>
                <p class="lead">{{ $description }}</p>
                <div class="actions">
                    <a class="btn" href="{{ route('news') }}">Открыть новости</a>
                    <a class="btn outline" href="{{ route('profile.notifications') }}">Все уведомления</a>
                </div>
            </div>
            <div class="page-visual">
                <img src="{{ asset('assets/images/' . $image) }}" alt="{{ $title }}">
            </div>
        </div>
    </section>

    <section class="section-tight">
        <div class="container">
            <h2>Подробности уведомления</h2>
            <div class="card notification-detail" style="margin-top: 36px;">
                <div class="notification-detail__meta">
                    <span class="badge">{{ $event?->type ?? ($data['type'] ?? 'событие') }}</span>
                    <span>{{ $event?->highway ?? ($data['highway'] ?? 'дорога не указана') }}</span>
                    <span>{{ $event?->location ?? ($data['location'] ?? 'место не указано') }}</span>
                    <span>Задержка: {{ $event?->delay_minutes ?? ($data['delay_minutes'] ?? 0) }} мин</span>
                    <span>Статус: {{ $event?->status ?? ($data['status'] ?? 'unknown') }}</span>
                </div>
                <p>{{ $description }}</p>
                <p class="small">Уведомление создано: {{ $notification->created_at->format('d.m.Y H:i') }}</p>
                @if($event?->reported_at || !empty($data['reported_at']))
                    <p class="small">Событие зафиксировано: {{ $event?->reported_at?->format('d.m.Y H:i') ?? $data['reported_at'] }}</p>
                @endif
            </div>
        </div>
    </section>
@endsection
