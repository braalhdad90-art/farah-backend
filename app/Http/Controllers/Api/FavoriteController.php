<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Api\Concerns\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Service;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Favorites', description: 'إدارة المفضلة وقائمة الخدمات المفضلة للمستخدم')]
class FavoriteController extends Controller
{
    use ApiResponse, ImageUrlHelper;

    #[OA\Get(
        path: '/api/favorites',
        summary: 'جلب قائمة الخدمات المفضلة للمستخدم',
        description: 'يسترجع قائمة بجميع الخدمات التي قام المستخدم المسجل بإضافتها إلى المفضلة.',
        security: [['bearerAuth' => []]],
        tags: ['Favorites'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'تم جلب المفضلة بنجاح',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'service_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'title', type: 'string', example: 'صالة أورنا'),
                                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 1400.00),
                                    new OA\Property(property: 'currency', type: 'string', example: 'ILS'),
                                    new OA\Property(property: 'image', type: 'string', nullable: true, example: 'services/hall_orna.jpg'),
                                    new OA\Property(property: 'rating_avg', type: 'number', format: 'float', example: 4.8),
                                    new OA\Property(property: 'reviews_count', type: 'integer', example: 19),
                                    new OA\Property(property: 'location', type: 'string', example: 'غزة - الرمال - شمال مطعم التايلندي'),
                                    new OA\Property(property: 'city', type: 'string', example: 'غزة'),
                                    new OA\Property(property: 'is_favorited', type: 'boolean', example: true),
                                    new OA\Property(
                                        property: 'category',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'صالات'),
                                        ]
                                    ),
                                    new OA\Property(
                                        property: 'provider',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'name', type: 'string', example: 'صالة أورنا'),
                                            new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'providers/orna.jpg'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'غير مصرح (يرجى تسجيل الدخول)'
            ),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();

        $favorites = Favorite::with([
            'service.category',
            'service.city',
            'service.provider.providerProfile',
            'service.provider.locations.city',
            'service.images',
        ])
            ->where('user_id', $user->id)
            ->whereHas('service', fn ($query) => $query
                ->where('status', 'active')
                ->where('is_available', true)
                ->forApprovedProviders())
            ->latest()
            ->get();

        $data = $favorites->map(function ($favorite) {
            $service = $favorite->service;
            if (! $service) {
                return null;
            }

            $providerName = $service->provider?->providerProfile?->business_name ?: ($service->provider?->name ?? 'مزود خدمة');
            $providerAvatar = $service->provider?->avatar ?: ($service->provider?->providerProfile?->cover_image);

            $primaryLocation = $service->provider?->locations?->first();
            $locationText = $primaryLocation?->address
                ? (($primaryLocation->city?->name ? $primaryLocation->city->name.' - ' : '').$primaryLocation->address)
                : ($service->city?->name ? $service->city->name : 'غزة');

            return [
                'id' => $favorite->id,
                'service_id' => $service->id,
                'title' => $service->title,
                'description' => $service->description,
                'price' => (float) $service->price,
                'currency' => $service->currency ?: 'ILS',
                'image' => $this->imageUrl($service->image ?: ($service->images->first()?->image_path)),
                'rating_avg' => (float) $service->rating_avg,
                'reviews_count' => (int) $service->reviews_count,
                'location' => $locationText,
                'city' => $service->city?->name ?: ($primaryLocation?->city?->name ?? 'غزة'),
                'is_favorited' => true,
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
        })->filter()->values();

        return response()->json([
            'status' => true,
            'message' => 'تم جلب المفضلة بنجاح.',
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: '/api/favorites/toggle/{service_id}',
        summary: 'إضافة أو إزالة خدمة من المفضلة (Toggle)',
        description: 'يقوم بالتبديل بين إضافة الخدمة إلى المفضلة أو حذفها في حال كانت موجودة مسبقاً.',
        security: [['bearerAuth' => []]],
        tags: ['Favorites'],
        parameters: [
            new OA\Parameter(
                name: 'service_id',
                in: 'path',
                description: 'معرّف الخدمة المطلوب إضافتها أو إزالتها',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'تم تحديث حالة المفضلة بنجاح',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean', example: true),
                        new OA\Property(property: 'is_favorited', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'تمت إضافة الخدمة إلى المفضلة بنجاح'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'الخدمة غير موجودة'
            ),
            new OA\Response(
                response: 401,
                description: 'غير مصرح (يرجى تسجيل الدخول)'
            ),
        ]
    )]
    #[OA\Post(
        path: '/api/services/{id}/favorite',
        summary: 'إضافة أو إزالة خدمة من المفضلة',
        security: [['bearerAuth' => []]],
        tags: ['Favorites'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث حالة المفضلة'),
            new OA\Response(response: 401, description: 'غير مصرح'),
            new OA\Response(response: 404, description: 'الخدمة غير موجودة'),
        ]
    )]
    public function toggle(Request $request, $serviceId)
    {
        $user = $request->user();

        // التأكد من وجود الخدمة
        $service = Service::where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->findOrFail($serviceId);

        $favorite = Favorite::where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'status' => true,
                'message' => 'تمت إزالة الخدمة من المفضلة بنجاح.',
                'is_favorited' => false,
            ]);
        }

        Favorite::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة الخدمة إلى المفضلة بنجاح.',
            'is_favorited' => true,
        ]);
    }
}
