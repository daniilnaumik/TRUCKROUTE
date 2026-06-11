<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('share_route_history_with_fleet')->default(false);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('telegram_notifications')->default(false);
            $table->string('telegram_chat_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'share_route_history_with_fleet',
                'email_notifications',
                'push_notifications',
                'telegram_notifications',
                'telegram_chat_id',
            ]);
        });
    }
};
