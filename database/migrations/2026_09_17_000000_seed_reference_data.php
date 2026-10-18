<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('cities')->insertOrIgnore(array_map(
            fn (string $name): array => [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'Gaza',
                'Khan Younis',
                'Deir al-Balah',
                'Jabalia',
                'Nuseirat',
                'Bureij',
                'Maghazi',
            ]
        ));

        $categories = [
            ['name' => 'Cleaning', 'slug' => 'cleaning'],
            ['name' => 'Repair', 'slug' => 'repair'],
            ['name' => 'Beauty', 'slug' => 'beauty'],
            ['name' => 'Home', 'slug' => 'home'],
            ['name' => 'Delivery', 'slug' => 'delivery'],
            ['name' => 'Tutoring', 'slug' => 'tutoring'],
        ];

        DB::table('categories')->insertOrIgnore(array_map(
            fn (array $category): array => $category + [
                'image' => 'categories/default.jpg',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $categories
        ));
    }

    public function down(): void
    {
        DB::table('cities')->whereIn('name', [
            'Gaza', 'Khan Younis', 'Deir al-Balah', 'Jabalia',
            'Nuseirat', 'Bureij', 'Maghazi',
        ])->delete();

        DB::table('categories')->whereIn('slug', [
            'cleaning', 'repair', 'beauty', 'home', 'delivery', 'tutoring',
        ])->delete();
    }
};
