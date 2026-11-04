<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// فرح: التطبيق بياخد Web Client ID تبع Google من /api/settings حتى يطابق GOOGLE_CLIENT_ID بالسيرفر.
class GoogleClientIdSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_expose_the_google_client_id(): void
    {
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        $this->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.google_client_id', 'web-client.apps.googleusercontent.com');

        config(['services.google.client_id' => '']);
        $this->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.google_client_id', null);
    }

    public function test_google_login_without_a_valid_token_is_refused(): void
    {
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        $this->postJson('/api/auth/google', ['id_token' => 'not-a-real-token'])->assertUnauthorized();
    }
}
