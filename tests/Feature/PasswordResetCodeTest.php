<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// فرح: نسيت كلمة المرور برمز من 4 أرقام بالإيميل، وحذف الحساب يحرر الإيميل.
class PasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    private function requestCode(User $user): string
    {
        $code = null;
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, PasswordResetCode::class, function ($notification) use (&$code, $user) {
            preg_match('/\*\*(\d{4})\*\*/', implode(' ', $notification->toMail($user)->introLines), $m);
            $code = $m[1] ?? null;

            return $code !== null;
        });

        return $code;
    }

    public function test_code_resets_the_password_once(): void
    {
        $user = User::factory()->create(['email' => 'reset@test.com', 'status' => 'active']);
        $code = $this->requestCode($user);

        $this->postJson('/api/verify-reset-code', ['email' => 'reset@test.com', 'token' => $code])->assertOk();
        $this->postJson('/api/reset-password', [
            'email' => 'reset@test.com',
            'token' => $code,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
        // Used up: the same code can't reset again.
        $this->postJson('/api/reset-password', [
            'email' => 'reset@test.com',
            'token' => $code,
            'password' => 'another-pass1',
            'password_confirmation' => 'another-pass1',
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_wrong_and_expired_codes_are_refused(): void
    {
        $user = User::factory()->create(['email' => 'wrong@test.com', 'status' => 'active']);
        $code = $this->requestCode($user);
        $wrong = $code === '0000' ? '1111' : '0000';

        $this->postJson('/api/verify-reset-code', ['email' => 'wrong@test.com', 'token' => $wrong])->assertStatus(422);

        $this->travel(16)->minutes();
        $this->postJson('/api/verify-reset-code', ['email' => 'wrong@test.com', 'token' => $code])->assertStatus(422);
    }

    public function test_five_wrong_tries_cancel_the_code(): void
    {
        $user = User::factory()->create(['email' => 'guess@test.com', 'status' => 'active']);
        $code = $this->requestCode($user);
        $wrong = $code === '0000' ? '1111' : '0000';

        // The route limit (5/min) would stop the tries first; this checks the code's own limit.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/verify-reset-code', ['email' => 'guess@test.com', 'token' => $wrong])->assertStatus(422);
        }
        $this->postJson('/api/verify-reset-code', ['email' => 'guess@test.com', 'token' => $code])->assertStatus(422);
    }

    public function test_unknown_email_is_refused(): void
    {
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => 'nobody@test.com'])->assertStatus(422);
        Notification::assertNothingSent();
    }

    public function test_deleting_the_account_frees_the_email(): void
    {
        $user = User::factory()->create(['email' => 'again@test.com', 'password' => bcrypt('correct-password')]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/account', ['current_password' => 'correct-password'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/register', [
            'name' => 'Again',
            'email' => 'again@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }
}
