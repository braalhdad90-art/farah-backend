<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExternalBookingRequest;
use App\Models\ExternalBooking;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "External Bookings", description: "إدارة الحجوزات الخارجية للمزود")]
class ExternalBookingController extends Controller
{
    #[OA\Get(
        path: "/api/provider/external-bookings",
        summary: "عرض الحجوزات الخارجية للمزود",
        security: [["bearerAuth" => []]],
        tags: ["External Bookings"],
        responses: [
            new OA\Response(response: 200, description: "قائمة الحجوزات الخارجية"),
            new OA\Response(response: 401, description: "غير مصرح")
        ]
    )]
    public function index(Request $request)
    {
        $bookings = ExternalBooking::query()
            ->where('provider_id', $request->user()->id)
            ->latest('booking_date')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $bookings->map(fn ($booking) => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'service_name' => $booking->service_name,
                'booking_date' => $booking->booking_date?->toDateString(),
                'booking_time' => $booking->booking_time?->format('H:i'),
                'total_price' => (float) $booking->total_price,
                'deposit_amount' => (float) $booking->deposit_amount,
                'remaining_amount' => (float) $booking->remaining_amount,
                'payment_method' => $booking->payment_method,
                'status' => $booking->status,
                'notes' => $booking->notes,
                'source' => $booking->source,
                'created_at' => $booking->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    #[OA\Post(
        path: "/api/provider/external-bookings",
        summary: "إضافة حجز خارجي للمزود",
        security: [["bearerAuth" => []]],
        tags: ["External Bookings"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["customer_name", "service_name", "booking_date", "total_price"],
                properties: [
                    new OA\Property(property: "customer_name", type: "string", example: "سارة أحمد"),
                    new OA\Property(property: "customer_phone", type: "string", nullable: true, example: "0501234567"),
                    new OA\Property(property: "service_name", type: "string", example: "قاعة الموهبة"),
                    new OA\Property(property: "booking_date", type: "string", format: "date", example: "2026-10-20"),
                    new OA\Property(property: "booking_time", type: "string", format: "time", nullable: true, example: "18:00"),
                    new OA\Property(property: "total_price", type: "number", format: "float", example: 960.00),
                    new OA\Property(property: "deposit_amount", type: "number", format: "float", nullable: true, example: 300.00),
                    new OA\Property(property: "payment_method", type: "string", nullable: true, example: "bank_transfer"),
                    new OA\Property(property: "status", type: "string", enum: ["pending", "confirmed", "cancelled"], nullable: true),
                    new OA\Property(property: "notes", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "تم حفظ الحجز الخارجي"),
            new OA\Response(response: 401, description: "غير مصرح"),
            new OA\Response(response: 422, description: "بيانات غير صالحة")
        ]
    )]
    public function store(StoreExternalBookingRequest $request)
    {
        $validated = $request->validated();
        $deposit = (float) ($validated['deposit_amount'] ?? 0);
        $total = (float) $validated['total_price'];
        $remaining = max(0, $total - $deposit);

        $booking = ExternalBooking::create([
            'provider_id' => $request->user()->id,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'service_name' => $validated['service_name'],
            'booking_date' => $validated['booking_date'],
            'booking_time' => $validated['booking_time'] ?? null,
            'total_price' => $total,
            'deposit_amount' => $deposit,
            'remaining_amount' => $remaining,
            'payment_method' => $validated['payment_method'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'notes' => $validated['notes'] ?? null,
            'source' => 'outside_app',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ الحجز الخارجي بنجاح.',
            'data' => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'service_name' => $booking->service_name,
                'booking_date' => $booking->booking_date?->toDateString(),
                'booking_time' => $booking->booking_time?->format('H:i'),
                'total_price' => (float) $booking->total_price,
                'deposit_amount' => (float) $booking->deposit_amount,
                'remaining_amount' => (float) $booking->remaining_amount,
                'payment_method' => $booking->payment_method,
                'status' => $booking->status,
                'notes' => $booking->notes,
                'source' => 'outside_app',
            ],
        ], 201);
    }
}
