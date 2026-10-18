<?php

namespace App\Enums;

enum BookingStatus: string
{
    // حالات سير العمل
    case ProviderPending    = 'provider_pending';
    case PaymentAwaiting    = 'payment_awaiting';
    case ReviewUnderProof   = 'review_under_proof';
    case Confirmed          = 'confirmed';
    case Completed          = 'completed';
    case Cancelled          = 'cancelled';
    case Rejected           = 'rejected';
    case Expired            = 'expired';

    /**
     * الحالات التي تعتبر "نشطة" (الحجز قيد المعالجة)
     */
    public static function active(): array
    {
        return [
            self::ProviderPending->value,
            self::PaymentAwaiting->value,
            self::ReviewUnderProof->value,
            self::Confirmed->value,
        ];
    }

    /**
     * الحالات التي تستوجب انتهاء الصلاحية التلقائي
     */
    public static function expirable(): array
    {
        return [
            self::ProviderPending->value,
            self::PaymentAwaiting->value,
        ];
    }

    /**
     * هل يمكن إلغاء الحجز في هذه الحالة؟
     */
    public static function cancellable(): array
    {
        return [
            self::ProviderPending->value,
            self::PaymentAwaiting->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::ProviderPending  => 'بانتظار قرار المزود',
            self::PaymentAwaiting  => 'بانتظار الدفع',
            self::ReviewUnderProof => 'قيد مراجعة إثبات الدفع',
            self::Confirmed        => 'مؤكد',
            self::Completed        => 'مكتمل',
            self::Cancelled        => 'ملغى',
            self::Rejected         => 'مرفوض',
            self::Expired          => 'منتهي الصلاحية',
        };
    }
}
