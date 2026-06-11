# TruckRoute

Информационная система поддержки маршрутизации грузового транспорта.  
Дипломный проект. Laravel 12 API + Vue 3 SPA + Expo (React Native) мобильное приложение.

---

## Архитектура

```
TruckRouteLaravel_light/
│
├── app/                     Laravel backend (API)
├── resources/js/            Vue 3 SPA (фронтенд)
├── mobile/                  React Native / Expo (мобилка)
├── public/assets/           Статика: CSS, JS legacy, изображения
├── tests/Unit/              PHPUnit unit-тесты
├── tests/Feature/           PHPUnit feature/API тесты
└── tests/e2e/               Playwright E2E тесты
```

### Компоненты и ответственность

| Компонент | Технология | Отвечает за |
|-----------|-----------|------------|
| **API backend** | Laravel 12, PHP 8.3, MySQL 8 | Бизнес-логика, хранение данных, JWT/Sanctum аутентификация, расчёт маршрутов, геокодирование через Nominatim, роутинг через OSRM |
| **Web SPA** | Vue 3, Pinia, Vue Router, Vite | Интерфейс пользователя, 5-шаговый wizard построения маршрута, карты (Leaflet + CartoDB Voyager), POI каталог, профиль |
| **Mobile** | React Native, Expo SDK 52, TypeScript | Мобильный интерфейс, фоновая геолокация, FCM push-уведомления, proximity alerts |
| **Очередь** | Laravel Queue (database driver) | Фоновые задачи: `ProximityAlertJob` — проверка расстояния до точек маршрута |
| **Геокодирование** | Nominatim (OpenStreetMap) | Адрес → координаты, обратное геокодирование |
| **Роутинг** | OSRM (router.project-osrm.org) | Построение реального маршрута, полилиния, расстояние |

---

## Требования

- PHP 8.3+
- MySQL 8.0+ (с поддержкой `ST_Distance_Sphere`)
- Node.js 20+
- Composer 2
- Для мобилки: Expo CLI, Android Studio / Xcode

---

## Установка — Backend + Web

### 1. Клонировать и установить зависимости

```bash
git clone <repo>
cd TruckRouteLaravel_light

composer install
npm install
```

### 2. Настроить окружение

```bash
cp .env.example .env
php artisan key:generate
```

Отредактировать `.env`:

```env
APP_URL=http://localhost:8000
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=truckroute_laravel
DB_USERNAME=root
DB_PASSWORD=

# Геокодирование (бесплатный Nominatim, без ключа)
GEO_GEOCODER=nominatim
GEO_ROUTING=osrm
NOMINATIM_USER_AGENT="TruckRouteDiplomaApp/1.0"
OSRM_BASE_URL=https://router.project-osrm.org

# FCM (опционально, для мобильных push-уведомлений)
FCM_SERVER_KEY=
```

### 3. База данных и сиды

```bash
# Создать базу данных трукроут в MySQL, затем:
php artisan migrate
php artisan db:seed
```

После сида доступны тестовые аккаунты:

| Email | Пароль | Роль |
|-------|--------|------|
| `driver@truckroute.local` | `password` | driver |
| `admin@truckroute.local` | `password` | admin |

### 4. Собрать фронтенд

```bash
npm run build
```

Или в dev-режиме (hot reload):

```bash
npm run dev
```

### 5. Запустить сервер

```bash
php artisan serve
# → http://localhost:8000
```

### 6. Запустить очередь (для proximity alerts)

```bash
php artisan queue:work
```

Для разработки все сразу:

```bash
npm run dev:all
# = php artisan serve + vite (concurrently)
# очередь запускать отдельно
```

---

## Установка — Мобильное приложение

### 1. Установить зависимости

```bash
cd mobile
npm install
```

### 2. Настроить URL сервера

Открыть `mobile/src/api/client.ts`, строка с `API_BASE`:

```ts
// Android-эмулятор обращается к localhost хоста через 10.0.2.2
export const API_BASE = __DEV__
    ? 'http://10.0.2.2:8000/api/v1'   // ← менять здесь
    : 'https://your-production.com/api/v1';
```

Для физического Android-устройства в одной Wi-Fi сети — указать реальный IP компьютера:  
`http://192.168.x.x:8000/api/v1`

### 3. Запустить

