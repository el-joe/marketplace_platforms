@extends('layouts.partner')

@section('title', __('partner.listings.title'))
@section('page-title', __('partner.listings.title'))

@push('scripts')
    @vite('resources/js/partner/listings.js')
    <script>
        window.LISTINGS = {
            datatableUrl: '{{ route('partner.listings.datatable') }}',
            statusFilter: '{{ request('status', 'all') }}',
            createUrl: '{{ Route::has('partner.listings.create') ? route('partner.listings.create') : '#' }}',
        };
    </script>
@endpush

@section('content')

    {{-- Status filter tabs --}}
    <div class="bg-white rounded-2xl border border-gray-200 mb-4">
        <div class="flex items-center overflow-x-auto">

            <a href="{{ route('partner.listings.index') }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                'border-yellow-400 text-yellow-600' => !request('status'),
                'border-transparent text-gray-500 hover:text-gray-700' => request('status')
            ])>
                {{ __('common.all') }}
                <span class="mr-1 text-xs text-gray-400">({{ $counts->sum() }})</span>
            </a>

            @php
                $tabMap = [
                    'active' => ['label' => __('common.active'), 'color' => 'text-green-600'],
                    'paused' => ['label' => __('partner.listings.paused'), 'color' => 'text-gray-600'],
                    'pending_review' => ['label' => __('common.pending'), 'color' => 'text-yellow-600'],
                    'draft' => ['label' => __('common.draft'), 'color' => 'text-gray-500'],
                    'rejected' => ['label' => __('common.rejected'), 'color' => 'text-red-600'],
                    'out_of_stock' => ['label' => __('partner.listings.out_of_stock'), 'color' => 'text-red-500'],
                    'archived' => ['label' => __('common.archived'), 'color' => 'text-gray-400'],
                ];
            @endphp

            @foreach($tabMap as $key => $tab)
                <a href="{{ route('partner.listings.index', ['status' => $key]) }}" @class([
                    'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                    'border-yellow-400 ' . $tab['color'] => request('status') === $key,
                    'border-transparent text-gray-500 hover:text-gray-700' => request('status') !== $key
                ])>
                    {{ $tab['label'] }}
                    @if(($counts[$key] ?? 0) > 0)
                        <span class="mr-1 text-xs text-gray-400">({{ $counts[$key] }})</span>
                    @endif
                </a>
            @endforeach

            {{-- Low stock special tab --}}
            <a href="{{ route('partner.listings.index', ['status' => 'low_stock']) }}" @class([
                'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1',
                'border-orange-400 text-orange-600' => request('status') === 'low_stock',
                'border-transparent text-gray-500 hover:text-gray-700' => request('status') !== 'low_stock'
            ])>
                ⚠ {{ __('partner.listings.low_stock') }}
                @if($lowStockCount > 0)
                    <span
                        class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $lowStockCount }}</span>
                @endif
            </a>

        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <div class="relative">
                <input type="text" id="listing-search" placeholder="{{ __('partner.listings.search_placeholder') }}"
                    value="{{ request('search') }}"
                    class="w-64 border border-gray-200 rounded-xl pr-9 pl-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                <svg class="w-4 h-4 text-gray-400 absolute top-2.5 right-2.5 pointer-events-none" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
        <div class="flex items-center gap-2">
            {{-- Bulk action buttons (shown only when rows selected) --}}
            <div id="bulk-actions" class="hidden flex items-center gap-2">
                <span id="bulk-count" class="text-sm text-gray-500"></span>
                <button id="bulk-activate"
                    class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">{{ __('partner.listings.activate') }}</button>
                <button id="bulk-pause"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">{{ __('partner.listings.pause_temporarily') }}</button>
                <button id="bulk-archive"
                    class="bg-gray-500 hover:bg-gray-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">{{ __('partner.listings.archive') }}</button>
            </div>
            <a href="{{ route('partner.listings.create') }}"
                class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('partner.listings.add_listing') }}
            </a>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table id="listings-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 w-10 font-medium">
                        <input type="checkbox" id="select-all-listings" class="rounded border-gray-300">
                    </th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.listings.product') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('partner.listings.variant_sku') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 text-right font-semibold tracking-wide">{{ __('common.price') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.listings.available') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.listings.sold') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide text-center">{{ __('partner.listings.rating') }}</th>
                    <th class="px-4 py-3 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100"></tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50" id="listings-table-footer">
            <span id="listings-info" class="text-xs text-gray-400"></span>
            <div id="listings-pagination" class="flex items-center gap-1"></div>
        </div>
    </div>

    {{-- Inline price edit modal --}}
    <div id="price-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.listings.edit_price') }}</h3>
                <button id="price-modal-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="price-form" class="p-5 space-y-4">
                <input type="hidden" id="price-listing-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.new_price') }}</label>
                    <input type="number" id="price-input" name="price" step="1" min="1"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        placeholder="0">
                </div>
                <div id="price-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('common.save') }}</button>
                    <button type="button" id="price-cancel-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection