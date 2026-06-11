@extends('layouts.app')

@section('title', 'TruckRoute - регистрация')

@section('content')
    <section class="page-hero auth-page">
        <div class="container">
            <div>
                <h1>Создать аккаунт</h1>
                <p class="lead">Аккаунт нужен, чтобы привязать маршруты к профилю водителя, сохранить транспорт и управлять уведомлениями.</p>
            </div>
            <form class="auth-card" method="POST" action="{{ route('register.store') }}">
                @csrf
                <div class="field">
                    <label>Имя</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <label>Телефон</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                    @error('phone')
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
                <div class="field">
                    <label>Повторите пароль</label>
                    <input type="password" name="password_confirmation" required>
                </div>
                <div class="actions">
                    <button type="submit">Зарегистрироваться</button>
                    <a class="btn outline" href="{{ route('login') }}">Уже есть аккаунт</a>
                </div>
            </form>
        </div>
    </section>
@endsection
