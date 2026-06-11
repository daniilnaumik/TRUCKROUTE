<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100);                    // e.g. "Мебель стандарт"
            $table->string('flag', 50)->default('Обычный');  // Обычный / Опасный / Негабарит / Рефриж
            $table->decimal('weight_t', 5, 2)->default(0);
            $table->string('requirements', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
