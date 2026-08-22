<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('carrier.auth.login_title') }} | {{ __('carrier.auth.title') }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 to-slate-100 flex items-center justify-center" style="font-family: 'Cairo', sans-serif;">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-4 text-3xl shadow-lg">🚚</div>
            <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.auth.title') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('carrier.auth.subtitle') }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <form method="POST" action="{{ route('carrier.login.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('email') border-red-400 @enderror">
                    @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.password') }}</label>
                    <input type="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300 text-indigo-600">
                    <label for="remember" class="text-sm text-gray-600">{{ __('carrier.auth.remember_me') }}</label>
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg transition text-sm">
                    {{ __('carrier.auth.submit') }}
                </button>
            </form>
        </div>
    </div>

</body>
</html>
