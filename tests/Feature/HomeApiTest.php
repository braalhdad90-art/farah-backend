<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_api_returns_expected_structure_for_guest(): void
    {
        $response = $this->getJson('/api/home');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user',
            'categories',
            'banners',
            'suggested_providers',
            'featured_packages',
            'most_popular',
            'featured_providers',
            'services',
        ]);
        $this->assertNull($response->json('user'));
    }

    public function test_home_api_returns_user_info_when_authenticated(): void
    {
        $city = City::firstOrCreate(['name' => 'غزة']);
        $user = User::factory()->create([
            'name' => 'مالك',
            'city_id' => $city->id,
        ]);

        Location::create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'label' => 'المنزل',
            'address' => 'الوحدة',
            'latitude' => 31.5000,
            'longitude' => 34.4667,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/home');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('user'));
        $this->assertEquals('مالك', $response->json('user.name'));
        $this->assertStringContainsString('الوحدة', $response->json('user.location'));
    }
}
