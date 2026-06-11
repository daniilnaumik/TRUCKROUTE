<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Pivot — водители автопарка. user_role в pivot, чтобы потом ввести "диспетчер" и т.п.
        Schema::create('fleet_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_fleet', 32)->default('driver');
            $table->timestamps();
            $table->unique(['fleet_id', 'user_id']);
        });

        // Маршрут-задание: автопарк выдаёт водителю готовый маршрут (origin/dest/via),
        // водитель его принимает/выполняет. RoutePlan создаётся водителем в момент принятия,
        // здесь — только spec задания.
        Schema::create('route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('route_plan_id')->nullable()->constrained('route_plans')->nullOnDelete();
            $table->string('origin');
            $table->json('origin_point')->nullable();
            $table->string('destination');
            $table->json('destination_point')->nullable();
            $table->json('via_points')->nullable();
            $table->timestamp('planned_start_at')->nullable();
            $table->text('comment')->nullable();
            $table->enum('status', ['issued', 'accepted', 'in_progress', 'completed', 'cancelled'])
                ->default('issued');
            $table->timestamps();

            $table->index(['fleet_id', 'status']);
            $table->index(['driver_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_assignments');
        Schema::dropIfExists('fleet_drivers');
        Schema::dropIfExists('fleets');
    }
};
