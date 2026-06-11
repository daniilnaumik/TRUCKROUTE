<?php

/*
|--------------------------------------------------------------------------
| Road events configuration
|--------------------------------------------------------------------------
| TTL по типам, скоринг доверия, лимиты антиспама и параметры матчинга
| событий с маршрутами. Каждое поле сделано настраиваемым через .env, чтобы
| на защите диплома можно было быстро покрутить без редеплоя.
*/

return [

    /*
    | Авто-протухание событий: после этого срока статус становится "expired".
    | Подбирается по типу события из ключей ниже; неизвестный тип → default.
    | Минуты.
    */
    'ttl_minutes' => [
        'default' => env('EVENT_TTL_DEFAULT_MIN', 360),     // 6 часов
        'ДТП' => env('EVENT_TTL_ACCIDENT_MIN', 360),         // 6 часов
        'Перекрытие' => env('EVENT_TTL_BLOCK_MIN', 720),     // 12 часов
        'Ремонт' => env('EVENT_TTL_ROADWORK_MIN', 20160),    // 14 дней
        'Переезд' => env('EVENT_TTL_RAILCROSS_MIN', 60),     // 1 час
        'Погода' => env('EVENT_TTL_WEATHER_MIN', 180),       // 3 часа
        'Опасный участок' => env('EVENT_TTL_DANGER_MIN', 4320), // 3 дня
    ],

    /*
    | Скоринг доверия. confidence_score рассчитывается как:
    |   base + (votes_up - votes_down) * weight + (has_photo ? photo_bonus : 0)
    | и кламп в [min, max]. Дальше события с confidence < hide_threshold
    | прячутся в публичной выдаче (видны только автору и админам).
    */
    'confidence' => [
        'base' => 1,
        'vote_weight' => 1,
        'photo_bonus' => 1,
        'min' => 0,
        'max' => 10,
        'hide_threshold' => env('EVENT_HIDE_THRESHOLD', 0),
        'auto_active_threshold' => env('EVENT_AUTO_ACTIVE_THRESHOLD', 3),
    ],

    /*
    | Антиспам по созданию событий одним пользователем.
    */
    'spam' => [
        'max_events_per_user_per_day' => env('EVENT_MAX_PER_USER_PER_DAY', 10),
        'max_votes_per_user_per_day' => env('EVENT_MAX_VOTES_PER_USER_PER_DAY', 50),
    ],

    /*
    | Слияние дублей: события одного типа в радиусе считаются одним,
    | новый POST конвертится в +1 голос автору существующего.
    */
    'dedupe' => [
        'enabled' => true,
        'radius_meters' => env('EVENT_DEDUPE_RADIUS_M', 500),
        'time_window_minutes' => env('EVENT_DEDUPE_WINDOW_MIN', 120),
    ],

    /*
    | Подписка маршрутов на события. "Активный маршрут" — это план,
    | у которого arrival_time ещё впереди, либо arrival_time не задан
    | и план создан недавно (active_route_window_hours).
    */
    'route_subscription' => [
        'corridor_km' => env('EVENT_ROUTE_CORRIDOR_KM', 5.0),
        'polyline_sample_step' => env('EVENT_POLYLINE_STEP', 20),
        'active_route_window_hours' => env('EVENT_ACTIVE_ROUTE_WINDOW_H', 48),
        // Уведомление шлём только при confidence >= порога — чтобы не спамить
        // непроверенными краудсорс-событиями.
        'min_confidence_for_notify' => env('EVENT_MIN_CONFIDENCE_FOR_NOTIFY', 2),
    ],

];
