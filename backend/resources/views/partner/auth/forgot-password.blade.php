<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('partner.auth.forgot_password_title') }} | {{ __('partner.auth.brand_suffix') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/partner/app.js'])
</head>
<body class="min-h-screen bg-gray-950 flex items-center justify-center p-4" style="font-family: 'Cairo', sans-serif;">

    <div class="w-full max-w-md">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <span class="inline-block bg-yellow-400 text-gray-950 font-black text-3xl px-4 py-1 rounded-lg">noon</span>
            <p class="text-gray-400 text-sm mt-3">{{ __('partner.auth.vendor_panel_subtitle') }}</p>
        </div>

        {{-- Card --}}
        <div class="bg-gray-900 rounded-2xl border border-gray-800 p-8 shadow-2xl">

            <h1 class="text-white text-xl font-bold mb-2">{{ __('partner.auth.forgot_password_heading') }}</h1>
            <p class="text-gray-400 text-sm mb-6">{{ __('partner.auth.forgot_password_desc') }}</p>

            {{-- Status message --}}
            @if(session('status'))
                <div class="mb-4 p-4 bg-green-500/10 border border-green-500/30 rounded-xl text-green-400 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('partner.auth.forgot.send') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('common.email') }}</label>
                    <input id="email" name="email" type="email" required autofocus
                           value="{{ old('email') }}"
                           class="w-full bg-gray-800 border @error('email') border-red-500 @else border-gray-700 @enderror
                                  rounded-xl px-4 py-3 text-white placeholder-gray-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400 transition-colors"
                           placeholder="example@store.com">
                    @error('email')
                        <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full bg-yellow-400 hover:bg-yellow-300 text-gray-950 font-bold py-3 rounded-xl
                               transition-colors text-sm">
                    {{ __('partner.auth.send_reset_link') }}
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('partner.login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                    &larr; {{ __('partner.auth.back_to_login') }}
                </a>
            </div>

        </div>
    </div>

</body>
</html>
