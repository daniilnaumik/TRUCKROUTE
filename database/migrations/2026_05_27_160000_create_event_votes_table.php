<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // +1 = подтверждаю, -1 = опровергаю. Доп. значения зарезервированы (например 0 = "был на месте, не уверен").
            $table->tinyInteger('vote');
            $table->timestamps();

            // Один пользователь — один голос на событие. Повторный POST переписывает существующий через updateOrCreate.
            $table->unique(['road_event_id', 'user_id'], 'event_votes_unique');
            $table->index(['road_event_id', 'vote'], 'event_votes_event_vote_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_votes');
    }
};
