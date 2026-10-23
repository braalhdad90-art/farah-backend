<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// فرح: "نسيت كلمة المرور" برمز من 4 أرقام بالإيميل (حسب تصميم شاشة رمز التحقق).
// الرمز محفوظ مشفّر في password_reset_tokens، ينتهي بعد 15 دقيقة، و5 محاولات خاطئة تلغيه.
class PasswordResetCodeService
{
    public const MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function send(User $user): void
    {
        $email = $user->getEmailForPasswordReset();
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
        Cache::forget($this->attemptsKey($email));

        try {
            $user->notify(new PasswordResetCode($code, self::MINUTES));
        } catch (\Throwable $exception) {
            // A code nobody received must not stay valid.
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw $exception;
        }
    }

    /** Checks the code without using it up. */
    public function check(string $email, string $code): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $record || now()->subMinutes(self::MINUTES)->gt($record->created_at)) {
            return false;
        }

        if (Hash::check($code, $record->token)) {
            return true;
        }

        $attempts = Cache::increment($this->attemptsKey($email));
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->forget($email);
        }

        return false;
    }

    public function forget(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        Cache::forget($this->attemptsKey($email));
    }

    private function attemptsKey(string $email): string
    {
        return 'password-reset-attempts:'.sha1(strtolower($email));
    }
}
