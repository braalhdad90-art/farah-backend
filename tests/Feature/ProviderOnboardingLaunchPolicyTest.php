<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOnboardingLaunchPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_provider_onboarding_is_approved_immediately(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);
        $city = City::create(['name' => 'غزة']);
        $category = Category::factory()->create();

        $response = $this->actingAs($provider, 'sanctum')->post('/api/provider/onboarding', [
            'business_name' => 'قاعة فرح',
            'category_id' => $category->id,
            'description' => 'قاعة مناسبات وخدمات احتفالات.',
            'city_id' => $city->id,
            'address' => 'غزة - الرمال',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.registration_status', 'pending')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'status' => 'pending',
            'registration_status' => 'pending',
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/verification')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.next_step', 'wait');
    }
}
