<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (($this->app->environment('production') || config('app.env') === 'production') && ! $this->app->runningInConsole()) {
            URL::forceScheme('https');
        }

        // Named limiters keep separate counters; plain throttle:N,1 shares one
        // counter per IP across every route, so browsing used up the login limit.
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('signed', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('authenticated', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));

        // فرح: ناقل الإيميل "brevo" (HTTPS API) المعرّف في config/mail.php.
        Mail::extend('brevo', fn (array $config) => new BrevoTransport((string) ($config['key'] ?? '')));
    }
}