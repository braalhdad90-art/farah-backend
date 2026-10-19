<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Wedding service categories, keyed by slug. Names match the category
     * list in the mobile app's design.
     */
    private const CATEGORIES = [
        'wedding-halls' => 'صالات أفراح',
        'photography' => 'تصوير فوتوغرافي',
        'hair-makeup' => 'كوافير',
        'wedding-dresses' => 'فساتين زفاف',
        'mens-suits' => 'بدلات رجالية',
        'flowers' => 'تنسيق ورد',
        'kosha' => 'كوشة',
        'car-decoration' => 'تزيين سيارات',
        'car-rental' => 'تأجير سيارات',
        'dj' => 'دي جي',
        'cakes' => 'كيك',
        'invitations' => 'كروت أفراح',
    ];

    /** Placeholder categories from the original template. */
    private const RETIRED = ['cleaning', 'repair', 'beauty', 'home', 'delivery', 'tutoring'];

    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => $name) {
            $category = Category::firstOrNew(['slug' => $slug]);
            $category->fill(['name' => $name, 'status' => 'active']);
            $category->image ??= 'categories/default.jpg';
            $category->save();
        }

        // Hidden rather than deleted, so services already linked to them stay valid.
        Category::whereIn('slug', self::RETIRED)->update(['status' => 'inactive']);
    }
}
