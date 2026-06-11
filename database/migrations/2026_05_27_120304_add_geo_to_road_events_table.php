<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('road_events', function (Blueprint $table) {
            // lat/lng уже есть DECIMAL(10,7). Добавляем композитный индекс под bbox.
            $table->index(['lat', 'lng'], 'road_events_lat_lng_index');

            // Автозакрытие событий (ДТП ~6 ч, ремонт ~14 дней) — настраивается.
            $table->timestamp('expires_at')->nullable()->after('reported_at');
            $table->index(['status', 'expires_at'], 'road_events_status_expires_index');

            // Автор события (краудсорсинг). Null = заведено админом/системой.
            $table->foreignId('created_by_user_id')->nullable()->after('expires_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('road_events', function (Blueprint $table) {
            $table->dropIndex('road_events_status_expires_index');
            $table->dropIndex('road_events_lat_lng_index');
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['expires_at', 'created_by_user_id']);
        });
    }
};
