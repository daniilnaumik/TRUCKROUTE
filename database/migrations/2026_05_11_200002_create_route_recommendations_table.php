<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_object_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('order_index');
            $table->unsignedSmallInteger('distance_from_start_km');
            $table->decimal('detour_km', 5, 1)->default(0);
            $table->timestamp('eta_at')->nullable();
            $table->decimal('fuel_before_l', 8, 2)->nullable();
            $table->decimal('suggested_fuel_l', 8, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_recommendations');
    }
};
