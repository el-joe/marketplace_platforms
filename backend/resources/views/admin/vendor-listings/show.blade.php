@extends('layouts.admin')

@section('title', __('admin.vendor_listings.show_title'))

@section('content')
<div class="p-6 space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $listing->productVariant->product->name_en }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $listing->vendor->business_name }} · {{ $listing->country->name_en }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                onclick="
                    fetch('{{ route('admin.vendor-listings.clear-cache', $listing) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        window.Toastify && window.Toastify({
                            text: '✅ ' + d.message,
                            duration: 3500,
                            backgroundColor: '#10b981'
                        }).showToast();
                    })
                    .catch(() => alert('Failed to clear cache.'));
                "
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Clear Cache
            </button>

            <a href="{{ route('admin.vendor-listings.edit', $listing) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {{ __('common.edit') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- General --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.vendor_listings.general_section') }}</h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.status_col') }}</dt>
                    <dd class="mt-1 text-gray-800">{{ ucfirst(str_replace('_', ' ', $listing->status?->value)) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.price_col') }}</dt>
                    <dd class="mt-1 text-gray-800">{{ number_format($listing->price, 2) }} {{ $listing->currency }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.vendor_sku') }}</dt>
                    <dd class="mt-1 text-gray-800">{{ $listing->vendor_sku ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.warehouse_col') }}</dt>
                    <dd class="mt-1 text-gray-800">{{ $listing->warehouse?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Fulfillment / Shipping --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.vendor_listings.fulfillment_section') }}</h3>
            <dl class="grid grid-cols-1 gap-4 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.primary_shipping_method') }}</dt>
                    <dd class="mt-1">
                        @if($listing->primaryShippingMethod)
                            <x-shipping-method-badge :method="$listing->primaryShippingMethod" />
                        @else
                            <span class="text-gray-800">{{ __('admin.vendor_listings.inherits_category_default') }}</span>
                            <x-shipping-method-badge :method="$categoryDefaultShippingMethod" fallback-text="— none configured —" />
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.vendor_listings.available_shipping_methods') }}</dt>
                    <dd class="mt-2">
                        @if($availableShippingMethods->isEmpty())
                            <span class="text-xs text-gray-400">{{ __('admin.vendor_listings.no_shipping_methods_available') }}</span>
                        @else
                            <ul class="flex flex-wrap gap-3">
                                @foreach($availableShippingMethods as $method)
                                    <li class="flex items-center gap-1.5 text-sm text-gray-700">
                                        <x-shipping-method-badge :method="$method" />
                                        <span>{{ $method->name }}</span>
                                        @if($method->is_default)
                                            <span class="text-[10px] uppercase tracking-wide text-gray-400">(default)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Price History (client feature request doc, section 6) --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.price_history_section.price_history') }}</h3>
            @if($listing->disposable_by_admin)
                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                    ⚠ {{ __('admin.price_history_section.disposable_by_admin') }} — {{ __('admin.price_history_section.disposable_hint') }}
                </span>
            @endif
        </div>
        <dl class="grid grid-cols-2 gap-4 text-sm mb-4">
            <div>
                <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.price_history_section.first_price') }}</dt>
                <dd class="mt-1 text-gray-800">
                    @if($listing->first_price !== null)
                        {{ number_format($listing->first_price, 2) }} {{ $listing->currency }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-400 text-xs uppercase tracking-wide">{{ __('admin.price_history_section.first_price_locked_at') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $listing->first_price_locked_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            </div>
        </dl>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('admin.price_history_section.recorded_at') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('admin.price_history_section.price') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('admin.price_history_section.source') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('admin.price_history_section.recorded_by') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($priceHistory as $entry)
                    <tr>
                        <td class="px-3 py-2 text-gray-800">{{ $entry->recorded_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 text-gray-800">{{ number_format($entry->price, 2) }} {{ $listing->currency }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $entry->source === 'initial' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($entry->source) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ $entry->recorded_by ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-4 text-center text-gray-400">No price history recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-3">
            {{ $priceHistory->onEachSide(1)->links() }}
        </div>
    </div>
</div>
@endsection
