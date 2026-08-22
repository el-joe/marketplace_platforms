@extends('layouts.admin')

@section('title', __('admin.wishlist_section.group_detail_title'))

@section('content')

    <div class="mb-4">
        <a href="{{ route('admin.wishlist.index') }}" class="text-sm text-primary-600 hover:underline">
            &larr; {{ __('admin.wishlist_section.back_to_list') }}
        </a>
    </div>

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">
            {{ __('admin.wishlist_section.customer_column') }}: {{ $group->customer->name ?? '—' }}
            &nbsp;|&nbsp;
            {{ __('admin.wishlist_section.group_name_column') }}: {{ $group->name }}
            &nbsp;|&nbsp;
            {{ $items->total() }} {{ __('admin.wishlist_section.items_count_suffix') }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">{{ $group->customer->email ?? '' }}</p>
        <div class="flex items-center gap-2 mt-2">
            @if($group->is_default)
                <x-badge color="primary">{{ __('admin.wishlist_section.default_column') }}</x-badge>
            @endif
            <x-badge :color="$group->is_public ? 'success' : 'gray'">
                {{ $group->is_public ? __('admin.wishlist_section.public_column') : __('admin.wishlist_section.private_only') }}
            </x-badge>
        </div>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.thumbnail_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.product_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.listing_type_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.price_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.currency_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.wishlist_section.added_at_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $listing = $item->vendorListing ?? $item->adminListing;
                            $product = $item->productVariant?->product;
                            $thumbnail = $item->productVariant?->images->first()?->url;
                        @endphp
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4">
                                @if($thumbnail)
                                    <img src="{{ $thumbnail }}" alt="" class="w-12 h-12 object-cover rounded border border-gray-100">
                                @else
                                    <div class="w-12 h-12 rounded border border-gray-100 bg-gray-50"></div>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $product->name_en ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if($item->vendorListing)
                                    <x-badge color="primary">{{ __('admin.wishlist_section.vendor_listing') }}</x-badge>
                                @elseif($item->adminListing)
                                    <x-badge color="gray">{{ __('admin.wishlist_section.admin_listing') }}</x-badge>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $listing->price ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $listing->currency ?? '—' }}</td>
                            <td class="py-2">{{ $item->added_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500">{{ __('admin.wishlist_section.no_items') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    </x-card>

@endsection
