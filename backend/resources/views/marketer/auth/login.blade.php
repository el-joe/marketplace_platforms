<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل دخول الماركتر | نون</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center" style="font-family:'Cairo',sans-serif;">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-yellow-400 items-center justify-center font-black text-2xl text-gray-900 mb-3">M</div>
            <h1 class="text-2xl font-bold text-gray-900">بوابة الماركتر</h1>
            <p class="text-gray-500 text-sm mt-1">سجّل دخولك لإدارة حملاتك وعمولاتك</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            @if(session('status'))
                <div class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('marketer.login.post') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 @error('email') border-red-500 @enderror">
                    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">كلمة المرور</label>
                    <input type="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400">
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-gray-600">
                        <input type="checkbox" name="remember" class="rounded">
                        تذكّرني
                    </label>
                    <a href="{{ route('marketer.auth.forgot') }}" class="text-yellow-600 hover:underline">نسيت كلمة المرور؟</a>
                </div>
                <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-2.5 rounded-lg text-sm transition">
                    تسجيل الدخول
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-gray-500 mt-4">
            ليس لديك حساب؟
            <a href="{{ route('marketer.register') }}" class="text-yellow-600 font-semibold hover:underline">سجّل الآن</a>
        </p>
    </div>
</body>
</html>
