@extends('layouts.app')

@section('title', 'TruckRoute - главная')

@section('content')
    <section class="hero">
        <img src="{{ asset('assets/images/road-dark-forest.jpg') }}" alt="Темная трасса через лес">
        <div class="hero-content">
            <div class="hero-kicker">информационная система для рейса</div>
            <h1>TruckRoute - маршруты для грузового транспорта</h1>
            <p class="lead">Планируйте рейс с учетом топлива, параметров грузовика, дорожных происшествий, АЗС, стоянок и ночлега.</p>
            <div class="actions">
                <a class="btn light" href="{{ route('routes') }}">Построить маршрут</a>
                <a class="btn outline" href="{{ route('news') }}">Новости на дороге</a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container grid-2">
            <div>
                <h2>Не просто карта, а помощник в рейсе</h2>
                <p class="lead">TruckRoute собирает ключевые данные поездки в одном интерфейсе: маршрут, расход топлива, остановки, происшествия и полезные объекты рядом с трассой.</p>
                <div class="stats">
                    <div class="stat">
                        <strong>740</strong>
                        <span>км примерного маршрута</span>
                    </div>
                    <div class="stat">
                        <strong>215</strong>
                        <span>л расчетного топлива</span>
                    </div>
                    <div class="stat">
                        <strong>4</strong>
                        <span>остановки по рейсу</span>
                    </div>
                </div>
            </div>
            <div class="image-panel">
                <img src="{{ asset('assets/images/truck-white.jpg') }}" alt="Грузовик на стоянке">
            </div>
        </div>
    </section>

    <section class="section dark">
        <div class="container grid-2">
            <div class="image-panel tall">
                <img src="{{ asset('assets/images/road-black-canyon.jpg') }}" alt="Дальняя дорога">
            </div>
            <div>
                <h2>Планирование, которое учитывает реальный рейс</h2>
                <p class="lead">Система помогает заранее понять, где заправиться, когда сделать остановку и какие события на дороге могут повлиять на движение.</p>
                <div class="grid-2 equal-card-grid home-planning-grid" style="margin-top: 44px;">
                    <div class="card dark-card">
                        <h3>Профиль грузовика</h3>
                        <p>Тип транспорта, бак, расход, скорость и ограничения используются в расчете маршрута.</p>
                    </div>
                    <div class="card dark-card">
                        <h3>Резерв топлива</h3>
                        <p>Маршрут строится так, чтобы водитель не оставался без безопасного остатка топлива.</p>
                    </div>
                    <div class="card dark-card">
                        <h3>АЗС и стоянки</h3>
                        <p>Полезные точки подбираются рядом с трассой и с учетом грузовой инфраструктуры.</p>
                    </div>
                    <div class="card dark-card">
                        <h3>События</h3>
                        <p>ДТП, заторы и перекрытия отображаются в отдельной ленте дорожных новостей.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2>Возможности системы</h2>
            <div class="grid-3 equal-card-grid" style="margin-top: 42px;">
                <article class="card feature-card">
                    <div class="feature-image"><img src="{{ asset('assets/images/road-mountains-fog.jpg') }}" alt="Маршрут"></div>
                    <div class="feature-body">
                        <h3>Маршрут А - Б</h3>
                        <p>Построение направления с промежуточными точками и временем старта.</p>
                    </div>
                </article>
                <article class="card feature-card">
                    <div class="feature-image"><img src="{{ asset('assets/images/feature-fuel-actros.jpg') }}" alt="Кабина грузовика на маршруте"></div>
                    <div class="feature-body">
                        <h3>Расчет топлива</h3>
                        <p>Объем бака, средний расход, резерв и прогноз потребления.</p>
                    </div>
                </article>
                <article class="card feature-card">
                    <div class="feature-image"><img src="{{ asset('assets/images/feature-route-gas-station.jpg') }}" alt="АЗС ночью"></div>
                    <div class="feature-body">
                        <h3>АЗС на маршруте</h3>
                        <p>Рекомендации по заправкам, стоянкам, кафе и местам ночлега.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section red-band home-control-section">
        <div class="container grid-2">
            <div>
                <h2>Меньше ручного планирования - больше контроля на маршруте</h2>
                <p class="lead">Сайт показывает важную информацию до старта поездки и помогает водителю быстрее принять решение в пути.</p>
                <div class="actions">
                    <a class="btn light" href="{{ route('routes') }}">Открыть маршруты</a>
                </div>
            </div>
            <div class="image-panel">
                <img src="{{ asset('assets/images/road-sunset-long.jpg') }}" alt="Трасса на закате">
            </div>
        </div>
    </section>
@endsection
