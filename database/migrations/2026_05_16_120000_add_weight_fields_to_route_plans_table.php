<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->decimal('cargo_weight_t', 6, 2)->nullable()->after('cargo_type');
            $table->decimal('vehicle_curb_weight_t', 6, 2)->nullable()->after('cargo_weight_t');
            $table->decimal('gross_weight_t', 6, 2)->nullable()->after('vehicle_curb_weight_t');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn([
                'cargo_weight_t',
                'vehicle_curb_weight_t',
                'gross_weight_t',
            ]);
        });
    }
};
