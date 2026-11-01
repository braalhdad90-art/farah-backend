<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// فرح: صفحة المزود العامة: بيانات حقيقية وخدماته المنشورة فقط.
class ProviderPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $status = 'approved'): User
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active', 'phone' => '0599111222']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Lavender',
            'description' => 'Flowers and gifts',
            'category_id' => Category::factory()->create(['name' => 'Flowers'])->id,
            'status' => $status,
            'registration_status' => $status,
        ]);

        return $provider;
    }

    public function test_shows_the_store_and_only_published_services(): void
    {
        $provider = $this->provider();
        Service::factory()->create(['provider_id' => $provider->id, 'title' => 'Bouquet', 'rating_avg' => 4, 'reviews_count' => 1]);
        Service::factory()->create(['provider_id' => $provider->id, 'title' => 'Car decor', 'rating_avg' => 5, 'reviews_count' => 3]);
        Service::factory()->create(['provider_id' => $provider->id, 'title' => 'Draft', 'status' => 'inactive']);
        Service::factory()->create(['provider_id' => $provider->id, 'title' => 'Hidden', 'is_available' => false]);

        $response = $this->getJson('/api/providers/'.$provider->id)->assertOk()
            ->assertJsonPath('data.id', $provider->id)
            ->assertJsonPath('data.name', 'Lavender')
            ->assertJsonPath('data.specialty', 'Flowers')
            ->assertJsonPath('data.description', 'Flowers and gifts')
            ->assertJsonPath('data.services_count', 2)
            ->assertJsonPath('data.reviews_count', 4)
            ->assertJsonPath('data.rating', 4.8)
            ->assertJsonPath('data.phone', null);

        $titles = collect($response->json('data.services'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Bouquet', 'Car decor'], $titles->all());
    }

    public function test_phone_is_shown_to_signed_in_users(): void
    {
        $provider = $this->provider();
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')->getJson('/api/providers/'.$provider->id)
            ->assertOk()
            ->assertJsonPath('data.phone', '0599111222');
    }

    public function test_unapproved_or_unknown_providers_are_not_found(): void
    {
        $pending = $this->provider('pending');
        $customer = User::factory()->create(['user_type' => 'customer']);

        $this->getJson('/api/providers/'.$pending->id)->assertNotFound();
        $this->getJson('/api/providers/'.$customer->id)->assertNotFound();
        $this->getJson('/api/providers/999999')->assertNotFound();
    }
}
