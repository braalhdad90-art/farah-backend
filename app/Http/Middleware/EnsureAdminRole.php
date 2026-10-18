<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        App::setLocale('ar');

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'ليس لديك صلاحية الوصول إلى لوحة الإدارة.');
        }

        if ($user->user_type !== 'admin' || $user->status !== 'active') {
            abort(403, 'حساب الإدارة هذا غير نشط.');
        }

        $authorizedRoles = $roles ?: ['ops.moderator', 'finance.reviewer', 'system.admin'];

        if (! $user->hasAnyRole($authorizedRoles)) {
            abort(403, 'دورك الإداري لا يتيح الوصول إلى هذا القسم.');
        }

        return $next($request);
    }
}
