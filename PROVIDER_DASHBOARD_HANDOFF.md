# تسليم واجهات مزود الخدمة لفريق Flutter

هذا الدليل خاص بشاشات مزود الخدمة في المهمة الثالثة. كل المسارات أدناه محمية بـ `Authorization: Bearer <token>` وتتطلب حسابًا نوعه `provider` وبريدًا موثقًا.

## قرارات الإطلاق المعتمدة

- إرسال بيانات النشاط ومستندات التوثيق ينشئ طلبًا بانتظار مراجعة الإدارة. لا يمكن نشر الخدمات قبل اعتماد الطلب، ويُتاح للمزوّد متابعة حالة التوثيق من واجهة التحقق.
- أول اشتراك للمزوّد مجاني لمدة شهر، مهما كانت الباقة المختارة في الواجهة. الباقات المدفوعة وإثبات تحويل الاشتراك مؤجلة.
- لا يوجد OTP أو مسار استعادة كلمة مرور بالهاتف.
- لا توجد بوابة دفع داخلية أو أرباح منصة. العميل يحوّل مباشرة إلى المزوّد، ثم يرفع إشعار التحويل ليُراجعه المزوّد.

تُدار طلبات التوثيق من لوحة الويب `/admin`، ويمكن مراجعة تجهيزها وتشغيلها في `ADMIN_OPERATIONS.md`. مزود الخدمة يحتاج إلى تسجيل الدخول ببريد موثق وحساب نشط؛ حالة التوثيق المعتمدة مطلوبة لنشر الخدمة، وليست شرطًا لعرض حالة الطلب أو استكمال بياناته.

## لوحة الرئيسية

```http
GET /api/provider/dashboard
```

يعيد اسم النشاط، حالة الاشتراك وفترة التجربة، عدادات الطلبات الجديدة وإثباتات الدفع والحجوزات القادمة والخدمات المنشورة/المخفية والباقات، والتقييم العام وآخر خمسة طلبات. استخدم `counts.new_orders` و`counts.payment_reviews` لشارات التبويب والإشعارات.

## الخدمات والباقات

```http
GET    /api/provider/services
GET    /api/provider/services?type=service
GET    /api/provider/services?type=package
POST   /api/provider/services
POST   /api/provider/services/{id}
PATCH  /api/provider/services/{id}/visibility
DELETE /api/provider/services/{id}
```

`POST` و`POST /{id}` يقبلان `multipart/form-data`:

- `title`, `category_id`, `description`, `price`, `currency`.
- `service_type`: `service` أو `package`.
- `images[]` حتى 10 صور، و`remove_image_ids` عند التعديل.
- `features` مصفوفة JSON لمزايا الخدمة.
- `package_items` مصفوفة JSON للباقة: `title`, `description`, `quantity`, `sort_order`.
- `booking_slots` مصفوفة JSON: `label`, `start_time`, `end_time`, `price`.
- `deposit_amount` أو `deposit_percentage`, و`execution_duration`.

مثال إخفاء أو إعادة نشر خدمة:

```json
PATCH /api/provider/services/12/visibility
{ "is_available": false }
```

الخدمة المخفية لا تظهر للعميل، لكن تبقى قابلة للتعديل ولا تضيع صورها أو حجوزاتها السابقة.

## تقويم المواعيد

```http
GET /api/provider/services/{id}/availability?month=2026-09
PUT /api/provider/services/{id}/availability
```

`GET` يعرض الفترات اليومية، الأيام المحجوزة، والتواريخ التي أغلقها المزوّد. `PUT` يقبل:

```json
{
  "block_dates": [{ "date": "2026-09-21", "reason": "التزام سابق" }],
  "unblock_dates": ["2026-09-22"]
}
```

التاريخ المحجوب لا يمكن للعميل حجزه، ويظهر في `GET /api/services/{id}/booking-options` بقيمتي `is_blocked_by_provider` و`blocked_reason`.

## الطلبات ومراجعة التحويل

```http
GET  /api/provider/bookings?status=provider_pending
GET  /api/provider/bookings/{id}
POST /api/provider/bookings/{id}/decision
POST /api/provider/bookings/{bookingId}/payments/{paymentId}/review
POST /api/provider/bookings/{id}/complete
GET  /api/provider/bookings/{bookingId}/payments/{paymentId}/proof
```

لقبول الطلب، احفظ تعليمات التحويل أولًا ثم أرسل القرار والعربون:

```json
POST /api/provider/bookings/45/decision
{ "decision": "accepted", "deposit_amount": 500, "provider_note": "يرجى التحويل خلال 24 ساعة" }
```

مراجعة الإثبات تستخدم `decision` بقيمة `confirmed` أو `rejected` و`review_note` اختياريًا. عند التأكيد تصبح حالة الحجز `confirmed` ويصل إشعار للعميل.

## تعليمات التحويل والإشعارات والتقييمات

```http
PUT /api/provider/payment-instructions
GET /api/notifications
POST /api/notifications/{id}/read
POST /api/notifications/read-all
GET /api/provider/reviews
```

قيمة `payment_instructions` نص واضح للعميل، مثل: اسم بنك فلسطين أو المحفظة، اسم المستفيد، رقم الحساب/المحفظة، وIBAN عند توفره. لا ترسل بيانات حساسة غير لازمة.

## حالات الحجز للواجهة

| الحالة | الإجراء في واجهة المزوّد |
|---|---|
| `provider_pending` | قبول أو رفض الطلب |
| `payment_awaiting` | انتظار إثبات العميل |
| `review_under_proof` | عرض الإشعار وقبوله أو رفضه |
| `confirmed` | عرض الحجز القادم ثم تعليمه مكتملًا بعد الموعد |
| `completed` | عرض منجز والتقييم متاح للعميل |
| `rejected`, `cancelled`, `expired` | سجل فقط، دون إجراء دفع |
