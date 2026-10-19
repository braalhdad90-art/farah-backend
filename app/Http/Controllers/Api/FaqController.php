<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FaqController extends Controller
{
    // فرح: أسئلة مركز المساعدة. المزود يطلب audience=provider والعميل audience=customer.
    #[OA\Get(
        path: "/api/faqs",
        summary: "الأسئلة الشائعة لمركز المساعدة",
        tags: ["Settings"],
        parameters: [new OA\Parameter(name: "audience", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["customer", "provider"]))],
        responses: [new OA\Response(response: 200, description: "قائمة الأسئلة")]
    )]
    public function index(Request $request)
    {
        $data = $request->validate(['audience' => ['nullable', 'in:customer,provider']]);

        $faqs = Faq::query()
            ->where('is_active', true)
            ->when($data['audience'] ?? null, fn ($query, $audience) => $query->whereIn('audience', [$audience, 'all']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer']);

        return response()->json(['status' => true, 'data' => $faqs]);
    }
}
