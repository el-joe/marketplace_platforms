@extends('layouts.partner')
@section('title', 'Nawi Ads')
@section('page-title', 'Nawi Ads — Boost your listings')

@section('content')

    <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
        Ad packages are legacy. Boost your listings from <a class="underline font-semibold" href="{{ route('partner.ad-slots.index') }}">Ad Slots</a>.
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        @foreach ($packages as $package)
            <div class="bg-white rounded-2xl border-2 border-gray-200 p-5">
                <span class="text-xs font-bold rounded-full px-2.5 py-0.5 bg-indigo-100 text-indigo-700">
                    {{ $package->tier === 'serious_featured' ? 'إعلان جاد ومميز' : 'إعلان جاد' }}
                </span>
                <p class="text-sm font-semibold text-gray-800 mt-2">{{ $package->name_en }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $package->description_en }}</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-3">
                    {{ $package->priceFormatted() }}
                    <span class="text-sm font-medium text-gray-400">/mo</span>
                </p>
                <a href="{{ route('partner.ad-slots.index') }}" class="btn btn-primary btn-sm w-full mt-3">Book via Ad Slots</a>
            </div>
        @endforeach
    </div>

@endsection

