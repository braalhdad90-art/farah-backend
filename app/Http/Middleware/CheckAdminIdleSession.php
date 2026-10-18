<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminIdleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = (int) $request->session()->get('admin_last_activity', 0);
        $idleSeconds = 1800;

        if ($lastActivity > 0 && (time() - $lastActivity) > $idleSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Your admin session expired after 30 minutes of inactivity.',
            ]);
        }

        $request->session()->put('admin_last_activity', time());

        return $next($request);
    }
}
