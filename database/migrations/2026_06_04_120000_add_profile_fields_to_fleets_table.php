<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            if (! Schema::hasColumn('fleets', 'avatar')) {
                $table->string('avatar')->nullable()->after('description');
            }

            if (! Schema::hasColumn('fleets', 'phone')) {
                $table->string('phone', 40)->nullable()->after('inn');
            }

            if (! Schema::hasColumn('fleets', 'base_city')) {
                $table->string('base_city', 120)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('fleets', 'address')) {
                $table->string('address')->nullable()->after('base_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            foreach (['avatar', 'phone', 'base_city', 'address'] as $column) {
                if (Schema::hasColumn('fleets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
