<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Простой RBAC middleware без сторонних пакетов.
 * Применение: ->middleware('role:provider,admin') — нужна одна из ролей.
 * Админ имеет доступ ко всему (super-role).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if ($user->role === User::ROLE_ADMIN) {
            return $next($request);
        }

        foreach ($roles as $allowed) {
            if ($user->role === $allowed) {
                return $next($request);
            }
        }

        abort(403, 'Недостаточно прав. Требуется роль: '.implode(' / ', $roles));
    }
}
