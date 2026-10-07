@extends('layouts.marketer')
@section('title', __('marketer.listings.index_title'))
@section('page-title', __('marketer.listings.index_title'))

@section('content')
<div class="space-y-4">

    {{-- Header + Add button --}}
    <div class="flex items-center justify-between">
        <div class="flex flex-wrap gap-2">
            @php
                $filterLabels = [
                    ''               => __('marketer.listings.filter_all'),
                    'active'         => 'نشط',
                    'pending_review' => 'قيد المراجعة',
                    'paused'         => 'موقوف',
                    'rejected'       => 'مرفوض',
                    'draft'          => 'مسودة',
                ];
            @endphp
            @foreach($filterLabels as $s => $label)
                <a href="{{ route('marketer.listings.index', $s ? ['status' => $s] : []) }}"
                   class="px-3 py-1 rounded-full text-xs font-semibold border {{ request('status') === $s || (!request('status') && !$s) ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <a href="{{ route('marketer.listings.create') }}"
           class="px-4 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">
            {{ __('marketer.listings.add_listing_button') }}
        </a>
    </div>

    @if($listings->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📦</div>
            <h3 class="font-bold text-gray-700">{{ __('marketer.listings.no_listings') }}</h3>
            <p class="text-gray-400 text-sm mt-1">{{ __('marketer.listings.no_listings_hint') }}</p>
            <a href="{{ route('marketer.listings.create') }}"
               class="inline-block mt-4 px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">
                {{ __('marketer.listings.add_product_button') }}
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('marketer.listings.product_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.country_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.price_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.sales_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.stock_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.status_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.source_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.listings.actions_header') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($listings as $listing)
                    @php
                        $isClassified = ($listing->listing_category ?? 'product') === 'classified';
                        $product = $isClassified ? null : $listing->productVariant?->product;
                        $img = $isClassified
                            ? null
                            : ($listing->productVariant?->images->first()?->url ?? $product?->images->first()?->url);

                        $priceEditable = true;
                        if ($isClassified) {
                            $classifiedCategoryId = $listing->classifiedListing?->classified_category_id;
                            $listingPrice = $classifiedCategoryId
                                ? \App\Models\OpenMarketListingPrice::where('classified_category_id', $classifiedCategoryId)->first()
                                : null;
                            $priceEditable = (bool) $listingPrice?->allow_marketer_override;
                        }
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($img)
                                    <img src="{{ $img }}" alt="" class="w-10 h-10 object-cover rounded-lg border">
                                @endif
                                <div>
                                    @if($isClassified)
                                        <div class="font-semibold text-gray-900 text-xs">{{ Str::limit($listing->classifiedListing?->title_ar ?? '—', 40) }}</div>
                                        <div class="text-gray-400 text-xs">{{ __('marketer.listings.open_market_ad') }}</div>
                                    @else
                                        <div class="font-semibold text-gray-900 text-xs">{{ Str::limit($product?->name_ar ?? '—', 40) }}</div>
                                        <div class="text-gray-400 text-xs">{{ $listing->productVariant?->sku }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $listing->country->name_ar }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="font-bold text-gray-900">{{ number_format($listing->price) }}</div>
                            <div class="text-xs text-gray-400">{{ $listing->currency }}</div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ number_format($listing->total_sold) }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">
                            @if($listing->invitation_id)
                                <span class="text-blue-500">{{ __('marketer.listings.from_campaign') }}</span>
                            @elseif(!$isClassified)
                                @php $stockQty = $listing->warehouseInventories->sum(fn($wi) => $wi->quantity_on_hand - $wi->quantity_reserved); @endphp
                                <span class="{{ $stockQty <= 0 ? 'text-red-500 font-bold' : ($stockQty <= 5 ? 'text-orange-500 font-semibold' : 'text-gray-700') }}">
                                    {{ number_format($stockQty) }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        @php
                            $statusMap = [
                                'draft'          => ['bg-gray-100 text-gray-500',    'مسودة'],
                                'pending_review' => ['bg-yellow-100 text-yellow-700','قيد المراجعة'],
                                'active'         => ['bg-green-100 text-green-700',  'نشط'],
                                'paused'         => ['bg-gray-100 text-gray-500',    'موقوف'],
                                'rejected'       => ['bg-red-100 text-red-700',      'مرفوض'],
                                'out_of_stock'   => ['bg-orange-100 text-orange-600','نفد المخزون'],
                                'archived'       => ['bg-gray-100 text-gray-400',    'مؤرشف'],
                            ];
                            $sv = $listing->status instanceof \BackedEnum ? $listing->status->value : (string) $listing->status;
                            [$statusClass, $statusLabel] = $statusMap[$sv] ?? ['bg-gray-100 text-gray-500', $sv];
                            $isActive = $sv === 'active';
                        @endphp
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($listing->invitation_id)
                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">{{ __('marketer.listings.source_campaign') }}</span>
                            @else
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded">{{ __('marketer.listings.source_independent') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                {{-- View link --}}
                                <a href="{{ route('marketer.listings.show', $listing) }}"
                                   class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:opacity-80">عرض</a>

                                {{-- Toggle status --}}
                                <form method="POST" action="{{ route('marketer.listings.toggle-status', $listing) }}">
                                    @csrf
                                    <button class="text-xs px-2 py-1 rounded {{ $isActive ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700' }} hover:opacity-80">
                                        {{ $isActive ? __('marketer.listings.toggle_pause') : __('marketer.listings.toggle_activate') }}
                                    </button>
                                </form>

                                <a href="{{ route('marketer.listings.promo-badges.edit', $listing) }}"
                                   class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-700 hover:opacity-80">{{ __('marketer.promo_badges_button') }}</a>

                                {{-- Update price inline --}}
                                @if($priceEditable)
                                <form method="POST" action="{{ route('marketer.listings.update-price', $listing) }}"
                                      x-data="{ open: false }" class="relative">
                                    @csrf @method('PATCH')
                                    <button type="button" @click="open = !open"
                                            class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:opacity-80">
                                        {{ __('marketer.listings.price_button') }}
                                    </button>
                                    <div x-show="open" x-cloak
                                         class="absolute left-0 top-8 z-20 bg-white border rounded-lg shadow p-3 w-44 space-y-2">
                                        <input type="number" name="price" value="{{ $listing->price }}" min="1"
                                               @if($isClassified && $listingPrice?->min_price) min="{{ $listingPrice->min_price }}" @endif
                                               @if($isClassified && $listingPrice?->max_price) max="{{ $listingPrice->max_price }}" @endif
                                               placeholder="{{ __('marketer.listings.price_placeholder') }}" class="w-full border rounded px-2 py-1 text-xs">
                                        <button type="submit" class="w-full bg-yellow-400 text-gray-900 text-xs font-bold py-1 rounded">{{ __('marketer.listings.save_button') }}</button>
                                    </div>
                                </form>
                                @endif

                                {{-- Delete --}}
                                @if(!$listing->invitation_id)
                                <form method="POST" action="{{ route('marketer.listings.destroy', $listing) }}">
                                    @csrf @method('DELETE')
                                    <button onclick="return confirm('{{ __('marketer.listings.confirm_delete') }}')"
                                            class="text-xs px-2 py-1 rounded bg-red-100 text-red-600 hover:opacity-80">
                                        {{ __('marketer.listings.delete_button') }}
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="px-4 py-3 border-t">{{ $listings->withQueryString()->links() }}</div>
        </div>
    @endif
</div>
@endsection