```bash
npx expo start

# Выбрать платформу:
# a — Android (нужен эмулятор или устройство)
# i — iOS (только macOS)
# w — Web-превью (ограниченная функциональность)
```

### 4. Сборка APK (продакшн)

```bash
npm install -g eas-cli
eas build --platform android
```

---

## Тестирование

### PHPUnit (unit + feature тесты)

```bash
# Запуск всех тестов
php artisan test

# Только unit
php artisan test --testsuite=Unit

# Только feature/API
php artisan test --testsuite=Feature

# Конкретный файл
php artisan test tests/Unit/RouteCalculatorTest.php
```

Тесты используют SQLite в памяти (`:memory:`), не трогают основную БД.

### Playwright E2E тесты

Требуется запущенный сервер (`php artisan serve`) и засеянная БД.

```bash
# Установить браузеры Playwright (первый раз)
npx playwright install chromium

# Запустить все E2E тесты
npm run test:e2e

# С интерактивным UI
npm run test:e2e:ui

# Конкретный файл
npx playwright test tests/e2e/auth.spec.ts --config=tests/e2e/playwright.config.ts
```

---

## Структура проекта

### Backend (`app/`)

```
app/
├── Http/Controllers/Api/V1/
│   ├── AuthController.php        # Регистрация, вход, выход (Bearer token)
│   ├── RoutesController.php      # CRUD маршрутов, POST /routes → build
│   ├── VehiclesController.php    # Профили ТС водителя
│   ├── CargosController.php      # Профили груза (сохраняемые)
│   ├── EventsController.php      # Дорожные события (краудсорсинг + голосование)
│   ├── PoiController.php         # Публичный каталог POI
│   ├── FavoritesController.php   # Избранные POI пользователя
│   ├── TripController.php        # Активная поездка: start/location/end
│   ├── NotificationsController.php # Уведомления (база данных)
│   ├── GeoController.php         # Прокси на Nominatim + OSRM
│   ├── AdminController.php       # Статистика и модерация (role:admin)
│   ├── ProviderPoiController.php # CRUD своих POI (role:provider)
│   └── Fleet*/                   # Автопарк и задания (role:fleet)
│
├── Services/
│   ├── RouteCalculator.php       # Расчёт топлива, остановок, ETA
│   ├── RouteBuildService.php     # Оркестратор: геокод → OSRM → POI → расчёт → сохранение
│   ├── PoiSearchService.php      # Поиск POI вдоль маршрута (ST_Distance_Sphere)
│   ├── RoadEventNotifier.php     # Рассылка уведомлений о событиях
│   └── Geo/
│       ├── Providers/
│       │   ├── NominatimGeoProvider.php   # Геокодер OSM
│       │   └── OsrmRoutingProvider.php    # Роутер OSRM
│       └── DTO/
│           ├── GeoPoint.php       # Иммутабельная точка (lat, lng, label)
│           └── RouteGeometry.php  # Маршрут: дистанция, полилиния
│
├── Jobs/
│   └── ProximityAlertJob.php     # Проверяет расстояние до POI, шлёт уведомление
│
├── Notifications/
│   └── ProximityAlert.php        # Proximity-уведомление (database канал)
│
└── Models/
    ├── User.php          # Водитель / провайдер / автопарк / admin
    ├── Vehicle.php       # Профиль ТС
    ├── RoutePlan.php     # Сохранённый маршрут
    ├── RouteRecommendation.php  # Точка на маршруте (АЗС, ночлег, отдых)
    ├── RoadEvent.php     # Дорожное событие
    ├── ServiceObject.php # POI (АЗС, стоянка, кафе, ночлег, СТО)
    ├── TripSession.php   # Активная поездка + GPS позиция
    ├── Cargo.php         # Профиль груза
    ├── UserPoiFavorite.php # Избранные POI
    ├── Fleet.php         # Автопарк
    └── Device.php        # FCM-токены устройств
```

### Frontend Vue SPA (`resources/js/`)

