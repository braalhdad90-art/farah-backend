<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\AppNotification;
use App\Models\ProviderProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Home", description: "بيانات الصفحة الرئيسية للتطبيق")]
class HomeController extends Controller
{
    use ImageUrlHelper;
    #[OA\Get(
        path: "/api/home",
        summary: "جلب بيانات الصفحة الرئيسية الكاملة حسب التصميم",
        description: "يسترجع بيانات الصفحة الرئيسية بما يشمل: معلومات الهيدر والمستخدم، قائمة الخدمات والتصنيفات، سلايدر العروض المميزة، المزودين المقترحين، الباقات المميزة، والخدمات الأكثر طلباً.",
        tags: ["Home"],
        responses: [
            new OA\Response(
                response: 200,
                description: "تم جلب بيانات الصفحة الرئيسية بنجاح",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "user",
                            type: "object",
                            nullable: true,
                            description: "معلومات المستخدم المسجل وموقعه (null في حال كان الزائر غير مسجل)",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "مالك"),
                                new OA\Property(property: "email", type: "string", example: "malik@example.com"),
                                new OA\Property(property: "avatar", type: "string", nullable: true, example: "avatars/malik.jpg"),
                                new OA\Property(property: "location", type: "string", example: "غزة، الوحدة"),
                                new OA\Property(property: "city", type: "string", example: "غزة"),
                                new OA\Property(property: "unread_notifications_count", type: "integer", example: 3)
                            ]
                        ),
                        new OA\Property(
                            property: "categories",
                            type: "array",
                            description: "قائمة التصنيفات والأقسام (الخدمات)",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "صالات"),
                                    new OA\Property(property: "slug", type: "string", example: "wedding-halls"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "categories/hall.jpg"),
                                    new OA\Property(property: "status", type: "string", example: "active")
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "banners",
                            type: "array",
                            description: "سلايدر العروض والخدمات المميزة في أعلى الشاشة",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "service_id", type: "integer", example: 1),
                                    new OA\Property(property: "title", type: "string", example: "صالة اورنا"),
                                    new OA\Property(property: "badge", type: "string", example: "عرض لفترة محدودة"),
                                    new OA\Property(property: "price", type: "number", format: "float", example: 1400.00),
                                    new OA\Property(property: "currency", type: "string", example: "ILS"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "services/hall_orna.jpg"),
                                    new OA\Property(
                                        property: "features",
                                        type: "array",
                                        items: new OA\Items(type: "string", example: "دي جي متكامل")
                                    )
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "suggested_providers",
                            type: "array",
                            description: "مزودين مقترحين للمستخدم",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "user_id", type: "integer", example: 4),
                                    new OA\Property(property: "name", type: "string", example: "إيمان فؤاد"),
                                    new OA\Property(property: "business_name", type: "string", example: "إيمان فؤاد"),
                                    new OA\Property(property: "avatar", type: "string", nullable: true, example: "providers/eman.jpg"),
                                    new OA\Property(property: "specialty", type: "string", example: "أضواء"),
                                    new OA\Property(property: "category_name", type: "string", example: "أضواء"),
                                    new OA\Property(property: "rating", type: "number", format: "float", example: 4.9),
                                    new OA\Property(property: "city", type: "string", example: "غزة"),
                                    new OA\Property(property: "is_featured", type: "boolean", example: true)
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "featured_packages",
                            type: "array",
                            description: "باقات مميزة وعروض ذات نسب خصم",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "title", type: "string", example: "باقة الورد والتنسيق الملكي"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "services/package1.jpg"),
                                    new OA\Property(property: "discount_badge", type: "string", example: "خصم 15%"),
                                    new OA\Property(property: "discount_percentage", type: "integer", example: 15),
                                    new OA\Property(property: "price", type: "number", format: "float", example: 350.00),
                                    new OA\Property(property: "original_price", type: "number", format: "float", example: 411.76),
                                    new OA\Property(property: "currency", type: "string", example: "ILS"),
                                    new OA\Property(property: "rating_avg", type: "number", format: "float", example: 4.9),
                                    new OA\Property(property: "reviews_count", type: "integer", example: 22),
                                    new OA\Property(
                                        property: "provider",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 2),
                                            new OA\Property(property: "name", type: "string", example: "لافندر ستور"),
                                            new OA\Property(property: "avatar", type: "string", nullable: true, example: "providers/lavender.jpg")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "category",
                                        type: "object",
                                        nullable: true,
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 2),
                                            new OA\Property(property: "name", type: "string", example: "تنسيق ورد")
                                        ]
                                    )
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "most_popular",
                            type: "array",
                            description: "الخدمات الأكثر طلباً وتقييماً",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "title", type: "string", example: "صالة هابي نايت"),
                                    new OA\Property(property: "description", type: "string", nullable: true, example: "صالة مجهزة بأحدث الديكورات"),
                                    new OA\Property(property: "price", type: "number", format: "float", example: 1800.00),
                                    new OA\Property(property: "currency", type: "string", example: "ILS"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "services/happy_night.jpg"),
                                    new OA\Property(property: "rating_avg", type: "number", format: "float", example: 5.0),
                                    new OA\Property(property: "reviews_count", type: "integer", example: 45),
                                    new OA\Property(property: "location", type: "string", example: "غزة - شارع الجلاء مقابل منتزه البلدية"),
                                    new OA\Property(property: "city", type: "string", example: "غزة"),
                                    new OA\Property(
                                        property: "provider",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 3),
                                            new OA\Property(property: "name", type: "string", example: "صالة هابي نايت"),
                                            new OA\Property(property: "avatar", type: "string", nullable: true, example: "providers/hall.jpg")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "category",
                                        type: "object",
                                        nullable: true,
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "name", type: "string", example: "صالات")
                                        ]
                                    )
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "featured_providers",
                            type: "array",
                            description: "مرادف متوافق عكسياً لـ suggested_providers",
                            items: new OA\Items(type: "object")
                        ),
                        new OA\Property(
                            property: "services",
                            type: "array",
                            description: "مرادف متوافق عكسياً لـ most_popular",
                            items: new OA\Items(type: "object")
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        // 1. بيانات المستخدم المسجل وموقعه للهيدر
        $user = $request->user('sanctum') ?? auth('sanctum')->user();
        $userData = null;
        $primaryLocation = null;

        if ($user) {
            $user->loadMissing(['locations.city', 'city']);
            $primaryLocation = $user->locations->firstWhere('is_primary', true) ?: $user->locations->first();
            $locationStr = $primaryLocation
                ? (($primaryLocation->city?->name ? $primaryLocation->city->name . '، ' : '') . $primaryLocation->address)
                : ($user->city?->name ?? 'غزة، الوحدة');

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $this->imageUrl($user->avatar),
                'location' => $locationStr,
                'city' => $primaryLocation?->city?->name ?? ($user->city?->name ?? 'غزة'),
                'unread_notifications_count' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            ];
        }

        // 2. قائمة التصنيفات / الخدمات
        $categories = Category::query()
            ->where('status', 'active')
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'image' => $this->imageUrl($cat->image),
                    'status' => $cat->status,
                ];
            });

        // 3. سلايدر العروض والخدمات المميزة في البانر العلوي
        $bannerServices = Service::with(['category', 'city', 'images'])
            ->where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->where(function ($q) {
                $q->where('is_featured', true)
                  ->orWhere('rating_avg', '>=', 4.0);
            })
            ->latest()
            ->limit(5)
            ->get();

        if ($bannerServices->isEmpty()) {
            $bannerServices = Service::with(['category', 'city', 'images'])
                ->where('status', 'active')
                ->where('is_available', true)
                ->forApprovedProviders()
                ->latest()
                ->limit(5)
                ->get();
        }

        $banners = $bannerServices->map(function ($service) {
            $features = is_array($service->features) ? array_values(array_filter($service->features)) : [];
            if (empty($features) && !empty($service->description)) {
                $lines = preg_split('/[\r\n,،\-•]+/', $service->description, -1, PREG_SPLIT_NO_EMPTY);
                $features = array_slice(array_map('trim', $lines), 0, 4);
            }
            if (empty($features)) {
                $features = ['بوفيه مفتوح', 'دي جي متكامل', 'شاشة عرض', 'إضاءة ليزر'];
            }

            return [
                'id' => $service->id,
                'service_id' => $service->id,
                'title' => $service->title,
                'badge' => $service->offer_badge ?: ($service->is_on_offer ? 'عرض لفترة محدودة' : null),
                'price' => (float) $service->price,
                'original_price' => $service->original_price !== null ? (float) $service->original_price : null,
                'discount_percentage' => $service->discount_percentage,
                'currency' => $service->currency ?: 'ILS',
                'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
                'features' => $features,
            ];
        });

        // 4. مزودين مقترحين لك
        $userLatitude = $primaryLocation?->latitude;
        $userLongitude = $primaryLocation?->longitude;
        $suggestedProviders = ProviderProfile::with(['user.locations.city', 'user.city', 'category', 'city'])
            ->where(function ($q) {
                $q->where('status', 'approved')
                  ->orWhereNull('status');
            })
            ->where(function ($query): void {
                $query->whereNull('category_id')
                    ->orWhereHas('category', fn ($category) => $category->where('status', 'active'));
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->get()
            ->map(function ($provider) use ($userLatitude, $userLongitude) {
                $name = $provider->business_name ?: ($provider->user?->name ?? 'مزود خدمة');
                $avatar = $provider->cover_image ?: ($provider->user?->avatar);
                $specialty = $provider->category?->name ?: ($provider->bio ?: 'خدمات مناسبات');
                $city = $provider->city?->name ?: ($provider->user?->city?->name ?? 'غزة');
                $providerLocation = $provider->user?->locations?->firstWhere('is_primary', true) ?: $provider->user?->locations?->first();
                $distance = ($userLatitude !== null && $userLongitude !== null && $providerLocation?->latitude !== null && $providerLocation?->longitude !== null)
                    ? $this->distanceKm((float) $userLatitude, (float) $userLongitude, (float) $providerLocation->latitude, (float) $providerLocation->longitude)
                    : null;

                return [
                    'id' => $provider->id,
                    'user_id' => $provider->user_id,
                    'name' => $name,
                    'business_name' => $provider->business_name ?: $name,
                    'avatar' => $this->imageUrl($avatar),
                    'specialty' => $specialty,
                    'category_name' => $specialty,
                    'rating' => (float) $provider->rating,
                    'city' => $city,
                    'distance_km' => $distance,
                    'is_featured' => (bool) $provider->is_featured,
                ];
            })
            ->sortBy(fn (array $provider) => $provider['distance_km'] ?? PHP_FLOAT_MAX)
            ->take(6)
            ->values();

        // 5. باقات مميزة (خصومات وعروض)
        $featuredPackagesQuery = Service::with(['category', 'city', 'provider.providerProfile', 'images'])
            ->where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->where('service_type', 'package')
            ->orderByDesc('rating_avg')
            ->limit(6)
            ->get();

        if ($featuredPackagesQuery->isEmpty()) {
            $featuredPackagesQuery = Service::with(['category', 'city', 'provider.providerProfile', 'images'])
                ->where('status', 'active')
                ->where('is_available', true)
                ->forApprovedProviders()
                ->where('is_on_offer', true)
                ->orderByDesc('rating_avg')
                ->limit(6)
                ->get();
        }

        $featuredPackages = $featuredPackagesQuery->values()->map(function ($service, $index) {
            $discountPercent = $service->discount_percentage;
            $originalPrice = $service->original_price;

            $providerName = $service->provider?->providerProfile?->business_name ?: ($service->provider?->name ?? 'مزود خدمة');
            $providerAvatar = $service->provider?->avatar ?: ($service->provider?->providerProfile?->cover_image);

            return [
                'id' => $service->id,
                'title' => $service->title,
                'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
                'discount_badge' => $service->offer_badge ?: ($discountPercent ? "خصم {$discountPercent}%" : null),
                'discount_percentage' => $discountPercent,
                'price' => (float) $service->price,
                'original_price' => $originalPrice !== null ? (float) $originalPrice : null,
                'currency' => $service->currency ?: 'ILS',
                'rating_avg' => (float) $service->rating_avg,
                'reviews_count' => (int) $service->reviews_count,
                'provider' => [
                    'id' => $service->provider_id,
                    'name' => $providerName,
                    'avatar' => $this->imageUrl($providerAvatar),
                ],
                'category' => $service->category ? [
                    'id' => $service->category->id,
                    'name' => $service->category->name,
                ] : null,
            ];
        });

        // 6. الأكثر طلباً
        $mostPopular = Service::with(['category', 'city', 'provider.providerProfile', 'provider.locations.city', 'images'])
            ->withCount(['bookings as confirmed_bookings_count' => fn ($query) => $query->whereIn('workflow_status', ['confirmed', 'completed'])])
            ->where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->orderByDesc('confirmed_bookings_count')
            ->orderByDesc('rating_avg')
            ->limit(6)
            ->get()
            ->map(function ($service) {
                $providerName = $service->provider?->providerProfile?->business_name ?: ($service->provider?->name ?? 'مزود خدمة');
                $providerAvatar = $service->provider?->avatar ?: ($service->provider?->providerProfile?->cover_image);

                $primaryLocation = $service->provider?->locations?->firstWhere('is_primary', true)
                    ?: $service->provider?->locations?->first();
                $locationText = $primaryLocation?->address
                    ? (($primaryLocation->city?->name ? $primaryLocation->city->name . ' - ' : '') . $primaryLocation->address)
                    : ($service->city?->name ? $service->city->name : 'غزة - شارع الجلاء');

                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'currency' => $service->currency ?: 'ILS',
                    'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
                    'rating_avg' => (float) $service->rating_avg,
                    'reviews_count' => (int) $service->reviews_count,
                    'bookings_count' => (int) $service->confirmed_bookings_count,
                    'location' => $locationText,
                    'city' => $service->city?->name ?: ($primaryLocation?->city?->name ?? 'غزة'),
                    'provider' => [
                        'id' => $service->provider_id,
                        'name' => $providerName,
                        'avatar' => $this->imageUrl($providerAvatar),
                    ],
                    'category' => $service->category ? [
                        'id' => $service->category->id,
                        'name' => $service->category->name,
                    ] : null,
                ];
            });

        return response()->json([
            'user' => $userData,
            'categories' => $categories,
            'banners' => $banners,
            'suggested_providers' => $suggestedProviders,
            'featured_packages' => $featuredPackages,
            'most_popular' => $mostPopular,
            // للتوافق العكسي مع أي شاشات سابقة
            'featured_providers' => $suggestedProviders,
            'services' => $mostPopular,
        ]);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
