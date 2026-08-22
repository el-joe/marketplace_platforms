@extends('layouts.partner')

@section('title', __('partner.packaging_supplies.title'))
@section('page-title', __('partner.packaging_supplies.title'))

@section('content')

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('partner.packaging_supplies.title') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.packaging_supplies.subtitle') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('partner.packaging-supplies.my-requests') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 bg-white text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                {{ __('partner.packaging_supplies.my_requests') }}
            </a>
            <a href="{{ route('partner.packaging-supplies.request') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ __('partner.packaging_supplies.new_request') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ session('success') }}</div>
    @endif

    @if($supplies->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-10 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25-2.25m-2.25 2.25V6.75M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375C2.754 3.75 2.25 4.254 2.25 4.875v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
            <p class="text-gray-600 font-medium">{{ __('partner.packaging_supplies.no_supplies') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($supplies as $supply)
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors">
                    <div class="p-5 flex gap-4">
                        @if($supply->image_path)
                            <img src="{{ asset($supply->image_path) }}" alt="{{ $supply->name_en }}"
                                 class="w-16 h-16 object-cover rounded-xl shrink-0">
                        @else
                            <div class="w-16 h-16 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 shrink-0 text-2xl">
                                @if($supply->type === \App\Enums\PackagingSupplyType::Box) 📦
                                @elseif($supply->type === \App\Enums\PackagingSupplyType::Bag) 🛍
                                @elseif($supply->type === \App\Enums\PackagingSupplyType::Label) 🏷
                                @elseif($supply->type === \App\Enums\PackagingSupplyType::Tape) 🎗
                                @else 📋
                                @endif
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">{{ $supply->name_en }}</p>
                                    <p class="text-xs text-gray-400 font-arabic">{{ $supply->name_ar }}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $supply->typeBadgeClass() }} shrink-0">{{ ucfirst($supply->type->value) }}</span>
                            </div>
                            @if($supply->size)
                                <p class="text-xs text-gray-500 mt-1">{{ $supply->size }}</p>
                            @endif
                            @php($pricing = $supply->countryPricing->first())
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm font-bold {{ ($pricing && $pricing->isFree()) ? 'text-green-600' : 'text-gray-900' }}">
                                    {{ $pricing?->unit_cost_formatted }}
                                </span>
                                @if($pricing && $pricing->stock_available !== null)
                                    <span class="text-xs text-gray-400">{{ __('partner.packaging_supplies.in_stock', ['count' => number_format($pricing->stock_available)]) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('partner.packaging-supplies.request') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
                {{ __('partner.packaging_supplies.request_supplies') }}
            </a>
        </div>
    @endif

@endsection
