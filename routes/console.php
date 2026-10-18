<?php

use App\Services\BookingExpirationService;
use App\Models\AdminAuditLog;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Services\NotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-stale', function (BookingExpirationService $expiration): void {
    $expired = $expiration->expireStale();
    $this->info("Expired {$expired} stale bookings.");
})->purpose('Expire bookings that passed their response or payment deadline');

Schedule::command('bookings:expire-stale')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('providers:escalate-overdue', function (NotificationService $notifications): void {
    ProviderProfile::with('user')
        ->where('status', 'pending')
        ->whereNotNull('submitted_at')
        ->where('submitted_at', '<=', now()->subHours(24))
        ->whereNull('escalated_at')
        ->orderBy('id')
        ->chunkById(100, function ($providers) use ($notifications): void {
            foreach ($providers as $provider) {
                DB::transaction(function () use ($provider, $notifications): void {
                    $locked = ProviderProfile::whereKey($provider->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'pending' || ! $locked->submitted_at?->lte(now()->subHours(24)) || $locked->escalated_at) {
                        return;
                    }

                    $locked->update(['escalated_at' => now()]);
                    AdminAuditLog::create([
                        'actor_id' => null,
                        'user_id' => $locked->user_id,
                        'actor_role' => 'system',
                        'action' => 'provider_review_escalated',
                        'resource_type' => 'provider_profile',
                        'resource_id' => $locked->id,
                        'before' => ['escalated_at' => null],
                        'after' => ['escalated_at' => $locked->fresh()->escalated_at?->toIso8601String()],
                    ]);

                    foreach (User::whereHas('roles', fn ($roles) => $roles->whereIn('name', ['ops.moderator', 'system.admin']))->get() as $admin) {
                        $notifications->send(
                            $admin,
                            'provider_review_overdue',
                            'طلب اعتماد متجاوز للمهلة',
                            'يوجد طلب اعتماد مزود لم تتم مراجعته خلال 24 ساعة.',
                            'provider_profile',
                            $locked->id
                        );
                    }
                });
            }
        });
})->purpose('Escalate provider applications that have been pending for more than 24 hours');

Schedule::command('providers:escalate-overdue')->everyFifteenMinutes()->withoutOverlapping();
