<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Users", description: "بيانات المستخدم الحالي")]
class UserController extends Controller
{
    #[OA\Get(path: "/api/user", summary: "عرض بيانات المستخدم الحالي", security: [["bearerAuth" => []]], tags: ["Users"], responses: [new OA\Response(response: 200, description: "بيانات المستخدم"), new OA\Response(response: 401, description: "غير مصرح")])]
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المستخدم بنجاح.',
            'data' => $request->user(),
        ]);
    }
}
