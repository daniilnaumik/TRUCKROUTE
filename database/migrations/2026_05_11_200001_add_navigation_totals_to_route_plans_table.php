<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->decimal('effective_consumption_l_per_100', 5, 2)->nullable()->after('consumption_l_per_100');
            $table->decimal('reserve_l', 8, 2)->nullable()->after('reserve_percent');
            $table->timestamp('arrival_time')->nullable()->after('drive_time_minutes');
            $table->decimal('fuel_cost_rub', 10, 2)->nullable()->after('fuel_needed_l');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn([
                'effective_consumption_l_per_100',
                'reserve_l',
                'arrival_time',
                'fuel_cost_rub',
            ]);
        });
    }
};
