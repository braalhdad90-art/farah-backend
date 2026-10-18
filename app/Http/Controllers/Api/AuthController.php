<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\DeleteAccountRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\GoogleLoginRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResendVerificationEmailRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateEmailRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Google\Client as GoogleClient;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Auth", description: "إدارة الحسابات والمصادقة والملف الشخصي")]
class AuthController extends Controller
{
    use ApiResponse;

    #[OA\Post(path: "/api/login", summary: "تسجيل الدخول", tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["email", "password"], properties: [new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"), new OA\Property(property: "password", type: "string", format: "password", example: "password123")])), responses: [new OA\Response(response: 200, description: "تم تسجيل الدخول بنجاح"), new OA\Response(response: 401, description: "بيانات الدخول غير صحيحة"), new OA\Response(response: 403, description: "الحساب غير نشط أو غير مفعّل"), new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")])]


    public function apilogin(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->failure('البريد الإلكتروني أو كلمة المرور غير صحيحة.', 401);
        }
        if ($user->status !== 'active') {
            return $this->failure('هذا الحساب غير نشط. يرجى التواصل مع الإدارة.', 403);
        }
        if (! $user->hasVerifiedEmail()) {
            return $this->failure('يرجى تفعيل بريدك الإلكتروني أولًا قبل تسجيل الدخول.', 403);
        }

