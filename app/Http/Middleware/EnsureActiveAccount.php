<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب غير نشط. يرجى التواصل مع الإدارة.',
            ], 403);
        }

        return $next($request);
    }
}
