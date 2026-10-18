<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

class BookingExpirationService
{
    public function expireStale(): int
    {
        return Booking::query()
            ->whereIn('workflow_status', BookingStatus::expirable())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => BookingStatus::Cancelled->value,
                'workflow_status' => BookingStatus::Expired->value,
                'cancellation_reason' => 'انتهت مهلة الحجز أو الدفع.',
            ]);
    }
}
