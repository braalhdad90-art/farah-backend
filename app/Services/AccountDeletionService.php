<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// فرح: حذف حساب (من التطبيق أو من لوحة الأدمن): بيمسح البيانات الشخصية، بيحرر الإيميل
// للتسجيل من جديد، وبيلغي جلسات الدخول. ما بيحذف حساب عليه حجوزات قائمة.
class AccountDeletionService
{
    public function hasActiveBookings(User $user): bool
    {
        return Booking::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('provider_id', $user->id))
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'حساب محذوف',
                'email' => 'deleted-'.$user->id.'-'.Str::uuid().'@deleted.farah.local',
                'phone' => null,
                'avatar' => null,
                'bio' => null,
                'cover_image' => null,
                'pending_email' => null,
                'google_id' => null,
                'status' => 'inactive',
                'remember_token' => Str::random(60),
            ])->save();
            $user->delete();
        });
    }
}
