<template>
    <div>
        <!-- Hero -->
        <section class="hero">
            <img src="/assets/images/road-dark-forest.jpg" alt="Трасса через лес">
            <div class="hero-content">
                <div class="hero-kicker">информационная система для рейса</div>
                <h1>TruckRoute — маршруты для грузового транспорта</h1>
                <p class="lead">Планируйте рейс с учётом топлива, параметров грузовика, дорожных происшествий, АЗС, стоянок и ночлега.</p>
                <div class="actions">
                    <RouterLink :to="{ name: 'routes' }" class="btn light">Построить маршрут</RouterLink>
                    <RouterLink :to="{ name: 'news' }" class="btn outline">Новости на дороге</RouterLink>
                </div>
            </div>
        </section>

        <!-- Stats section -->
        <section class="section">
            <div class="container grid-2">
                <div>
                    <h2>Не просто карта, а помощник в рейсе</h2>
                    <p class="lead">TruckRoute собирает ключевые данные поездки в одном интерфейсе: маршрут, расход топлива, остановки, происшествия и полезные объекты рядом с трассой.</p>
                    <div class="stats mt-36">
                        <div class="stat">
                            <strong>740</strong>
                            <span>км примерного маршрута</span>
                        </div>
                        <div class="stat">
                            <strong>215</strong>
                            <span>л расчётного топлива</span>
                        </div>
                        <div class="stat">
                            <strong>4</strong>
                            <span>остановки по рейсу</span>
                        </div>
                    </div>
                </div>
                <div class="home-trip-flow">
                    <div class="home-trip-flow__head">
                        <span>Один рейс</span>
                        <strong>Вся важная информация по порядку</strong>
                    </div>
                    <ol>
                        <li>
                            <span>01</span>
                            <div>
                                <strong>До старта</strong>
                                <p>Маршрут, топливо, параметры грузовика и запланированные остановки.</p>
                            </div>
                        </li>
                        <li>
                            <span>02</span>
                            <div>
                                <strong>В пути</strong>
                                <p>Дорожные события и подходящие объекты рядом с текущим направлением.</p>
                            </div>
                        </li>
                        <li>
                            <span>03</span>
                            <div>
                                <strong>После рейса</strong>
                                <p>История маршрута, фактическое время и сохранённые данные поездки.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <!-- Dark feature section -->
        <section class="section dark home-planning-section">
            <div class="container home-planning-layout">
                <div class="home-planning-top">
                <div class="image-panel tall home-concept-panel">
                    <img src="/assets/images/home-real-planning.png" alt="Планирование реального рейса">
                </div>
                <div class="home-planning-copy">
                    <h2>Планирование, которое учитывает реальный рейс</h2>
                    <p class="lead">Система помогает заранее понять, где заправиться, когда сделать остановку и какие события на дороге могут повлиять на движение.</p>
                </div>
                </div>
                <div class="home-planning-cards equal-card-grid">
                        <div class="card dark-card">
                            <h3>Профиль грузовика</h3>
                            <p>Тип транспорта, бак, расход, скорость и ограничения используются в расчёте маршрута.</p>
                        </div>
                        <div class="card dark-card">
                            <h3>Резерв топлива</h3>
                            <p>Маршрут строится так, чтобы водитель не оставался без безопасного остатка топлива.</p>
                        </div>
                        <div class="card dark-card">
                            <h3>АЗС и стоянки</h3>
                            <p>Полезные точки подбираются рядом с трассой и с учётом грузовой инфраструктуры.</p>
                        </div>
                        <div class="card dark-card">
                            <h3>События</h3>
                            <p>ДТП, заторы и перекрытия отображаются в отдельной ленте дорожных новостей.</p>
                        </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="section">
            <div class="container">
                <h2>Возможности системы</h2>
                <div class="grid-3 equal-card-grid" style="margin-top:42px;">
                    <article class="card feature-card home-static-feature">
                        <div class="feature-image feature-image-route"><img src="/assets/images/feature-route-map.jpg" alt="Маршрут на карте"></div>
                        <div class="feature-body">
                            <h3>Маршрут А → Б</h3>
                            <p>Построение направления с промежуточными точками, временем старта и транзитными городами.</p>
                        </div>
                    </article>
                    <article class="card feature-card home-static-feature">
                        <div class="feature-image feature-image-fuel"><img src="/assets/images/feature-fuel-calculation.jpg" alt="Расчёт топлива"></div>
                        <div class="feature-body">
                            <h3>Расчёт топлива</h3>
                            <p>Объём бака, средний расход, резерв и прогноз потребления на маршруте.</p>
                        </div>
                    </article>
                    <article class="card feature-card home-static-feature">
                        <div class="feature-image"><img src="/assets/images/feature-route-gas-station.jpg" alt="АЗС ночью"></div>
                        <div class="feature-body">
                            <h3>АЗС на маршруте</h3>
                            <p>Рекомендации по заправкам, стоянкам, кафе и местам ночлега.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Recent events -->
        <section v-if="events.length" class="section-tight">
            <div class="container">
                <h2>Последние события на дорогах</h2>
                <div class="grid-3 equal-card-grid" style="margin-top:36px;">
                    <NewsCard v-for="ev in events" :key="ev.id" :event="ev" />
                </div>
                <div class="actions" style="margin-top:28px;">
                    <RouterLink :to="{ name: 'news' }" class="btn outline">Все новости</RouterLink>
                </div>
            </div>
        </section>

        <!-- CTA band -->
        <section class="section red-band home-control-section">
            <div class="container grid-2">
                <div>
                    <h2>Меньше ручного планирования — больше контроля</h2>
                    <p class="lead">Сайт показывает важную информацию до старта поездки и помогает водителю быстрее принять решение в пути.</p>
                    <div class="actions">
                        <RouterLink :to="{ name: 'routes' }" class="btn light">Открыть маршруты</RouterLink>
                        <RouterLink :to="{ name: 'places' }" class="btn outline">Объекты на трассе</RouterLink>
                    </div>
                </div>
                <div class="image-panel home-concept-panel">
                    <img src="/assets/images/home-control-dashboard.png" alt="Панель контроля рейса">
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import NewsCard from '@/components/NewsCard.vue';

