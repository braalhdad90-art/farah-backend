<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المسار خاص بالعملاء.',
            ], 403);
        }

        return $next($request);
    }
}
