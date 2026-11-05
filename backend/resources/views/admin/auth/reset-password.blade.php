<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.auth.set_password_title') }} — {{ config('app.name') }}</title>
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
            <h2 class="text-lg font-semibold text-gray-900 mb-2">{{ __('admin.auth.set_password_title') }}</h2>
            <p class="text-sm text-gray-500 mb-6">{{ __('admin.auth.set_password_desc') }}</p>

            <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.email_label') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                        class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition
                               {{ $errors->has('email') ? 'border-danger-400 bg-danger-50' : 'border-gray-300 bg-white' }}">
                    @error('email')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.new_password') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password"
                        class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition
                               {{ $errors->has('password') ? 'border-danger-400 bg-danger-50' : 'border-gray-300 bg-white' }}">
                    @error('password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.confirm_password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition">
                    {{ __('admin.auth.set_password_button') }}
                </button>
            </form>
        </div>
    </div>
</body>

</html>
