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
        Schema::create('road_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->string('highway')->nullable();
            $table->string('location');
            $table->text('description');
            $table->string('status')->default('active');
            $table->string('importance')->default('medium');
            $table->unsignedSmallInteger('delay_minutes')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('confidence_score')->default(1);
            $table->string('image')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('road_events');
    }
};
