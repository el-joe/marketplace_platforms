<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>{{ __('delivery.auth.sign_in_title') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        body {
            font-family: 'Figtree', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 32px 24px calc(32px + var(--safe-bottom));
        }

        .form-field {
            background: #1e293b;
            border: 1.5px solid #334155;
            border-radius: 14px;
            padding: 16px;
            color: #f1f5f9;
            font-size: 16px;
            width: 100%;
            outline: none;
            transition: border-color 0.15s;
            -webkit-appearance: none;
        }

        .form-field::placeholder {
            color: #475569;
        }

        .form-field:focus {
            border-color: #facc15;
        }

        .btn-login {
            width: 100%;
            min-height: 58px;
            background: #facc15;
            color: #0f172a;
            font-size: 17px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-login:active {
            opacity: 0.85;
            transform: scale(0.98);
        }

        .error-box {
            background: #450a0a;
            border: 1px solid #7f1d1d;
            color: #fca5a5;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="login-card">

        {{-- Logo --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-baseline gap-1 mb-3">
                <span class="text-yellow-400 font-extrabold text-4xl tracking-tight">noon</span>
                <span class="text-slate-400 text-lg font-semibold">delivery</span>
            </div>
            <p class="text-slate-400 text-sm mt-1">{{ __('delivery.auth.sign_in_subtitle') }}</p>
        </div>

        {{-- Error --}}
        @if($errors->any())
            <div class="error-box mb-5">{{ $errors->first() }}</div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('delivery.login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">{{ __('delivery.auth.phone_number') }}</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" class="form-field"
                    placeholder="+20 XXX XXX XXXX" autocomplete="username" autofocus required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">{{ __('delivery.auth.password') }}</label>
                <input type="password" name="password" class="form-field" placeholder="••••••••"
                    autocomplete="current-password" required>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded accent-yellow-400">
                    <span class="text-slate-400 text-sm">{{ __('delivery.auth.keep_signed_in') }}</span>
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-login">{{ __('delivery.auth.sign_in') }}</button>
            </div>
        </form>

        <p class="text-center text-slate-500 text-xs mt-8">
            {{ __('delivery.auth.trouble_contact_dispatcher') }}
        </p>

    </div>

</body>

</html>
