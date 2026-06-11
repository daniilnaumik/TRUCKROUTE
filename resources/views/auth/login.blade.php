@extends('layouts.app')

@section('title', 'TruckRoute - вход')

@section('content')
    <section class="page-hero auth-page">
        <div class="container">
            <div>
                <h1>Вход в аккаунт</h1>
                <p class="lead">Войдите, чтобы сохранять маршруты, использовать профиль транспорта и получать уведомления по рейсу.</p>
            </div>
            <form class="auth-card" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <label>Пароль</label>
                    <input type="password" name="password" required>
                    @error('password')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Запомнить меня</span>
                </label>
                <div class="actions">
                    <button type="submit">Войти</button>
                    <a class="btn outline" href="{{ route('register') }}">Создать аккаунт</a>
                </div>
                <p class="small">Демо-вход: driver@truckroute.local / password</p>
            </form>
        </div>
    </section>
@endsection
