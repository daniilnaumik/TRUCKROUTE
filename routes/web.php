<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// ── Demo-only: quick account switcher ─────────────────────────────────────
// Independent from APP_DEBUG so a deployed diploma demo can keep debug off.
if (config('demo.accounts_enabled')) {
    Route::get('/dev/switch', function (\Illuminate\Http\Request $request) {
        $allowed = [
            'driver@truckroute.local',
            'driver2@truckroute.local',
            'provider@truckroute.local',
            'fleet@truckroute.local',
            'admin@truckroute.local',
        ];
        $email   = $request->query('email', '');
        if (!in_array($email, $allowed, true)) {
            return redirect('/');
        }
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) return redirect('/');

        if (auth()->check()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    })->name('dev.switch');
}

// ── SPA catch-all — all routes handled by Vue Router ──────────────────────
// API routes (/api/...) are registered separately and take priority.
// Named routes kept so Blade legacy code (if any) doesn't break.
Route::get('/{any}', function () {
    return view('spa');
})->where('any', '.*')->name('spa');
