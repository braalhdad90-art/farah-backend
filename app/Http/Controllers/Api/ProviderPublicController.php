<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

// فرح: صفحة المزود للعميل (الضغط على مزود من الرئيسية): بيانات المتجر الحقيقية
// وخدماته المنشورة فقط. {id} هو رقم حساب المزود (provider_id / user_id)، نفس provider.id في الخدمات.
class ProviderPublicController extends Controller
{
    use ImageUrlHelper;

    #[OA\Get(
        path: "/api/providers/{id}",
        summary: "صفحة المزود العامة: بيانات المتجر وخدماته المنشورة",
        tags: ["Providers"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, description: "رقم حساب المزود (user_id)", schema: new OA\Schema(type: "integer"))],
        responses: [
            new OA\Response(response: 200, description: "بيانات المزود وخدماته"),
            new OA\Response(response: 404, description: "المزود غير موجود أو غير معتمد"),
        ]
    )]
    public function show(Request $request, int $id)
    {
        $provider = User::query()
            ->where('user_type', 'provider')
            ->where('status', 'active')
            ->whereHas('providerProfile', fn ($profile) => $profile
                ->where('status', 'approved')
                ->where('registration_status', 'approved'))
            ->with(['providerProfile.category', 'providerProfile.city', 'city', 'locations.city', 'activeProviderSubscription'])
            ->findOrFail($id);
        $profile = $provider->providerProfile;

        $services = Service::query()
            ->with(['category', 'images'])
            ->where('provider_id', $provider->id)
            ->where('status', 'active')
            ->where('is_available', true)
            ->forApprovedProviders()
            ->latest()
            ->get();

        $viewer = $request->user('sanctum');
        $favoriteIds = $viewer
            ? Favorite::where('user_id', $viewer->id)->whereIn('service_id', $services->pluck('id'))->pluck('service_id')->all()
            : [];

        // Rating from the services' real reviews (weighted by review count).
        $reviewsCount = (int) $services->sum('reviews_count');
        $rating = $reviewsCount > 0
            ? round($services->sum(fn (Service $s) => (float) $s->rating_avg * (int) $s->reviews_count) / $reviewsCount, 1)
            : 0.0;

        $location = $provider->locations->firstWhere('label', 'business')
            ?? $provider->locations->firstWhere('is_primary', true)
            ?? $provider->locations->first();
        $city = $profile->city?->name ?? $provider->city?->name;

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $provider->id,
                'profile_id' => $profile->id,
                'name' => $profile->business_name ?: $provider->name,
                'avatar' => $this->imageUrl($provider->avatar ?: $profile->cover_image),
                'cover_image' => $this->imageUrl($profile->cover_image),
                'specialty' => $profile->category?->name,
                'category' => $profile->category?->only(['id', 'name']),
                'description' => $profile->description,
                'city' => $city,
                'address' => $location?->address,
                // فرح: رقم المزود يظهر للمستخدم المسجّل فقط، مثل صفحة الخدمة.
                'phone' => $viewer ? ($profile->phone ?: $provider->phone) : null,
                'rating' => $rating,
                'reviews_count' => $reviewsCount,
                'services_count' => $services->count(),
                'is_bookable' => (bool) $provider->activeProviderSubscription?->is_active && $services->isNotEmpty(),
                'services' => $services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'title' => $service->title,
                    'description' => $service->description,
                    'service_type' => $service->service_type,
                    'price' => (float) $service->price,
                    'pricing_unit' => $service->pricing_unit,
                    'currency' => $service->currency ?: 'ILS',
                    'is_on_offer' => (bool) $service->is_on_offer,
                    'original_price' => $service->original_price !== null ? (float) $service->original_price : null,
                    'image' => $this->imageUrl($service->image ?: $service->images->first()?->image_path),
                    'rating_avg' => (float) $service->rating_avg,
                    'reviews_count' => (int) $service->reviews_count,
                    'category' => $service->category?->only(['id', 'name']),
                    'is_favorited' => in_array($service->id, $favoriteIds, true),
                ])->values(),
            ],
        ]);
    }
}