const events = ref([]);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/v1/events?limit=20&status=feed');
        events.value = selectUrgentEvents(data.data ?? []);
    } catch { /* ignore */ }
});

function selectUrgentEvents(items) {
    return items
        .filter((event) => urgencyScore(event) >= 3)
        .sort((a, b) => urgencyScore(b) - urgencyScore(a))
        .slice(0, 3);
}

function urgencyScore(event) {
    const importance = String(event.importance ?? '').toLowerCase();
    let score = 0;
    if (['high', 'important', 'важно', 'высокий'].includes(importance)) score += 4;
    if (Number(event.delay_minutes ?? 0) >= 30) score += 3;
    else if (Number(event.delay_minutes ?? 0) >= 15) score += 1;
    if (Number(event.confidence_score ?? 0) >= 6) score += 2;
    return score;
}
</script>

<style scoped>
.home-trip-flow {
    align-self: stretch;
    display: grid;
    align-content: center;
    padding: 8px 0 8px 42px;
    border-left: 1px solid var(--border);
}

.home-trip-flow__head span,
.home-trip-flow__head strong {
    display: block;
}

.home-trip-flow__head span {
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 10px;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.home-trip-flow__head strong {
    max-width: 420px;
    margin-top: 8px;
    color: var(--text);
    font-family: var(--font-d);
    font-size: clamp(22px, 2.4vw, 34px);
    font-weight: 400;
    line-height: 1.1;
}

.home-trip-flow ol {
    margin: 26px 0 0;
    padding: 0;
    list-style: none;
}

.home-trip-flow li {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 16px;
    padding: 16px 0;
    border-top: 1px solid var(--border);
}

.home-trip-flow li > span {
    padding-top: 3px;
    color: var(--accent);
    font-family: var(--font-m);
    font-size: 11px;
}

.home-trip-flow li strong {
    color: var(--text);
    font-size: 15px;
}

.home-trip-flow li p {
    max-width: 470px;
    margin-top: 5px;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
}

:global(.home-planning-section.section.dark) {
    background: var(--s1);
}

@media (max-width: 900px) {
    .home-trip-flow {
        padding: 28px 0 0;
        border-top: 1px solid var(--border);
        border-left: 0;
    }
}
</style>
