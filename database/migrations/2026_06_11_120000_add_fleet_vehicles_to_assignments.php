<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('fleet_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->index(['fleet_id', 'is_active']);
        });

        Schema::table('route_assignments', function (Blueprint $table) {
            $table->string('vehicle_source', 16)->default('driver')->after('route_plan_id');
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('vehicle_source')
                ->constrained('vehicles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('route_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn('vehicle_source');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['fleet_id', 'is_active']);
            $table->dropConstrainedForeignId('fleet_id');
        });
    }
};
