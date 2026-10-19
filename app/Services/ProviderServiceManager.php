<?php

namespace App\Services;

use App\Models\Location;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\BookingExpirationService;

class ProviderServiceManager
{
    public function __construct(private readonly BookingExpirationService $expiration)
    {
    }
    public function verificationStatus(User $user): array
    {
        $profile = $user->providerProfile;

        if (! $profile) {
            return [
                'status' => 'not_started',
                'title' => 'لم تبدأ عملية التوثيق بعد.',
                'message' => 'أكمل بيانات النشاط وأرسل الوثائق للمتابعة.',
                'next_step' => 'onboarding',
            ];
        }

        $status = $profile->registration_status ?: 'draft';
        $statusData = [
            'draft' => [
                'title' => 'أكمل بيانات التوثيق',
                'message' => 'أرسل بيانات النشاط والوثائق المطلوبة لبدء المراجعة.',
                'next_step' => 'onboarding',
            ],
            'pending' => [
                'title' => 'قيد المراجعة',
                'message' => 'تستغرق مراجعة المستندات من فريق المنصة حوالي 24 ساعة.',
                'next_step' => 'wait',
            ],
            'approved' => [
                'title' => 'تم توثيق الحساب',
                'message' => 'تم اعتماد بيانات مزود الخدمة ويمكنك الآن استقبال الحجوزات.',
                'next_step' => 'services',
            ],
            'rejected' => [
                'title' => 'تحتاج البيانات إلى تعديل',
                'message' => 'راجع ملاحظات المنصة وأعد إرسال بيانات التوثيق.',
                'next_step' => 'onboarding',
            ],
        ][$status] ?? [
            'title' => 'قيد المراجعة',
            'message' => 'بياناتك قيد المراجعة من فريق المنصة.',
            'next_step' => 'wait',
        ];

        return array_merge([
            'status' => $status,
            'profile_id' => $profile->id,
            'decision_reason' => $profile->decision_reason,
            'has_identity_document' => (bool) $profile->identity_document_path,
            'has_commercial_register' => (bool) $profile->commercial_register_path,
        ], $statusData);
    }

