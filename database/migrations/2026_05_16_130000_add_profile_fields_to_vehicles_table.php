<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('allowed_fuel')->nullable()->after('fuel_type');
            $table->decimal('curb_weight_t', 6, 2)->nullable()->after('cruise_speed_kmh');
            $table->boolean('is_active')->default(false)->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'allowed_fuel',
                'curb_weight_t',
                'is_active',
            ]);
        });
    }
};
