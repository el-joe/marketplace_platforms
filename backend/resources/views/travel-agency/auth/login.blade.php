<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('travel.auth.login') }} | {{ __('travel.portal_title') }} </title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/portal/app.js'])
</head>
<body class="bg-gray-950 min-h-screen flex items-center justify-center" style="font-family: 'Cairo', sans-serif;">

    <div class="w-full max-w-md px-4">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 mb-4">
                <span class="bg-blue-500 text-white font-black text-2xl px-3 py-1 rounded">✈</span>
                <span class="text-white font-bold text-lg">{{ __('travel.portal_title') }}</span>
            </div>
            <h1 class="text-2xl font-black text-white">{{ __('travel.auth.login') }}</h1>
            <p class="mt-1 text-gray-400 text-sm">{{ __('travel.auth.login_subtitle') }}</p>
        </div>

        @if(session('error'))
        <div class="mb-4 p-4 bg-red-900/50 border border-red-500/40 text-red-400 rounded-2xl text-sm text-center">
            {{ session('error') }}
        </div>
        @endif

        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8">
            <form method="POST" action="{{ route('travel-agency.login.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('travel.auth.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           dir="ltr"
                           class="w-full bg-gray-800 border {{ $errors->has('email') ? 'border-red-500' : 'border-gray-700' }} text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                    @error('email')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('travel.auth.password') }}</label>
                    <input type="password" name="password" required
                           dir="ltr"
                           class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400">
                </div>

                <div class="flex items-center gap-3 flex-row-reverse justify-end">
                    <input type="checkbox" name="remember" id="remember"
                           class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-blue-400 focus:ring-blue-400 focus:ring-offset-gray-900">
                    <label for="remember" class="text-sm text-gray-400">{{ __('travel.auth.remember') }}</label>
                </div>

                <button type="submit"
                        class="w-full bg-blue-500 hover:bg-blue-400 text-white font-black py-3.5 rounded-xl transition-colors text-base">
                    {{ __('travel.auth.login_button') }}
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-gray-600">
            {{ __('travel.auth.register_subtitle') }}
        </p>
    </div>

</body>
</html>
