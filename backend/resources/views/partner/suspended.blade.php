<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('partner.suspended.page_title') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4" style="font-family: 'Cairo', sans-serif;">

    <div class="w-full max-w-lg text-center">

        {{-- Logo --}}
        <div class="mb-8">
            <span class="inline-block bg-yellow-400 text-gray-950 font-black text-3xl px-4 py-1 rounded-lg">noon</span>
        </div>

        {{-- Warning icon --}}
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
        </div>

        {{-- Message --}}
        <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ __('partner.suspended.heading') }}</h1>
        <p class="text-gray-500 text-sm leading-relaxed mb-2">
            {{ __('partner.suspended.message_1') }}
        </p>
        <p class="text-gray-500 text-sm mb-8">
            {{ __('partner.suspended.message_2') }}
        </p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="mailto:seller-support@noon.com"
                class="inline-flex items-center justify-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-gray-950 font-bold px-6 py-3 rounded-xl transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
                {{ __('partner.suspended.contact_support') }}
            </a>
            <a href="{{ route('partner.login') }}"
                class="inline-flex items-center justify-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium px-6 py-3 rounded-xl transition-colors text-sm">
                {{ __('partner.suspended.back_to_login') }}
            </a>
        </div>

    </div>

</body>

</html>