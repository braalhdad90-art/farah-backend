<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Review;
use App\Models\ProviderProfile;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use App\Services\NotificationService;
use App\Notifications\ProviderRejectedNotification;

class AdminController extends Controller
{
    public function dashboard()
    {
        $providerPending = ProviderProfile::where('status', 'pending')->count();
        $role = Auth::user()->getRoleNames()->first() ?? 'system.admin';
        $stats = ['active_users' => User::where('status', 'active')->count()];
        if (in_array($role, ['ops.moderator', 'system.admin'], true)) {
            $stats += [
                'providers_pending' => $providerPending,
                'approved_providers' => ProviderProfile::where('status', 'approved')->count(),
                'providers' => User::where('user_type', 'provider')->count(),
                'bookings' => Booking::count(),
            ];
        }
        if (in_array($role, ['finance.reviewer', 'system.admin'], true)) {
            $stats['subscription_proofs_pending'] = SubscriptionPayment::where('status', 'submitted')->count();
            $stats['subscriptions_expiring'] = ProviderSubscription::whereIn('status', ['trialing', 'active'])
                ->whereBetween('current_period_end', [now(), now()->addDays(7)])
                ->count();
        }

        // فرح: توزيع الحجوزات حسب الحالة (ADM-57).
        $bookingsByStatus = in_array($role, ['ops.moderator', 'system.admin'], true)
            ? Booking::query()->selectRaw('workflow_status, count(*) as total')->groupBy('workflow_status')->pluck('total', 'workflow_status')
            : collect();

        return view('admin.dashboard', [
            'providerPending' => $providerPending,
            'role' => $role,
            'stats' => $stats,
            'bookingsByStatus' => $bookingsByStatus,
        ]);
    }

    public function providers(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $providers = ProviderProfile::with('user')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.providers', ['providers' => $providers, 'filters' => $filters, 'categories' => Category::orderBy('name')->get(['id', 'name'])]);
    }

