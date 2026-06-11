<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Регистр устройств пользователя для пуш-уведомлений (FCM/APNS/Web Push).
 * Один пользователь — много устройств; токен глобально уникален.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['android', 'ios', 'web'])->default('android');
            $table->string('fcm_token', 512)->unique();
            $table->string('app_version', 32)->nullable();
            $table->string('locale', 16)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