```
resources/js/
├── App.vue                  # Корень: header + router-view + toasts
├── app.js                   # Точка входа: Vue + Pinia + Router
├── router/index.js          # Маршруты + guards (auth, role, guest)
│
├── stores/
│   ├── auth.js              # Pinia: токен, user, login/logout/fetchMe
│   ├── ui.js                # Pinia: тема (dark/light), toast-уведомления
│   └── wizard.js            # Pinia: состояние 5-шагового wizard маршрута
│
├── composables/
│   ├── useGeocode.js        # Debounced вызов /api/v1/geo/geocode
│   └── useWizardMap.js      # Leaflet: маркеры, полилиния, fitBounds
│
├── components/
│   ├── AppHeader.vue        # Навбар: ссылки по ролям, bell, theme toggle
│   ├── AppFooter.vue        # Подвал
│   ├── GeoInput.vue         # Поле адреса с автодополнением (geocode)
│   ├── NewsCard.vue         # Карточка дорожного события
│   ├── ToastContainer.vue   # Toast-уведомления (teleport to body)
│   └── DevSwitcher.vue      # Dev-only: быстрый вход как driver/admin
│
└── pages/
    ├── auth/
    │   ├── LoginPage.vue    # Форма входа
    │   └── RegisterPage.vue # Форма регистрации
    ├── HomePage.vue         # Главная: hero, stats, события, CTA
    ├── NewsPage.vue         # Лента событий + Leaflet карта + фильтры
    ├── RoutesPage.vue       # 5-шаговый wizard + Leaflet карта (sticky right)
    ├── RouteDetailPage.vue  # Таймлайн маршрута + карта с полилинией
    ├── PlacesPage.vue       # Каталог POI: фильтры, карточки
    ├── PlaceDetailPage.vue  # Детальная страница POI + карта
    ├── ProfilePage.vue      # Профиль: ТС, история маршрутов
    ├── SettingsPage.vue     # Настройки уведомлений, смена пароля
    ├── NotificationsPage.vue# Список уведомлений
    ├── AdminPage.vue        # Статистика + модерация событий
    ├── provider/DashboardPage.vue  # CRUD POI для провайдера
    └── fleet/DashboardPage.vue     # Автопарки
```

### Мобильное приложение (`mobile/src/`)

```
mobile/src/
├── api/
│   ├── client.ts     # Axios + Bearer token из SecureStore
│   ├── auth.ts       # login/register/me/logout
│   ├── routes.ts     # Маршруты: список, детали, построение
│   ├── events.ts     # Дорожные события + голосование
│   └── trip.ts       # Активная поездка: start/location/end/current
│
├── store/
│   ├── auth.ts       # Zustand: user, login/logout, initialize
│   └── trip.ts       # Zustand: активная сессия, tracking state
│
├── hooks/
│   ├── useLocation.ts     # expo-location: background task → POST /trip/location
│   └── useNotifications.ts# expo-notifications: FCM регистрация + foreground handler
│
├── navigation/
│   ├── index.tsx     # Stack navigator: tabs | login/register
│   └── TabNavigator.tsx  # 5 вкладок: Главная/Маршруты/Новости/Объекты/Профиль
│
├── screens/
│   ├── auth/
│   │   ├── LoginScreen.tsx   # Форма входа
│   │   └── RegisterScreen.tsx# Форма регистрации
│   ├── HomeScreen.tsx        # Банер активной поездки + список маршрутов + события
│   ├── RoutesScreen.tsx      # Список маршрутов → detail + кнопка на карту
│   ├── RouteDetailScreen.tsx # Таймлайн остановок + кнопки Карта / Начать поездку
│   ├── MapScreen.tsx         # react-native-maps: полилиния, маркеры, кнопка start/end trip
│   ├── EventsFeedScreen.tsx  # Лента событий с голосованием
│   ├── PlacesScreen.tsx      # Каталог POI: поиск, фильтр по типу
│   ├── PlaceDetailScreen.tsx # Детали POI + кнопка избранного
│   └── ProfileScreen.tsx     # Данные пользователя, выход
│
├── components/
│   └── ProximitySheet.tsx    # Bottom sheet: карточка ближайшей точки маршрута
│
└── theme.ts           # Цвета, отступы, радиусы — зеркало CSS-токенов веба
```

### Тесты

