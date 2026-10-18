<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateNotificationSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Notification Settings", description: "إعدادات إشعارات المستخدم")]
class NotificationSettingController extends Controller
{
    use ApiResponse;

    #[OA\Get(path: "/api/notification-settings", summary: "عرض إعدادات الإشعارات", security: [["bearerAuth" => []]], tags: ["Notification Settings"], responses: [new OA\Response(response: 200, description: "إعدادات الإشعارات")])]
    public function show(Request $request): JsonResponse
    {
        $settings = $request->user()->notificationSetting()->firstOrCreate([], [
            'enabled' => true,
            'new_orders' => true,
            'offers' => true,
            'promotions' => true,
            'reminders' => true,
        ]);

        return $this->success('تم جلب إعدادات الإشعارات بنجاح.', $this->settingsData($settings));
    }

    #[OA\Put(path: "/api/notification-settings", summary: "تحديث إعدادات الإشعارات", security: [["bearerAuth" => []]], tags: ["Notification Settings"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: "enabled", type: "boolean"), new OA\Property(property: "new_orders", type: "boolean"), new OA\Property(property: "offers", type: "boolean"), new OA\Property(property: "promotions", type: "boolean"), new OA\Property(property: "reminders", type: "boolean")])), responses: [new OA\Response(response: 200, description: "تم تحديث الإعدادات"), new OA\Response(response: 422, description: "بيانات غير صالحة")])]
    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $settings = $request->user()->notificationSetting()->firstOrCreate([], [
            'enabled' => true,
            'new_orders' => true,
            'offers' => true,
            'promotions' => true,
            'reminders' => true,
        ]);
        $settings->update($request->validated());

        return $this->success('تم تحديث إعدادات الإشعارات بنجاح.', $this->settingsData($settings->fresh()));
    }

    private function settingsData(object $settings): array
    {
        return [
            'enabled' => (bool) $settings->enabled,
            'new_orders' => (bool) $settings->new_orders,
            'offers' => (bool) $settings->offers,
            'promotions' => (bool) $settings->promotions,
            'reminders' => (bool) $settings->reminders,
        ];
    }
}