    public function providersPending(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'overdue' => ['nullable', 'boolean'],
        ]);
        $providers = ProviderProfile::with('user')
            ->where('status', 'pending')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['overdue'] ?? false), fn ($query) => $query->where('submitted_at', '<=', now()->subHours(AppSetting::hours('provider_review_sla_hours'))))
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.providers-pending', ['providers' => $providers, 'filters' => $filters, 'slaHours' => AppSetting::hours('provider_review_sla_hours')]);
    }

    public function providerDetail(ProviderProfile $provider)
    {
        $provider->load(['user', 'category', 'city']);

        return view('admin.provider-detail', ['provider' => $provider]);
    }

    public function providerDocument(ProviderProfile $provider, string $document)
    {
        abort_unless(in_array($document, ['identity_document', 'commercial_register'], true), 404);

        $attribute = $document === 'identity_document' ? 'identity_document_path' : 'commercial_register_path';
        $path = $provider->{$attribute};

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'المستند غير موجود.');

        return Storage::disk('local')->response(
            $path,
            Str::slug($document).'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }

    public function providerApprove(ProviderProfile $provider, NotificationService $notifications)
    {
        $this->authorizeAdminAction('approve-provider');

        DB::transaction(function () use ($provider, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'pending', 409, 'لا يمكن اعتماد إلا الطلبات قيد المراجعة.');
            $user = User::whereKey($provider->user_id)->lockForUpdate()->first();
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update([
                'status' => 'approved',
                'registration_status' => 'approved',
                'decision_reason' => null,
                'decided_at' => now(),
            ]);
            $user?->update(['status' => 'active']);

            $trialPlan = SubscriptionPlan::where('slug', 'free-trial')->where('is_active', true)->firstOrFail();
            $trialCreated = false;
            if ($user && ! ProviderSubscription::where('provider_id', $provider->user_id)->exists()) {
                $trialEndsAt = now()->addMonthNoOverflow();
                ProviderSubscription::create([
                    'provider_id' => $provider->user_id,
                    'subscription_plan_id' => $trialPlan->id,
                    'status' => 'trialing',
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => $trialEndsAt,
                ]);
                $trialCreated = true;
            }

            $after = $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $after['free_trial_activated'] = $trialCreated;
            $this->writeAudit('provider_approved', $provider, $before, $after);
            if ($user) {
                $notifications->send(
                    $user,
                    'provider_approved',
                    'تم اعتماد حساب مزود الخدمة',
                    $trialCreated ? 'تم اعتماد حسابك وتفعيل الشهر المجاني.' : 'تم اعتماد حسابك. يمكنك متابعة إعداد اشتراكك.',
                    'provider_profile',
                    $provider->id
                );
            }
        });

        return redirect()->route('admin.providers.pending')->with('success', 'تم اعتماد مزود الخدمة بنجاح.');
    }

    public function providerReject(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('reject-provider');
        $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $rejectedUser = null;

        DB::transaction(function () use ($provider, $request, $notifications, &$rejectedUser): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'pending', 409, 'لا يمكن رفض إلا الطلبات قيد المراجعة.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $reason = $request->string('reason')->trim()->toString();
            $provider->update([
                'status' => 'rejected',
                'registration_status' => 'rejected',
                'decision_reason' => $reason,
                'decided_at' => now(),
            ]);
            $this->writeAudit('provider_rejected', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $rejectedUser = $provider->user;
                $notifications->send($provider->user, 'provider_rejected', 'يلزم تعديل بيانات التوثيق', $reason, 'provider_profile', $provider->id);
            }
        });

        if ($rejectedUser) {
            $rejectedUser->notify(new ProviderRejectedNotification($request->string('reason')->trim()->toString()));
        }

        return redirect()->route('admin.providers.pending')->with('success', 'تم رفض طلب مزود الخدمة.');
    }

    public function providerSuspend(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('suspend-provider');
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']])['reason'];
        abort_unless($provider->status === 'approved', 409, 'لا يمكن إيقاف إلا مزود معتمد.');
        DB::transaction(function () use ($provider, $reason, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'approved', 409, 'لا يمكن إيقاف إلا مزود معتمد.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update(['status' => 'suspended', 'registration_status' => 'suspended', 'decision_reason' => $reason, 'decided_at' => now()]);
            $this->writeAudit('provider_suspended', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $notifications->send($provider->user, 'provider_suspended', 'تم إيقاف حساب مزود الخدمة', $reason, 'provider_profile', $provider->id);
            }
        });

        return back()->with('success', 'تم إيقاف مزود الخدمة.');
    }

    public function providerReactivate(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('reactivate-provider');
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']])['reason'];
        abort_unless($provider->status === 'suspended', 409, 'لا يمكن إعادة تفعيل إلا مزود موقوف.');
        DB::transaction(function () use ($provider, $reason, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'suspended', 409, 'لا يمكن إعادة تفعيل إلا مزود موقوف.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update(['status' => 'approved', 'registration_status' => 'approved', 'decision_reason' => $reason, 'decided_at' => now()]);
            $this->writeAudit('provider_reactivated', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $notifications->send($provider->user, 'provider_reactivated', 'تمت إعادة تفعيل حساب مزود الخدمة', $reason, 'provider_profile', $provider->id);
            }
        });

        return back()->with('success', 'تمت إعادة تفعيل مزود الخدمة.');
    }

    public function auditLog(Request $request)
    {
        $this->authorizeAdminAction('view-audit-log');

        $filters = $request->validate([
            'actor' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AdminAuditLog::with(['actor', 'targetUser'])
            ->when($filters['actor'] ?? null, fn ($query, string $actor) => $query->whereHas(
                'actor',
                fn ($users) => $users->where('name', 'like', "%{$actor}%")->orWhere('email', 'like', "%{$actor}%")
            ))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit-log', ['logs' => $logs, 'filters' => $filters]);
    }

    public function reviews(Request $request)
    {
        $filters = $request->validate([
            'visibility' => ['nullable', 'in:visible,hidden'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $reviews = Review::with(['user:id,name,email', 'service:id,title'])
            ->when(isset($filters['visibility']), fn ($query) => $query->where('is_visible', $filters['visibility'] === 'visible'))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn ($services) => $services->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews', compact('reviews', 'filters'));
    }

    public function reviewAction(Request $request, Review $review)
    {
        $this->authorizeAdminAction('moderate-review');
        $data = $request->validate([
            'action' => ['required', 'in:hide,restore'],
            'reason' => ['nullable', 'string', 'min:5', 'max:2000', 'required_if:action,hide'],
        ]);

        DB::transaction(function () use ($review, $data, $request): void {
            $locked = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['is_visible', 'moderated_by', 'moderated_at', 'moderation_reason']);
            $visible = $data['action'] === 'restore';
            $locked->update([
                'is_visible' => $visible,
                'moderated_by' => Auth::id(),
                'moderated_at' => now(),
                'moderation_reason' => $visible ? null : trim($data['reason']),
            ]);
            $this->writeRecordAudit(
                $visible ? 'review_restored' : 'review_hidden',
                'review',
                $locked->id,
                $locked->user_id,
                $before,
                $locked->fresh()->only(['is_visible', 'moderated_by', 'moderated_at', 'moderation_reason']),
                $request
            );
            $this->refreshServiceRating($locked->service_id);
        });

        return back()->with('success', 'تم تحديث حالة ظهور التقييم.');
    }

    public function moderatedServices(Request $request)
    {
        $filters = $request->validate([
            'visibility' => ['nullable', 'in:visible,hidden'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $services = Service::with(['provider:id,name,email', 'category:id,name'])
            ->when(isset($filters['visibility']), fn ($query) => $query->where('admin_hidden', $filters['visibility'] === 'hidden'))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('provider', fn ($providers) => $providers->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.moderated-services', compact('services', 'filters'));
    }

    public function moderateService(Request $request, Service $service)
    {
        $this->authorizeAdminAction('moderate-review');
        $data = $request->validate([
            'action' => ['required', 'in:hide,restore'],
            'reason' => ['nullable', 'string', 'min:5', 'max:2000', 'required_if:action,hide'],
        ]);

        DB::transaction(function () use ($request, $service, $data): void {
            $locked = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 409, 'لا يمكن مراجعة إلا الخدمات النشطة.');
            $before = ['admin_hidden' => (bool) $locked->admin_hidden];
            $locked->admin_hidden = $data['action'] === 'hide';
            $locked->save();
            $this->writeRecordAudit(
                $data['action'] === 'hide' ? 'service_hidden' : 'service_restored',
                'service',
                $locked->id,
                $locked->provider_id,
                $before,
                ['admin_hidden' => (bool) $locked->fresh()->admin_hidden, 'reason' => $data['action'] === 'hide' ? trim($data['reason']) : null],
                $request
            );
        });

        return back()->with('success', 'تم تحديث حالة ظهور الخدمة.');
    }

    public function team()
    {
        $admins = User::role(['ops.moderator', 'finance.reviewer', 'system.admin'])
            ->where('user_type', 'admin')
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.team', ['admins' => $admins]);
    }

    // فرح: إنشاء حساب لعضو في فريق الإدارة بدور واحد فقط (ADM-44).
    public function createAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:ops.moderator,finance.reviewer,system.admin'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $admin = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'user_type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $admin->assignRole($data['role']);
            $this->writeRecordAudit('admin_account_created', 'user', $admin->id, $admin->id, null, [
                'email' => $admin->email,
                'roles' => [$data['role']],
            ], $request);
        });

        return back()->with('success', 'تم إنشاء حساب الإدارة.');
    }

    public function updateAdminRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:ops.moderator,finance.reviewer,system.admin'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        abort_unless($user->user_type === 'admin' && $user->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin']), 404);
        abort_if($user->is(Auth::user()), 422, 'You cannot change your own role or status.');

        DB::transaction(function () use ($request, $user, $data): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_type === 'admin' && $locked->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin']), 404);
            $before = ['roles' => $locked->getRoleNames()->values()->all(), 'status' => $locked->status];
            if ($locked->hasRole('system.admin') && ($data['role'] !== 'system.admin' || $data['status'] !== 'active')) {
                abort_if(
                    User::role('system.admin')->where('status', 'active')->where('id', '!=', $locked->id)->doesntExist(),
                    409,
                    'At least one active system administrator must remain.'
                );
            }
            $locked->syncRoles([$data['role']]);
            $locked->update(['status' => $data['status'], 'user_type' => 'admin']);
            $after = ['roles' => $locked->fresh()->getRoleNames()->values()->all(), 'status' => $locked->fresh()->status];
            $this->writeRecordAudit('admin_account_updated', 'user', $locked->id, $locked->id, $before, $after, $request);
        });

        return back()->with('success', 'تم تحديث صلاحيات المسؤول وحالة حسابه.');
    }

    public function categories()
    {
        return view('admin.categories', ['categories' => Category::withCount('services')->orderBy('name')->paginate(30)]);
    }

    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:140', 'unique:categories,slug'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $category = DB::transaction(function () use ($request, $data): Category {
            $category = Category::create($data);
            $this->writeRecordAudit('category_created', 'category', $category->id, null, null, $category->only(['name', 'slug', 'status']), $request);

            return $category;
        });

        return back()->with('success', 'تمت إضافة التصنيف: '.$category->name);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:140', 'unique:categories,slug,'.$category->id],
            'status' => ['required', 'in:active,inactive'],
        ]);

        DB::transaction(function () use ($request, $category, $data): void {
            $locked = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['name', 'slug', 'status']);
            $locked->update($data);
            $this->writeRecordAudit('category_updated', 'category', $locked->id, null, $before, $locked->fresh()->only(['name', 'slug', 'status']), $request);
        });

        return back()->with('success', 'تم تحديث التصنيف.');
    }

    public function subscriptionPlans()
    {
        return view('admin.plans', ['plans' => SubscriptionPlan::withCount('providerSubscriptions')->orderBy('price')->get()]);
    }

    public function updateSubscriptionPlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['required', 'alpha', 'size:3'],
            'interval' => ['nullable', 'in:month,year'],
            'interval_count' => ['required', 'integer', 'min:1', 'max:24'],
            'is_active' => ['required', 'boolean'],
            'included_features' => ['nullable', 'string', 'max:2000'],
            'excluded_features' => ['nullable', 'string', 'max:2000'],
        ]);

        // فرح: مميزات الباقة تُكتب سطرًا لكل ميزة، وتظهر تحت الباقة في شاشة الاشتراك بالتطبيق.
        $lines = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text))));
        $data['features'] = array_merge(
            array_map(fn ($name) => ['name' => $name, 'included' => true], $lines($data['included_features'] ?? null)),
            array_map(fn ($name) => ['name' => $name, 'included' => false], $lines($data['excluded_features'] ?? null)),
        );
        unset($data['included_features'], $data['excluded_features']);

        DB::transaction(function () use ($request, $plan, $data): void {
            $locked = SubscriptionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['name', 'description', 'features', 'price', 'currency', 'interval', 'interval_count', 'is_active']);
            $locked->update($data);
            $this->writeRecordAudit('subscription_plan_updated', 'subscription_plan', $locked->id, null, $before, $locked->fresh()->only(array_keys($before)), $request);
        });

        return back()->with('success', 'تم تحديث باقة الاشتراك.');
    }

    public function settings()
    {
        $settings = AppSetting::query()->pluck('value', 'key');

        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_iban' => ['nullable', 'string', 'max:60'],
            'provider_review_sla_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'payment_due_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'working_hours' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $before = AppSetting::whereIn('key', array_keys($data))->pluck('value', 'key')->all();
            foreach ($data as $key => $value) {
                AppSetting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
            }
            $this->writeRecordAudit('system_settings_updated', 'app_settings', null, null, $before, $data, $request);
        });

        return back()->with('success', 'تم حفظ إعدادات المنصة.');
    }

    public function exportBookingsCsv()
    {
        $this->writeExportAudit('bookings');

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['reference', 'customer', 'provider', 'service', 'date', 'status', 'amount', 'created_at']);
            DB::table('bookings')
                ->leftJoin('users as customers', 'customers.id', '=', 'bookings.user_id')
                ->leftJoin('users as providers', 'providers.id', '=', 'bookings.provider_id')
                ->leftJoin('services', 'services.id', '=', 'bookings.service_id')
                ->select(['bookings.reference', 'customers.name as customer', 'providers.name as provider', 'services.title as service', 'bookings.booking_date', 'bookings.workflow_status', 'bookings.total_price', 'bookings.created_at'])
                ->orderBy('bookings.id')
                ->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, array_map([$this, 'csvSafeValue'], (array) $row));
                    }
                });
            fclose($output);
        }, 'farah-bookings-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPaymentsCsv()
    {
        $this->writeExportAudit('payments');

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['payment_reference', 'booking_reference', 'customer', 'provider', 'amount', 'method', 'status', 'submitted_at', 'reviewed_at']);
            DB::table('booking_payments')
                ->leftJoin('bookings', 'bookings.id', '=', 'booking_payments.booking_id')
                ->leftJoin('users as customers', 'customers.id', '=', 'booking_payments.user_id')
                ->leftJoin('users as providers', 'providers.id', '=', 'booking_payments.provider_id')
                ->select(['booking_payments.reference', 'bookings.reference as booking_reference', 'customers.name as customer', 'providers.name as provider', 'booking_payments.amount', 'booking_payments.method', 'booking_payments.status', 'booking_payments.created_at', 'booking_payments.reviewed_at'])
                ->orderBy('booking_payments.id')
                ->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, array_map([$this, 'csvSafeValue'], (array) $row));
                    }
                });
            fclose($output);
        }, 'farah-payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials + ['status' => 'active', 'user_type' => 'admin'], $request->boolean('remember'))) {
            if (! Auth::user()->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin'])) {
                Auth::logout();

                return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->onlyInput('email');
            }
            $request->session()->regenerate();
            $request->session()->put('admin_last_activity', now()->timestamp);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function authorizeAdminAction(string $permission): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin'])) {
            abort(403, 'You are not allowed to perform this action.');
        }

        if ($permission === 'approve-provider' && $user->hasRole('finance.reviewer')) {
            abort(403, 'Finance reviewers cannot approve providers.');
        }

        if ($permission === 'reject-provider' && $user->hasRole('finance.reviewer')) {
            abort(403, 'Finance reviewers cannot reject providers.');
        }

        if ($permission === 'view-audit-log' && ! $user->hasRole('system.admin')) {
            abort(403, 'Only the system administrator can view the audit log.');
        }

        if (in_array($permission, ['suspend-provider', 'reactivate-provider'], true) && ! $user->hasAnyRole(['ops.moderator', 'system.admin'])) {
            abort(403, 'Only ops moderators and system administrators can manage provider suspension.');
        }

        if (in_array($permission, ['moderate-review'], true) && ! $user->hasAnyRole(['ops.moderator', 'system.admin'])) {
            abort(403, 'Only ops moderators and system administrators can moderate reviews.');
        }
    }

    private function writeAudit(string $action, ProviderProfile $provider, array $before, array $after): void
    {
        AdminAuditLog::create([
            'actor_id' => Auth::id(),
            'user_id' => $provider->user_id,
            'actor_role' => Auth::user()?->getRoleNames()->first(),
            'action' => $action,
            'resource_type' => 'provider_profile',
            'resource_id' => $provider->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
        ]);
    }

    private function writeRecordAudit(
        string $action,
        string $resourceType,
        ?int $resourceId,
        ?int $targetUserId,
        ?array $before,
        ?array $after,
        Request $request
    ): void {
        AdminAuditLog::create([
            'actor_id' => Auth::id(),
            'user_id' => $targetUserId,
            'actor_role' => Auth::user()?->getRoleNames()->first(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);
    }

    private function refreshServiceRating(int $serviceId): void
    {
        $service = Service::findOrFail($serviceId);
        $service->update([
            'rating_avg' => $service->reviews()->avg('rating') ?? 0,
            'reviews_count' => $service->reviews()->count(),
        ]);
    }

    // فرح: تقرير الاشتراكات (ADM-55).
    public function exportSubscriptionsCsv()
    {
        $this->writeExportAudit('subscriptions');

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['provider', 'email', 'plan', 'status', 'period_start', 'period_end', 'trial_ends_at', 'created_at']);
            ProviderSubscription::with(['provider:id,name,email', 'plan:id,name'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, array_map([$this, 'csvSafeValue'], [
                            $row->provider?->name,
                            $row->provider?->email,
                            $row->plan?->name,
                            $row->status,
                            $row->current_period_start?->toDateTimeString(),
                            $row->current_period_end?->toDateTimeString(),
                            $row->trial_ends_at?->toDateTimeString(),
                            $row->created_at?->toDateTimeString(),
                        ]));
                    }
                });
            fclose($output);
        }, 'farah-subscriptions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvSafeValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if (is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }

    private function writeExportAudit(string $report): void
    {
        AdminAuditLog::create([
            'actor_id' => Auth::id(),
            'actor_role' => Auth::user()?->getRoleNames()->first(),
            'action' => 'report_exported',
            'resource_type' => 'csv_report',
            'before' => null,
            'after' => ['report' => $report],
            'ip_address' => request()->ip(),
        ]);
    }
}
