<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.auth.login_title') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-gray-50 flex items-center justify-center px-4">

    <div class="w-full max-w-sm">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-600 text-white font-bold text-xl mb-3">
                M
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('admin.panel_title') }}</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">{{ __('admin.auth.login_subtitle') }}</h2>

            {{-- Session errors --}}
            @if(session('error'))
                <div class="mb-4 rounded-lg bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-700">
                    {{ session('error') }}
                </div>
            @endif

            <form id="login-form" method="POST" action="{{ route('admin.login.submit') }}" novalidate>
                @csrf

                {{-- Email --}}
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.email_label') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                        autocomplete="email"
                        class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition
                               {{ $errors->has('email') ? 'border-danger-400 bg-danger-50' : 'border-gray-300 bg-white' }}"
                        placeholder="admin@example.com">
                    @error('email')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.auth.password_label') }}</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition
                                   {{ $errors->has('password') ? 'border-danger-400 bg-danger-50' : 'border-gray-300 bg-white' }}"
                            placeholder="••••••••">
                        <button type="button" id="toggle-password"
                            class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center mb-6">
                    <input id="remember" name="remember" type="checkbox" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <label for="remember" class="ms-2 block text-sm text-gray-700">{{ __('admin.auth.remember_me') }}</label>
                </div>

                {{-- Submit --}}
                <button type="submit" id="login-btn"
                    class="w-full flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition disabled:opacity-60">
                    <svg id="login-spinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                    </svg>
                    <span id="login-btn-text">{{ __('admin.auth.login_button') }}</span>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('common.all_rights_reserved') }}
        </p>
    </div>

    <script>
        window.TRANSLATIONS = {
            signing_in: "{{ __('admin.auth.login_button') }}…",
        };

        document.getElementById('toggle-password').addEventListener('click', function () {
            const pw = document.getElementById('password');
            pw.type = pw.type === 'password' ? 'text' : 'password';
        });

        document.getElementById('login-form').addEventListener('submit', function () {
            const btn = document.getElementById('login-btn');
            const text = document.getElementById('login-btn-text');
            const spinner = document.getElementById('login-spinner');
            btn.disabled = true;
            text.textContent = window.TRANSLATIONS.signing_in;
            spinner.classList.remove('hidden');
        });
    </script>
</body>

</html>
