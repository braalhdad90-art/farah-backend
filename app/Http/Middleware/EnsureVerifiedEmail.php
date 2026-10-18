<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى تفعيل بريدك الإلكتروني أولًا للمتابعة.',
            ], 403);
        }

        return $next($request);
    }
}
