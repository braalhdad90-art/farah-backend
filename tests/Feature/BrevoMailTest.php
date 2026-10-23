<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// فرح: الإيميلات تطلع عبر Brevo API (HTTPS) لأن Railway يمنع SMTP على خطة Hobby.
class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_code_email_goes_through_the_brevo_api(): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-key',
            'mail.from.address' => 'farah@test.com',
            'mail.from.name' => 'Farah',
        ]);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);
        $user = User::factory()->create(['email' => 'mail@test.com', 'status' => 'active']);

        $this->postJson('/api/forgot-password', ['email' => 'mail@test.com'])->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->header('api-key')[0] === 'test-key'
            && $request['to'][0]['email'] === 'mail@test.com'
            && $request['sender']['email'] === 'farah@test.com'
            && preg_match('/\d{4}/', $request['htmlContent']) === 1);
    }

    public function test_a_failed_send_reports_it_and_cancels_the_code(): void
    {
        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'bad-key']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'unauthorized'], 401)]);
        User::factory()->create(['email' => 'fail@test.com', 'status' => 'active']);

        $this->postJson('/api/forgot-password', ['email' => 'fail@test.com'])->assertStatus(503);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'fail@test.com']);
    }
}
