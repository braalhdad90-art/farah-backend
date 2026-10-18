<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProviderOnboardingRequest;
use App\Http\Requests\Api\ProviderServiceRequest;
use App\Http\Requests\Api\UpdateProviderAvailabilityRequest;
use App\Http\Requests\Api\UpdateProviderServiceVisibilityRequest;
use App\Services\ProviderServiceManager;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Provider", description: "إدارة ملف مزود الخدمة وخدماته")]
class ProviderController extends Controller
{
    public function __construct(private readonly ProviderServiceManager $providerServices)
    {
    }

    #[OA\Get(
        path: "/api/provider/verification",
        summary: "عرض حالة توثيق حساب مزود الخدمة",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        responses: [new OA\Response(response: 200, description: "حالة التوثيق")]
    )]
    public function verificationStatus(Request $request)
    {
        return response()->json(['data' => $this->providerServices->verificationStatus($request->user())]);
    }

    #[OA\Get(path: "/api/provider/dashboard", summary: "لوحة تحكم مزود الخدمة", security: [["bearerAuth" => []]], tags: ["Provider"], responses: [new OA\Response(response: 200, description: "إحصاءات المزود والطلبات الأخيرة")])]
    public function dashboard(Request $request)
    {
        return response()->json(['data' => $this->providerServices->dashboard($request->user())]);
    }

    #[OA\Get(path: "/api/provider/onboarding", summary: "عرض بيانات تسجيل مزود الخدمة", security: [["bearerAuth" => []]], tags: ["Provider"], responses: [new OA\Response(response: 200, description: "بيانات المزود")])]
    public function onboarding(Request $request)
    {
        return response()->json(['data' => $this->providerServices->onboarding($request->user())]);
    }

    #[OA\Post(
        path: "/api/provider/onboarding",
        summary: "حفظ بيانات تسجيل مزود الخدمة",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["business_name", "category_id", "description", "city_id", "address"],
                    properties: [
                        new OA\Property(property: "business_name", type: "string", example: "صالة أورنا"),
                        new OA\Property(property: "category_id", type: "integer", example: 1),
                        new OA\Property(property: "description", type: "string", example: "قاعة مناسبات مجهزة بالكامل"),
                        new OA\Property(property: "city_id", type: "integer", example: 1),
                        new OA\Property(property: "address", type: "string", example: "غزة - الرمال"),
                        new OA\Property(property: "latitude", type: "number", format: "float", nullable: true),
                        new OA\Property(property: "longitude", type: "number", format: "float", nullable: true),
                        new OA\Property(property: "identity_document", type: "string", format: "binary"),
                        new OA\Property(property: "commercial_register", type: "string", format: "binary"),
                        new OA\Property(property: "cover_image", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "تم حفظ البيانات"), new OA\Response(response: 422, description: "بيانات غير صالحة")]
    )]
    public function saveOnboarding(ProviderOnboardingRequest $request)
    {
        $profile = $this->providerServices->saveOnboarding(
            $request->user(),
            $request->validated(),
            [
                'identity_document' => $request->file('identity_document'),
                'commercial_register' => $request->file('commercial_register'),
                'cover_image' => $request->file('cover_image'),
            ]
        );

        return response()->json([
            'icon' => 'success',
            'title' => 'تم حفظ بيانات مزود الخدمة وتفعيل الحساب مباشرة.',
            'data' => $profile->load(['category', 'city']),
        ]);
    }

    #[OA\Get(path: "/api/provider/services", summary: "عرض خدمات المزود", security: [["bearerAuth" => []]], tags: ["Provider"], responses: [new OA\Response(response: 200, description: "قائمة الخدمات")])]
    public function services(Request $request)
    {
        return response()->json(['data' => $this->providerServices->services($request->user(), $request->query('type'))]);
    }

    #[OA\Post(
        path: "/api/provider/services",
        summary: "إنشاء خدمة للمزود",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["title", "price"],
                    properties: [
                        new OA\Property(property: "title", type: "string", example: "صالة أورنا"),
                        new OA\Property(property: "category_id", type: "integer", nullable: true),
                        new OA\Property(property: "city_id", type: "integer", nullable: true),
                        new OA\Property(property: "description", type: "string", nullable: true),
                        new OA\Property(property: "service_type", type: "string", enum: ["service", "package"]),
                        new OA\Property(property: "features", type: "string", description: "مصفوفة JSON مرسلة كنص.", example: "[\"مواقف سيارات\",\"نظام صوتي\"]"),
                        new OA\Property(property: "capacity", type: "integer", nullable: true),
                        new OA\Property(property: "area", type: "number", nullable: true),
                        new OA\Property(property: "area_unit", type: "string", nullable: true),
                        new OA\Property(property: "booking_slots", type: "string", description: "مصفوفة JSON مرسلة كنص.", example: "[{\"label\":\"الفترة المسائية\",\"start_time\":\"17:00\",\"end_time\":\"22:00\"}]"),
                        new OA\Property(property: "price", type: "number", format: "float"),
                        new OA\Property(property: "pricing_unit", type: "string", nullable: true),
                        new OA\Property(property: "execution_duration", type: "string", nullable: true),
                        new OA\Property(property: "cancellation_policy", type: "string", nullable: true),
                        new OA\Property(property: "address", type: "string", nullable: true),
                        new OA\Property(property: "latitude", type: "number", nullable: true),
                        new OA\Property(property: "longitude", type: "number", nullable: true),
                        new OA\Property(property: "deposit_amount", type: "number", nullable: true),
                        new OA\Property(property: "deposit_percentage", type: "integer", nullable: true),
                        new OA\Property(property: "currency", type: "string", example: "ILS"),
                        new OA\Property(property: "is_available", type: "boolean", nullable: true),
                        new OA\Property(property: "image", type: "string", format: "binary"),
                        new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "remove_image_ids", type: "string", description: "مصفوفة JSON لمعرّفات الصور المراد حذفها.", example: "[1,2]"),
                        new OA\Property(property: "package_items", type: "string", description: "مصفوفة JSON لعناصر الباقة.", example: "[{\"title\":\"التصوير\",\"quantity\":1}]")
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: "تم إنشاء الخدمة", content: new OA\JsonContent(properties: [new OA\Property(property: "icon", type: "string", example: "success"), new OA\Property(property: "title", type: "string"), new OA\Property(property: "data", type: "object")]))]
    )]
    public function storeService(ProviderServiceRequest $request)
    {
        $service = $this->providerServices->createService(
            $request->user(),
            $request->validated(),
            $request->file('image'),
            $request->file('images', [])
        );

        return response()->json([
            'icon' => 'success',
            'title' => 'تم نشر الخدمة مباشرة.',
            'data' => $this->providerServices->serviceData($service),
        ], 201);
    }

    #[OA\Post(
        path: "/api/provider/services/{id}",
        summary: "تعديل خدمة للمزود",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["title", "price"],
                    properties: [
                        new OA\Property(property: "title", type: "string"),
                        new OA\Property(property: "category_id", type: "integer", nullable: true),
                        new OA\Property(property: "city_id", type: "integer", nullable: true),
                        new OA\Property(property: "description", type: "string"),
                        new OA\Property(property: "service_type", type: "string", enum: ["service", "package"]),
                        new OA\Property(property: "features", type: "string", description: "مصفوفة JSON مرسلة كنص."),
                        new OA\Property(property: "capacity", type: "integer", nullable: true),
                        new OA\Property(property: "area", type: "number", nullable: true),
                        new OA\Property(property: "area_unit", type: "string", nullable: true),
                        new OA\Property(property: "booking_slots", type: "string", description: "مصفوفة JSON مرسلة كنص."),
                        new OA\Property(property: "price", type: "number", format: "float"),
                        new OA\Property(property: "pricing_unit", type: "string", nullable: true),
                        new OA\Property(property: "execution_duration", type: "string", nullable: true),
                        new OA\Property(property: "cancellation_policy", type: "string", nullable: true),
                        new OA\Property(property: "address", type: "string", nullable: true),
                        new OA\Property(property: "latitude", type: "number", nullable: true),
                        new OA\Property(property: "longitude", type: "number", nullable: true),
                        new OA\Property(property: "deposit_amount", type: "number", nullable: true),
                        new OA\Property(property: "deposit_percentage", type: "integer", nullable: true),
                        new OA\Property(property: "currency", type: "string"),
                        new OA\Property(property: "is_available", type: "boolean"),
                        new OA\Property(property: "image", type: "string", format: "binary"),
                        new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "remove_image_ids", type: "string", description: "مصفوفة JSON لمعرّفات الصور المراد حذفها."),
                        new OA\Property(property: "package_items", type: "string", description: "مصفوفة JSON لعناصر الباقة.")
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "تم تعديل الخدمة", content: new OA\JsonContent(properties: [new OA\Property(property: "icon", type: "string", example: "success"), new OA\Property(property: "title", type: "string"), new OA\Property(property: "data", type: "object")]))]
    )]
    public function updateService(ProviderServiceRequest $request, $id)
    {
        $service = $this->providerServices->updateService(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->file('image'),
            $request->file('images', [])
        );

        return response()->json([
            'icon' => 'success',
            'title' => 'تم حفظ التعديلات ونشرها مباشرة.',
            'data' => $this->providerServices->serviceData($service),
        ]);
    }

    #[OA\Delete(
        path: "/api/provider/services/{id}",
        summary: "حذف خدمة للمزود",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "تم حذف الخدمة"),
            new OA\Response(response: 403, description: "المستخدم ليس مزود خدمة"),
            new OA\Response(response: 404, description: "الخدمة غير موجودة")
        ]
    )]
    public function destroyService(Request $request, $id)
    {
        $this->providerServices->deleteService($request->user(), (int) $id);

        return response()->json(['icon' => 'success', 'title' => 'تم حذف الخدمة.']);
    }

    #[OA\Patch(path: "/api/provider/services/{id}/visibility", summary: "إظهار أو إخفاء خدمة", security: [["bearerAuth" => []]], tags: ["Provider"], parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["is_available"], properties: [new OA\Property(property: "is_available", type: "boolean")])), responses: [new OA\Response(response: 200, description: "تم تحديث ظهور الخدمة"), new OA\Response(response: 404, description: "الخدمة غير موجودة")])]
    public function updateServiceVisibility(UpdateProviderServiceVisibilityRequest $request, $id)
    {
        $service = $this->providerServices->updateVisibility($request->user(), (int) $id, (bool) $request->boolean('is_available'));

        return response()->json([
            'status' => true,
            'message' => $service->is_available ? 'الخدمة ظاهرة للعملاء الآن.' : 'تم إخفاء الخدمة من العملاء.',
            'data' => $this->providerServices->serviceData($service),
        ]);
    }

    #[OA\Get(path: "/api/provider/services/{id}/availability", summary: "عرض تقويم إتاحة الخدمة", security: [["bearerAuth" => []]], tags: ["Provider"], parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")), new OA\Parameter(name: "month", in: "query", required: false, schema: new OA\Schema(type: "string", example: "2026-09"))], responses: [new OA\Response(response: 200, description: "تقويم الخدمة")])]
    public function availability(Request $request, $id)
    {
        return response()->json(['data' => $this->providerServices->availability($request->user(), (int) $id, $request->query('month'))]);
    }

    #[OA\Put(
        path: "/api/provider/services/{id}/availability",
        summary: "تحديث تقويم إتاحة الخدمة",
        security: [["bearerAuth" => []]],
        tags: ["Provider"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(
                    property: "block_dates",
                    type: "array",
                    items: new OA\Items(properties: [
                        new OA\Property(property: "date", type: "string", format: "date", example: "2026-10-15"),
                        new OA\Property(property: "reason", type: "string", nullable: true)
                    ])
                ),
                new OA\Property(property: "unblock_dates", type: "array", items: new OA\Items(type: "string", format: "date"))
            ])
        ),
        responses: [new OA\Response(response: 200, description: "تم تحديث التقويم", content: new OA\JsonContent(properties: [new OA\Property(property: "status", type: "boolean"), new OA\Property(property: "message", type: "string"), new OA\Property(property: "data", type: "object")])) , new OA\Response(response: 422, description: "بيانات غير صالحة")]
    )]
    public function updateAvailability(UpdateProviderAvailabilityRequest $request, $id)
    {
        return response()->json([
            'status' => true,
            'message' => 'تم تحديث تقويم إتاحة الخدمة.',
            'data' => $this->providerServices->updateAvailability($request->user(), (int) $id, $request->validated()),
        ]);
    }

    #[OA\Get(path: "/api/provider/reviews", summary: "عرض تقييمات خدمات المزود", security: [["bearerAuth" => []]], tags: ["Provider"], responses: [new OA\Response(response: 200, description: "قائمة تقييمات العملاء")])]
    public function reviews(Request $request)
    {
        $reviews = $this->providerServices->reviews($request->user());

        return response()->json([
            'data' => $reviews->getCollection()->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'customer' => ['id' => $review->user?->id, 'name' => $review->user?->name, 'avatar' => $review->user?->avatar ? asset('storage/'.$review->user->avatar) : null],
                'service' => ['id' => $review->service?->id, 'title' => $review->service?->title],
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'pagination' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total(), 'has_more' => $reviews->hasMorePages()],
        ]);
    }
}

