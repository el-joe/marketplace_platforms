<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.auth.forgot_password_title') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-gray-50 flex items-center justify-center px-4">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-600 text-white font-bold text-xl mb-3">
                M
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('admin.panel_title') }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">{{ __('admin.auth.forgot_password_title') }}</h2>
            <p class="text-sm text-gray-500 mb-6">{{ __('admin.auth.forgot_password_desc') }}</p>

            @if(session('status'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.email') }}" novalidate>
                @csrf

                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.email_label') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                        class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition
                               {{ $errors->has('email') ? 'border-danger-400 bg-danger-50' : 'border-gray-300 bg-white' }}"
                        placeholder="admin@example.com">
                    @error('email')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition">
                    {{ __('admin.auth.send_reset_link') }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm">
                <a href="{{ route('admin.login') }}" class="font-medium text-primary-600 hover:text-primary-700">{{ __('admin.auth.back_to_login') }}</a>
            </p>
        </div>
    </div>
</body>

</html>
