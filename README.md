# Farah API Project

هذا الملف يشرح المشروع خطوة بخطوة بالكامل ويستخدم كمرجع لفهم بنية المشروع والخدمات التي يقدمها للواجهة Flutter.

## 1) مقدمة المشروع

هذا المشروع مبني على Laravel ويهدف إلى بناء API مناسب لتطبيق Flutter. تم تصميمه بناء على فكرة التطبيق التي ظهرت في التصميم وبخاصة في الأقسام التالية:
- الصفحة الرئيسية
- التصنيفات
- الخدمات والمنتجات
- تقييمات الخدمات
- المفضلة
- الحجوزات
- إعدادات الإشعارات

---

## 4) التحديثات التي تمت على المشروع

خلال تطوير المشروع تم ربط الـ API بالشاشات الموجودة في تصميم تطبيق Flutter، بحيث لا تكون البيانات مكتوبة داخل التطبيق بشكل ثابت، بل يتم جلبها من قاعدة البيانات.

### تدفق العميل والحجز والدفع

تمت إضافة دورة حجز كاملة: طلب العميل، قبول أو رفض مزود الخدمة، التحويل الخارجي المباشر إلى المزود، رفع إثبات التحويل، ثم تأكيده أو رفضه من المزود مع إشعارات داخل التطبيق للطرفين. تفاصيل التكامل ومسارات الـAPI موجودة في `CUSTOMER_BOOKING_HANDOFF.md`.

### لوحة مزود الخدمة وإدارة الخدمات

تمت إضافة API يدعم شاشة مزود الخدمة كاملة: لوحة الإحصاءات والطلبات الأخيرة، تجربة الاشتراك المجانية، إدارة الخدمات والباقات وصورها ومحتوياتها، نشر أو إخفاء الخدمة، تقويم الفترات والأيام المحجوبة، مراجعة إثباتات التحويل، والإشعارات والتقييمات. التوثيق التفصيلي لفريق Flutter موجود في `PROVIDER_DASHBOARD_HANDOFF.md`.

### لوحة الإدارة

تتوفر لوحة إدارة ويب على `/admin`، وهي متصلة بقاعدة بيانات المشروع وليست شاشة داخل تطبيق Flutter. تشمل مراجعة واعتماد مزودي الخدمة، إدارة ظهور الخدمات والتقييمات، مراجعة إثباتات الدفع وتتبع الاستردادات، التقارير، التصنيفات، باقات الاشتراك، إعدادات المنصة، حسابات الإدارة وسجل التدقيق. تتوزع صلاحياتها على مشرف العمليات والمراجع المالي ومدير النظام.

يُنشأ طلب توثيق المزوّد بحالة انتظار بعد إرسال بيانات النشاط؛ ولا يُسمح بنشر خدماته قبل اعتماد الإدارة. خطوات إعداد لوحة الإدارة وتشغيل migrations وإنشاء حساب الإدارة موضحة في `ADMIN_OPERATIONS.md`. تتطلب بيانات المرسل وإعداد SMTP إرسال رسائل الرفض فعليًا؛ راجع ذلك الدليل قبل النشر.

### شاشة تفاصيل الخدمة

تم تجهيز endpoint يعرض بيانات الخدمة كاملة، مثل:

- اسم الخدمة والوصف والصور.
- السعر والعملة.
- السعة والمساحة.
- مزايا الخدمة مثل المواقف والإضاءة ونظام الصوت.
- المدينة والعنوان.
- بيانات مزود الخدمة.
- التقييمات وعدد التقييمات.
- حالة الخدمة في المفضلة.

الطلب المستخدم:

```text
GET /api/services/{id}
```

### شاشة اختيار موعد الحجز

تمت إضافة نظام للفترات المتاحة للحجز. كل خدمة يمكن أن تحتوي على فترات مختلفة مثل الفترة الصباحية أو المسائية، ويتم إظهار الأيام المحجوزة والأيام المتاحة.

```text
GET /api/services/{id}/booking-options?month=2026-09
```

كما يتم منع حجز نفس الفترة إذا كانت محجوزة مسبقًا.

### شاشة تأكيد الحجز

تم إضافة معاينة للحجز قبل إنشائه، وتعرض:

- صورة واسم الخدمة.
- تاريخ ووقت الحجز.
- الفترة المختارة.
- السعر والإجمالي.
- حالة الدفع.

