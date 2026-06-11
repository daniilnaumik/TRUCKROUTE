<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Device;
use App\Models\EventVote;
use App\Models\Fleet;
use App\Models\NewsArticle;
use App\Models\PoiRouteSelection;
use App\Models\RoadEvent;
use App\Models\RouteAssignment;
use App\Models\RoutePlan;
use App\Models\RouteRecommendation;
use App\Models\ServiceDocument;
use App\Models\ServiceObject;
use App\Models\TripSession;
use App\Models\User;
use App\Models\UserPoiFavorite;
use App\Models\UserSetting;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            PoiRouteSelection::class,
            UserPoiFavorite::class,
            EventVote::class,
            RouteRecommendation::class,
            RouteAssignment::class,
            TripSession::class,
            Device::class,
            Cargo::class,
            NewsArticle::class,
            UserSetting::class,
            RoutePlan::class,
            Vehicle::class,
            RoadEvent::class,
            ServiceObject::class,
            ServiceDocument::class,
            Fleet::class,
            User::class,
        ] as $model) {
            $model::query()->delete();
        }

        DB::table('fleet_drivers')->delete();

        Schema::enableForeignKeyConstraints();

        $admin = User::create([
            'name' => 'Администратор TruckRoute BY',
            'email' => 'admin@truckroute.local',
            'phone' => '+375 29 100-00-01',
            'role' => User::ROLE_ADMIN,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $driver = User::create([
            'name' => 'Даниил Наумик',
            'email' => 'driver@truckroute.local',
            'phone' => '+375 29 200-00-02',
            'role' => User::ROLE_DRIVER,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $secondDriver = User::create([
            'name' => 'Алексей Ковалев',
            'email' => 'driver2@truckroute.local',
            'phone' => '+375 44 210-43-11',
            'role' => User::ROLE_DRIVER,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $provider = User::create([
            'name' => 'Партнер Белоруснефть',
            'email' => 'provider@truckroute.local',
            'phone' => '+375 17 300-77-77',
            'role' => User::ROLE_PROVIDER,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $fleetOwner = User::create([
            'name' => 'Логист МинскТрансКарго',
            'email' => 'fleet@truckroute.local',
            'phone' => '+375 29 500-80-80',
            'role' => User::ROLE_FLEET,
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $fleet = Fleet::create([
            'owner_id' => $fleetOwner->id,
            'name' => 'МинскТрансКарго',
            'inn' => '192837465',
            'description' => 'Демо-автопарк для рейсов по Республике Беларусь и коридору Брест - Москва.',
        ]);
        $fleet->drivers()->attach($driver->id, ['role_in_fleet' => 'driver']);
        $fleet->drivers()->attach($secondDriver->id, ['role_in_fleet' => 'driver']);

        $vehicles = [
            Vehicle::create([
                'user_id' => $driver->id,
                'title' => 'Volvo FH для М1',
                'type' => 'Тягач + полуприцеп',
                'model' => 'Volvo FH 500',
                'fuel_type' => 'Дизель',
                'allowed_fuel' => 'Дизель Евро-5, AdBlue',
                'tank_capacity_l' => 650,
                'consumption_l_per_100' => 30.50,
                'cruise_speed_kmh' => 82,
                'curb_weight_t' => 15.80,
                'restrictions' => 'ADR только по согласованному маршруту',
                'image' => 'trucks/volvo.jpg',
                'is_active' => true,
            ]),
            Vehicle::create([
                'user_id' => $driver->id,
                'title' => 'MAN TGX рефрижератор',
                'type' => 'Рефрижератор',
                'model' => 'MAN TGX 18.480',
                'fuel_type' => 'Дизель',
                'allowed_fuel' => 'Дизель, AdBlue',
                'tank_capacity_l' => 580,
                'consumption_l_per_100' => 32.20,
                'cruise_speed_kmh' => 80,
                'curb_weight_t' => 17.20,
                'restrictions' => 'Температурный режим от -18 до +6',
                'image' => 'truck-white.jpg',
                'is_active' => false,
            ]),
            Vehicle::create([
                'user_id' => $secondDriver->id,
                'title' => 'Scania R450 тент',
                'type' => 'Тентованный полуприцеп',
                'model' => 'Scania R450',
                'fuel_type' => 'Дизель',
                'allowed_fuel' => 'Дизель',
                'tank_capacity_l' => 620,
                'consumption_l_per_100' => 29.80,
                'cruise_speed_kmh' => 84,
                'curb_weight_t' => 15.40,
                'restrictions' => 'Без негабарита',
                'image' => 'truck-red.jpg',
                'is_active' => true,
            ]),
        ];

        foreach ([
            ['Мебель Минск - Гродно', 'Обычный', 11.40, 'Тент, пломба, без температурного режима'],
            ['Молочная продукция на Витебск', 'Рефриж', 8.20, 'Температура +2...+6, контроль каждые 2 часа'],
            ['Запчасти для СТО Брест', 'Обычный', 5.60, 'Хрупкие места закрепить ремнями'],
            ['Лакокрасочные материалы', 'Опасный', 6.10, 'ADR, огнетушители и стоянки вне жилой зоны'],
        ] as $cargo) {
            Cargo::create([
                'user_id' => $driver->id,
                'title' => $cargo[0],
                'flag' => $cargo[1],
                'weight_t' => $cargo[2],
                'requirements' => $cargo[3],
            ]);
        }

        $pois = collect($this->serviceObjects($provider->id))
            ->map(fn (array $poi) => ServiceObject::create($poi));

        $events = collect($this->roadEvents($driver->id))
            ->map(fn (array $event) => RoadEvent::create($event));

        $routes = collect($this->routes($driver->id, $vehicles[0]->id, $vehicles[1]->id))
            ->map(fn (array $route) => RoutePlan::create($route));

        $this->createRecommendations($routes, $pois);

        RouteAssignment::create([
            'fleet_id' => $fleet->id,
            'driver_user_id' => $secondDriver->id,
            'issued_by_user_id' => $fleetOwner->id,
            'route_plan_id' => null,
            'origin' => 'Брест',
            'origin_point' => ['lat' => 52.0976, 'lng' => 23.7341, 'label' => 'Брест'],
            'destination' => 'Минск',
            'destination_point' => ['lat' => 53.9006, 'lng' => 27.5590, 'label' => 'Минск'],
            'via_points' => [
                ['lat' => 52.4453, 'lng' => 25.1784, 'label' => 'Кобрин'],
                ['lat' => 53.1327, 'lng' => 26.0139, 'label' => 'Барановичи'],
            ],
            'planned_start_at' => now()->addDays(2)->setTime(6, 40),
            'comment' => 'Доставка паллет с комплектующими. Предпочтительны остановки на М1 с охраняемой парковкой.',
            'status' => 'issued',
        ]);

        $session = TripSession::create([
            'user_id' => $driver->id,
            'route_plan_id' => $routes->first()->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(35),
            'last_lat' => 53.5170,
            'last_lng' => 26.3400,
            'last_location_at' => now()->subMinutes(3),
            'notified_recommendation_ids' => [],
            'accepted_stop_ids' => [],
            'rejected_stop_ids' => [],
        ]);

        PoiRouteSelection::create([
            'trip_session_id' => $session->id,
            'service_object_id' => $pois->where('name', 'Белоруснефть М1 Столбцы')->first()->id,
            'action' => 'accepted',
        ]);

        foreach ($pois->take(5) as $poi) {
            UserPoiFavorite::create([
                'user_id' => $driver->id,
                'service_object_id' => $poi->id,
            ]);
        }

        foreach ($events->take(3) as $event) {
            EventVote::create([
                'road_event_id' => $event->id,
                'user_id' => $secondDriver->id,
                'vote' => 1,
            ]);
        }

        Device::create([
            'user_id' => $driver->id,
            'platform' => 'android',
            'fcm_token' => 'demo-fcm-token-belarus-driver',
            'app_version' => '1.0.0-demo',
            'locale' => 'ru_BY',
            'last_seen_at' => now()->subMinutes(8),
        ]);

        foreach ($this->news($admin->id) as $article) {
            NewsArticle::create($article);
        }

        foreach ($this->documents() as $doc) {
            ServiceDocument::create($doc);
        }

        foreach ([$driver, $secondDriver] as $user) {
            UserSetting::create([
                'user_id' => $user->id,
                'incident_notifications' => true,
                'privacy_policy_accepted' => true,
                'data_processing_accepted' => true,
                'notification_radius_km' => $user->id === $driver->id ? 20 : 15,
                'last_password_change_at' => now()->subDays(9),
            ]);
        }
    }

    private function serviceObjects(int $providerId): array
    {
        $items = [
            ['Белоруснефть М1 Брест Восток', 'АЗС', 'М1', 12, 'Белоруснефть', 2.36, true, 0.4, 'Брестская область, трасса М1, восточный выезд из Бреста', 52.1220, 23.8910, 'Крупная АЗС на выезде из Бреста: дизель, AdBlue, магазин, душ и площадка для автопоездов.', 'Дизель, AdBlue, кафе, душ, стоянка, туалет', 'verified', 4.8, 'road-green-forest.jpg', $providerId, ['М1', 'Брест', 'AdBlue', '24/7'], '<p>Удобная точка для старта рейса по коридору Брест - Минск - граница РФ. Есть широкая зона маневрирования и отдельный въезд для фур.</p>'],
            ['Стоянка Кобрин Логистик', 'Стоянка', 'М1', 48, null, null, true, 1.1, 'Кобринский район, рядом с М1', 52.2445, 24.3567, 'Охраняемая парковка на 70 мест, освещение, видеонаблюдение, недорогой душ.', 'Охрана, душ, туалет, шиномонтаж рядом', 'verified', 4.5, 'trucks-night.jpg', null, ['ночлег', 'Кобрин', 'охрана'], '<p>Хорошая остановка после прохождения Бреста или перед утренним выходом на Минск.</p>'],
            ['Кафе Дальнобой Пружаны', 'Еда', 'Р85', 31, null, null, true, 0.8, 'Пружанский район, Р85', 52.5459, 24.4641, 'Домашняя кухня, ранние завтраки, парковка для грузовых машин.', 'Горячее питание, кофе, туалет, парковка', 'verified', 4.3, 'road-sunset-low.jpg', null, ['кафе', 'Пружаны', 'еда'], '<p>Популярное место у водителей на направлении Брест - Слоним.</p>'],
            ['Белоруснефть М1 Барановичи', 'АЗС', 'М1', 207, 'Белоруснефть', 2.35, true, 0.5, 'Барановичский район, М1', 53.1503, 25.9878, 'АЗС с широким въездом, AdBlue, магазином и быстрой кухней.', 'Дизель, AdBlue, магазин, кафе, стоянка', 'verified', 4.7, 'truck-white.jpg', $providerId, ['М1', 'Барановичи', 'кафе'], '<p>Опорная точка для маршрутов Брест - Минск и Гродно - Минск.</p>'],
            ['СТО Магистраль Барановичи', 'СТО', 'М1', 214, null, null, true, 1.6, 'Барановичи, промзона возле М1', 53.1288, 26.0412, 'Диагностика тягачей, электрика, тормозная система, шиномонтаж 24/7.', 'Ремонт, диагностика, шиномонтаж, электрика', 'verified', 4.6, 'truck-red.jpg', null, ['СТО', 'шиномонтаж', '24/7'], '<p>Сервис для планового и срочного ремонта перед длинным плечом до Минска или Бреста.</p>'],
            ['Белоруснефть М1 Столбцы', 'АЗС', 'М1', 287, 'Белоруснефть', 2.36, true, 0.6, 'Столбцовский район, М1', 53.5176, 26.7306, 'АЗС с кафе, душем, AdBlue и парковкой для ночной паузы.', 'Дизель, AdBlue, кафе, душ, парковка', 'verified', 4.8, 'road-warm-forest.jpg', $providerId, ['М1', 'Столбцы', 'ночлег'], '<p>Одна из самых удобных точек перед Минском: можно дозаправиться, поесть и проверить крепление груза.</p>'],
            ['Мотель Мирский тракт', 'Ночлег', 'М1', 302, null, null, true, 2.3, 'Недалеко от поворота на Мир', 53.4631, 26.5025, 'Небольшой мотель с охраняемой стоянкой, душем и поздним заселением.', 'Номера, душ, кафе, охрана, парковка', 'verified', 4.2, 'road-dark-forest.jpg', null, ['ночлег', 'Мир', 'стоянка'], '<p>Подходит для водителей, которые не хотят заходить в Минск вечером.</p>'],
            ['Белоруснефть М2 аэропорт', 'АЗС', 'М2', 31, 'Белоруснефть', 2.37, true, 0.3, 'М2, район Национального аэропорта Минск', 53.8879, 28.0263, 'АЗС на аэропортовском направлении с магазином, кофе и быстрым выездом на М1/МКАД.', 'Дизель, кофе, магазин, туалет', 'verified', 4.4, 'road-sunset-long.jpg', $providerId, ['М2', 'Минск', 'аэропорт'], '<p>Удобна для рейсов через Минск и Смолевичи.</p>'],
            ['Стоянка Логойск М3', 'Стоянка', 'М3', 48, null, null, true, 1.4, 'Логойский район, М3', 54.2010, 27.8512, 'Охраняемая стоянка на витебском направлении, есть кафе и душ.', 'Охрана, кафе, душ, туалет', 'verified', 4.4, 'trucks-night.jpg', null, ['М3', 'Логойск', 'ночлег'], '<p>Хорошая пауза перед участком на Бегомль и Лепель.</p>'],
            ['Белоруснефть М3 Бегомль', 'АЗС', 'М3', 104, 'Белоруснефть', 2.35, true, 0.5, 'Бегомль, трасса М3', 54.7310, 28.0507, 'Точка дозаправки на маршруте Минск - Витебск, есть горячие напитки и парковка.', 'Дизель, магазин, кофе, парковка', 'verified', 4.5, 'road-mountains-fog.jpg', $providerId, ['М3', 'Бегомль', 'АЗС'], '<p>Полезная остановка для рейсов на Витебск и Полоцк.</p>'],
            ['Белоруснефть М4 Березино', 'АЗС', 'М4', 103, 'Белоруснефть', 2.36, true, 0.4, 'Березино, трасса М4', 53.8433, 28.9874, 'АЗС на направлении Минск - Могилев, удобная для грузовых составов.', 'Дизель, AdBlue, магазин, кафе', 'verified', 4.6, 'road-green-forest.jpg', $providerId, ['М4', 'Березино', 'AdBlue'], '<p>Рекомендуемая точка перед длинным участком к Могилеву.</p>'],
            ['СТО Могилев ТракСервис', 'СТО', 'М4', 194, null, null, true, 2.0, 'Могилев, объездная дорога', 53.9301, 30.2749, 'Ремонт тягачей, диагностика пневмосистемы, сварка и шиномонтаж.', 'СТО, шиномонтаж, сварка, диагностика', 'verified', 4.7, 'truck-red.jpg', null, ['Могилев', 'СТО', 'ремонт'], '<p>Сервис для маршрутов на Гомель, Бобруйск и Оршу.</p>'],
            ['Белоруснефть М5 Бобруйск', 'АЗС', 'М5', 139, 'Белоруснефть', 2.35, true, 0.6, 'Бобруйский район, М5', 53.1615, 29.1908, 'АЗС и кафе на южном направлении Минск - Гомель.', 'Дизель, кафе, магазин, парковка', 'verified', 4.5, 'road-sunset-low.jpg', $providerId, ['М5', 'Бобруйск', 'еда'], '<p>Удобная точка для обеда и проверки маршрута перед Гомельской областью.</p>'],
            ['Мотель Светлогорск М5', 'Ночлег', 'М5', 205, null, null, true, 2.5, 'Светлогорский район, недалеко от М5', 52.6389, 29.7508, 'Ночлег, душ, стоянка и простое кафе для водителей.', 'Номера, душ, кафе, парковка', 'verified', 4.1, 'road-dark-forest.jpg', null, ['М5', 'ночлег', 'Светлогорск'], '<p>Подходит для рейсов с поздним прибытием в южную часть страны.</p>'],
            ['Белоруснефть М6 Лида', 'АЗС', 'М6', 142, 'Белоруснефть', 2.36, true, 0.7, 'Лидский район, М6', 53.8850, 25.2926, 'АЗС на маршруте Минск - Гродно, есть кафе, AdBlue и грузовая парковка.', 'Дизель, AdBlue, кафе, стоянка', 'verified', 4.7, 'road-warm-forest.jpg', $providerId, ['М6', 'Лида', 'Гродно'], '<p>Опорная остановка перед заходом в Гродно или разворотом на Новогрудок.</p>'],
            ['Стоянка Щучин М6', 'Стоянка', 'М6', 189, null, null, true, 1.2, 'Щучинский район, М6', 53.6152, 24.7571, 'Охраняемая стоянка, кафе, душ, рядом шиномонтаж.', 'Охрана, кафе, душ, шиномонтаж', 'verified', 4.3, 'trucks-night.jpg', null, ['М6', 'Щучин', 'стоянка'], '<p>Хороший вариант для ночной паузы перед Гродно.</p>'],
            ['Белоруснефть М8 Орша', 'АЗС', 'М8', 74, 'Белоруснефть', 2.37, true, 0.5, 'Орша, трасса М8/Е95', 54.5057, 30.4108, 'АЗС на северо-восточном коридоре, удобна для рейсов на Витебск и Смоленск.', 'Дизель, AdBlue, магазин, кафе', 'verified', 4.6, 'road-black-canyon.jpg', $providerId, ['М8', 'Орша', 'Е95'], '<p>Точка для международных рейсов через северо-восток Беларуси.</p>'],
            ['Кафе Полесский обед', 'Еда', 'Р31', 66, null, null, true, 0.9, 'Мозырский район, Р31', 52.0484, 29.2441, 'Сытные обеды, парковка для фур, душ по запросу.', 'Горячее питание, кофе, туалет, парковка', 'verified', 4.2, 'road-sunset-long.jpg', null, ['Полесье', 'еда', 'Мозырь'], '<p>Демо-точка для южных рейсов по Гомельской области.</p>'],
        ];

        return array_map(function (array $item): array {
            return [
                'name' => $item[0],
                'type' => $item[1],
                'highway' => $item[2],
                'km_marker' => $item[3],
                'brand' => $item[4],
                'fuel_price' => $item[5],
                'has_truck_parking' => $item[6],
                'detour_km' => $item[7],
                'location' => $item[8],
                'lat' => $item[9],
                'lng' => $item[10],
                'description' => $item[11],
                'services' => $item[12],
                'status' => $item[13],
                'rating' => $item[14],
                'image' => $item[15],
                'provider_id' => $item[16],
                'tags' => $item[17],
                'content' => $item[18],
                'verified' => true,
                'view_count' => random_int(24, 620),
                'selections_count' => random_int(1, 86),
                'gallery' => [$item[15], 'road-green-forest.jpg', 'truck-white.jpg'],
            ];
        }, $items);
    }

    private function roadEvents(int $driverId): array
    {
        $events = [
            ['Ремонт правой полосы на М1 у Столбцов', 'Ремонт', 'М1', 'М1, 286 км', 'Сужение до одной полосы в сторону Минска. Грузовикам лучше заложить плюс 20 минут.', 'active', 'средне', 20, 7, 'road-warm-forest.jpg', 53.5160, 26.7110],
            ['Очередь грузовиков у ПП Козловичи', 'Очередь', 'М1', 'Брест, пункт пропуска Козловичи', 'Накопление транспорта перед границей. Водители сообщают о медленном оформлении.', 'active', 'важно', 90, 8, 'trucks-night.jpg', 52.0932, 23.6504],
            ['Туман на участке Логойск - Бегомль', 'Погода', 'М3', 'М3, 70-110 км', 'Плотный утренний туман, видимость местами до 150 метров.', 'checking', 'средне', 15, 5, 'road-mountains-fog.jpg', 54.4507, 28.0120],
            ['ДТП на объездной Бобруйска', 'ДТП', 'М5', 'М5, объездная Бобруйска', 'Занята правая полоса, работают службы. Движение грузовых машин замедлено.', 'active', 'важно', 35, 6, 'truck-red.jpg', 53.1510, 29.2422],
            ['Весовой контроль на М6 перед Лидой', 'Контроль', 'М6', 'М6, 137 км', 'Передвижной пункт весового контроля. Проверяют осевые нагрузки и документы.', 'active', 'низко', 10, 4, 'truck-white.jpg', 53.8738, 25.3440],
            ['Ямочный ремонт у Орши', 'Ремонт', 'М8', 'М8, 72 км', 'Короткие остановки потока из-за дорожных работ.', 'checking', 'низко', 12, 4, 'road-black-canyon.jpg', 54.5006, 30.3908],
        ];

        return array_map(fn (array $event): array => [
            'title' => $event[0],
            'type' => $event[1],
            'highway' => $event[2],
            'location' => $event[3],
            'description' => $event[4],
            'status' => $event[5],
            'importance' => $event[6],
            'delay_minutes' => $event[7],
            'confidence_score' => $event[8],
            'image' => $event[9],
            'lat' => $event[10],
            'lng' => $event[11],
            'reported_at' => now()->subMinutes(random_int(20, 240)),
            'expires_at' => now()->addHours(random_int(2, 10)),
            'created_by_user_id' => $driverId,
        ], $events);
    }

    private function routes(int $driverId, int $mainVehicleId, int $reeferVehicleId): array
    {
        return [
            $this->route(
                $driverId,
                $mainVehicleId,
                'Брест - Минск по М1',
                ['Брест', 52.0976, 23.7341],
                ['Минск', 53.9006, 27.5590],
                [['Кобрин', 52.2138, 24.3564], ['Барановичи', 53.1327, 26.0139], ['Столбцы', 53.4785, 26.7434]],
                'Тягач + полуприцеп',
                'Мебель',
                11.40,
                348,
                285,
                106.14,
                410,
                4,
                'Оптимально заправиться в Бресте, сделать паузу у Барановичей и финальную проверку перед Минском у Столбцов.',
                'road-green-forest.jpg'
            ),
            $this->route(
                $driverId,
                $reeferVehicleId,
                'Минск - Витебск через М3',
                ['Минск', 53.9006, 27.5590],
                ['Витебск', 55.1848, 30.2016],
                [['Логойск', 54.2064, 27.8512], ['Бегомль', 54.7314, 28.0573], ['Лепель', 54.8828, 28.6990]],
                'Рефрижератор',
                'Молочная продукция',
                8.20,
                292,
                245,
                94.02,
                360,
                3,
                'Температурный груз: держать короткие остановки, контроль холодильной установки на Бегомле и перед Витебском.',
                'road-mountains-fog.jpg'
            ),
            $this->route(
                $driverId,
                $mainVehicleId,
                'Минск - Гродно через Лиду',
                ['Минск', 53.9006, 27.5590],
                ['Гродно', 53.6694, 23.8131],
                [['Воложин', 54.0906, 26.5267], ['Лида', 53.8874, 25.3022], ['Щучин', 53.6014, 24.7465]],
                'Тентованный полуприцеп',
                'Паллеты',
                13.00,
                276,
                230,
                84.18,
                420,
                3,
                'Главная остановка на М6 у Лиды: дозаправка, кофе, проверка ремней и давления в шинах.',
                'road-warm-forest.jpg'
            ),
            $this->route(
                $driverId,
                $mainVehicleId,
                'Минск - Гомель по М5',
                ['Минск', 53.9006, 27.5590],
                ['Гомель', 52.4345, 30.9754],
                [['Марьина Горка', 53.5078, 28.1466], ['Бобруйск', 53.1384, 29.2214], ['Жлобин', 52.8926, 30.0240]],
                'Тягач + полуприцеп',
                'Стройматериалы',
                18.50,
                312,
                270,
                98.28,
                405,
                4,
                'Маршрут с тяжелым грузом: не пропускать паузу у Бобруйска и смотреть события на объездной.',
                'road-sunset-low.jpg'
            ),
            $this->route(
                $driverId,
                $mainVehicleId,
                'Гродно - Витебск через Минск',
                ['Гродно', 53.6694, 23.8131],
                ['Витебск', 55.1848, 30.2016],
                [['Лида', 53.8874, 25.3022], ['Минск', 53.9006, 27.5590], ['Бегомль', 54.7314, 28.0573]],
                'Тягач + полуприцеп',
                'Сборный груз',
                9.80,
                535,
                430,
                163.18,
                410,
                5,
                'Длинный внутренний рейс: ночлег лучше планировать после Минска или на стоянке Логойск М3.',
                'trucks-night.jpg'
            ),
            $this->route(
                $driverId,
                $mainVehicleId,
                'Брест - Москва через М1',
                ['Брест', 52.0976, 23.7341],
                ['Москва', 55.7558, 37.6173],
                [['Минск', 53.9006, 27.5590], ['Орша', 54.5081, 30.4172], ['Смоленск', 54.7826, 32.0453]],
                'Тягач + полуприцеп',
                'Экспортный груз',
                16.70,
                1060,
                820,
                323.30,
                410,
                7,
                'Международный коридор: проверка документов в Бресте, топливо на М1, контроль отдыха до Смоленска.',
                'road-sunset-long.jpg'
            ),
        ];
    }

    private function route(
        int $driverId,
        int $vehicleId,
        string $title,
        array $origin,
        array $destination,
        array $via,
        string $vehicleType,
        string $cargoType,
        float $cargoWeight,
        int $distance,
        int $driveMinutes,
        float $fuelNeeded,
        int $range,
        int $stops,
        string $recommendations,
        string $image
    ): array {
        $polyline = array_merge(
            [[$origin[1], $origin[2]]],
            array_map(fn (array $point) => [$point[1], $point[2]], $via),
            [[$destination[1], $destination[2]]],
        );

        return [
            'user_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'title' => $title,
            'origin' => $origin[0],
            'origin_point' => ['lat' => $origin[1], 'lng' => $origin[2], 'label' => $origin[0]],
            'destination' => $destination[0],
            'destination_point' => ['lat' => $destination[1], 'lng' => $destination[2], 'label' => $destination[0]],
            'via_point' => implode(', ', array_column($via, 0)),
            'via_points' => array_map(fn (array $point) => ['lat' => $point[1], 'lng' => $point[2], 'label' => $point[0]], $via),
            'start_time' => now()->addDays(random_int(1, 8))->setTime(random_int(5, 10), random_int(0, 1) ? 0 : 30),
            'vehicle_type' => $vehicleType,
            'cargo_type' => $cargoType,
            'cargo_weight_t' => $cargoWeight,
            'vehicle_curb_weight_t' => $vehicleType === 'Рефрижератор' ? 17.20 : 15.80,
            'gross_weight_t' => $cargoWeight + ($vehicleType === 'Рефрижератор' ? 17.20 : 15.80) + 0.45,
            'start_fuel_l' => 430,
            'tank_capacity_l' => $vehicleType === 'Рефрижератор' ? 580 : 650,
            'consumption_l_per_100' => $vehicleType === 'Рефрижератор' ? 32.20 : 30.50,
            'effective_consumption_l_per_100' => round($fuelNeeded / max($distance, 1) * 100, 2),
            'reserve_percent' => 15,
            'reserve_l' => $vehicleType === 'Рефрижератор' ? 87 : 97.5,
            'cruise_speed_kmh' => $vehicleType === 'Рефрижератор' ? 80 : 82,
            'planning_mode' => 'Безопасный',
            'distance_km' => $distance,
            'drive_time_minutes' => $driveMinutes,
            'arrival_time' => now()->addDays(2)->addMinutes($driveMinutes),
            'fuel_needed_l' => $fuelNeeded,
            'fuel_cost_rub' => round($fuelNeeded * 2.36, 2),
            'range_km' => $range,
            'stops_count' => $stops,
            'recommendations' => $recommendations,
            'image' => $image,
            'polyline_json' => json_encode($polyline, JSON_UNESCAPED_UNICODE),
            'routing_provider' => 'demo-by',
        ];
    }

    private function createRecommendations($routes, $pois): void
    {
        $map = [
            'Брест - Минск по М1' => ['Белоруснефть М1 Брест Восток', 'Стоянка Кобрин Логистик', 'Белоруснефть М1 Барановичи', 'Белоруснефть М1 Столбцы'],
            'Минск - Витебск через М3' => ['Стоянка Логойск М3', 'Белоруснефть М3 Бегомль'],
            'Минск - Гродно через Лиду' => ['Белоруснефть М6 Лида', 'Стоянка Щучин М6'],
            'Минск - Гомель по М5' => ['Белоруснефть М5 Бобруйск', 'Мотель Светлогорск М5'],
            'Гродно - Витебск через Минск' => ['Белоруснефть М6 Лида', 'Белоруснефть М2 аэропорт', 'Стоянка Логойск М3', 'Белоруснефть М3 Бегомль'],
            'Брест - Москва через М1' => ['Белоруснефть М1 Брест Восток', 'Белоруснефть М1 Барановичи', 'Белоруснефть М1 Столбцы', 'Белоруснефть М8 Орша'],
        ];

        foreach ($routes as $route) {
            foreach (($map[$route->title] ?? []) as $index => $poiName) {
                $poi = $pois->firstWhere('name', $poiName);
                if (!$poi) {
                    continue;
                }

                RouteRecommendation::create([
                    'route_plan_id' => $route->id,
                    'service_object_id' => $poi->id,
                    'type' => $poi->type,
                    'order_index' => $index + 1,
                    'distance_from_start_km' => min($route->distance_km - 20, 45 + $index * 95),
                    'detour_km' => $poi->detour_km,
                    'eta_at' => $route->start_time?->copy()->addMinutes(50 + $index * 95),
                    'fuel_before_l' => max(90, 380 - $index * 70),
                    'suggested_fuel_l' => $poi->type === 'АЗС' ? 180 : null,
                    'note' => $poi->type === 'АЗС'
                        ? 'Рекомендуемая дозаправка и короткая пауза.'
                        : 'Подходит для отдыха без заметного отклонения от маршрута.',
                ]);
            }
        }
    }

    private function news(int $adminId): array
    {
        $articles = [
            ['Открыт обновленный участок М1 под Столбцами', 'После ремонта расширены зоны разгона и обновлена разметка для грузового транспорта.', 'М1', 'road-green-forest.jpg'],
            ['Где водителю фуры безопасно переночевать в Беларуси', 'Подборка проверенных стоянок на М1, М3, М5 и М6 с охраной, душем и кафе.', 'безопасность', 'trucks-night.jpg'],
            ['Белоруснефть добавила AdBlue на ключевых трассовых АЗС', 'На демо-карте отмечены точки на М1, М3, М4, М6 и М8, где водителю удобно пополнить AdBlue.', 'АЗС', 'road-warm-forest.jpg'],
            ['Погодный чек-лист для рейса Минск - Витебск', 'На северном направлении часто встречаются туман и мокрая обочина: что проверить перед выездом.', 'погода', 'road-mountains-fog.jpg'],
            ['TruckRoute BY: карточки точек теперь появляются до приближения', 'Демо-сценарий показывает, как приложение заранее предлагает АЗС, еду, ночлег и СТО по маршруту.', 'TruckRoute', 'truck-white.jpg'],
            ['Как планировать рейс Брест - Москва без лишних остановок', 'Маршрут по М1 требует контроля документов, топлива и режима отдыха еще до выезда из Бреста.', 'логистика', 'road-sunset-long.jpg'],
        ];

        return array_map(function (array $article) use ($adminId): array {
            return [
                'title' => $article[0],
                'slug' => Str::slug($article[0]) . '-' . Str::lower(Str::random(5)),
                'excerpt' => $article[1],
                'content' => '<h2>' . e($article[0]) . '</h2><p>' . e($article[1]) . '</p><p>Материал подготовлен для демо-стенда TruckRoute с акцентом на Республику Беларусь. Он помогает проверить страницы новостей, карточки, теги и связь с логистическим сценарием водителя.</p><ul><li>учет трассы и ближайших точек;</li><li>подсказки для топлива, отдыха и питания;</li><li>акцент на безопасность и планирование рейса.</li></ul>',
                'image' => $article[3],
                'gallery' => [$article[3], 'road-green-forest.jpg', 'trucks-night.jpg'],
                'tags' => [$article[2], 'Беларусь', 'дальнобой'],
                'author_id' => $adminId,
                'status' => 'published',
                'published_at' => now()->subDays(random_int(1, 18)),
            ];
        }, $articles);
    }

    private function documents(): array
    {
        $documents = [
            ['Политика конфиденциальности', 'privacy', 'Как TruckRoute хранит данные профиля, маршрутов, транспорта и геолокации водителя.'],
            ['Согласие на обработку данных', 'data-processing', 'Демо-документ для обработки персональных данных, истории маршрутов и уведомлений.'],
            ['Пользовательское соглашение', 'terms', 'Правила использования сервиса личной логистики водителя фуры.'],
            ['Правила публикации дорожных событий', 'event-rules', 'Какие события можно добавлять на карту и как работает подтверждение сообществом.'],
        ];

        return array_map(fn (array $doc): array => [
            'title' => $doc[0],
            'slug' => $doc[1],
            'summary' => $doc[2],
            'body' => $doc[2] . ' Текст используется как демонстрационный материал для дипломного проекта TruckRoute.',
            'version' => '1.0',
            'published_at' => now(),
        ], $documents);
    }
}
