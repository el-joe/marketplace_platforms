<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('marketer.auth.forgot_password_page_title') }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center" style="font-family:'Cairo',sans-serif;">
<form method="POST" action="{{ route('marketer.locale.switch') }}" class="fixed top-4 end-4 z-50">
    @csrf
    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
    <button type="submit" class="px-3 py-1 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-600 hover:bg-gray-100">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button>
</form>
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('marketer.auth.forgot_password_heading') }}</h2>
            <p class="text-gray-500 text-sm mb-5">{{ __('marketer.auth.forgot_password_hint') }}</p>
            @if(session('status'))
                <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('marketer.auth.forgot.send') }}" class="space-y-4">
                @csrf
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="{{ __('marketer.auth.email_placeholder') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 @error('email') border-red-500 @enderror">
                @error('email') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-2.5 rounded-lg text-sm transition">
                    {{ __('marketer.auth.send_reset_link') }}
                </button>
            </form>
            <a href="{{ route('marketer.login') }}" class="block text-center text-sm text-gray-500 mt-4 hover:text-gray-700">{{ __('marketer.auth.back_to_login') }}</a>
        </div>
    </div>
</body>
</html>