```text
POST /api/services/{id}/booking-preview
POST /api/services/{id}/bookings
GET /api/bookings/{id}/confirmation
```

لا توجد بوابة دفع داخلية في هذه النسخة. بعد قبول مزود الخدمة للطلب، يحوّل العميل العربون مباشرة إلى البنك أو المحفظة التي يحددها المزوّد، ثم يرفع إشعار التحويل ليقوم المزوّد بمراجعته وتأكيد الحجز داخل التطبيق.

تكامل بوابة دفع إلكترونية غير مفعّل بعد؛ يتطلب تحديد مزود الدفع المعتمد وبيانات التاجر وبيئة الاختبار قبل دمجه. لا تُعامل حالات إثبات التحويل الحالية على أنها تأكيد آلي من بنك أو بوابة.

### واجهات مزود الخدمة

تم تجهيز واجهات خاصة بمزود الخدمة حتى يستطيع إدخال بياناته وإدارة الخدمات التي يقدمها:

```text
GET    /api/provider/verification
GET    /api/provider/onboarding
POST   /api/provider/onboarding
GET    /api/provider/services
POST   /api/provider/services
POST   /api/provider/services/{id}
DELETE /api/provider/services/{id}
```

وتشمل بيانات المزود:

- اسم النشاط.
- تصنيف الخدمة.
- وصف النشاط.
- المدينة والعنوان والإحداثيات.
- صورة الغلاف.
- صورة الهوية.
- السجل التجاري.
- حالة التوثيق.

بعد إرسال بيانات النشاط ومستندات التوثيق، يبقى حساب المزوّد بانتظار مراجعة الإدارة. لا يستطيع نشر خدماته قبل الاعتماد، وتظهر حالة الطلب في واجهات التحقق والتسجيل. أول شهر اشتراك مجاني بعد الاعتماد، أما تحصيل رسوم الباقات المدفوعة وإثبات تحويل اشتراك المزوّد فمؤجلان.

---

## 5) تنظيم المشروع باستخدام Clean Architecture

استخدمت في المشروع تقسيمًا مبسطًا من Clean Architecture يناسب Laravel وحجم المشروع. الفكرة الأساسية هي ألا يكون كل الكود داخل Controller واحد، بل كل جزء يكون في المكان المناسب له.

### Controller

المسار:

```text
app/Http/Controllers/Api/ProviderController.php
```

الـ Controller يستقبل الطلب ويستدعي الخدمة المناسبة ثم يرجع response بصيغة JSON. حاولت أن أجعله خفيفًا ولا يحتوي على تفاصيل رفع الملفات أو عمليات قاعدة البيانات.

### Service Layer

المسار:

```text
app/Services/ProviderServiceManager.php
```

هذا الملف يحتوي على منطق مزود الخدمة، مثل:

- حفظ بيانات التسجيل.
- رفع واستبدال وحذف الملفات.
- إنشاء وتعديل وحذف الخدمات.
- جلب حالة التوثيق.
- استخدام transaction عند حفظ بيانات التسجيل.

بهذا الشكل يمكن تعديل منطق العمل لاحقًا بدون تكبير Controller.

### Form Requests

المسار:

```text
app/Http/Requests/Api/
```

تم وضع التحقق من البيانات داخل Form Requests بدل كتابته داخل Controller، ومن هذه الملفات:

- `ProviderOnboardingRequest`
- `ProviderServiceRequest`
- `CreateBookingRequest`
- `CreateReviewRequest`

### Middleware

المسار:

```text
app/Http/Middleware/EnsureProvider.php
```

هذا الـ middleware يتأكد أن المستخدم مسجل الدخول وأن نوع حسابه `provider`. وتم تطبيقه على مسارات مزود الخدمة بعد `auth:sanctum`.

### Models

المسار:

```text
app/Models/
```

الـ Models مسؤولة عن العلاقات مع الجداول، مثل علاقة المزود بالخدمات وعلاقة الخدمة بالتقييمات والصور والحجوزات.

### Routes

المسار:

```text
routes/api.php
```

الـ routes مسؤولة عن ربط الرابط بالـ Controller وتحديد middleware المستخدم، وتم تجميع مسارات المزود تحت:

```text
/api/provider/*
```

### خلاصة التقسيم

```text
Route -> Middleware -> Controller -> Form Request -> Service -> Model -> Database
```

