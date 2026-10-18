<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Categories", description: "إدارة واسترجاع التصنيفات والفئات")]
class CategoryController extends Controller
{
    #[OA\Get(
        path: "/api/categories",
        summary: "جلب قائمة التصنيفات النشطة",
        description: "يسترجع جميع الفئات والتصنيفات المتاحة في المنصة والحاملة للحالة active.",
        tags: ["Categories"],
        responses: [
            new OA\Response(
                response: 200,
                description: "تم جلب التصنيفات بنجاح",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "صالات أفراح"),
                                    new OA\Property(property: "slug", type: "string", example: "wedding-halls"),
                                    new OA\Property(property: "image", type: "string", nullable: true, example: "categories/hall.jpg"),
                                    new OA\Property(property: "status", type: "string", example: "active"),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-08-26T12:00:00.000000Z"),
                                    new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2026-08-26T12:00:00.000000Z")
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $categories = Category::query()->where('status', 'active')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }
}