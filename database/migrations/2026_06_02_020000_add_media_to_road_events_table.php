<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('road_events', function (Blueprint $table) {
            if (! Schema::hasColumn('road_events', 'gallery')) {
                $table->json('gallery')->nullable()->after('image');
            }

            if (! Schema::hasColumn('road_events', 'video_url')) {
                $table->string('video_url')->nullable()->after('gallery');
            }
        });
    }

    public function down(): void
    {
        Schema::table('road_events', function (Blueprint $table) {
            if (Schema::hasColumn('road_events', 'video_url')) {
                $table->dropColumn('video_url');
            }

            if (Schema::hasColumn('road_events', 'gallery')) {
                $table->dropColumn('gallery');
            }
        });
    }
};
