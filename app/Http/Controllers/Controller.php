<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Farah API Documentation",
    description: "توثيق واجهات برمجة التطبيقات (APIs) لمنصة فرح لتطبيق Flutter"
)]
#[OA\Server(
    url: "https://farah-production.up.railway.app",
    description: "سيرفر الإنتاج (Railway Production)"
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "السيرفر المحلي (Local Server)"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "أدخل التوكن بهذا الشكل: Bearer {token}"
)]
#[OA\Tag(name: "Auth", description: "إدارة الحسابات والمصادقة والملف الشخصي")]
#[OA\Tag(name: "Home", description: "بيانات الصفحة الرئيسية")]
#[OA\Tag(name: "Categories", description: "التصنيفات والقطاعات")]
#[OA\Tag(name: "Services", description: "الخدمات والمزودين")]
#[OA\Tag(name: "Locations", description: "عناوين المستخدمين")]
#[OA\Tag(name: "Cities", description: "المدن والمناطق")]
abstract class Controller
{
    //
}