    public function onboarding(User $user): array
    {
        $user->load(['providerProfile.category', 'city', 'locations.city']);
        $profile = $user->providerProfile;

        return [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $this->publicUrl($user->avatar),
            ],
            'profile' => $profile ? [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'category' => $profile->category ? [
                    'id' => $profile->category->id,
                    'name' => $profile->category->name,
                ] : null,
                'description' => $profile->description,
                'city' => $profile->city ? [
                    'id' => $profile->city->id,
                    'name' => $profile->city->name,
                ] : null,
                'address' => $user->locations->first()?->address,
                'cover_image' => $this->publicUrl($profile->cover_image),
                'identity_document' => null,
                'commercial_register' => null,
                'registration_status' => $profile->registration_status,
            ] : null,
        ];
    }

    public function saveOnboarding(User $user, array $data, array $files = []): ProviderProfile
    {
        return DB::transaction(function () use ($user, $data, $files) {
            $profile = ProviderProfile::firstOrNew(['user_id' => $user->id]);
            abort_unless(! in_array($profile->status, ['approved', 'suspended'], true), 409, 'لا يمكن إعادة إرسال بيانات التوثيق لحساب معتمد أو موقوف.');
            $profile->fill(collect($data)->only([
                'business_name', 'category_id', 'city_id', 'description',
            ])->all());
            // After admin panel sprint Phase 1, onboarding requires explicit
            // administrative approval. Mark new onboarding as pending so an
            // admin can review documents and approve or reject.
            $profile->status = 'pending';
            $profile->registration_status = 'pending';

            $profile->submitted_at = now();
            $profile->escalated_at = null;
            $profile->decided_at = null;
            $profile->decision_reason = null;
            $this->replaceFile($profile, 'identity_document_path', $files['identity_document'] ?? null, 'provider-documents', 'local');
            $this->replaceFile($profile, 'commercial_register_path', $files['commercial_register'] ?? null, 'provider-documents', 'local');
            $this->replaceFile($profile, 'cover_image', $files['cover_image'] ?? null, 'provider-covers', 'public');
            $profile->save();

            $user->update([
                'city_id' => $data['city_id'],
                'bio' => $data['description'],
            ]);

            Location::updateOrCreate(
                ['user_id' => $user->id, 'label' => 'business'],
                collect($data)->only(['address', 'city_id', 'latitude', 'longitude'])->all()
            );

            return $profile->load(['category', 'city']);
        });
    }

    public function dashboard(User $user): array
    {
        $this->expiration->expireStale();
        $services = $user->services();
        $serviceIds = $services->pluck('id');
        $subscription = $user->activeProviderSubscription()->with('plan')->first();
        $pendingStates = ['provider_pending', 'payment_awaiting', 'review_under_proof'];
        $activeServices = (clone $services)->where('status', 'active')->where('is_available', true)->count();
        $profile = $user->providerProfile?->loadMissing('category');

        return [
            'provider' => [
                'id' => $user->id,
                'business_name' => $profile?->business_name ?? $user->name,
                'avatar' => $this->publicUrl($user->avatar ?: $profile?->cover_image),
                'cover_image' => $this->publicUrl($profile?->cover_image),
                'category' => $profile?->category?->only(['id', 'name']),
                // فرح: شارة "متاح للحجز": حساب معتمد + اشتراك فعّال + خدمة منشورة واحدة على الأقل.
                'is_bookable' => $profile?->status === 'approved' && (bool) $subscription?->is_active && $activeServices > 0,
                'registration_status' => $user->providerProfile?->registration_status,
                'payment_instructions_configured' => filled($user->providerProfile?->payment_instructions),
                'payment_instructions' => $user->providerProfile?->payment_instructions,
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'is_active' => $subscription->is_active,
                'plan' => $subscription->plan?->only(['id', 'slug', 'name', 'price', 'currency']),
                'starts_at' => $subscription->current_period_start?->toIso8601String(),
                'ends_at' => $subscription->current_period_end?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'days_remaining' => max(0, now()->diffInDays($subscription->current_period_end, false)),
                'message' => $subscription->status === 'trialing' ? 'أول شهر مجاني ومفعّل الآن.' : 'الاشتراك فعّال.',
            ] : [
                'status' => 'not_started',
                'is_active' => false,
                'message' => 'فعّل التجربة المجانية لمدة شهر من صفحة الاشتراك.',
            ],
            'counts' => [
                'new_orders' => Booking::where('provider_id', $user->id)->where('workflow_status', 'provider_pending')->count(),
                'new_orders_today' => Booking::where('provider_id', $user->id)->where('workflow_status', 'provider_pending')->whereDate('created_at', today())->count(),
                'payment_reviews' => Booking::where('provider_id', $user->id)->where('workflow_status', 'review_under_proof')->count(),
                'open_orders' => Booking::where('provider_id', $user->id)->whereIn('workflow_status', $pendingStates)->count(),
                'upcoming_bookings' => Booking::where('provider_id', $user->id)->where('workflow_status', 'confirmed')->whereDate('booking_date', '>=', today())->count(),
                'completed_bookings' => Booking::where('provider_id', $user->id)->where('workflow_status', 'completed')->count(),
                'active_services' => $activeServices,
                'services' => (clone $services)->count(),
                'hidden_services' => (clone $services)->where(function ($query) { $query->where('status', '!=', 'active')->orWhere('is_available', false); })->count(),
                'packages' => (clone $services)->where('service_type', 'package')->count(),
            ],
            'rating' => [
                'average' => round((float) Review::whereIn('service_id', $serviceIds)->avg('rating'), 2),
                'count' => Review::whereIn('service_id', $serviceIds)->count(),
            ],
            'store_stats' => $this->storeStats($user),
            'unread_notifications' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'latest_orders' => Booking::with(['service.images', 'user'])->where('provider_id', $user->id)->latest()->limit(5)->get()
                ->map(fn (Booking $booking) => $this->bookingCard($booking))->values(),
        ];
    }

    // فرح: إحصائيات صفحة خدماتي من بيانات حقيقية. تكون null للمزود الجديد.
    private function storeStats(User $user): array
    {
        $bookings = Booking::where('provider_id', $user->id);
        $responses = (clone $bookings)->whereNotNull('provider_responded_at')->latest('provider_responded_at')->limit(100)
            ->get(['created_at', 'provider_responded_at']);
        // Requests that expired before the provider answered (accepting sets payment_due_at).
        $missed = (clone $bookings)->where('workflow_status', 'expired')->whereNull('provider_responded_at')->whereNull('payment_due_at')->count();
        $answered = (clone $bookings)->whereNotNull('provider_responded_at')->count();

        return [
            'completed_bookings' => (clone $bookings)->where('workflow_status', 'completed')->count(),
            'avg_response_minutes' => $responses->isEmpty() ? null
                : (int) round($responses->avg(fn (Booking $booking) => $booking->created_at->diffInMinutes($booking->provider_responded_at))),
            'commitment_rate' => $answered + $missed === 0 ? null : (int) round($answered / ($answered + $missed) * 100),
        ];
    }

    public function services(User $user, ?string $type = null)
    {
        return $user->services()->with(['category', 'city', 'images', 'items', 'unavailableDates'])
            ->when(in_array($type, ['service', 'package'], true), fn ($query) => $query->where('service_type', $type))
            ->latest()->get()->map(fn (Service $service) => $this->serviceData($service))->values();
    }

    public function createService(User $user, array $data, ?UploadedFile $image = null, array $images = []): Service
    {
        $this->ensureProviderApproved($user);

        $data['provider_id'] = $user->id;
        $data['city_id'] = $data['city_id'] ?? $user->city_id;
        $data['category_id'] = $data['category_id'] ?? $user->providerProfile?->category_id;
        $data['currency'] = $data['currency'] ?? 'ILS';
        $data['is_available'] = $data['is_available'] ?? true;
        $data = $this->applyPublishing($this->applyOfferPrice($data));
        $data['image'] = $image?->store('services', 'public');

        $service = Service::create($data);
        $this->syncServiceImages($service, $images);
        $this->syncPackageItems($service, $data);

        return $service->fresh()->load(['category', 'city', 'images', 'items']);
    }

    public function updateService(User $user, int $serviceId, array $data, ?UploadedFile $image = null, array $images = []): Service
    {
        $this->ensureProviderApproved($user);

        $service = $user->services()->findOrFail($serviceId);
        if ($image) {
            $this->deleteFile($service->image);
            $data['image'] = $image->store('services', 'public');
        }

        $data = $this->applyPublishing($this->applyOfferPrice($data));
        $service->update($data);
        $this->removeServiceImages($service, $data['remove_image_ids'] ?? []);
        $this->syncServiceImages($service, $images);
        $this->syncPackageItems($service, $data);

        return $service->fresh()->load(['category', 'city', 'images', 'items']);
    }

    public function deleteService(User $user, int $serviceId): void
    {
        $service = $user->services()->findOrFail($serviceId);
        if ($service->bookings()->whereIn('workflow_status', ['provider_pending', 'payment_awaiting', 'review_under_proof', 'confirmed'])->exists()) {
            abort(409, 'لا يمكن حذف خدمة مرتبطة بحجز قائم. يمكنك إخفاؤها بدلًا من ذلك.');
        }
        $this->deleteFile($service->image);
        $service->delete();
    }

    public function updateVisibility(User $user, int $serviceId, bool $isAvailable): Service
    {
        if ($isAvailable) {
            $this->ensureProviderApproved($user);
        }

        $service = $user->services()->findOrFail($serviceId);
        // Activating a draft publishes it.
        $service->update($isAvailable ? ['is_available' => true, 'status' => 'active', 'is_draft' => false] : ['is_available' => false]);

        return $service->fresh(['category', 'city', 'images', 'items', 'unavailableDates']);
    }

    public function availability(User $user, int $serviceId, ?string $month = null): array
    {
        $this->expiration->expireStale();
        $service = $user->services()->with('unavailableDates')->findOrFail($serviceId);
        $month = $month ?: now()->format('Y-m');
        abort_unless(preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month), 422, 'صيغة الشهر يجب أن تكون YYYY-MM.');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $reserved = Booking::where('service_id', $service->id)->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('workflow_status', ['provider_pending', 'payment_awaiting', 'review_under_proof', 'confirmed'])
            ->get(['booking_date', 'booking_time', 'booking_slot', 'workflow_status'])
            ->groupBy(fn (Booking $booking) => $booking->booking_date->toDateString())
            ->map(fn ($items) => $items->map(fn (Booking $booking) => [
                'time' => Carbon::parse($booking->booking_time)->format('H:i'), 'slot' => $booking->booking_slot, 'status' => $booking->workflow_status,
            ])->values());

        return [
            'service_id' => $service->id,
            'month' => $month,
            'is_published' => (bool) $service->is_available && $service->status === 'active',
            'periods' => $service->booking_slots ?: [],
            'unavailable_dates' => $service->unavailableDates()->whereBetween('unavailable_date', [$start->toDateString(), $end->toDateString()])->get()
                ->map(fn ($date) => ['date' => $date->unavailable_date->toDateString(), 'reason' => $date->reason])->values(),
            'reserved_dates' => $reserved,
        ];
    }

    public function updateAvailability(User $user, int $serviceId, array $data): array
    {
        $service = $user->services()->findOrFail($serviceId);
        foreach ($data['block_dates'] ?? [] as $date) {
            $service->unavailableDates()->updateOrCreate(['unavailable_date' => $date['date']], ['reason' => $date['reason'] ?? null]);
        }
        if (! empty($data['unblock_dates'])) {
            $service->unavailableDates()->whereIn('unavailable_date', $data['unblock_dates'])->delete();
        }

        return $this->availability($user, $service->id, now()->format('Y-m'));
    }

    public function reviews(User $user)
    {
        return Review::with(['user:id,name,avatar', 'service:id,title,image'])
            ->where('is_visible', true)
            ->whereIn('service_id', $user->services()->select('id'))->latest()->paginate(20);
    }

    // فرح: "سعر العرض" من شاشة إضافة خدمة: السعر الأصلي يصبح original_price ويُعرض سعر العرض.
    private function applyOfferPrice(array $data): array
    {
        if (! array_key_exists('offer_price', $data)) {
            return $data;
        }
        $offer = $data['offer_price'];
        unset($data['offer_price']);
        if ($offer === null || $offer === '') {
            return $data + ['original_price' => null, 'is_on_offer' => false, 'discount_percentage' => null];
        }

        $price = (float) $data['price'];

        return array_merge($data, [
            'original_price' => $price,
            'price' => (float) $offer,
            'is_on_offer' => true,
            'discount_percentage' => $price > 0 ? (int) round((1 - (float) $offer / $price) * 100) : null,
        ]);
    }

    // فرح: "حفظ كمسودة" يحفظ الخدمة غير منشورة (is_draft)، و"نشر الآن" ينشرها.
    private function applyPublishing(array $data): array
    {
        $draft = (bool) ($data['save_as_draft'] ?? false);
        unset($data['save_as_draft']);
        $data['status'] = $draft ? 'inactive' : 'active';
        $data['is_draft'] = $draft;
        if ($draft) {
            $data['is_available'] = false;
        }
        if (array_key_exists('removable_features', $data)) {
            $data['removable_features'] = array_values(array_intersect($data['removable_features'] ?? [], $data['features'] ?? []));
        }

        return $data;
    }

    public function serviceData(Service $service): array
    {
        return [
            'id' => $service->id,
            'title' => $service->title,
            'type' => $service->service_type,
            'description' => $service->description,
            'category' => $service->category?->only(['id', 'name', 'slug']),
            'city' => $service->city?->only(['id', 'name']),
            'images' => $service->images->map(fn (ServiceImage $image) => ['id' => $image->id, 'url' => $this->publicUrl($image->image_path), 'sort_order' => $image->sort_order])->values(),
            'cover_image' => $this->publicUrl($service->image) ?: $this->publicUrl($service->images->first()?->image_path),
            'features' => $service->features ?: [],
            'feature_items' => collect($service->features ?: [])->map(fn ($name) => [
                'name' => $name,
                'removable' => in_array($name, $service->removable_features ?: [], true),
            ])->values(),
            'package_items' => $service->items->map(fn ($item) => $item->only(['id', 'title', 'description', 'quantity', 'removable', 'sort_order']))->values(),
            'pricing' => ['price' => (float) $service->price, 'original_price' => $service->original_price ? (float) $service->original_price : null, 'currency' => $service->currency, 'unit' => $service->pricing_unit, 'deposit_amount' => $service->deposit_amount, 'deposit_percentage' => $service->deposit_percentage],
            'execution_duration' => $service->execution_duration,
            'booking_periods' => $service->booking_slots ?: [],
            'is_published' => $service->status === 'active' && (bool) $service->is_available,
            'is_draft' => (bool) $service->is_draft,
            'status' => $service->status,
            'rating' => ['average' => (float) $service->rating_avg, 'count' => (int) $service->reviews_count],
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }

    private function replaceFile(ProviderProfile $profile, string $attribute, ?UploadedFile $file, string $directory, string $disk = 'public'): void
    {
        if (! $file) {
            return;
        }

        $disks = $directory === 'provider-documents' ? ['local', 'public'] : ['public'];
        $this->deleteFile($profile->{$attribute}, $disks);
        $profile->{$attribute} = $file->store($directory, $disk);
    }

    private function deleteFile(?string $path, array $disks = ['public']): void
    {
        if (! $path) {
            return;
        }

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function ensureProviderApproved(User $user): void
    {
        abort_unless(
            $user->status === 'active'
                && $user->providerProfile?->status === 'approved'
                && $user->providerProfile?->registration_status === 'approved',
            403,
            'لا يمكن نشر الخدمات قبل اعتماد حساب مزود الخدمة.'
        );
    }

    private function syncServiceImages(Service $service, array $images): void
    {
        $nextOrder = (int) $service->images()->max('sort_order') + 1;
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                ServiceImage::create([
                    'service_id' => $service->id,
                    'image_path' => $image->store('services', 'public'),
                    'sort_order' => $nextOrder++,
                ]);
            }
        }
    }

    private function removeServiceImages(Service $service, array $ids): void
    {
        $service->images()->whereIn('id', $ids)->get()->each(function (ServiceImage $image): void {
            $this->deleteFile($image->image_path);
            $image->delete();
        });
    }

    private function syncPackageItems(Service $service, array $data): void
    {
        if (! array_key_exists('package_items', $data)) {
            return;
        }
        $service->items()->delete();
        foreach ($data['package_items'] ?? [] as $index => $item) {
            $service->items()->create([
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'removable' => (bool) ($item['removable'] ?? false),
                'sort_order' => $item['sort_order'] ?? $index,
            ]);
        }
    }

    private function bookingCard(Booking $booking): array
    {
        $image = $booking->service?->image ?: $booking->service?->images?->first()?->image_path;
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->workflow_status,
            'customer' => ['id' => $booking->user?->id, 'name' => $booking->user?->name, 'avatar' => $this->publicUrl($booking->user?->avatar)],
            'service' => ['id' => $booking->service?->id, 'title' => $booking->service?->title, 'image' => $this->publicUrl($image)],
            'date' => $booking->booking_date?->toDateString(),
            'time' => $booking->booking_time ? Carbon::parse($booking->booking_time)->format('H:i') : null,
            'total' => (float) $booking->selected_price,
            'currency' => $booking->service?->currency ?? 'ILS',
        ];
    }

}
