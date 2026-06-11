<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_items', function (Blueprint $table) {
            $table->id();
            $table->string('dictionary', 50)->index();
            $table->string('value', 100);
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dictionary', 'value']);
        });

        $now = now();
        $rows = [];

        $defaults = [
            'vehicle_types' => [
                ['Тягач + полуприцеп', 'Магистральный тягач с полуприцепом'],
                ['Одиночка', 'Грузовой автомобиль без прицепа'],
                ['Фургон', 'Закрытый грузовой фургон'],
                ['Рефрижератор', 'Транспорт с температурным режимом'],
                ['Цистерна', 'Перевозка жидких и сыпучих грузов'],
            ],
            'cargo_types' => [
                ['Обычный', 'Груз без специальных ограничений'],
                ['Скоропортящийся', 'Требует контроля срока и условий перевозки'],
                ['Опасный', 'Опасный груз с требованиями ADR'],
                ['Негабарит', 'Негабаритный или тяжеловесный груз'],
                ['Рефриж', 'Груз с температурным режимом'],
            ],
            'event_types' => [
                ['Контроль', 'Весовой, документальный или дорожный контроль'],
                ['Очередь', 'Очередь транспорта или задержка оформления'],
                ['Ремонт', 'Дорожные работы и сужение полос'],
                ['ДТП', 'Дорожно-транспортное происшествие'],
                ['Погода', 'Туман, снег, гололёд и другие погодные условия'],
                ['Затор', 'Замедление или остановка движения'],
                ['Перекрытие', 'Полное или частичное перекрытие дороги'],
            ],
            'poi_categories' => [
                ['АЗС', 'Автозаправочная станция'],
                ['Стоянка', 'Стоянка для грузового транспорта'],
                ['Ночлег', 'Мотель, гостиница или место отдыха'],
                ['СТО', 'Ремонт и техническое обслуживание'],
                ['Кафе', 'Питание по маршруту'],
                ['Еда', 'Питание и придорожный сервис'],
            ],
            'tags' => [
                ['АЗС', 'Заправки и топливо'],
                ['Безопасность', 'Безопасность водителя и груза'],
                ['Беларусь', 'Материалы и объекты в Беларуси'],
                ['Дальнобой', 'Дальние грузовые перевозки'],
                ['Логистика', 'Планирование и организация перевозок'],
                ['М1', 'Материалы о трассе М1'],
                ['Погода', 'Погодные условия'],
                ['Ремонт', 'Ремонт дорог и транспорта'],
                ['СТО', 'Техническое обслуживание'],
                ['TruckRoute', 'Новости и возможности сервиса'],
            ],
        ];

        foreach ($defaults as $dictionary => $items) {
            foreach ($items as $index => [$value, $description]) {
                $rows[] = [
                    'dictionary' => $dictionary,
                    'value' => $value,
                    'label' => $value,
                    'description' => $description,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('dictionary_items')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_items');
    }
};
