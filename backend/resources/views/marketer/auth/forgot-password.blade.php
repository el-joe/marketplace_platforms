<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نسيت كلمة المرور | ماركتر</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center" style="font-family:'Cairo',sans-serif;">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-1">استعادة كلمة المرور</h2>
            <p class="text-gray-500 text-sm mb-5">أدخل بريدك الإلكتروني وسنرسل لك رابط الاستعادة</p>
            @if(session('status'))
                <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('marketer.auth.forgot.send') }}" class="space-y-4">
                @csrf
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="البريد الإلكتروني"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 @error('email') border-red-500 @enderror">
                @error('email') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-2.5 rounded-lg text-sm transition">
                    إرسال رابط الاستعادة
                </button>
            </form>
            <a href="{{ route('marketer.login') }}" class="block text-center text-sm text-gray-500 mt-4 hover:text-gray-700">← العودة لتسجيل الدخول</a>
        </div>
    </div>
</body>
</html>
