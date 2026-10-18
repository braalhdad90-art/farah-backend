<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\CheckAdminIdleSession;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureProvider;
use App\Http\Middleware\EnsureValidApiSignature;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\RequireHttps;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->api(append: [RequireHttps::class]);
        $middleware->alias([
            'account.active' => EnsureActiveAccount::class,
            'customer' => EnsureCustomer::class,
            'provider' => EnsureProvider::class,
            'admin' => EnsureAdminRole::class,
            'admin.idle' => CheckAdminIdleSession::class,
            'verified' => EnsureVerifiedEmail::class,
            'signed-api' => EnsureValidApiSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
