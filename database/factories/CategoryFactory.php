<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $slug = $this->faker->unique()->slug(2);

        return [
            'name' => $name,
            'slug' => $slug,
            'image' => 'categories/default.jpg',
            'status' => 'active',
        ];
    }
}
