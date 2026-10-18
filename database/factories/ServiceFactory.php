<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $provider = User::query()->where('user_type', 'provider')->inRandomOrder()->first() ?? User::factory()->create([
            'user_type' => 'provider',
        ]);

        return [
            'provider_id' => $provider->id,
            'category_id' => Category::query()->inRandomOrder()->first()?->id ?? Category::factory()->create()->id,
            'city_id' => $provider->city_id,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'features' => $this->faker->randomElements([
                'بوفيه مفتوح',
                'دي جي متكامل',
                'شاشة عرض',
                'إضاءة ليزر',
            ], $this->faker->numberBetween(1, 4)),
            'capacity' => $this->faker->numberBetween(100, 800),
            'area' => $this->faker->randomFloat(2, 200, 2000),
            'area_unit' => 'm²',
            'booking_slots' => [
                ['label' => 'الفترة الصباحية', 'start_time' => '09:00', 'end_time' => '15:00'],
                ['label' => 'الفترة المسائية', 'start_time' => '17:00', 'end_time' => '22:00'],
            ],
            'price' => $this->faker->numberBetween(50, 500),
            'currency' => 'SAR',
            'image' => 'services/default.jpg',
            'rating_avg' => $this->faker->randomFloat(2, 3, 5),
            'reviews_count' => $this->faker->numberBetween(0, 50),
            'is_featured' => $this->faker->boolean(30),
            'is_available' => true,
            'status' => 'active',
        ];
    }
}
