<?php

namespace Tests\Feature;

use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// فرح: تفاصيل الخدمة ترجع المزايا القابلة للإزالة اللي حددها المزود.
class ServiceRemovableFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_details_return_removable_features(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Store',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);
        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'features' => ['Chairs', 'Flowers', 'Lights'],
            'removable_features' => ['Flowers', 'Not a feature'],
        ]);

        $this->getJson('/api/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('data.features', ['Chairs', 'Flowers', 'Lights'])
            ->assertJsonPath('data.removable_features', ['Flowers']);
    }
}
