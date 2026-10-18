<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول — فرح</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin-login.css') }}" rel="stylesheet">
</head>
<body>
    <main class="login-card">
        <div class="login-brand d-flex align-items-center gap-3">
            <span class="brand-icon"><i class="fa-solid fa-feather-pointed"></i></span>
            <div><h1 class="h5 fw-bold mb-1">فرح</h1><div class="small text-white-50">بوابة إدارة المنصة</div></div>
        </div>
        <div class="p-4 p-md-5">
            <h2 class="h5 fw-bold mb-1">مرحباً بعودتك</h2>
            <p class="text-secondary small mb-4">سجّل الدخول للمتابعة إلى لوحة الإدارة.</p>
            @if($errors->any())
                <div class="alert alert-danger small"><i class="fa-solid fa-circle-exclamation ms-1"></i>{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label small fw-bold">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label small fw-bold">كلمة المرور</label>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label small" for="remember">تذكرني على هذا الجهاز</label>
                </div>
                <button type="submit" class="btn btn-primary text-white w-100"><i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>تسجيل الدخول</button>
            </form>
        </div>
    </main>
</body>
</html>
