<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            if (!Schema::hasColumn('road_events', 'location_point')) {
                Schema::table('road_events', function (Blueprint $table) {
                    $table->text('location_point')->nullable();
                });
            }

            return;
        }

        if (!Schema::hasColumn('road_events', 'location_point')) {
            DB::statement('ALTER TABLE road_events ADD COLUMN location_point POINT NULL AFTER lng');
        }

        DB::statement(
            "UPDATE road_events SET location_point = ST_GeomFromText(CONCAT('POINT(', lng, ' ', lat, ')')) WHERE lat IS NOT NULL AND lng IS NOT NULL AND location_point IS NULL",
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('road_events', 'location_point')) {
            Schema::table('road_events', function (Blueprint $table) {
                $table->dropColumn('location_point');
            });
        }
    }
};
