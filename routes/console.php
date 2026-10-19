<?php

use App\Services\BookingExpirationService;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
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
    $slaHours = AppSetting::hours('provider_review_sla_hours');
    ProviderProfile::with('user')
        ->where('status', 'pending')
        ->whereNotNull('submitted_at')
        ->where('submitted_at', '<=', now()->subHours($slaHours))
        ->whereNull('escalated_at')
        ->orderBy('id')
        ->chunkById(100, function ($providers) use ($notifications, $slaHours): void {
            foreach ($providers as $provider) {
                DB::transaction(function () use ($provider, $notifications, $slaHours): void {
                    $locked = ProviderProfile::whereKey($provider->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'pending' || ! $locked->submitted_at?->lte(now()->subHours($slaHours)) || $locked->escalated_at) {
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
                            "يوجد طلب اعتماد مزود لم تتم مراجعته خلال {$slaHours} ساعة.",
                            'provider_profile',
                            $locked->id
                        );
                    }
                });
            }
        });
})->purpose('Escalate provider applications pending longer than the review SLA');

Schedule::command('providers:escalate-overdue')->everyFifteenMinutes()->withoutOverlapping();

// فرح: وثائق هوية المزود تُحذف بعد 30 يومًا من قرار الاعتماد أو الرفض (ADM-15، BR-17).
Artisan::command('providers:purge-documents', function (): void {
    $purged = 0;
    ProviderProfile::whereIn('status', ['approved', 'rejected'])
        ->where('decided_at', '<=', now()->subDays(30))
        ->where(fn ($query) => $query->whereNotNull('identity_document_path')->orWhereNotNull('commercial_register_path'))
        ->orderBy('id')
        ->chunkById(100, function ($providers) use (&$purged): void {
            foreach ($providers as $provider) {
                $paths = array_filter([$provider->identity_document_path, $provider->commercial_register_path]);
                Storage::disk('local')->delete($paths);
                $provider->forceFill(['identity_document_path' => null, 'commercial_register_path' => null])->save();
                AdminAuditLog::create([
                    'actor_id' => null,
                    'user_id' => $provider->user_id,
                    'actor_role' => 'system',
                    'action' => 'provider_documents_purged',
                    'resource_type' => 'provider_profile',
                    'resource_id' => $provider->id,
                    'before' => ['documents' => count($paths)],
                    'after' => ['documents' => 0],
                ]);
                $purged++;
            }
        });
    $this->info("Purged documents for {$purged} providers.");
})->purpose('Delete provider identity documents 30 days after the review decision');

Schedule::command('providers:purge-documents')->daily()->withoutOverlapping();
