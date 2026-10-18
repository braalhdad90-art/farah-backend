<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->isSecure()) {
            return response()->json([
                'icon' => 'error',
                'title' => 'HTTPS is required.',
            ], 400);
        }

        return $next($request);
    }
}