<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->string('highway')->nullable()->after('type');
            $table->unsignedSmallInteger('km_marker')->nullable()->after('highway');
            $table->string('brand')->nullable()->after('km_marker');
            $table->decimal('fuel_price', 8, 2)->nullable()->after('brand');
            $table->boolean('has_truck_parking')->default(false)->after('fuel_price');
            $table->decimal('detour_km', 5, 1)->default(0)->after('has_truck_parking');
        });

        $this->seedNavigationObjects();
    }

    public function down(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->dropColumn([
                'highway',
                'km_marker',
                'brand',
                'fuel_price',
                'has_truck_parking',
                'detour_km',
            ]);
        });
    }

    private function seedNavigationObjects(): void
    {
        $existingUpdates = [
            'АЗС Северная' => ['highway' => 'М-4 Дон', 'km_marker' => 180, 'brand' => 'Лукойл', 'fuel_price' => 67.40, 'has_truck_parking' => true, 'detour_km' => 0.6],
            'Стоянка Трасса' => ['highway' => 'М-7 Волга', 'km_marker' => 240, 'brand' => null, 'fuel_price' => null, 'has_truck_parking' => true, 'detour_km' => 1.2],
            'Мотель Дорожный' => ['highway' => 'Р-22 Каспий', 'km_marker' => 510, 'brand' => null, 'fuel_price' => null, 'has_truck_parking' => true, 'detour_km' => 2.4],
            'СТО Магистраль' => ['highway' => 'М-4 Дон', 'km_marker' => 420, 'brand' => null, 'fuel_price' => null, 'has_truck_parking' => true, 'detour_km' => 1.0],
        ];

        foreach ($existingUpdates as $name => $values) {
            DB::table('service_objects')->where('name', $name)->update($values);
        }

        $objects = [
            ['Лукойл М-4 180 км', 'АЗС', 'М-4 Дон', 180, 'Лукойл', 67.40, true, 0.5, 'М-4, 180 км', 'Дизель, AdBlue, душ, кафе, парковка для фур.', 'Дизель, AdBlue, кафе, душ, парковка', 'verified', 4.8, 'road-sunset-low.jpg'],
            ['Газпромнефть М-4 315 км', 'АЗС', 'М-4 Дон', 315, 'Газпромнефть', 66.90, true, 0.8, 'М-4, 315 км', 'АЗС с широким въездом, кафе и зоной короткого отдыха.', 'Дизель, AdBlue, кафе, туалет, парковка', 'verified', 4.6, 'truck-white.jpg'],
            ['Роснефть М-4 470 км', 'АЗС', 'М-4 Дон', 470, 'Роснефть', 66.50, true, 1.1, 'М-4, 470 км', 'Топливная точка для большегрузов, есть стоянка и СТО рядом.', 'Дизель, магазин, СТО, парковка', 'verified', 4.5, 'truck-red.jpg'],
            ['Татнефть М-4 620 км', 'АЗС', 'М-4 Дон', 620, 'Татнефть', 65.80, true, 0.9, 'М-4, 620 км', 'Удобная заправка перед вечерним участком маршрута.', 'Дизель, кафе, душ, парковка', 'verified', 4.7, 'road-warm-forest.jpg'],
            ['Стоянка Дон 390 км', 'Стоянка', 'М-4 Дон', 390, null, null, true, 1.5, 'М-4, 390 км', 'Охраняемая стоянка для фур с освещением и кафе.', 'Охрана, кафе, туалет, душ', 'verified', 4.4, 'trucks-night.jpg'],
            ['Мотель Южный 650 км', 'Ночлег', 'М-4 Дон', 650, null, null, true, 2.0, 'М-4, 650 км', 'Мотель и стоянка для ночного отдыха водителя.', 'Номера, душ, охрана, парковка', 'verified', 4.3, 'road-dark-forest.jpg'],
            ['Лукойл М-7 145 км', 'АЗС', 'М-7 Волга', 145, 'Лукойл', 67.10, true, 0.7, 'М-7, 145 км', 'АЗС на выезде к Волге, есть грузовой подъезд.', 'Дизель, AdBlue, кафе, парковка', 'verified', 4.5, 'road-green-forest.jpg'],
            ['Газпромнефть М-7 285 км', 'АЗС', 'М-7 Волга', 285, 'Газпромнефть', 66.70, true, 0.9, 'М-7, 285 км', 'Заправка с кафе и зоной отдыха.', 'Дизель, кафе, туалет, парковка', 'verified', 4.4, 'truck-white.jpg'],
            ['Стоянка Волга 330 км', 'Стоянка', 'М-7 Волга', 330, null, null, true, 1.4, 'М-7, 330 км', 'Охраняемая стоянка для короткого отдыха и ночлега.', 'Охрана, кафе, душ, парковка', 'verified', 4.2, 'trucks-night.jpg'],
            ['Роснефть Р-22 260 км', 'АЗС', 'Р-22 Каспий', 260, 'Роснефть', 66.30, true, 1.0, 'Р-22, 260 км', 'АЗС с парковкой для большегрузного транспорта.', 'Дизель, магазин, парковка', 'verified', 4.4, 'road-black-canyon.jpg'],
            ['Мотель Каспий 520 км', 'Ночлег', 'Р-22 Каспий', 520, null, null, true, 2.2, 'Р-22, 520 км', 'Ночлег и охраняемая зона для грузового транспорта.', 'Номера, душ, охрана, парковка', 'verified', 4.2, 'road-mountains-fog.jpg'],
        ];

        foreach ($objects as $object) {
            if (DB::table('service_objects')->where('name', $object[0])->exists()) {
                continue;
            }

            DB::table('service_objects')->insert([
                'name' => $object[0],
                'type' => $object[1],
                'highway' => $object[2],
                'km_marker' => $object[3],
                'brand' => $object[4],
                'fuel_price' => $object[5],
                'has_truck_parking' => $object[6],
                'detour_km' => $object[7],
                'location' => $object[8],
                'description' => $object[9],
                'services' => $object[10],
                'status' => $object[11],
                'rating' => $object[12],
                'image' => $object[13],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
