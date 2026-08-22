@extends('layouts.partner')

@section('title', __('partner.marketer_profile.title'))
@section('page-title', __('partner.marketer_profile.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_profile.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_profile.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_profile.stats.total_campaigns') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $profile->total_campaigns ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_profile.stats.total_conversions') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $profile->total_conversions ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_profile.stats.total_earnings') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">
                {{ number_format($profile->total_earnings ?? 0, 2) }} {{ $profile->earnings_currency }}
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('partner.marketer.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">

                {{-- Banner --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <label class="block text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_profile.banner') }}</label>
                    @if ($profile->bannerFile)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profile->bannerFile->path) }}"
                             alt="banner" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-100">
                    @endif
                    <input type="file" name="banner" accept="image/*"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                    @error('banner')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Video --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <label for="video_url" class="block text-sm font-semibold text-gray-900 mb-2">{{ __('partner.marketer_profile.video_url') }}</label>
                    <input type="url" id="video_url" name="video_url" value="{{ old('video_url', $profile->video_url) }}"
                           placeholder="https://youtube.com/..."
                           class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                </div>

                {{-- Bio --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
                    <div>
                        <label for="bio_ar" class="block text-sm font-semibold text-gray-900 mb-2">{{ __('partner.marketer_profile.bio_ar') }}</label>
                        <textarea id="bio_ar" name="bio_ar" rows="3" maxlength="1000"
                                  class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">{{ old('bio_ar', $profile->bio_ar) }}</textarea>
                    </div>
                    <div>
                        <label for="bio_en" class="block text-sm font-semibold text-gray-900 mb-2">{{ __('partner.marketer_profile.bio_en') }}</label>
                        <textarea id="bio_en" name="bio_en" rows="3" maxlength="1000"
                                  class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">{{ old('bio_en', $profile->bio_en) }}</textarea>
                    </div>
                </div>

                {{-- Social links --}}
                @php
                    $socials = [
                        'instagram' => 'Instagram',
                        'tiktok'    => 'TikTok',
                        'youtube'   => 'YouTube',
                        'twitter'   => 'Twitter / X',
                        'facebook'  => 'Facebook',
                        'snapchat'  => 'Snapchat',
                    ];
                    $socialLinks = old('social_links', $profile->social_links ?? []);
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <label class="block text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_profile.social_links') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($socials as $key => $label)
                            <div class="flex items-center gap-2">
                                <span class="w-24 shrink-0 text-xs text-gray-500">{{ $label }}</span>
                                <input type="url" name="social_links[{{ $key }}]" value="{{ $socialLinks[$key] ?? '' }}"
                                       placeholder="https://{{ $key }}.com/..."
                                       class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Contact details --}}
                @php
                    $contacts = [
                        'phone'    => __('partner.marketer_profile.phone'),
                        'whatsapp' => __('partner.marketer_profile.whatsapp'),
                        'email'    => __('partner.marketer_profile.email'),
                        'website'  => __('partner.marketer_profile.website'),
                    ];
                    $contactDetails = old('contact_details', $profile->contact_details ?? []);
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <label class="block text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_profile.contact_details') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($contacts as $key => $label)
                            <div class="flex items-center gap-2">
                                <span class="w-24 shrink-0 text-xs text-gray-500">{{ $label }}</span>
                                <input type="text" name="contact_details[{{ $key }}]" value="{{ $contactDetails[$key] ?? '' }}"
                                       class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    {{ __('partner.marketer_profile.save') }}
                </button>
            </div>

            {{-- QR sidebar --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
                    <label class="block text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_profile.qr_code') }}</label>
                    @if ($profile->qr_code_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profile->qr_code_path) }}" alt="QR"
                             class="mx-auto w-40 h-40 border border-gray-100 rounded-lg">
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profile->qr_code_path) }}" download
                           class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-800">
                            {{ __('partner.marketer_profile.download_qr') }}
                        </a>
                    @else
                        <p class="text-xs text-gray-400">{{ __('partner.marketer_profile.download_qr') }} —</p>
                    @endif
                </div>
            </div>

        </div>
    </form>
</div>
@endsection