```
tests/
├── Unit/
│   ├── RouteCalculatorTest.php  # Топливные расчёты, weight profile, ETA
│   └── HaversineTest.php        # Формула расстояния в ProximityAlertJob
│
├── Feature/Api/
│   ├── AuthApiTest.php          # Регистрация, вход, me, выход
│   ├── VehiclesApiTest.php      # CRUD ТС, изоляция пользователей
│   ├── EventsApiTest.php        # Публичная лента, добавление, голосование
│   ├── FavoritesApiTest.php     # Добавление/удаление избранного POI
│   └── TripApiTest.php          # Trip session + proximity notification
│
└── e2e/
    ├── playwright.config.ts     # Конфиг: baseURL, устройства, timeout
    ├── helpers.ts               # loginViaDevSwitcher, loginViaForm, waitForSpa
    ├── auth.spec.ts             # Login/register/logout flow
    ├── navigation.spec.ts       # Страницы, тема, hamburger, 404
    ├── wizard.spec.ts           # Wizard маршрута: 5 шагов, geo input, карта
    └── places.spec.ts           # POI каталог, фильтры, detail, избранное
```

---

## API — ключевые эндпоинты

Базовый путь: `/api/v1`  
Аутентификация: `Authorization: Bearer <token>`

| Метод | Путь | Доступ | Описание |
|-------|------|--------|----------|
| POST | `/auth/register` | Все | Регистрация → токен |
| POST | `/auth/login` | Все | Вход → токен |
| GET | `/auth/me` | Auth | Текущий пользователь |
| GET | `/events` | Все | Дорожные события (фильтры: status, highway) |
| GET | `/poi` | Все | POI каталог (фильтры: type, bbox) |
| GET | `/geo/geocode?q=` | Все | Адрес → координаты (Nominatim) |
| POST | `/geo/route` | Все | Построить полилинию (OSRM) |
| GET | `/vehicles` | Auth | Мои ТС |
| POST | `/vehicles` | Auth | Создать ТС |
| POST | `/routes` | Auth | Построить маршрут (geocode + OSRM + расчёт) |
| GET | `/routes/:id` | Auth | Маршрут с остановками и полилинией |
| POST | `/trip/start` | Auth | Начать поездку |
| POST | `/trip/location` | Auth | Обновить GPS → триггер ProximityAlertJob |
| POST | `/trip/end` | Auth | Завершить поездку |
| GET | `/favorites` | Auth | Избранные POI |
| POST | `/favorites/:poi` | Auth | Добавить в избранное |
| GET | `/notifications` | Auth | Список уведомлений |
| POST | `/admin/events/:id/approve` | Admin | Модерация события |
| POST | `/provider/poi` | Provider | Добавить объект |

---

## Роли

| Роль | Возможности |
|------|------------|
| `driver` | Маршруты, ТС, профиль, события, уведомления, поездка |
| `provider` | + CRUD своих POI объектов |
| `fleet` | + Создание автопарка, добавление водителей, выдача заданий |
| `admin` | Все + модерация событий и POI, управление пользователями |

---

## Dev-инструменты

**Переключатель ролей в navbar** (видим только при `APP_DEBUG=true`):  
Кнопка `тест` открывает дропдаун с быстрым входом как driver или admin.  
Работает через `/dev/switch?email=...` — выход + вход в одном GET-запросе.

**Тестовые тайлы карты:**  
CartoDB Voyager — светлые, читаемые: `https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png`

---

## Запуск в продакшн (основное)

```bash
# Backend
composer install --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Frontend
npm ci
npm run build

# Очередь (supervisor или systemd)
php artisan queue:work --tries=3 --sleep=3 --daemon
```

---

## Переменные окружения

| Переменная | По умолчанию | Описание |
|-----------|-------------|----------|
| `APP_DEBUG` | `true` | Dev-режим. `false` в продакшн. Управляет dev-switcher |
| `GEO_GEOCODER` | `nominatim` | Геокодер: `nominatim` или `yandex` |
| `GEO_ROUTING` | `osrm` | Роутер: `osrm` или `yandex` |
| `OSRM_BASE_URL` | `https://router.project-osrm.org` | URL OSRM сервера |
| `YANDEX_API_KEY` | *(пусто)* | Ключ Яндекс API (если GEO_GEOCODER=yandex) |
| `FCM_SERVER_KEY` | *(пусто)* | Firebase Cloud Messaging (для push мобилки) |
| `QUEUE_CONNECTION` | `database` | Драйвер очереди (`database`, `redis`, `sync`) |
