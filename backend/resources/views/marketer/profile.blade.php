@extends('layouts.marketer')
@section('title', __('marketer.profile.title'))
@section('page-title', __('marketer.profile.page_title'))

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Profile Info Card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4 mb-6">
            @if($profile->avatarFile)
                <img src="{{ Storage::url($profile->avatarFile->path) }}" alt="{{ $marketer->name }}"
                     class="w-16 h-16 rounded-full object-cover">
            @else
                <div class="w-16 h-16 rounded-full bg-yellow-400 flex items-center justify-center font-black text-2xl text-gray-900">
                    {{ mb_substr($marketer->name, 0, 1) }}
                </div>
            @endif
            <div>
                <div class="font-bold text-gray-900 text-lg">{{ $marketer->name }}</div>
                <div class="text-gray-500 text-sm">{{ $marketer->email }}</div>
                <span class="inline-flex mt-1 px-2 py-0.5 rounded text-xs font-semibold
                    {{ $marketer->isInfluencer() ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $marketer->isInfluencer() ? __('marketer.profile.type_influencer') : __('marketer.profile.type_affiliate') }}
                </span>
            </div>
        </div>

        {{-- QR Code & Referral Slug --}}
        @if($profile->qr_code_path)
        @php $profileUrl = rtrim(config('app.frontend_url', url('')), '/') . '/marketer/' . $profile->profile_slug; @endphp
        <div class="p-4 bg-gray-50 rounded-lg mb-5 space-y-3">
            <div class="flex items-start gap-4">
                <img src="{{ Storage::url($profile->qr_code_path) }}" alt="{{ __('marketer.static_text.marketer_profile.qr_code') }}"
                     class="w-28 h-28 rounded-lg border border-gray-200 shrink-0">
                <div class="flex-1 space-y-2">
                    <div class="text-xs font-semibold text-gray-600">{{ __('marketer.profile.public_profile_url_label') }}</div>
                    <div class="flex items-center gap-2">
                        <code class="text-xs bg-white px-2 py-1 rounded border text-gray-700 flex-1 overflow-auto">{{ $profileUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $profileUrl }}')"
                                class="shrink-0 text-xs px-3 py-1 bg-yellow-400 text-gray-900 font-bold rounded hover:bg-yellow-500">
                            {{ __('marketer.profile.copy_button') }}
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ Storage::url($profile->qr_code_path) }}" download="marketer-qr-{{ $profile->profile_slug }}.png"
                           class="text-xs px-3 py-1.5 bg-gray-800 text-white rounded hover:bg-gray-900">
                            {{ __('marketer.profile.download_qr') }}
                        </a>
                        {{-- WhatsApp share --}}
                        <a href="https://wa.me/?text={{ urlencode(__('marketer.profile.whatsapp_share_text') . ' ' . $profileUrl) }}" target="_blank"
                           class="text-xs px-3 py-1.5 bg-green-500 text-white rounded hover:bg-green-600">
                            {{ __('marketer.profile.share_whatsapp') }}
                        </a>
                        {{-- Twitter/X share --}}
                        <a href="https://x.com/intent/tweet?url={{ urlencode($profileUrl) }}&text={{ urlencode(__('marketer.profile.twitter_share_text')) }}" target="_blank"
                           class="text-xs px-3 py-1.5 bg-black text-white rounded hover:bg-gray-800">
                            X
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('marketer.profile.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.whatsapp_label') }}</label>
                <input type="text" name="whatsapp_for_campaigns" value="{{ old('whatsapp_for_campaigns', $marketer->whatsapp_for_campaigns) }}"
                       placeholder="+966XXXXXXXXX"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.bio_ar_label') }}</label>
                <textarea name="bio_ar" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">{{ old('bio_ar', $profile->bio_ar) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.bio_en_label') }}</label>
                <textarea name="bio_en" rows="3" dir="ltr" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">{{ old('bio_en', $profile->bio_en) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('common.specialty_ar') }}</label>
                <input type="text" name="specialty_ar" maxlength="150" value="{{ old('specialty_ar', $profile->specialty_ar) }}" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('common.specialty_en') }}</label>
                <input type="text" name="specialty_en" dir="ltr" maxlength="150" value="{{ old('specialty_en', $profile->specialty_en) }}" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.video_url_label') }}</label>
                <input type="url" name="video_url" value="{{ old('video_url', $profile->video_url) }}" dir="ltr"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.avatar_label') }}</label>
                @if($profile->avatarFile)
                    <img src="{{ Storage::url($profile->avatarFile->path) }}" class="w-20 h-20 rounded-full mb-2 object-cover">
                @endif
                <input type="file" name="avatar" accept="image/*" class="text-sm text-gray-600">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.banner_label') }}</label>
                @if($profile->bannerFile)
                    <img src="{{ Storage::url($profile->bannerFile->path) }}" class="h-24 rounded-lg mb-2 object-cover w-full">
                @endif
                <input type="file" name="banner" accept="image/*" class="text-sm text-gray-600">
            </div>

            @if($marketer->isAffiliate())
            <div class="border border-gray-200 rounded-lg p-4 space-y-3" id="broker-specialization">
                <div class="font-semibold text-gray-700 text-sm">{{ __('marketer.profile.broker_specialization_heading') }}</div>
                <select name="broker_category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('marketer.profile.no_specialization') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('broker_category_id', $profile->broker_category_id) === $cat->id ? 'selected' : '' }}>{{ $cat->name_ar ?? $cat->name_en }}</option>
                    @endforeach
                </select>
                <select name="broker_city_id" id="brokerCitySelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        {{ old('broker_serves_all_cities', $profile->broker_serves_all_cities) ? 'disabled' : '' }}>
                    <option value="">{{ __('marketer.profile.choose_city') }}</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" {{ old('broker_city_id', $profile->broker_city_id) === $city->id ? 'selected' : '' }}>{{ $city->name_ar ?? $city->name_en }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="broker_serves_all_cities" value="1"
                           {{ old('broker_serves_all_cities', $profile->broker_serves_all_cities) ? 'checked' : '' }}
                           onchange="document.getElementById('brokerCitySelect').disabled = this.checked">
                    {{ __('marketer.profile.serves_all_cities_label') }}
                </label>
            </div>
            @endif

            <button type="submit" class="px-6 py-2.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-lg text-sm">
                {{ __('marketer.profile.save_button') }}
            </button>
        </form>
    </div>

    {{-- Ad display price self-edit (only shown if admin granted permission) --}}
    @if($profile->can_self_edit_ad_price)
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">{{ __('marketer.profile.ad_price_heading') }}</h3>
        <form method="POST" action="{{ route('marketer.profile.ad-price.update') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.ad_price_label') }}</label>
                <input type="number" min="0" name="ad_price" value="{{ old('ad_price', $profile->ad_price) }}"
                       class="border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 w-40">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('marketer.profile.ad_currency_label') }}</label>
                <input type="text" maxlength="3" name="ad_price_currency" value="{{ old('ad_price_currency', $profile->ad_price_currency) }}"
                       placeholder="SAR" class="border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 w-24">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-lg text-sm">
                {{ __('marketer.profile.save_price_button') }}
            </button>
        </form>
    </div>
    @endif

    {{-- Performance summary --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">{{ __('marketer.profile.stats_heading') }}</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-gray-900">{{ number_format($profile->total_campaigns) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.profile.campaigns_stat') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-gray-900">{{ number_format($profile->total_conversions) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.profile.conversions_stat') }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-green-600">{{ number_format($profile->total_earnings) }}</div>
                <div class="text-xs text-gray-500">{{ __('marketer.profile.earnings_stat') }}</div>
            </div>
        </div>
    </div>

</div>
@endsection
