<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            // Геоданные. Координаты nullable, потому что у демо-сидов их пока нет;
            // bbox-индексы дают эффективный pre-filter перед ST_Distance_Sphere.
            $table->decimal('lat', 10, 7)->nullable()->after('location');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->index(['lat', 'lng'], 'service_objects_lat_lng_index');

            // Кто создал/владеет карточкой POI (поставщик). Null = системный/демо.
            $table->foreignId('provider_id')->nullable()->after('lng')
                ->constrained('users')->nullOnDelete();

            // Подтверждено модератором.
            $table->boolean('verified')->default(false)->after('status');
            $table->index(['type', 'verified'], 'service_objects_type_verified_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->dropIndex('service_objects_type_verified_index');
            $table->dropIndex('service_objects_lat_lng_index');
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['lat', 'lng', 'provider_id', 'verified']);
        });
    }
};