        return $this->success('تم تسجيل الدخول بنجاح.', $this->tokenData($user));
    }




    #[OA\Post(
        path: "/api/register",
        summary: "تسجيل حساب جديد",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "محمد ياسر"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "user_type", type: "string", enum: ["customer", "provider"], example: "customer"),
                    new OA\Property(property: "phone", type: "string", example: "0599000000"),
                    new OA\Property(property: "city_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "تم إنشاء الحساب وتفعيله بنجاح",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "success", type: "boolean", example: true),
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "object", properties: [
                        new OA\Property(property: "token", type: "string"),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                        new OA\Property(property: "user", type: "object"),
                    ])
                ])
            ),
            new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'user_type' => $request->input('user_type', 'customer'),
            'phone' => $request->input('phone'),
            'city_id' => $request->input('city_id'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return $this->success('تم إنشاء الحساب وتفعيله بنجاح.', $this->tokenData($user), 201);
    }

    #[OA\Post(path: "/api/auth/google", summary: "تسجيل الدخول عبر Google", tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["id_token"], properties: [new OA\Property(property: "id_token", type: "string", example: "google-id-token"), new OA\Property(property: "user_type", type: "string", enum: ["customer", "provider"], example: "customer")])), responses: [new OA\Response(response: 200, description: "تم تسجيل الدخول عبر Google بنجاح"), new OA\Response(response: 401, description: "رمز Google غير صالح"), new OA\Response(response: 403, description: "الحساب غير نشط"), new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")])]
    public function googleLogin(GoogleLoginRequest $request): JsonResponse
    {
        $payload = $this->verifyGoogleToken($request->string('id_token')->toString());

        if ($payload === null || empty($payload['sub']) || empty($payload['email']) || empty($payload['email_verified'])) {
            return $this->failure('تعذر التحقق من حساب Google. حاول تسجيل الدخول مرة أخرى.', 401);
        }

        $email = strtolower($payload['email']);
        $user = User::where('google_id', $payload['sub'])->first() ?? User::where('email', $email)->first();

        if ($user) {
            if ($user->status !== 'active') {
                return $this->failure('هذا الحساب غير نشط. يرجى التواصل مع الإدارة.', 403);
            }
            $user->forceFill([
                'google_id' => $payload['sub'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $payload['name'] ?? $payload['given_name'] ?? 'مستخدم Google',
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'user_type' => $request->input('user_type', 'customer'),
                'status' => 'active',
                'google_id' => $payload['sub'],
                'email_verified_at' => now(),
            ]);
        }

        return $this->success('تم تسجيل الدخول عبر Google بنجاح.', $this->tokenData($user));
    }

    #[OA\Get(path: "/api/email/verify/{id}/{hash}", summary: "تفعيل البريد الإلكتروني", tags: ["Auth"], parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")), new OA\Parameter(name: "hash", in: "path", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "تم تفعيل البريد بنجاح"), new OA\Response(response: 403, description: "رابط التفعيل غير صالح")])]
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->failure('رابط تفعيل البريد غير صالح.', 403);
        }
        if ($user->hasVerifiedEmail()) {
            return $this->success('البريد الإلكتروني مفعّل مسبقًا.');
        }
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->success('تم تفعيل البريد الإلكتروني بنجاح. يمكنك الآن تسجيل الدخول.');
    }

    #[OA\Post(path: "/api/resend-verification-email", summary: "إعادة إرسال بريد التفعيل", tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["email"], properties: [new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com")])), responses: [new OA\Response(response: 200, description: "تم إرسال رابط التفعيل"), new OA\Response(response: 409, description: "البريد مفعّل مسبقًا"), new OA\Response(response: 422, description: "البريد غير مسجل")])]
    public function resendVerificationEmail(ResendVerificationEmailRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->firstOrFail();
        if ($user->hasVerifiedEmail()) {
            return $this->failure('البريد الإلكتروني مفعّل مسبقًا.', 409);
        }
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('تعذر إعادة إرسال رسالة تفعيل البريد: '.$exception->getMessage());
            return $this->failure('تعذر إرسال رسالة التفعيل حاليًا. حاول لاحقًا.', 503);
        }

        return $this->success('تم إرسال رابط تفعيل جديد إلى بريدك الإلكتروني.');
    }

    #[OA\Post(path: "/api/logout", summary: "تسجيل الخروج", security: [["bearerAuth" => []]], tags: ["Auth"], responses: [new OA\Response(response: 200, description: "تم تسجيل الخروج بنجاح"), new OA\Response(response: 401, description: "غير مصرح")])]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if (! $token) {
            return $this->failure('تعذر تسجيل الخروج لأن جلسة الدخول غير صالحة.', 401);
        }
        $token->delete();

        return $this->success('تم تسجيل الخروج بنجاح.');
    }

    #[OA\Get(path: "/api/profile", summary: "عرض الملف الشخصي", security: [["bearerAuth" => []]], tags: ["Auth"], responses: [new OA\Response(response: 200, description: "تم جلب الملف الشخصي بنجاح"), new OA\Response(response: 401, description: "غير مصرح")])]
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['city', 'locations.city']);
        return $this->success('تم جلب الملف الشخصي بنجاح.', $this->userData($user, true));
    }

    #[OA\Post(
        path: "/api/profile/update",
        summary: "تحديث الملف الشخصي",
        security: [["bearerAuth" => []]],
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "name", type: "string", example: "محمد ياسر"),
                        new OA\Property(property: "phone", type: "string", example: "0599000000"),
                        new OA\Property(property: "city_id", type: "integer", example: 1),
                        new OA\Property(property: "avatar", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "تم تحديث الملف الشخصي بنجاح"),
            new OA\Response(response: 401, description: "غير مصرح"),
            new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $user->update($data);
        $user->load(['city', 'locations.city']);

        return $this->success('تم تحديث الملف الشخصي بنجاح.', $this->userData($user, true));
    }

    #[OA\Post(path: "/api/email/change-request", summary: "طلب تغيير البريد الإلكتروني", security: [["bearerAuth" => []]], tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["email"], properties: [new OA\Property(property: "email", type: "string", format: "email", example: "new@example.com"), new OA\Property(property: "current_password", type: "string", format: "password", example: "password123"), new OA\Property(property: "google_id_token", type: "string", example: "google-id-token")])), responses: [new OA\Response(response: 200, description: "تم إرسال رابط تأكيد البريد الجديد"), new OA\Response(response: 403, description: "تعذر التحقق من الهوية"), new OA\Response(response: 409, description: "البريد مستخدم بالفعل"), new OA\Response(response: 503, description: "تعذر إرسال رسالة التأكيد"), new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")])]
    public function requestEmailChange(UpdateEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $newEmail = $request->string('email')->toString();
        if ($newEmail === $user->email) {
            return $this->failure('البريد الإلكتروني الجديد مطابق للبريد الحالي.');
        }
        if (! $this->hasRecentAuthentication($user, $request)) {
            return $this->failure('تعذر التحقق من هويتك. أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.', 403);
        }

        $user->forceFill(['pending_email' => $newEmail])->save();
        $url = URL::temporarySignedRoute('email.change.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($newEmail),
        ]);
        try {
            Notification::route('mail', $newEmail)->notify(new VerifyEmailChange($url, $user->name));
        } catch (\Throwable $exception) {
            $user->forceFill(['pending_email' => null])->save();
            Log::warning('تعذر إرسال رسالة تغيير البريد: '.$exception->getMessage());
            return $this->failure('تعذر إرسال رسالة التأكيد إلى البريد الجديد. حاول لاحقًا.', 503);
        }

        return $this->success('أرسلنا رابط تأكيد إلى البريد الإلكتروني الجديد. لن يتغير البريد قبل فتح الرابط.');
    }

    #[OA\Get(path: "/api/email/change/verify/{id}/{hash}", summary: "تأكيد تغيير البريد الإلكتروني", tags: ["Auth"], parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")), new OA\Parameter(name: "hash", in: "path", required: true, schema: new OA\Schema(type: "string"))], responses: [new OA\Response(response: 200, description: "تم تغيير البريد الإلكتروني وتفعيله"), new OA\Response(response: 403, description: "رابط التأكيد غير صالح أو منتهي"), new OA\Response(response: 409, description: "البريد الجديد مستخدم بالفعل")])]
    public function verifyEmailChange(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        $pendingEmail = $user->pending_email;
        if (! $pendingEmail || ! hash_equals($hash, sha1($pendingEmail))) {
            return $this->failure('رابط تأكيد البريد الجديد غير صالح أو منتهي.', 403);
        }
        if (User::where('email', $pendingEmail)->where('id', '!=', $user->id)->exists()) {
            return $this->failure('تعذر تغيير البريد لأن البريد الجديد أصبح مستخدمًا.', 409);
        }

        DB::transaction(function () use ($user, $pendingEmail): void {
            $user->forceFill([
                'email' => $pendingEmail,
                'pending_email' => null,
                'email_verified_at' => now(),
            ])->save();
            $user->tokens()->delete();
        });

        return $this->success('تم تغيير البريد الإلكتروني وتفعيله بنجاح. سجّل الدخول من جديد لحماية حسابك.');
    }

    #[OA\Delete(path: "/api/account", summary: "حذف الحساب", security: [["bearerAuth" => []]], tags: ["Auth"], requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: "current_password", type: "string", format: "password", example: "password123"), new OA\Property(property: "google_id_token", type: "string", example: "google-id-token")])), responses: [new OA\Response(response: 200, description: "تم حذف الحساب بنجاح"), new OA\Response(response: 401, description: "غير مصرح"), new OA\Response(response: 403, description: "تعذر التحقق من الهوية"), new OA\Response(response: 409, description: "يوجد حجوزات قائمة"), new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")])]
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->hasRecentAuthentication($user, $request)) {
            return $this->failure('تعذر التحقق من هويتك. أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.', 403);
        }
        $hasActiveBookings = Booking::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('provider_id', $user->id))
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();
        if ($hasActiveBookings) {
            return $this->failure('لا يمكن حذف الحساب لوجود حجوزات قائمة. ألغِ الحجوزات أو أتممها أولًا.', 409);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'حساب محذوف',
                'email' => 'deleted-'.$user->id.'-'.Str::uuid().'@deleted.farah.local',
                'phone' => null,
                'avatar' => null,
                'bio' => null,
                'cover_image' => null,
                'pending_email' => null,
                'google_id' => null,
                'status' => 'inactive',
                'remember_token' => Str::random(60),
            ])->save();
            $user->delete();
        });

        return $this->success('تم حذف الحساب وإلغاء جميع جلسات الدخول بنجاح.');
    }

    #[OA\Post(path: "/api/change-password", summary: "تغيير كلمة المرور", security: [["bearerAuth" => []]], tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["current_password", "new_password", "new_password_confirmation"], properties: [new OA\Property(property: "current_password", type: "string", format: "password", example: "oldpassword123"), new OA\Property(property: "new_password", type: "string", format: "password", example: "newpassword123"), new OA\Property(property: "new_password_confirmation", type: "string", format: "password", example: "newpassword123")])), responses: [new OA\Response(response: 200, description: "تم تغيير كلمة المرور بنجاح"), new OA\Response(response: 401, description: "غير مصرح"), new OA\Response(response: 403, description: "كلمة المرور الحالية غير صحيحة"), new OA\Response(response: 422, description: "خطأ في البيانات المدخلة")])]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return $this->failure('كلمة المرور الحالية غير صحيحة.', 403);
        }
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->forceFill(['password' => Hash::make($request->string('new_password')->toString())])->save();
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return $this->success('تم تغيير كلمة المرور وإلغاء الجلسات الأخرى بنجاح.');
    }

    #[OA\Post(path: "/api/forgot-password", summary: "طلب إعادة ضبط كلمة المرور", tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["email"], properties: [new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com")])), responses: [new OA\Response(response: 200, description: "تم إرسال رابط إعادة الضبط"), new OA\Response(response: 400, description: "تعذر إرسال رابط إعادة الضبط"), new OA\Response(response: 422, description: "البريد غير مسجل"), new OA\Response(response: 503, description: "خدمة البريد غير متاحة")])]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            Log::error('فشل طلب استعادة كلمة المرور: '.$exception->getMessage());
            return $this->failure('تعذر إرسال رابط إعادة ضبط كلمة المرور حاليًا. حاول لاحقًا.', 503);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return $this->failure('تعذر إرسال رابط إعادة ضبط كلمة المرور.', 400);
        }

        return $this->success('تم إرسال رابط إعادة ضبط كلمة المرور إلى بريدك الإلكتروني.');
    }

    #[OA\Post(path: "/api/reset-password", summary: "إعادة ضبط كلمة المرور باستخدام التوكن", tags: ["Auth"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["token", "email", "password", "password_confirmation"], properties: [new OA\Property(property: "token", type: "string", example: "sampletoken123"), new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"), new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"), new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123")])), responses: [new OA\Response(response: 200, description: "تم إعادة ضبط كلمة المرور بنجاح"), new OA\Response(response: 422, description: "التوكن غير صالح أو منتهي")])]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            return $this->failure('رابط إعادة ضبط كلمة المرور غير صالح أو منتهي.', 422, [
                'token' => ['رابط إعادة ضبط كلمة المرور غير صالح أو منتهي.'],
            ]);
        }

        return $this->success('تم إعادة ضبط كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.');
    }

    private function tokenData(User $user): array
    {
        return [
            'token' => $user->createToken('api_token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->userData($user),
        ];
    }

    private function userData(User $user, bool $includePrimaryLocation = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone' => $user->phone,
            'role' => $user->user_type ?? 'customer',
            'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
            'city_id' => $user->city_id,
            'city' => $user->city ? ['id' => $user->city->id, 'name' => $user->city->name] : null,
        ];
        if ($includePrimaryLocation) {
            $primary = $user->locations->firstWhere('is_primary', true) ?? $user->locations->first();
            $data['primary_location'] = $primary ? [
                'id' => $primary->id,
                'label' => $primary->label,
                'address' => $primary->address,
                'latitude' => $primary->latitude,
                'longitude' => $primary->longitude,
                'city' => $primary->city ? ['id' => $primary->city->id, 'name' => $primary->city->name] : null,
            ] : null;
        }

        return $data;
    }

    private function hasRecentAuthentication(User $user, Request $request): bool
    {
        $password = $request->input('current_password');
        if (is_string($password) && $password !== '' && Hash::check($password, $user->password)) {
            return true;
        }
        $googleToken = $request->input('google_id_token');
        if (! $user->google_id || ! is_string($googleToken) || $googleToken === '') {
            return false;
        }
        $payload = $this->verifyGoogleToken($googleToken);

        return $payload !== null && isset($payload['sub']) && hash_equals($user->google_id, (string) $payload['sub']);
    }

    private function verifyGoogleToken(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');
        if (! is_string($clientId) || $clientId === '') {
            Log::error('GOOGLE_CLIENT_ID غير مضبوط.');
            return null;
        }
        try {
            $payload = (new GoogleClient(['client_id' => $clientId]))->verifyIdToken($idToken);
            return is_array($payload) ? $payload : null;
        } catch (\Throwable $exception) {
            Log::warning('فشل التحقق من Google ID token: '.$exception->getMessage());
            return null;
        }
    }
}
