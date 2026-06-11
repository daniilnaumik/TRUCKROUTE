<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->string('platform', 20)->default('web');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'activity_date']);
            $table->index(['activity_date', 'platform']);
        });

        Schema::table('trip_sessions', function (Blueprint $table) {
            $table->decimal('actual_fuel_used_l', 8, 2)->nullable()->after('ended_at');
            $table->decimal('actual_distance_km', 9, 2)->nullable()->after('actual_fuel_used_l');
        });

        $this->backfillActivity();
    }

    public function down(): void
    {
        Schema::table('trip_sessions', function (Blueprint $table) {
            $table->dropColumn(['actual_fuel_used_l', 'actual_distance_km']);
        });

        Schema::dropIfExists('user_activity_days');
    }

    private function backfillActivity(): void
    {
        $sources = [
            ['table' => 'route_plans', 'timestamp' => 'created_at'],
            ['table' => 'trip_sessions', 'timestamp' => 'started_at'],
        ];

        foreach ($sources as $source) {
            if (!Schema::hasTable($source['table'])) {
                continue;
            }

            DB::table($source['table'])
                ->whereNotNull('user_id')
                ->whereNotNull($source['timestamp'])
                ->select(['user_id', $source['timestamp']])
                ->orderBy('user_id')
                ->each(function (object $row) use ($source): void {
                    $seenAt = Carbon::parse($row->{$source['timestamp']});

                    DB::table('user_activity_days')->updateOrInsert(
                        [
                            'user_id' => $row->user_id,
                            'activity_date' => $seenAt->toDateString(),
                        ],
                        [
                            'platform' => 'web',
                            'first_seen_at' => $seenAt,
                            'last_seen_at' => $seenAt,
                        ],
                    );
                });
        }
    }
};
