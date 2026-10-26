# فرح: النشر على Railway

## كيف يوصل الكود للسيرفر
1. يندمج الطلب (Pull Request) على `farah-platform/farah-backend` ← فرع `main`.
2. على الـ fork `braalhdad90-art/farah-backend` اضغط **Sync fork** ← **Update branch**.
3. Railway مربوط بالـ fork (فرع `main`، Auto deploys مفعّل) فينشر لحاله خلال دقائق.
   تأكد من تبويب **Deployments** أن آخر نشر هو آخر طلب مدموج وحالته **Active**.

> لا تجعل `farah-platform/farah-backend` خاصًا (Private): GitHub يفصل الـ fork العام عنه
> فيتوقف Sync fork والنشر التلقائي.

## متغيرات Railway (service: farah-backend ← Variables)
| المتغير | القيمة / الملاحظة |
|---|---|
| `APP_KEY` | مفتاح Laravel (لا يتغير بعد الإعداد) |
| `APP_URL` | رابط الخدمة مع `https://` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `DATABASE_URL` / `DB_CONNECTION` | من خدمة Postgres في نفس المشروع / `pgsql` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | أول حساب أدمن للوحة `/admin` (يُنشأ مرة واحدة) |
| `MAIL_MAILER` | `brevo` (Railway يمنع SMTP على خطة Hobby/Trial) |
| `BREVO_API_KEY` | مفتاح Brevo (صلاحية Transactional email = All). ينتهي إذا لم يُستخدم 90 يومًا |
| `MAIL_FROM_ADDRESS` | إيميل مرسِل مؤكَّد في Brevo ← Senders |
| `MAIL_FROM_NAME` | `فرح` |

## التخزين (الصور والملفات المرفوعة)
اربط Volume بخدمة farah-backend على المسار `/app/storage`، وإلا تُمسح الصور المرفوعة
(أغلفة المتاجر، الشعارات، صور الخدمات، إشعارات الدفع) مع كل نشر جديد.

## بعد كل نشر
- `docker/start.sh` يشغّل: `migrate` و `db:seed` وتوليد Swagger وحساب الأدمن والمهام المجدولة.
- اللوحة: `APP_URL/admin/login` — التوثيق: `APP_URL/api/documentation`.
