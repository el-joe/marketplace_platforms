@extends('layouts.admin')

@section('title', __('admin.admin_listings.title'))

@section('content')
    <div class="p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ __('admin.admin_listings.title') }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.admin_listings.page_subtitle') }}</p>
            </div>
            <a href="{{ route('admin.admin-listings.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                {{ __('admin.admin_listings.new_listing') }}
            </a>
        </div>

        {{-- Quick stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500">{{ __('admin.admin_listings.total_active_listings') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['active_listings']) }}</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500">{{ __('admin.admin_listings.revenue_from_admin_listings') }}</p>
                @forelse($stats['revenue_by_currency'] as $row)
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ number_format($row->total, 2) }} {{ $row->currency }}</p>
                @empty
                    <p class="mt-1 text-2xl font-bold text-gray-900">—</p>
                @endforelse
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500">{{ __('admin.admin_listings.top_selling_admin_product') }}</p>
                @if($stats['top_selling'])
                    <p class="mt-1 text-sm font-bold text-gray-900 truncate" title="{{ $stats['top_selling']->name_en }}">{{ $stats['top_selling']->name_en }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.admin_listings.units_sold', ['count' => number_format($stats['top_selling']->units_sold)]) }}</p>
                @else
                    <p class="mt-1 text-2xl font-bold text-gray-900">—</p>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500">{{ __('admin.admin_listings.countries_active') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['countries_active']) }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.admin-listings.index') }}"
            class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="{{ __('admin.admin_listings.search_placeholder') }}"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400/40">
            </div>

            <div class="min-w-[160px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.admin_listings.country_col') }}</label>
                <select name="country_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('admin.admin_listings.all_countries') }}</option>
                    @foreach($countries as $id => $name)
                        <option value="{{ $id }}" @selected(request('country_id') == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[160px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.status') }}</label>
                <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <option value="all" @selected(request('status', 'all') === 'all')>{{ __('admin.admin_listings.all_statuses') }}</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 pb-2.5">
                <input type="checkbox" id="daily_deal" name="daily_deal" value="1" @checked(request()->boolean('daily_deal'))
                    class="rounded border-gray-300">
                <label for="daily_deal" class="text-sm text-gray-700">{{ __('admin.admin_listings.daily_deal_only') }}</label>
            </div>

            <button type="submit"
                class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                {{ __('admin.admin_listings.filter') }}
            </button>
        </form>

        {{-- Listings table --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                            <th class="px-4 py-3 font-medium text-left"></th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.product_col') }}</th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.country_col') }}</th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.price_col') }}</th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.stock_col') }}</th>
                            <th class="px-4 py-3 font-medium text-left"></th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.status_col') }}</th>
                            <th class="px-4 py-3 font-medium text-left">{{ __('admin.admin_listings.daily_deal_col') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('admin.admin_listings.actions_col') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($listings as $listing)
                            @php
                                $product = $listing->productVariant->product;
                                $thumbnail = $product->images->first();
                                $inventory = $listing->warehouseInventory;
                                $lowStock = $inventory && $inventory->quantity_available <= $listing->low_stock_threshold;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    @if($thumbnail)
                                        <img src="{{ $thumbnail->url }}" alt="{{ $product->name_en }}"
                                            class="w-[50px] h-[50px] object-cover rounded-lg border border-gray-100">
                                    @else
                                        <div class="w-[50px] h-[50px] rounded-lg bg-gray-100"></div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.admin-listings.edit', $listing) }}" class="block">
                                        <p class="font-bold text-gray-900">{{ $product->name_en }}</p>
                                        <p class="text-xs text-gray-500">{{ $product->name_ar }}</p>
                                        @if($listing->productVariant->variant_name)
                                            <p class="text-xs text-blue-600">{{ $listing->productVariant->variant_name }}</p>
                                        @endif
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $listing->country->flag_emoji }} {{ $listing->country->name_en }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ number_format($listing->price) }} {{ $listing->currency }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="{{ $lowStock ? 'text-red-600' : 'text-green-600' }} font-semibold">
                                        {{ $inventory?->quantity_available ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                                        ⚡ {{ $listing->express_badge_label_en }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-700',
                                            'paused' => 'bg-yellow-100 text-yellow-700',
                                            'out_of_stock' => 'bg-gray-100 text-gray-700',
                                            'archived' => 'bg-red-100 text-red-700',
                                        ];
                                    @endphp
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $statusColors[$listing->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ __('admin.admin_listings.' . $listing->status->value) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($listing->is_daily_deal)
                                        <span class="bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-0.5 rounded">
                                            🔥 Deal {{ $listing->daily_deal_ends_at ? '· ends '.$listing->daily_deal_ends_at->diffForHumans() : '' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('admin.admin-listings.edit', $listing) }}"
                                            class="text-blue-600 hover:text-blue-800 text-xs font-semibold">{{ __('common.edit') }}</a>

                                        <form method="POST" action="{{ route('admin.admin-listings.toggle-status', $listing) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-gray-600 hover:text-gray-900 text-xs font-semibold">
                                                {{ $listing->status->value === 'active' ? __('admin.admin_listings.paused') : __('admin.admin_listings.active') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.admin-listings.destroy', $listing) }}" class="inline"
                                            onsubmit="return confirm('{{ __('admin.admin_listings.remove_listing_confirm') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-semibold">{{ __('common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-400">
                                    {{ __('admin.admin_listings.no_listings_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $listings->links() }}
            </div>
        </div>
    </div>
@endsection
