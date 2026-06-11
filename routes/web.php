<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// ── Dev-only: quick account switcher ──────────────────────────────────────
// Only registered when APP_DEBUG=true. Allows instant role switch in testing.
if (config('app.debug')) {
    Route::get('/dev/switch', function (\Illuminate\Http\Request $request) {
        $allowed = ['driver@truckroute.local', 'admin@truckroute.local'];
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