هذا التقسيم يجعل الكود أسهل في القراءة والاختبار، ويساعد على إضافة شاشات جديدة بدون وضع كل المنطق في ملف واحد.

---

## 6) الإضافات على قاعدة البيانات

تمت إضافة بعض الأعمدة التي كانت مطلوبة في التصاميم:

### جدول services

- `features`: مزايا الخدمة بصيغة JSON.
- `capacity`: سعة المكان.
- `area`: مساحة المكان.
- `area_unit`: وحدة المساحة.
- `booking_slots`: فترات الحجز بصيغة JSON.

### جدول bookings

- `booking_slot`: اسم الفترة التي اختارها المستخدم.

### جدول provider_profiles

- `registration_status`: حالة تسجيل المزود.
- `identity_document_path`: مسار صورة الهوية.
- `commercial_register_path`: مسار السجل التجاري.

تم وضع هذه التغييرات في migrations منفصلة حتى يتم تطبيقها على أي نسخة من قاعدة البيانات بسهولة.

---

## 7) التوثيق باستخدام Swagger

تم استخدام L5 Swagger لتوثيق الـ API. بعد تشغيل المشروع يمكن فتح التوثيق من:

```text
/api/documentation
```

ويحتوي Swagger على:

- مسارات تسجيل الدخول والحسابات.
- مسارات الخدمات والحجوزات.
- مسارات المفضلة والتقييمات.
- مسارات مزود الخدمة.
- الحقول المطلوبة في JSON وmultipart/form-data.
- Bearer Authentication لاختبار المسارات المحمية.

لتحديث ملف التوثيق:

```bash
php artisan l5-swagger:generate
```

---

## 8) طريقة تشغيل المشروع

بعد تنزيل المشروع يتم تنفيذ الأوامر التالية:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

### تشغيل الإنتاج والمهام الخلفية

في بيئة الإنتاج اضبط `APP_ENV=production` و`APP_DEBUG=false` ومفتاح `APP_KEY` وقاعدة بيانات الإنتاج وإعدادات البريد والتخزين قبل نشر التطبيق. لا تستخدم إعدادات SQLite والبريد والـ queue المحلية الموجودة في `.env.example` كإعدادات إنتاج.

المشروع ينهي الحجوزات التي انتهت مهلتها عبر مهمة مجدولة. يجب تشغيل Laravel Scheduler باستمرار عبر cron في الخادم:

```cron
* * * * * cd /path/to/FarahBackend-main && php artisan schedule:run >> /dev/null 2>&1
```

تُنفذ المهمة `bookings:expire-stale` كل خمس دقائق. تحقق من تعريف المواعيد باستخدام:

```bash
php artisan schedule:list
```

إذا فُعّلت jobs في queue، شغّل `php artisan queue:work --tries=3 --timeout=90` تحت process manager يعيد تشغيله عند الفشل، وراقب queue المتراكمة. يمكن استخدام `php artisan schedule:work` للتطوير المحلي، وليس بديلًا عن cron دائم في الإنتاج. يعتمد فحص صحة التطبيق على `GET /up`؛ اربطه بفحص صحة الاستضافة، واجمع سجلات Laravel وفعّل تنبيهات الأخطاء من مزود الاستضافة.

في بيئة Railway يجب إضافة متغيرات البيئة من لوحة Railway، خصوصًا:

- `APP_KEY`
- `DATABASE_URL`
- `DB_CONNECTION=pgsql`
- `RESEND_KEY`
- `MAIL_MAILER=resend`

ملف `.env` لا يتم رفعه إلى GitHub لأنه يحتوي على بيانات سرية.

---

## 9) الاختبار والتوثيق

لتشغيل الاختبارات بعد التعديلات:

```bash
php artisan test
```

لتوليد ومراجعة مواصفات OpenAPI/Swagger بعد تعديل المسارات أو الـ annotations:

```bash
php artisan l5-swagger:generate
```

المسارات الحالية غير مُصدّرة تحت بادئة إصدار مثل `/api/v1`؛ حافظ على توافق التطبيقات الحالية، واتفق على سياسة إصدار قبل إجراء تغييرات كاسرة للعقد.

---

## 10) ملاحظات مستقبلية

- ربط بوابة دفع حقيقية عند اعتماد الدفع الداخلي مستقبلًا.
- تفعيل تحصيل الاشتراكات المدفوعة بعد اختيار بوابة الدفع وتنفيذ webhooks والتسوية.
- إضافة إشعارات push بعد اختيار مزود الإشعارات وتنفيذ تسجيل رموز الأجهزة.
- إضافة اختبارات خاصة بواجهات مزود الخدمة والحجوزات.

