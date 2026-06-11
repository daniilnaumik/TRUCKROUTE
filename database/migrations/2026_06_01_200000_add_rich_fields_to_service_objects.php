<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('image');    // array of image paths/urls
            $table->string('video_url')->nullable()->after('gallery');
            $table->json('tags')->nullable()->after('video_url');
            $table->longText('content')->nullable()->after('tags'); // rich HTML from Tiptap
        });
    }

    public function down(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->dropColumn(['gallery', 'video_url', 'tags', 'content']);
        });
    }
};
