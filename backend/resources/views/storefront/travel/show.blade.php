@extends('layouts.storefront')

@section('title', $package->title_ar ?: $package->title_en)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500">
        <a href="{{ route('travel.index', request()->route('country')) }}" class="hover:text-blue-600">{{ __('portal.travel_page.packages_title') }}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">{{ $package->title_ar ?: $package->title_en }}</span>
    </nav>

    {{-- Media Gallery --}}
    @if($package->media->count())
    <div class="grid grid-cols-3 gap-3 h-80 rounded-2xl overflow-hidden">
        @php $mediaItems = $package->media->take(5); @endphp
        <div class="col-span-2 row-span-2">
            @if($mediaItems->first()?->media_type === 'video')
            <video src="{{ $mediaItems->first()->url() }}" controls class="w-full h-full object-cover"></video>
            @else
            <img src="{{ $mediaItems->first()?->url() }}" class="w-full h-full object-cover">
            @endif
        </div>
        @foreach($mediaItems->skip(1)->take(4) as $m)
        @if($m->media_type === 'image')
        <img src="{{ $m->url() }}" class="w-full h-full object-cover">
        @else
        <video src="{{ $m->url() }}" class="w-full h-full object-cover"></video>
        @endif
        @endforeach
    </div>
    @else
    <div class="w-full h-64 bg-gradient-to-br from-blue-100 to-blue-200 rounded-2xl flex items-center justify-center text-blue-300 text-8xl">✈</div>
    @endif

    <div class="grid grid-cols-3 gap-8">
        {{-- Main info --}}
        <div class="col-span-2 space-y-6">
            <div>
                <h1 class="text-3xl font-black text-gray-900 mb-1">{{ $package->title_ar ?: $package->title_en }}</h1>
                <p class="text-gray-400 text-sm">{{ $package->title_en }}</p>
                <p class="text-blue-600 font-medium mt-2">
                    📍 {{ $package->destination_country }}{{ $package->destination_city ? '، '.$package->destination_city : '' }}
                </p>
            </div>

            {{-- Quick Stats --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-blue-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-black text-blue-600">{{ $package->duration_days }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('portal.travel_page.days') }}</p>
                </div>
                <div class="bg-blue-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-black text-blue-600">{{ $package->duration_nights }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('portal.travel_page.nights') }}</p>
                </div>
                <div class="bg-blue-50 rounded-xl p-4 text-center">
                    @if($package->available_seats !== null)
                    <p class="text-2xl font-black text-blue-600">{{ $package->seatsRemaining() }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('portal.travel_page.seat_available') }}</p>
                    @else
                    <p class="text-2xl font-black text-blue-600">∞</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('portal.travel_page.seats_available_unlimited') }}</p>
                    @endif
                </div>
            </div>

            {{-- Inclusions --}}
            @if($package->inclusions->isNotEmpty())
            <div>
                <h3 class="font-bold text-gray-900 mb-3">{{ __('portal.travel_page.whats_included') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($package->inclusions as $item)
                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-3 py-1 text-sm font-medium">
                        {{ $item->icon ?: '✓' }} {{ $item->name }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Description --}}
            @if($package->description_ar || $package->description_en)
            <div class="prose prose-sm max-w-none">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('portal.travel_page.about_package') }}</h3>
                <p class="text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $package->description_ar ?: $package->description_en }}</p>
            </div>
            @endif

            {{-- Agency --}}
            <div class="bg-gray-50 rounded-xl p-4 flex items-center gap-4">
                @if($package->agency->logoUrl())
                <img src="{{ $package->agency->logoUrl() }}" class="w-12 h-12 rounded-xl object-cover">
                @else
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-lg">
                    {{ mb_substr($package->agency->name, 0, 1) }}
                </div>
                @endif
                <div>
                    <p class="font-semibold text-gray-900">{{ $package->agency->name }}</p>
                    <p class="text-xs text-gray-500">{{ __('portal.travel_page.verified_agency') }}</p>
                </div>
            </div>
        </div>

        {{-- Booking Card --}}
        <div class="col-span-1">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-6 sticky top-24 space-y-4">
                <div>
                    <p class="text-3xl font-black text-gray-900">{{ $package->priceFormatted() }}</p>
                    <p class="text-xs text-gray-400">{{ __('portal.travel_page.per_person') }}</p>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('portal.travel_page.departure') }}</span>
                        <span class="font-medium text-gray-900">{{ $package->departure_date->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>{{ __('portal.travel_page.return') }}</span>
                        <span class="font-medium text-gray-900">{{ $package->return_date->format('d M Y') }}</span>
                    </div>
                </div>

                @if(($package->seatsRemaining() ?? 1) > 0)
                @auth
                <a href="{{ route('travel.book', $package) }}"
                   class="block w-full bg-blue-600 hover:bg-blue-500 text-white text-center font-bold py-3.5 rounded-xl transition-colors">
                    {{ __('portal.travel_page.book_now') }}
                </a>
                @else
                <a href="{{ route('login') }}"
                   class="block w-full bg-blue-600 hover:bg-blue-500 text-white text-center font-bold py-3.5 rounded-xl transition-colors">
                    {{ __('portal.travel_page.login_to_book') }}
                </a>
                @endauth
                @else
                <button disabled class="block w-full bg-gray-200 text-gray-500 text-center font-bold py-3.5 rounded-xl cursor-not-allowed">
                    {{ __('portal.travel_page.seats_full') }}
                </button>
                @endif

                <p class="text-xs text-gray-400 text-center">{{ __('portal.travel_page.passport_note') }}</p>
            </div>
        </div>
    </div>

    {{-- Interest / Lead Inquiry Form --}}
    <div class="bg-white rounded-2xl border border-blue-100 shadow-sm p-6 max-w-lg">
        @if(session('inquiry_sent'))
        <div class="flex items-center gap-3 text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm font-medium">
            <span>✓</span>
            <span>{{ __('portal.travel_page.inquiry_thanks') }}</span>
        </div>
        @else
        <h3 class="text-lg font-bold text-gray-900 mb-1">{{ __('portal.travel_page.interested_in_package') }}</h3>
        <p class="text-sm text-gray-500 mb-4">{{ __('portal.travel_page.inquiry_note') }}</p>

        @if($errors->has('name') || $errors->has('phone') || $errors->has('email') || $errors->has('travelers_count') || $errors->has('message'))
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 space-y-1">
            @foreach(['name','phone','email','travelers_count','message'] as $f)
                @error($f)<p>{{ $message }}</p>@enderror
            @endforeach
        </div>
        @endif

        <form method="POST"
              action="{{ route('travel.packages.inquire', [request()->route('country'), $package]) }}"
              class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('portal.travel_page.name_label') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('name') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('portal.travel_page.phone_label') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('phone') border-red-400 @enderror">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('portal.travel_page.your_email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('email') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('portal.travel_page.travelers_count') }}</label>
                    <input type="number" name="travelers_count" value="{{ old('travelers_count') }}" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('travelers_count') border-red-400 @enderror">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('portal.travel_page.message') }}</label>
                <textarea name="message" rows="2" maxlength="1000"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none resize-none @error('message') border-red-400 @enderror">{{ old('message') }}</textarea>
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                {{ __('portal.travel_page.submit_inquiry') }}
            </button>
        </form>
        @endif
    </div>

</div>
@endsection
