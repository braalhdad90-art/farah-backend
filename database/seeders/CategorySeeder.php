<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Cleaning', 'slug' => 'cleaning'],
            ['name' => 'Repair', 'slug' => 'repair'],
            ['name' => 'Beauty', 'slug' => 'beauty'],
            ['name' => 'Home', 'slug' => 'home'],
            ['name' => 'Delivery', 'slug' => 'delivery'],
            ['name' => 'Tutoring', 'slug' => 'tutoring'],
        ] as $category) {
            Category::firstOrCreate($category, [
                'image' => 'categories/default.jpg',
                'status' => 'active',
            ]);
        }
    }
}
