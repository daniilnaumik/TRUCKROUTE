<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_sessions', function (Blueprint $table) {
            $table->json('accepted_stop_ids')->nullable()->after('notified_recommendation_ids');
            $table->json('rejected_stop_ids')->nullable()->after('accepted_stop_ids');
        });
    }

    public function down(): void
    {
        Schema::table('trip_sessions', function (Blueprint $table) {
            $table->dropColumn(['accepted_stop_ids', 'rejected_stop_ids']);
        });
    }
};
