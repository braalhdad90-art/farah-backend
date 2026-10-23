<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateBookingRequest;
use App\Http\Requests\Api\CreateReviewRequest;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Services", description: "إدارة واستعراض الخدمات والبحث والفلترة حسب التصنيف والموقع والسعر")]
class ServiceController extends Controller
{
    use ImageUrlHelper;
    #[OA\Get(
        path: "/api/services",
        summary: "استعراض والبحث في الخدمات والفلترة حسب التصنيف والفرز",
        description: "يسترجع قائمة الخدمات المطابقة للتصميم (كروت الصالات والخدمات) مع دعم البحث الفوري، الفلترة حسب التصنيف والمدينة، والفرز بالأقل سعراً أو الأعلى تقييماً أو الأقرب لموقعك، مع حالة المفضلة للمستخدم المسجل.",
        tags: ["Services"],
        parameters: [
            new OA\Parameter(
                name: "category_id",
                in: "query",
                description: "معرّف التصنيف (مثل صالات: 1)",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "category_slug",
                in: "query",
                description: "الاسم اللطيف للتصنيف (مثل: wedding-halls)",
                required: false,
                schema: new OA\Schema(type: "string", example: "wedding-halls")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "كلمة البحث في عنوان أو وصف الخدمة (ابحث ضمن الصالات...)",
                required: false,
                schema: new OA\Schema(type: "string", example: "اورنا")
            ),
            new OA\Parameter(
                name: "sort_by",
                in: "query",
                description: "طريقة الفرز: all (الكل)، price_low (الأقل سعراً)، price_high (الأعلى سعراً)، rating (الأعلى تقييماً)، nearest (الأقرب لموقعك)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["all", "price_low", "price_high", "rating", "nearest"], default: "all")
            ),
            new OA\Parameter(
                name: "city_id",
                in: "query",
                description: "فلترة حسب المدينة",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "latitude",
                in: "query",
                description: "خط العرض الحالي لحساب الأقرب لموقعك",
                required: false,
                schema: new OA\Schema(type: "number", format: "float", example: 31.5000)
            ),
            new OA\Parameter(
                name: "longitude",
                in: "query",
                description: "خط الطول الحالي لحساب الأقرب لموقعك",
                required: false,
                schema: new OA\Schema(type: "number", format: "float", example: 34.4667)
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "عدد العناصر في الصفحة الواحدة",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10)
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "رقم الصفحة (Pagination)",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "تم جلب الخدمات بنجاح وتوافقها التام مع كروت الواجهة",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "category",
                            type: "object",
                            nullable: true,
                            description: "بيانات التصنيف المختار (لعنوان الشاشة)",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "صالات"),
                                new OA\Property(property: "slug", type: "string", example: "wedding-halls"),
                                new OA\Property(property: "image", type: "string", nullable: true, example: "categories/hall.jpg")
                            ]
                        ),
                        new OA\Property(
                            property: "available_sorts",
                            type: "array",
                            description: "خيارات الفرز المتاحة في الواجهة",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "key", type: "string", example: "all"),
                                    new OA\Property(property: "label", type: "string", example: "الكل")
                                ]
                            )
                        ),
                        new OA\Property(property: "active_sort", type: "string", example: "all"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            description: "قائمة كروت الخدمات المطابقة للتصميم",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "title", type: "string", example: "صالة أورنا"),
                                    new OA\Property(property: "description", type: "string", nullable: true, example: "صالة أفراح مجهزة بأحدث الديكورات"),
                                    new OA\Property(property: "price", type: "number", format: "float", example: 1400.00),
                                    new OA\Property(property: "currency", type: "string", example: "ILS"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "services/hall_orna.jpg"),
                                    new OA\Property(
                                        property: "images",
                                        type: "array",
                                        items: new OA\Items(type: "string", example: "services/hall_orna.jpg")
                                    ),
                                    new OA\Property(property: "rating_avg", type: "number", format: "float", example: 4.8),
                                    new OA\Property(property: "reviews_count", type: "integer", example: 24),
                                    new OA\Property(property: "location", type: "string", example: "غزة - الرمال - شمال مطعم التايلندي، عمارة حرز الله"),
                                    new OA\Property(property: "city", type: "string", example: "غزة"),
                                    new OA\Property(property: "is_favorited", type: "boolean", example: false),
                                    new OA\Property(property: "button_text", type: "string", example: "تفاصيل الصالة"),
                                    new OA\Property(
                                        property: "provider",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 4),
                                            new OA\Property(property: "name", type: "string", example: "إدارة صالة أورنا"),
                                            new OA\Property(property: "avatar", type: "string", nullable: true, example: "providers/orna.jpg")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "category",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "name", type: "string", example: "صالات"),
                                            new OA\Property(property: "slug", type: "string", example: "wedding-halls")
                                        ]
                                    )
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "pagination",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer", example: 1),
                                new OA\Property(property: "last_page", type: "integer", example: 3),
                                new OA\Property(property: "per_page", type: "integer", example: 10),
                                new OA\Property(property: "total", type: "integer", example: 25),
                                new OA\Property(property: "has_more", type: "boolean", example: true)
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Get(path: "/api/categories/{id}/services", summary: "استعراض خدمات تصنيف محدد", tags: ["Services"], parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")), new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"))], responses: [new OA\Response(response: 200, description: "قائمة خدمات التصنيف"), new OA\Response(response: 404, description: "التصنيف غير موجود")])]
    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user();
        $userFavoriteIds = [];

        if ($user) {
            $userFavoriteIds = Favorite::where('user_id', $user->id)
                ->pluck('service_id')
                ->toArray();
        }

        $query = Service::with([
            'category',
            'city',
            'provider.providerProfile',
            'provider.locations.city',
            'images'
        ])->where('status', 'active')->where('is_available', true)->forApprovedProviders();

        // 1. الفلترة حسب التصنيف (ID أو Slug)
        $categoryId = $request->category_id ?? $request->route('id');
        $currentCategory = null;
        if ($categoryId) {
            $query->where('category_id', $categoryId);
            $currentCategory = Category::find($categoryId);
        } elseif ($request->filled('category_slug')) {
            $currentCategory = Category::where('slug', $request->category_slug)->first();
            if ($currentCategory) {
                $query->where('category_id', $currentCategory->id);
            }
        }

        // 2. الفلترة حسب المدينة
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // 3. البحث الذكي بالعربية في العنوان أو الوصف
        if ($request->filled('search')) {
            $rawSearch = trim($request->search);
            $variations = [$rawSearch];

            // استبدال الألف في بداية الكلمة (ا / أ / إ / آ)
            if (preg_match('/^[اأإآ]/u', $rawSearch)) {
                $rest = mb_substr($rawSearch, 1);
                $variations[] = 'ا' . $rest;
                $variations[] = 'أ' . $rest;
                $variations[] = 'إ' . $rest;
                $variations[] = 'آ' . $rest;
            }

            // استبدال التاء المربوطة والهاء في نهاية الكلمة (ة / ه)
            if (preg_match('/[ةه]$/u', $rawSearch)) {
                $base = mb_substr($rawSearch, 0, -1);
                $variations[] = $base . 'ة';
                $variations[] = $base . 'ه';
            }

            // استبدال الياء والألف المقصورة في نهاية الكلمة (ي / ى)
            if (preg_match('/[يى]$/u', $rawSearch)) {
                $base = mb_substr($rawSearch, 0, -1);
                $variations[] = $base . 'ي';
                $variations[] = $base . 'ى';
            }

            $variations = array_unique($variations);

            $query->where(function ($q) use ($variations) {
                foreach ($variations as $term) {
                    $searchTerm = '%' . $term . '%';
                    $q->orWhere('title', 'like', $searchTerm)
                      ->orWhere('description', 'like', $searchTerm);
                }
            });
        }

        // 4. الفرز والتريب (Sort by)
        $sortBy = $request->get('sort_by', 'all');

        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderByDesc('rating_avg')->orderByDesc('reviews_count');
                break;
            case 'nearest':
                // الفرز بالأقرب لموقع المستخدم إذا توفرت الإحداثيات أو مدينة المستخدم
                $lat = $request->latitude ?? ($user?->locations?->first()?->latitude);
                $lng = $request->longitude ?? ($user?->locations?->first()?->longitude);

                if ($lat !== null && $lng !== null) {
                    $query->select('services.*')->selectRaw(
                        '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) as distance_km',
                        [$lat, $lng, $lat]
                    )->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('distance_km');
                } elseif ($user?->city_id) {
                    $query->orderByRaw('CASE WHEN city_id = ? THEN 0 ELSE 1 END', [$user->city_id])
                          ->latest();
                } else {
                    $query->latest();
                }
                break;
            case 'all':
            default:
                $query->orderByDesc('is_featured')->latest();
                break;
        }

        $perPage = min(max((int) $request->get('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        $categoryName = $currentCategory?->name ?? 'الخدمة';
        $buttonText = (str_contains($categoryName, 'صالة') || str_contains($categoryName, 'صالات'))
            ? 'تفاصيل الصالة'
            : 'تفاصيل ' . $categoryName;

        $items = collect($paginated->items())->map(function ($service) use ($userFavoriteIds, $buttonText) {
            $providerName = $service->provider?->providerProfile?->business_name ?: ($service->provider?->name ?? 'مزود خدمة');
            $providerAvatar = $service->provider?->avatar ?: ($service->provider?->providerProfile?->cover_image);

            $primaryLocation = $service->provider?->locations?->firstWhere('is_primary', true)
                ?: $service->provider?->locations?->first();
            $locationText = $primaryLocation?->address
                ? (($primaryLocation->city?->name ? $primaryLocation->city->name . ' - ' : '') . $primaryLocation->address)
                : ($service->city?->name ? $service->city->name : 'غزة');

            $allImages = $service->images->pluck('image_path')
                ->map(fn ($path) => $this->imageUrl($path))
                ->filter()
                ->values()
                ->toArray();
            $serviceImageUrl = $this->imageUrl($service->image);
            if ($serviceImageUrl && !in_array($serviceImageUrl, $allImages)) {
                array_unshift($allImages, $serviceImageUrl);
            }

            return [
                'id' => $service->id,
                'title' => $service->title,
                'service_type' => $service->service_type,
                'description' => $service->description,
                'features' => $service->features ?? [],
                'capacity' => $service->capacity,
                'area' => $service->area,
                'area_unit' => $service->area_unit,
                'price' => (float) $service->price,
                'pricing_unit' => $service->pricing_unit,
                'execution_duration' => $service->execution_duration,
                'is_on_offer' => (bool) $service->is_on_offer,
                'discount_percentage' => $service->discount_percentage,
                'original_price' => $service->original_price !== null ? (float) $service->original_price : null,
                'offer_badge' => $service->offer_badge,
                'currency' => $service->currency ?: 'ILS',
                'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
                'images' => $allImages,
                'rating_avg' => (float) $service->rating_avg,
                'reviews_count' => (int) $service->reviews_count,
                'location' => $locationText,
                'address' => $service->address ?: $locationText,
                'latitude' => $service->latitude,
                'longitude' => $service->longitude,
                'distance_km' => isset($service->distance_km) ? round((float) $service->distance_km, 2) : null,
                'city' => $service->city?->name ?: ($primaryLocation?->city?->name ?? 'غزة'),
                'is_favorited' => in_array($service->id, $userFavoriteIds),
                'button_text' => $buttonText,
                'category' => $service->category ? [
                    'id' => $service->category->id,
                    'name' => $service->category->name,
                    'slug' => $service->category->slug,
                ] : null,
                'provider' => [
                    'id' => $service->provider_id,
                    'name' => $providerName,
                    'avatar' => $this->imageUrl($providerAvatar),
                ],
            ];
        });

        $availableSorts = [
            ['key' => 'all', 'label' => 'الكل'],
            ['key' => 'price_low', 'label' => 'الأقل سعراً'],
            ['key' => 'rating', 'label' => 'الأعلى تقييماً'],
            ['key' => 'nearest', 'label' => 'الأقرب لموقعك'],
        ];

        return response()->json([
            'category' => $currentCategory ? [
                'id' => $currentCategory->id,
                'name' => $currentCategory->name,
                'slug' => $currentCategory->slug,
                'image' => $this->imageUrl($currentCategory->image),
            ] : null,
            'available_sorts' => $availableSorts,
            'active_sort' => $sortBy,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ]);
    }

    #[OA\Get(path: "/api/services/compare", summary: "مقارنة الخدمات", tags: ["Services"], parameters: [new OA\Parameter(name: "ids", in: "query", required: true, description: "معرّفات خدمتين إلى ثلاث مفصولة بفواصل", schema: new OA\Schema(type: "string", example: "1,2,3"))], responses: [new OA\Response(response: 200, description: "بيانات الخدمات للمقارنة"), new OA\Response(response: 422, description: "عدد أو تصنيف الخدمات غير صالح")])]
    public function compare(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->count() < 2 || $ids->count() > 3) {
            return response()->json(['icon' => 'error', 'title' => 'اختر خدمتين إلى ثلاث خدمات للمقارنة.'], 422);
        }

        $services = Service::with(['category', 'images', 'items'])->whereIn('id', $ids)->where('status', 'active')->forApprovedProviders()->get();
        if ($services->count() !== $ids->count() || $services->pluck('category_id')->unique()->count() !== 1) {
            return response()->json(['icon' => 'error', 'title' => 'يمكن مقارنة خدمات نشطة من التصنيف نفسه فقط.'], 422);
        }

        return response()->json(['data' => $services->map(function (Service $service) {
            return [
                'id' => $service->id,
                'title' => $service->title,
                'image' => $this->imageUrl($service->image ?: $service->images->first()?->image_path),
                'price' => (float) $service->price,
                'currency' => $service->currency ?: 'ILS',
                'execution_duration' => $service->execution_duration,
                'rating_avg' => (float) $service->rating_avg,
                'reviews_count' => (int) $service->reviews_count,
                'features' => $service->features ?? [],
                'package_items' => $service->items->map(fn ($item) => ['title' => $item->title, 'quantity' => $item->quantity])->values(),
                'cancellation_policy' => $service->cancellation_policy,
            ];
        })->values()]);
    }

    #[OA\Get(
        path: "/api/services/{id}",
        summary: "عرض تفاصيل خدمة أو صالة محددة",
        description: "يسترجع كافة تفاصيل الخدمة بما يشمل معرض الصور، التقييمات، معلومات المزود، والموقع.",
        tags: ["Services"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "معرّف الخدمة",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "تم جلب تفاصيل الخدمة بنجاح",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "title", type: "string", example: "صالة أورنا"),
                                new OA\Property(property: "description", type: "string", example: "صالة مجهزة بأحدث الديكورات ونظام إضاءة وصوت مميز"),
                                new OA\Property(property: "features", type: "array", items: new OA\Items(type: "string"), example: ["مواقف سيارات", "نظام صوتي", "إضاءة"]),
                                new OA\Property(property: "capacity", type: "integer", nullable: true, example: 400),
                                new OA\Property(property: "area", type: "number", format: "float", nullable: true, example: 850),
                                new OA\Property(property: "area_unit", type: "string", example: "m²"),
                                new OA\Property(
                                    property: "booking_slots",
                                    type: "array",
                                    items: new OA\Items(type: "object"),
                                    example: [["label" => "الفترة الصباحية", "start_time" => "09:00", "end_time" => "15:00"]]
                                ),
                                new OA\Property(property: "price", type: "number", format: "float", example: 1400.00),
                                new OA\Property(property: "currency", type: "string", example: "ILS"),
                                new OA\Property(property: "image", type: "string", nullable: true, example: "services/hall_orna.jpg"),
                                new OA\Property(
                                    property: "images",
                                    type: "array",
                                    items: new OA\Items(type: "string", example: "services/hall_orna.jpg")
                                ),
                                new OA\Property(property: "rating_avg", type: "number", format: "float", example: 4.8),
                                new OA\Property(property: "reviews_count", type: "integer", example: 24),
                                new OA\Property(property: "location", type: "string", example: "غزة - الرمال - شمال مطعم التايلندي، عمارة حرز الله"),
                                new OA\Property(property: "city", type: "string", example: "غزة"),
                                new OA\Property(property: "is_favorited", type: "boolean", example: false),
                                new OA\Property(
                                    property: "category",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "صالات"),
                                        new OA\Property(property: "slug", type: "string", example: "wedding-halls")
                                    ]
                                ),
                                new OA\Property(
                                    property: "provider",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 4),
                                        new OA\Property(property: "name", type: "string", example: "صالة أورنا"),
                                        new OA\Property(property: "avatar", type: "string", nullable: true, example: "providers/orna.jpg"),
                                        new OA\Property(property: "phone", type: "string", nullable: true, example: "0599000000"),
                                        new OA\Property(property: "bio", type: "string", nullable: true, example: "أفضل صالات الأفراح في غزة")
                                    ]
                                ),
                                new OA\Property(
                                    property: "reviews",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "rating", type: "integer", example: 5),
                                            new OA\Property(property: "comment", type: "string", example: "مكان رائع وخدمة ممتازة جداً"),
                                            new OA\Property(property: "user_name", type: "string", example: "مالك"),
                                            new OA\Property(property: "created_at", type: "string", example: "2026-08-20")
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "الخدمة غير موجودة"
            )
        ]
    )]
    public function show(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user();

        $service = Service::with([
            'category',
            'city',
            'provider.providerProfile',
            'provider.locations.city',
            'images',
            'items',
            'reviews.user'
        ])
        ->where('status', 'active')
        ->where('is_available', true)
        ->forApprovedProviders()
        ->findOrFail($id);

        $isFavorited = false;
        if ($user) {
            $isFavorited = Favorite::where('user_id', $user->id)
                ->where('service_id', $service->id)
                ->exists();
        }

        $providerName = $service->provider?->providerProfile?->business_name ?: ($service->provider?->name ?? 'مزود خدمة');
        $providerAvatar = $service->provider?->avatar ?: ($service->provider?->providerProfile?->cover_image);
        $providerBio = $service->provider?->providerProfile?->bio ?: $service->provider?->bio;
        $providerPhone = $service->provider?->providerProfile?->phone ?: $service->provider?->phone;

        $primaryLocation = $service->provider?->locations?->firstWhere('is_primary', true)
            ?: $service->provider?->locations?->first();
        $locationText = $primaryLocation?->address
            ? (($primaryLocation->city?->name ? $primaryLocation->city->name . ' - ' : '') . $primaryLocation->address)
            : ($service->city?->name ? $service->city->name : 'غزة');

        $allImages = $service->images->pluck('image_path')
            ->map(fn ($path) => $this->imageUrl($path))
            ->filter()
            ->values()
            ->toArray();
        $serviceImageUrl = $this->imageUrl($service->image);
        if ($serviceImageUrl && !in_array($serviceImageUrl, $allImages)) {
            array_unshift($allImages, $serviceImageUrl);
        }

        $reviews = $service->reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'user_name' => $review->user?->name ?? 'مستخدم',
                'user_avatar' => $review->user?->avatar,
                'created_at' => $review->created_at?->format('Y-m-d'),
            ];
        });

        $data = [
            'id' => $service->id,
            'title' => $service->title,
            'service_type' => $service->service_type,
            'description' => $service->description,
            'features' => $service->features ?? [],
            // فرح: المزايا اللي سمح المزود بإزالتها، حتى يعرض التطبيق "قابلة للإزالة" بدل التخمين.
            'removable_features' => array_values(array_intersect($service->removable_features ?? [], $service->features ?? [])),
            'capacity' => $service->capacity,
            'area' => $service->area,
            'area_unit' => $service->area_unit,
            'booking_slots' => $service->booking_slots ?? [],
            'price' => (float) $service->price,
            'pricing_unit' => $service->pricing_unit,
            'execution_duration' => $service->execution_duration,
            'cancellation_policy' => $service->cancellation_policy,
            'deposit_amount' => $service->deposit_amount !== null ? (float) $service->deposit_amount : null,
            'deposit_percentage' => $service->deposit_percentage,
            'is_on_offer' => (bool) $service->is_on_offer,
            'discount_percentage' => $service->discount_percentage,
            'original_price' => $service->original_price !== null ? (float) $service->original_price : null,
            'offer_badge' => $service->offer_badge,
            'currency' => $service->currency ?: 'ILS',
            'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
            'images' => $allImages,
            'rating_avg' => (float) $service->rating_avg,
            'reviews_count' => (int) $service->reviews_count,
            'location' => $locationText,
            'address' => $service->address ?: $locationText,
            'latitude' => $service->latitude,
            'longitude' => $service->longitude,
            'city' => $service->city?->name ?: ($primaryLocation?->city?->name ?? 'غزة'),
            'is_favorited' => $isFavorited,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
            ] : null,
            'provider' => [
                'id' => $service->provider_id,
                'name' => $providerName,
                'avatar' => $this->imageUrl($providerAvatar),
                'phone' => $user ? $providerPhone : null,
                'bio' => $providerBio,
            ],
            'package_items' => $service->items->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
            ])->values(),
            'reviews' => $reviews,
        ];

        return response()->json([
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: "/api/services/{id}/booking-options",
        summary: "جلب تقويم وفترات الحجز للخدمة",
        tags: ["Services"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "month", in: "query", required: false, description: "الشهر بصيغة YYYY-MM", schema: new OA\Schema(type: "string", example: "2026-09"))
        ],
        responses: [
            new OA\Response(response: 200, description: "تقويم وفترات الحجز المتاحة"),
            new OA\Response(response: 404, description: "الخدمة غير موجودة")
        ]
    )]
    public function bookingOptions(Request $request, $id)
    {
        $service = Service::with(['provider.providerProfile', 'unavailableDates'])
            ->where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->findOrFail($id);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $today = now()->startOfDay();
        $blockedDates = $service->unavailableDates->keyBy(fn ($date) => $date->unavailable_date->toDateString());

        $bookedSlots = Booking::query()
            ->where('service_id', $service->id)
            ->whereBetween('booking_date', [$month->toDateString(), $monthEnd->toDateString()])
            ->whereIn('workflow_status', ['provider_pending', 'payment_awaiting', 'review_under_proof', 'confirmed'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['booking_date', 'booking_time', 'booking_slot'])
            ->groupBy(fn ($booking) => Carbon::parse($booking->booking_date)->toDateString())
            ->map(fn ($bookings) => $bookings->map(fn ($booking) => [
                'time' => Carbon::parse($booking->booking_time)->format('H:i'),
                'slot' => $booking->booking_slot,
            ])->values()->all())
            ->all();

        $servicePrice = (float) $service->price;
        $slots = collect($service->effectiveBookingSlots())->map(function ($slot) use ($servicePrice) {
            return [
                'label' => $slot['label'] ?? null,
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'price' => array_key_exists('price', $slot) ? (float) $slot['price'] : $servicePrice,
            ];
        });

        $days = [];
        for ($date = $month->copy(); $date->lte($monthEnd); $date->addDay()) {
            $dateString = $date->toDateString();
            $isPast = $date->lt($today);
            $dateBookings = $bookedSlots[$dateString] ?? [];
            $isBlocked = $blockedDates->has($dateString);
            $daySlots = $slots->map(function ($slot) use ($dateBookings, $isPast, $isBlocked, $dateString) {
                $isBooked = collect($dateBookings)->contains(fn ($booking) =>
                    $booking['time'] === $slot['start_time'] &&
                    ($booking['slot'] === null || $booking['slot'] === $slot['label'])
                );
                // A slot that already started today can no longer be booked.
                $started = $slot['start_time'] && Carbon::parse($dateString.' '.$slot['start_time'])->lte(now());

                return array_merge($slot, [
                    'is_available' => ! $isPast && ! $isBlocked && ! $isBooked && ! $started,
                ]);
            })->values();

            $days[] = [
                'date' => $dateString,
                'day' => (int) $date->day,
                'is_past' => $isPast,
                'is_blocked_by_provider' => $isBlocked,
                'blocked_reason' => $isBlocked ? $blockedDates->get($dateString)?->reason : null,
                'is_available' => ! $isPast && $daySlots->contains('is_available', true),
                'slots' => $daySlots,
            ];
        }

        return response()->json([
            'data' => [
                'service_id' => $service->id,
                'month' => $month->format('Y-m'),
                'currency' => $service->currency ?: 'ILS',
                'price' => (float) $service->price,
                'days' => $days,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/services/{id}/booking-preview",
        summary: "معاينة ملخص الحجز قبل الدفع",
        security: [["bearerAuth" => []]],
        tags: ["Services"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["booking_date", "booking_time"],
                properties: [
                    new OA\Property(property: "booking_date", type: "string", format: "date", example: "2026-09-21"),
                    new OA\Property(property: "booking_time", type: "string", example: "17:00"),
                    new OA\Property(property: "booking_slot", type: "string", nullable: true, example: "الفترة المسائية"),
                    new OA\Property(property: "notes", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "ملخص الحجز جاهز"),
            new OA\Response(response: 422, description: "الفترة غير متاحة")
        ]
    )]
    public function bookingPreview(CreateBookingRequest $request, $id)
    {
        $service = Service::with('images')->where('status', 'active')->forApprovedProviders()->findOrFail($id);
        $slot = $this->resolveBookingSlot($service, $request);

        $dateBlocked = $service->unavailableDates()->whereDate('unavailable_date', $request->booking_date)->exists();
        if (! $service->is_available || ! $slot || $dateBlocked || $this->bookingIsTaken($service->id, $request)) {
            return response()->json([
                'icon' => 'error',
                'title' => 'الفترة المختارة غير متاحة للحجز.',
            ], 422);
        }

        return response()->json([
            'data' => $this->bookingSummary($service, $request, $slot),
        ]);
    }

    #[OA\Post(
        path: "/api/services/{id}/reviews",
        summary: "إضافة أو تحديث تقييم الخدمة",
        security: [["bearerAuth" => []]],
        tags: ["Services"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rating"],
                properties: [
                    new OA\Property(property: "rating", type: "integer", minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: "comment", type: "string", nullable: true, example: "الخدمة ممتازة")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "تم حفظ التقييم"),
            new OA\Response(response: 401, description: "يجب تسجيل الدخول"),
            new OA\Response(response: 422, description: "بيانات التقييم غير صالحة")
        ]
    )]
    public function storeReview(CreateReviewRequest $request, $id)
    {
        $service = Service::where('status', 'active')->forApprovedProviders()->findOrFail($id);

        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', $request->user()->id)
            ->where('service_id', $service->id)
            ->where('workflow_status', 'completed')
            ->first();
        if (! $booking) {
            return response()->json([
                'icon' => 'error',
                'title' => 'يمكنك التقييم بعد اكتمال حجزك لهذه الخدمة فقط.',
            ], 422);
        }

        $review = Review::updateOrCreate(
            [
                'booking_id' => $booking->id,
            ],
            [
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]
        );

        $service->update([
            'rating_avg' => $service->reviews()->avg('rating') ?? 0,
            'reviews_count' => $service->reviews()->count(),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'تم حفظ التقييم بنجاح.',
            'data' => $review->load('user'),
        ], 201);
    }

    private function resolveBookingSlot(Service $service, $request): ?array
    {
        return collect($service->effectiveBookingSlots())->first(function ($slot) use ($request) {
            return ($slot['start_time'] ?? null) === $request->booking_time
                && (($slot['label'] ?? null) === $request->booking_slot || blank($request->booking_slot));
        });
    }

    private function bookingIsTaken(int $serviceId, $request): bool
    {
        return Booking::where('service_id', $serviceId)
            ->whereDate('booking_date', $request->booking_date)
            ->whereTime('booking_time', $request->booking_time)
            ->whereIn('workflow_status', ['provider_pending', 'payment_awaiting', 'review_under_proof', 'confirmed'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    private function bookingSummary(Service $service, $bookingData, array $slot, ?Booking $booking = null): array
    {
        $image = $this->imageUrl($service->image ?: ($service->images->first()?->image_path));
        $price = array_key_exists('price', $slot)
            ? (float) $slot['price']
            : (float) $service->price;

        return [
            'booking_id' => $booking?->id,
            'status' => $booking?->status ?? 'preview',
            'payment_status' => $booking?->workflow_status === 'confirmed' ? 'confirmed' : 'not_requested',
            'payment_required' => false,
            'service' => [
                'id' => $service->id,
                'title' => $service->title,
                'image' => $image,
                'currency' => $service->currency ?: 'ILS',
            ],
            'booking' => [
                'date' => Carbon::parse($bookingData->booking_date)->toDateString(),
                'time' => Carbon::parse($bookingData->booking_time)->format('H:i'),
                'slot' => $bookingData->booking_slot ?: ($slot['label'] ?? null),
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'notes' => $bookingData->notes ?? null,
            ],
            'pricing' => [
                'subtotal' => $price,
                'additional_fees' => 0,
                'total' => $price,
                'currency' => $service->currency ?: 'ILS',
            ],
            'next_step' => $booking ? $booking->workflow_status : 'provider_review',
        ];
    }

}
