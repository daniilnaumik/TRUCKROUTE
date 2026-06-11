<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user) {
            $now = now();
            DB::table('user_activity_days')->upsert(
                [[
                    'user_id' => $user->id,
                    'activity_date' => $now->toDateString(),
                    'platform' => $this->platform($request),
                    'last_seen_at' => $now,
                    'first_seen_at' => $now,
                ]],
                ['user_id', 'activity_date'],
                ['platform', 'last_seen_at'],
            );
        }

        return $response;
    }

    private function platform(Request $request): string
    {
        $explicit = strtolower((string) $request->header('X-Client-Platform'));
        if (in_array($explicit, ['web', 'ios', 'android'], true)) {
            return $explicit;
        }

        $agent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad') => 'ios',
            str_contains($agent, 'android'), str_contains($agent, 'okhttp') => 'android',
            default => 'web',
        };
    }
}