## 11) التفاصيل الأساسية للمشروع

### مجلدات المشروع الأساسية
- app/Models → تمثل الجداول والـ Models
- app/Http/Controllers/Api → Controllers الخاصة بـ API
- database/migrations → ملفات إنشاء الجداول
- database/factories → إنشاء بيانات تجريبية
- database/seeders → إدخال بيانات أولية وتجريبية
- routes/api.php → كل مسارات الـ API

---

## 12) الجداول الأساسية في المشروع

### 3.1 users
هو الجدول الأساسي للمستخدمين.

#### الأعمدة الأساسية
- id
- name
- email
- password
- phone
- user_type
- status
- avatar
- bio
- cover_image
- last_login_at
- is_online
- city_id
- email_verified_at
- remember_token
- created_at
- updated_at
- deleted_at

#### لماذا تم إنشاؤه
لأنه يحتوي على البيانات الأساسية للمستخدم مثل الاسم الإيميل كلمة المرور المدينة الصورة الشخصية وحالة المستخدم.

#### Model المرتبط
- app/Models/User.php

---

### 3.2 cities
هو الجدول الذي يحتفظ بالمدن.

#### الأعمدة
- id
- name
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن التطبيق يعتمد على المدينة في الملف الشخصي والخدمات.

#### Model المرتبط
- app/Models/City.php

---

### 3.3 locations
هذا الجدول يحفظ مواقع المستخدم أو مزود الخدمة.

#### الأعمدة
- id
- user_id
- label
- address
- city_id
- latitude
- longitude
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأنه مفيد إذا كان التطبيق يحتاج إلى أكثر من عنوان أو موقع مرتبط بالمستخدم.

#### Model المرتبط
- app/Models/Location.php

---

### 3.4 provider_profiles
هذا الجدول يمثل ملف مزود الخدمة.

#### الأعمدة
- id
- user_id
- city_id
- business_name
- category_id
- phone
- bio
- description
- cover_image
- identity_document_path
- commercial_register_path
- address
- status
- registration_status
- is_featured
- rating
- working_hours
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن التطبيق يحتوي على مزودين خدمات وكل مزود يحتاج إلى اسم عمل فئة تقييم صورة غلاف وحالة اعتماد.

#### Model المرتبط
- app/Models/ProviderProfile.php

---

### 3.5 categories
هذا الجدول يحتوي على التصنيفات الرئيسية للتطبيق.

#### الأعمدة
- id
- name
- slug
- image
- status
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن الصفحة الرئيسية والتصفح تحتاج إلى تصنيفات جاهزة لتقسيم الخدمات.

#### Model المرتبط
- app/Models/Category.php

---

### 3.6 services
هذا هو أهم جدول في التطبيق بعد جدول users.

#### الأعمدة
- id
- provider_id
- category_id
- city_id
- title
- description
- features
- capacity
- area
- area_unit
- booking_slots
- price
- currency
- image
- rating_avg
- reviews_count
- is_featured
- is_available
- status
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأنه يمثل الخدمة أو المنتج الذي يتم عرضه في التطبيق.

#### Model المرتبط
- app/Models/Service.php

---

### 3.7 service_images
هذا الجدول يخزن صور الخدمة المتعددة.

#### الأعمدة
- id
- service_id
- image_path
- sort_order
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن الخدمة قد تحتوي على أكثر من صورة وليس صورة واحدة فقط.

#### Model المرتبط
- app/Models/ServiceImage.php

---

### 3.8 reviews
هذا الجدول يخزن تقييمات المستخدمين للخدمات.

#### الأعمدة
- id
- user_id
- service_id
- rating
- comment
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن التطبيق يحتاج إلى تقييمات ونقاط للنجوم في كل خدمة.

#### Model المرتبط
- app/Models/Review.php

---

### 3.9 favorites
هذا الجدول يحفظ الخدمات المفضلة عند المستخدم.

#### الأعمدة
- id
- user_id
- service_id
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأنه مهم في شاشة المفضلة والعمليات التي تسمح للمستخدم بحفظ الخدمات.

#### Model المرتبط
- app/Models/Favorite.php

---

