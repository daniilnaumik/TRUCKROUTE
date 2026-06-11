<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            // Геокодированные точки маршрута: {lat,lng,label}
            $table->json('origin_point')->nullable()->after('origin');
            $table->json('destination_point')->nullable()->after('destination');

            // Транзитные точки 0..N — массив [{lat,lng,label}, ...].
            // Старая колонка via_point (string) остаётся ради legacy Blade-фронта.
            $table->json('via_points')->nullable()->after('via_point');

            // Полилиния OSRM/Yandex — массив [[lat,lng], ...]. LONGTEXT, потому что
            // на трассах в несколько тысяч точек JSON-колонке MySQL 8 не хватит
            // безопасного дефолтного row-format при некоторых ALTER (страховка).
            $table->longText('polyline_json')->nullable()->after('image');
            $table->string('routing_provider', 20)->nullable()->after('polyline_json');

            // Привязка к сохранённой фуре пользователя.
            $table->foreignId('vehicle_id')->nullable()->after('user_id')
                ->constrained('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn([
                'origin_point',
                'destination_point',
                'via_points',
                'polyline_json',
                'routing_provider',
                'vehicle_id',
            ]);
        });
    }
};
