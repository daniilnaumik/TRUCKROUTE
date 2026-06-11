<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('route_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('origin');
            $table->string('destination');
            $table->string('via_point')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->string('vehicle_type');
            $table->string('cargo_type')->default('Обычный груз');
            $table->unsignedSmallInteger('start_fuel_l')->default(0);
            $table->unsignedSmallInteger('tank_capacity_l');
            $table->decimal('consumption_l_per_100', 5, 2);
            $table->unsignedTinyInteger('reserve_percent')->default(15);
            $table->unsignedSmallInteger('cruise_speed_kmh')->default(85);
            $table->string('planning_mode')->default('Безопасный');
            $table->unsignedSmallInteger('distance_km');
            $table->unsignedSmallInteger('drive_time_minutes');
            $table->decimal('fuel_needed_l', 8, 2);
            $table->unsignedSmallInteger('range_km');
            $table->unsignedTinyInteger('stops_count');
            $table->text('recommendations');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_plans');
    }
};
