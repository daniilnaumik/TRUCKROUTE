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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('model')->nullable();
            $table->string('fuel_type')->default('Дизель');
            $table->unsignedSmallInteger('tank_capacity_l');
            $table->decimal('consumption_l_per_100', 5, 2);
            $table->unsignedSmallInteger('cruise_speed_kmh')->default(85);
            $table->text('restrictions')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