### 3.10 bookings
هذا الجدول يحفظ الحجوزات أو الطلبات المخصصة للخدمة.

#### الأعمدة
- id
- user_id
- service_id
- provider_id
- booking_date
- booking_time
- booking_slot
- total_price
- status
- notes
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن التطبيق يحتوي على مفهوم حجز الخدمة من قبل المستخدم.

#### Model المرتبط
- app/Models/Booking.php

---

### 3.11 notification_settings
هذا الجدول يحفظ إعدادات إشعارات المستخدم.

#### الأعمدة
- id
- user_id
- new_orders
- offers
- promotions
- reminders
- created_at
- updated_at

#### لماذا تم إنشاؤه
لأن التطبيق يحتوي على شاشة إعدادات الإشعارات toggles وهذا يحتاج إلى جدول مستقل.

#### Model المرتبط
- app/Models/NotificationSetting.php

---

## 13) شرح Models الأساسية

### User
يمثل المستخدم الرئيسي في التطبيق.

### ProviderProfile
يمثل ملف مزود الخدمة.

### Category
يمثل التصنيف أو الفئة.

### Service
يمثل الخدمة نفسها.

### ServiceImage
يمثل صور الخدمة.

### Review
يمثل تقييم الخدمة من العميل.

### Favorite
يمثل خدمة مفضلة للمستخدم.

### Booking
يمثل حجز أو طلب الخدمة.

### NotificationSetting
يمثل إعدادات الإشعارات الخاصة بالمستخدم.

---

## 14) ما هي Controllers التي تم إنشاؤها

### HomeController
وظيفته:
- تجهيز بيانات الصفحة الرئيسية
- جمع أبرز المزودين
- جمع التصنيفات
- جمع الخدمات المميزة

#### المسار
- GET /api/home

---

### CategoryController
وظيفته:
- جلب جميع التصنيفات
- تحويلها إلى JSON للواجهة

#### المسار
- GET /api/categories

---

### ServiceController
وظيفته:
- جلب الخدمات
- البحث حسب الاسم
- فلترة حسب category_id
- عرض تفاصيل الخدمة حسب ID

#### المسارات
- GET /api/services
- GET /api/services/{id}

---

## 15) لماذا نحتاج الـ Controller

لأن Laravel يحتاج طبقة وسيطة بين:
- Route
- Model
- JSON Response

أي أن الـ Controller يستقبل الطلب يطلب البيانات من قاعدة البيانات ثم يرجع النتيجة إلى Flutter بصيغة JSON.

---

## 16) ما هي Factory و Seeder

### Factory
هو ملف يساعد في إنشاء بيانات تجريبية بسرعة.

أمثلة:
- CategoryFactory
- ServiceFactory
- ReviewFactory
- BookingFactory
- NotificationSettingFactory

### Seeder
هو ملف يقوم بملء جدول معين ببيانات أولية أو تجريبية.

أمثلة:
- CategorySeeder
- ServiceSeeder
- ReviewSeeder
- FavoriteSeeder
- BookingSeeder
- NotificationSettingSeeder

### DatabaseSeeder
هو الملف الرئيسي الذي يربط جميع الـ Seeders معا.

---

## 17) لماذا Factory و Seeder مهمان

لأننا نحتاج إلى:
- اختبار التطبيق
- تعبئة قاعدة البيانات للعرض
- اختبار الـ API بسرعة
- إعداد بيئة تطوير كاملة

---

## 18) الـ Routes الأساسية في المشروع

هذه هي المسارات الأساسية المضافة في routes/api.php:

- GET /api/home
- GET /api/categories
- GET /api/services
- GET /api/services/{id}

هذه المسارات جاهزة بحيث يمكن لواجهة Flutter استدعاؤها مباشرة.

---

## 19) الخلاصة

المشروع الآن يتكون من:
- Users + Auth
- Cities
- Locations
- Provider Profiles
- Categories
- Services
- Reviews
- Favorites
- Bookings
- Notification Settings
- API controllers
- Factory + Seeder

وهذا يشكل أساسا قويا جدا لبناء التطبيق بالكامل بشكل احترافي.

---

## 20) الخطوة التالية
يمكن الآن متابعة تطوير الـ API للجزء التالي:
- Favorites
- Bookings
- Notification settings
- Reviews
- Auth/Profile

وهذا سيكمل التطبيق بشكل أكثر احترافية ومناسب جدا لواجهة Flutter.
