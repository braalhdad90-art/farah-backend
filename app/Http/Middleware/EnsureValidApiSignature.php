<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'رابط التحقق غير صالح أو منتهي الصلاحية.',
            ], 403);
        }

        return $next($request);
    }
}
