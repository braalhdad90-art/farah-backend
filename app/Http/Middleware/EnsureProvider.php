<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المسار خاص بمزودي الخدمة.',
            ], 403);
        }

        return $next($request);
    }
}
