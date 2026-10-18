@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $marketerTypes = [
        'influencer' => ['icon' => '🎬', 'label' => __('marketer.auth.type_influencer'), 'hint' => __('marketer.auth.type_influencer_hint')],
        'affiliate' => ['icon' => '🔗', 'label' => __('marketer.auth.type_affiliate'), 'hint' => __('marketer.auth.type_affiliate_hint')],
        'broker' => ['icon' => '🤝', 'label' => __('marketer.auth.type_broker'), 'hint' => __('marketer.auth.type_broker_hint')],
    ];

    // When a type is handed over from the public portal (e.g. /marketers?type=broker)
    // only that type is shown, so the visitor cannot pick a different one here.
    $activeType = old('marketer_type', $selectedType);
    if (is_string($activeType) && isset($marketerTypes[$activeType])) {
        $marketerTypes = [$activeType => $marketerTypes[$activeType]];
    }

    $typeGridClass = count($marketerTypes) > 1
        ? 'grid grid-cols-1 sm:grid-cols-3 gap-3'
        : 'grid grid-cols-1 gap-3 sm:max-w-xs sm:mx-auto';

    $inputBase = 'w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors';
@endphp

@section('title', __('marketer.auth.register_page_title'))
@section('hide_nav', true)

@section('content')
    <div class="min-h-screen bg-gray-950 py-10 px-4" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-2xl mx-auto">

            {{-- Header --}}
            <div class="text-center mb-8">
                <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-2">
                    <span class="bg-[#0F807E] text-gray-950 font-black text-2xl px-3 py-1 rounded">Nawy</span>
                    <span class="text-white text-lg font-semibold">{{ __('marketer.portal_title') }}</span>
                </a>
                <h1 class="mt-5 text-2xl sm:text-3xl font-black text-white">{{ __('marketer.auth.register_heading') }}</h1>
                <p class="mt-2 text-gray-400 text-sm">{{ __('marketer.auth.register_subtitle') }}</p>
            </div>

            {{-- Form card --}}
            <div class="bg-gray-900 rounded-2xl shadow-2xl p-5 sm:p-8">
                <form method="POST" action="{{ route('marketer.register.post') }}" class="space-y-5">
                    @csrf

                    {{-- Marketer type --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            {{ __('marketer.auth.account_type_label') }} <span class="text-red-400">*</span>
                        </label>
                        <div class="{{ $typeGridClass }}">
                            @foreach ($marketerTypes as $value => $type)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="marketer_type" value="{{ $value }}" class="sr-only peer"
                                        {{ old('marketer_type', $selectedType) === $value ? 'checked' : '' }}>
                                    <div class="h-full rounded-xl border-2 border-gray-700 bg-gray-800 p-4 text-center transition-colors hover:border-gray-600 peer-checked:border-[#feee00] peer-checked:bg-[#feee00]/10">
                                        <div class="text-2xl mb-1">{{ $type['icon'] }}</div>
                                        <div class="font-bold text-white text-sm">{{ $type['label'] }}</div>
                                        <div class="text-gray-400 text-xs">{{ ucfirst($value) }}</div>
                                        <div class="text-gray-500 text-xs mt-1">{{ $type['hint'] }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('marketer_type') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Name + Country --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                {{ __('marketer.auth.full_name_label') }} <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name"
                                class="{{ $inputBase }} @error('name') border-red-500 @else border-gray-700 focus:border-[#feee00] @enderror">
                            @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                {{ __('marketer.auth.country_label') }} <span class="text-red-400">*</span>
                            </label>
                            <select name="country_id" required
                                class="{{ $inputBase }} @error('country_id') border-red-500 @else border-gray-700 focus:border-[#feee00] @enderror">
                                <option value="">{{ __('marketer.auth.choose_country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id', $selectedCountryId) == $country->id ? 'selected' : '' }}>
                                        {{ $country->flag_emoji }} {{ $isAr ? $country->name_ar : ($country->name_en ?? $country->name_ar) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">
                            {{ __('marketer.auth.email_label') }} <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" dir="ltr"
                            class="{{ $inputBase }} text-left @error('email') border-red-500 @else border-gray-700 focus:border-[#feee00] @enderror">
                        @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Phone + WhatsApp --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('marketer.auth.phone_label') }}</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="+971 50 000 0000"
                                class="{{ $inputBase }} text-left border-gray-700 focus:border-[#feee00]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ __('marketer.auth.whatsapp_label') }}</label>
                            <input type="text" name="whatsapp_for_campaigns" value="{{ old('whatsapp_for_campaigns') }}" dir="ltr"
                                placeholder="+966XXXXXXXXX"
                                class="{{ $inputBase }} text-left border-gray-700 focus:border-[#feee00]">
                        </div>
                    </div>

                    {{-- Password + Confirmation --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                {{ __('marketer.auth.password_label') }} <span class="text-red-400">*</span>
                            </label>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password" dir="ltr"
                                class="{{ $inputBase }} @error('password') border-red-500 @else border-gray-700 focus:border-[#feee00] @enderror">
                            @error('password') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                {{ __('marketer.auth.password_confirmation_label') }} <span class="text-red-400">*</span>
                            </label>
                            <input type="password" name="password_confirmation" required autocomplete="new-password" dir="ltr"
                                class="{{ $inputBase }} border-gray-700 focus:border-[#feee00]">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-[#0F807E] hover:bg-[#0c6665] text-gray-900 font-bold py-3 rounded-xl text-sm transition-colors">
                        {{ __('marketer.auth.create_account') }}
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500 mt-6">
                {{ __('marketer.auth.have_account') }}
                <a href="{{ route('marketer.login') }}" class="text-[#0F807E] font-semibold hover:underline">{{ __('marketer.auth.login_link') }}</a>
            </p>
        </div>
    </div>
@endsection
