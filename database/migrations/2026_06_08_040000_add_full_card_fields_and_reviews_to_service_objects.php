<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_objects', function (Blueprint $table) {
            $table->json('working_hours')->nullable()->after('content');
            $table->json('contacts')->nullable()->after('working_hours');
            $table->json('price_details')->nullable()->after('contacts');
            $table->json('promotions')->nullable()->after('price_details');
            $table->json('truck_access')->nullable()->after('promotions');
        });

        Schema::create('poi_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_object_id')->constrained('service_objects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->text('owner_reply')->nullable();
            $table->timestamp('owner_replied_at')->nullable();
            $table->foreignId('owner_reply_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_object_id', 'user_id']);
            $table->index(['service_object_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poi_reviews');

        Schema::table('service_objects', function (Blueprint $table) {
            $table->dropColumn([
                'working_hours',
                'contacts',
                'price_details',
                'promotions',
                'truck_access',
            ]);
        });
    }
};
