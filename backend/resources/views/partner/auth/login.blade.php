<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('partner.auth.sign_in_title') }} | {{ __('partner.auth.brand_suffix') }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/portal/app.js'])
</head>

<body class="bg-gray-950 min-h-screen flex items-center justify-center" style="font-family: 'Cairo', sans-serif;">

    @php $isAr = session('locale', 'ar') === 'ar'; @endphp

    <div class="w-full max-w-md px-4">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-2">
                <span class="bg-yellow-400 text-gray-950 font-black text-2xl px-3 py-1 rounded">noon</span>
                <span class="text-white font-semibold">{{ __('partner.auth.sellers') }}</span>
            </a>
            <h1 class="mt-6 text-2xl font-black text-white">
                {{ __('partner.auth.sign_in_title') }}
            </h1>
            <p class="mt-1 text-gray-400 text-sm">
                {{ __('partner.auth.access_store_dashboard') }}
            </p>
        </div>

        {{-- Error flash --}}
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-900/50 border border-red-500/40 text-red-400 rounded-2xl text-sm text-center">
                {{ session('error') }}
            </div>
        @endif

        {{-- Card --}}
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8">
            <form method="POST" action="{{ route('partner.login.submit') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5 {{ $isAr ? 'text-right' : '' }}">
                        {{ __('partner.auth.email') }}
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full bg-gray-800 border {{ $errors->has('email') ? 'border-red-500' : 'border-gray-700' }}
                              text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm
                              focus:outline-none focus:border-yellow-400 focus:ring-1 focus:ring-yellow-400" dir="ltr">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-400 {{ $isAr ? 'text-right' : '' }}">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-sm font-medium text-gray-300">
                            {{ __('partner.auth.password') }}
                        </label>
                        <a href="{{ route('partner.auth.forgot') }}" class="text-xs text-yellow-400 hover:underline">
                            {{ __('partner.auth.forgot_password_link') }}
                        </a>
                    </div>
                    <input type="password" name="password" required autocomplete="current-password" class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500
                              rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-yellow-400
                              focus:ring-1 focus:ring-yellow-400" dir="ltr">
                </div>

                <div class="flex items-center gap-3 {{ $isAr ? 'flex-row-reverse justify-end' : '' }}">
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-yellow-400
                              focus:ring-yellow-400 focus:ring-offset-gray-900">
                    <label for="remember" class="text-sm text-gray-400">
                        {{ __('partner.auth.remember_me') }}
                    </label>
                </div>

                <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-300 text-gray-950 font-black
                           py-3.5 rounded-xl transition-colors text-base">
                    {{ __('partner.auth.sign_in_title') }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                {{ __('partner.auth.no_account') }}
                <a href="{{ route('portal.register') }}" class="text-yellow-400 hover:underline font-medium">
                    {{ __('partner.auth.register_now') }}
                </a>
            </p>
        </div>
    </div>

</body>

</html>
