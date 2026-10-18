# تسليم تدفق العميل والحجز لفريق Flutter

## ما تم اعتماده في هذه النسخة

- لا يوجد OTP أو استعادة كلمة مرور بالهاتف؛ الاستعادة بالبريد الإلكتروني فقط.
- بعد إرسال بيانات النشاط ومستندات التوثيق، ينتظر حساب مزود الخدمة اعتماد الإدارة قبل السماح له بنشر الخدمات.
- اشتراك مزود الخدمة الأول مجاني لمدة شهر واحد، والباقات المدفوعة مؤجلة.
- دفع الحجز خارجي ومباشر إلى مزود الخدمة. لا توجد بوابة دفع أو خصم عمولة داخل التطبيق.

## تدفق الحجز والدفع

1. يعرض العميل الخدمة ثم يطلب `GET /api/services/{id}/booking-options` لاختيار اليوم والفترة.
2. يرسل طلب الحجز: `POST /api/services/{id}/bookings`.
3. تظهر النتيجة في `GET /api/bookings` بحالة `provider_pending`، ويصل إشعار للمزود.
4. يقبل أو يرفض المزود الطلب من `POST /api/provider/bookings/{id}/decision`.
5. عند القبول، يعيد الـAPI العربون وتعليمات التحويل الخارجي في `payment_instructions`.
6. بعد التحويل، يرفع العميل الإثبات عبر `POST /api/bookings/{id}/payment-proofs` باستخدام `multipart/form-data`.
7. يراجع المزود الإثبات من `POST /api/provider/bookings/{bookingId}/payments/{paymentId}/review`.
8. عند التأكيد تصبح الحالة `confirmed` ويصل إشعار للعميل. بعد تنفيذ الخدمة يعلّمها المزود مكتملة عبر `POST /api/provider/bookings/{id}/complete`، وعندها فقط يستطيع العميل التقييم.

## حالات الحجز

| الحالة | المعنى |
|---|---|
| `provider_pending` | طلب بانتظار قبول أو رفض مزود الخدمة |
| `payment_awaiting` | وافق المزود، وينتظر تحويل العربون |
| `review_under_proof` | رُفع إثبات التحويل وينتظر قرار المزود |
| `confirmed` | تم تأكيد الإثبات والموعد |
| `completed` | الخدمة مكتملة ويُتاح التقييم |
| `rejected` / `cancelled` / `expired` | حالات نهائية غير قابلة للدفع |

## الإشعارات

- `GET /api/notifications`
- `POST /api/notifications/{notification}/read`
- `POST /api/notifications/read-all`

تُنشأ إشعارات عند إرسال الطلب، القبول أو الرفض، رفع إثبات التحويل، تأكيده أو رفضه، إلغاء الحجز، وإكمال الخدمة.

## إدارة الخدمة والباقات

في `POST /api/provider/services` أو تحديثها يمكن إرسال:

- `images[]`: حتى 10 صور للمعرض.
- `service_type`: `service` أو `package`.
- `package_items`: عناصر الباقة، وكل عنصر يحتوي `title` و`description` و`quantity`.
- `booking_slots`: الفترات المتاحة والسعر الخاص بكل فترة.
- `deposit_amount` أو `deposit_percentage`.
- `address`, `latitude`, `longitude`, `execution_duration`, `cancellation_policy`.

يجب على المزوّد حفظ تعليمات استلام التحويل الخارجي قبل قبول الحجوزات:

```http
PUT /api/provider/payment-instructions
Content-Type: application/json

{ "payment_instructions": "بنك فلسطين - اسم الحساب ... - رقم الحساب أو IBAN ..." }
```

لشاشات المزوّد كاملة (اللوحة، الإتاحة، النشر، الباقات، المراجعات، ومراجعة الإشعارات) راجع `PROVIDER_DASHBOARD_HANDOFF.md`.
