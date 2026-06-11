<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_assignments', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->unsignedTinyInteger('rating_stars')->nullable()->after('completed_at');
            $table->text('rating_comment')->nullable()->after('rating_stars');
            $table->foreignId('rated_by_user_id')->nullable()->after('rating_comment')->constrained('users')->nullOnDelete();
            $table->timestamp('rated_at')->nullable()->after('rated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('route_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rated_by_user_id');
            $table->dropColumn(['completed_at', 'rating_stars', 'rating_comment', 'rated_at']);
        });
    }
};
