<?php

/*
|--------------------------------------------------------------------------
| Geo / Routing configuration
|--------------------------------------------------------------------------
| Принцип: всё должно работать БЕЗ платных ключей (Nominatim + OSRM).
| Если задан YANDEX_API_KEY и провайдер выбран как 'yandex' — используется он.
| Иначе любой 'yandex' автоматически деградирует до бесплатной альтернативы.
*/

return [

    'geocoder' => [
        // Кого использовать как основной геокодер. Допустимо: 'nominatim', 'yandex'.
        'driver' => env('GEO_GEOCODER', 'nominatim'),

        'nominatim' => [
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
            // По правилам OSM требуется содержательный User-Agent с контактом.
            'user_agent' => env('NOMINATIM_USER_AGENT', 'TruckRouteDiplomaApp/1.0 (contact: nikita.zinkovich@gmail.com)'),
            'language' => env('NOMINATIM_LANG', 'ru'),
            'timeout' => 5,
        ],

        'yandex' => [
            // https://yandex.ru/dev/maps/geocoder/
            'base_url' => env('YANDEX_GEOCODER_BASE_URL', 'https://geocode-maps.yandex.ru/1.x/'),
            'api_key' => env('YANDEX_GEOCODER_API_KEY', env('YANDEX_API_KEY')),
            'language' => env('YANDEX_LANG', 'ru_RU'),
            'timeout' => 5,
        ],
    ],

    'routing' => [
        // 'osrm' (бесплатный публичный или self-hosted) или 'yandex'.
        'driver' => env('GEO_ROUTING', 'osrm'),

        'osrm' => [
            'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
            'profile' => env('OSRM_PROFILE', 'driving'),
            'timeout' => 8,
        ],

        'yandex' => [
            // Маршрутизация по фурам у Яндекса требует Driving Router (платный).
            // Здесь только заглушка; на практике под дипломом — OSRM.
            'base_url' => env('YANDEX_ROUTER_BASE_URL', 'https://api.routing.yandex.net/v2/route'),
            'api_key' => env('YANDEX_ROUTER_API_KEY', env('YANDEX_API_KEY')),
            'timeout' => 8,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Front-end map tiles
    |--------------------------------------------------------------------------
    | Для Leaflet/мобилки фронт ходит сюда напрямую. Backend это только знает,
    | чтобы отдать ключ через /api/v1/config (отдельный эндпоинт сделаем позже).
    */
    'tiles' => [
        'driver' => env('GEO_TILES', 'osm'), // 'osm' | 'yandex'
        'osm' => [
            'url_template' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '© OpenStreetMap contributors',
        ],
        'yandex' => [
            // Подключение Яндекс-плиток с фронта требует Yandex JS API + ключ.
            'api_key' => env('YANDEX_JS_API_KEY', env('YANDEX_API_KEY')),
        ],
    ],

    'defaults' => [
        // Дефолты для поиска POI/событий, если клиент ничего не передал.
        'search_radius_km' => 30,
        'max_radius_km' => 200,
        'max_results' => 100,
    ],

];
