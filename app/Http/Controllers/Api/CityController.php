<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\City;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Cities", description: "إدارة واسترجاع قائمة المدن المتاحة")]
class CityController extends Controller
{
    /**
     * Return list of cities for API (ordered by name).
     */
    #[OA\Get(
        path: "/api/cities",
        summary: "جلب قائمة المدن المتاحة",
        description: "يسترجع جميع المدن مرتبة أبدياً لاستخدامها في القوائم المنسدلة وعناوين الطلبات.",
        tags: ["Cities"],
        responses: [
            new OA\Response(
                response: 200,
                description: "تم جلب قائمة المدن بنجاح",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "icon", type: "string", example: "success"),
                        new OA\Property(property: "title", type: "string", example: "Cities fetched"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "غزة"),
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
    public function index(Request $request)
    {
        $cities = City::orderBy('name')->get();

        return response()->json([
            'icon' => 'success',
            'title' => 'Cities fetched',
            'data' => $cities,
        ], 200);
    }
}