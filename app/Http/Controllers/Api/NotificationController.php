<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Notifications", description: "إدارة إشعارات المستخدم")]
class NotificationController extends Controller
{
    #[OA\Get(path: "/api/notifications", summary: "عرض إشعارات المستخدم", security: [["bearerAuth" => []]], tags: ["Notifications"], parameters: [new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"))], responses: [new OA\Response(response: 200, description: "قائمة الإشعارات")])]
    public function index(Request $request)
    {
        $notifications = $request->user()->appNotifications()->paginate(min(max((int) $request->get('per_page', 20), 1), 50));

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (AppNotification $item) => $this->data($item))->values(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ]);
    }

    #[OA\Post(path: "/api/notifications/{notification}/read", summary: "تعليم إشعار كمقروء", security: [["bearerAuth" => []]], tags: ["Notifications"], parameters: [new OA\Parameter(name: "notification", in: "path", required: true, schema: new OA\Schema(type: "integer"))], responses: [new OA\Response(response: 200, description: "تم تعليم الإشعار"), new OA\Response(response: 403, description: "الإشعار لا يخص المستخدم")])]
    public function markRead(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['status' => true, 'data' => $this->data($notification->fresh())]);
    }

    #[OA\Post(path: "/api/notifications/read-all", summary: "تعليم كل الإشعارات كمقروءة", security: [["bearerAuth" => []]], tags: ["Notifications"], responses: [new OA\Response(response: 200, description: "تم تعليم الإشعارات")])]
    public function markAllRead(Request $request)
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['status' => true, 'message' => 'تم تعليم جميع الإشعارات كمقروءة.']);
    }

    private function data(AppNotification $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'body' => $item->body,
            'resource_type' => $item->resource_type,
            'resource_id' => $item->resource_id,
            'data' => $item->data,
            'is_read' => $item->read_at !== null,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
