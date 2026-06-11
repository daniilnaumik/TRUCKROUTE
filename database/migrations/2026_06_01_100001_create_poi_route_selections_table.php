<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poi_route_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_object_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['accepted', 'rejected']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poi_route_selections');
    }
};
