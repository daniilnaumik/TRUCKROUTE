<?php

namespace App\Providers;

use App\Services\Geo\Contracts\GeoProvider;
use App\Services\Geo\Contracts\RoutingProvider;
use App\Services\Geo\LocalGazetteer;
use App\Services\Geo\Providers\FallbackGeoProvider;
use App\Services\Geo\Providers\FallbackRoutingProvider;
use App\Services\Geo\Providers\NominatimGeoProvider;
use App\Services\Geo\Providers\OsrmRoutingProvider;
use App\Services\Geo\Providers\YandexGeoProvider;
use App\Services\Geo\Providers\YandexRoutingProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalGazetteer::class, fn () => new LocalGazetteer());

        // Geocoder: yandex с ключом → иначе nominatim. Обёрнут в FallbackGeoProvider
        // (офлайн-справочник городов РФ при недоступности внешнего сервиса).
        $this->app->singleton(GeoProvider::class, function ($app) {
            $driver = (string) config('geo.geocoder.driver', 'nominatim');
            $inner  = null;

            if ($driver === 'yandex') {
                $yandex = new YandexGeoProvider(
                    apiKey: config('geo.geocoder.yandex.api_key'),
                    baseUrl: config('geo.geocoder.yandex.base_url'),
                    language: config('geo.geocoder.yandex.language', 'ru_RU'),
                    timeoutSeconds: (int) config('geo.geocoder.yandex.timeout', 5),
                );
                if ($yandex->hasKey()) {
                    $inner = $yandex;
                }
            }

            $inner ??= new NominatimGeoProvider(
                baseUrl: config('geo.geocoder.nominatim.base_url'),
                userAgent: config('geo.geocoder.nominatim.user_agent'),
                language: config('geo.geocoder.nominatim.language', 'ru'),
                timeoutSeconds: (int) config('geo.geocoder.nominatim.timeout', 5),
            );

            return new FallbackGeoProvider($inner, $app->make(LocalGazetteer::class));
        });

        // Routing: yandex заглушка → OSRM. Обёрнут в FallbackRoutingProvider
        // (haversine прямая при недоступности OSRM).
        $this->app->singleton(RoutingProvider::class, function ($app) {
            $driver = (string) config('geo.routing.driver', 'osrm');
            $inner  = null;

            if ($driver === 'yandex') {
                $yandex = new YandexRoutingProvider(
                    apiKey: config('geo.routing.yandex.api_key'),
                    baseUrl: config('geo.routing.yandex.base_url'),
                    timeoutSeconds: (int) config('geo.routing.yandex.timeout', 8),
                );
                if ($yandex->hasKey()) {
                    $inner = $yandex;
                }
            }

            $inner ??= new OsrmRoutingProvider(
                baseUrl: config('geo.routing.osrm.base_url'),
                profile: config('geo.routing.osrm.profile', 'driving'),
                timeoutSeconds: (int) config('geo.routing.osrm.timeout', 8),
            );

            return new FallbackRoutingProvider($inner);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip())->response(
                    fn () => response()->json([
                        'message' => 'Слишком много попыток входа. Подождите минуту и попробуйте снова.',
                    ], 429)
                ),
                Limit::perHour(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request) =>
            Limit::perMinute(3)->by($request->ip())->response(
                fn () => response()->json([
                    'message' => 'Слишком много регистраций с этого устройства. Повторите попытку позже.',
                ], 429)
            )
        );
    }
}
