@extends('layouts.admin')
@section('title', __('admin.promotion_products.title'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.promotion_products.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.promotion_products.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.admin-listings.create', ['promotion_category' => $category->value]) }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('admin.promotion_products.new_product') }}</a>
    </div>

    @if (session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="flex gap-2">
        @foreach ($categories as $c)
            <a href="{{ route('admin.promotion-products.index', ['category' => $c->value]) }}"
               class="rounded-lg px-4 py-2 text-sm font-medium border {{ $c === $category ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300' }}">
                {{ $c->value }} — {{ $c->label() }}
            </a>
        @endforeach
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold mb-1">{{ __('admin.promotion_products.convert_title') }}</h2>
        <p class="text-xs text-gray-500 mb-3">{{ __('admin.promotion_products.convert_hint') }}</p>
        <form method="GET" class="flex gap-2 mb-4">
            <input type="hidden" name="category" value="{{ $category->value }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.promotion_products.search_placeholder') }}" class="w-72 rounded-md border-gray-300 text-sm">
            <button class="rounded-md bg-gray-800 px-3 py-2 text-xs font-medium text-white">{{ __('common.search') }}</button>
        </form>
        @if (request()->filled('search'))
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <tbody class="divide-y divide-gray-100">
            @forelse ($candidates as $listing)
                <tr>
                    <td class="px-3 py-2">{{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en }}</td>
                    <td class="px-3 py-2 text-gray-500">{{ $listing->country?->name_en }}</td>
                    <td class="px-3 py-2 text-gray-500">{{ $listing->promotion_category ? $listing->promotion_category->value.' — '.$listing->promotion_category->label() : '—' }}</td>
                    <td class="px-3 py-2 text-end">
                        <form method="POST" action="{{ route('admin.promotion-products.assign') }}">
                            @csrf
                            <input type="hidden" name="admin_listing_id" value="{{ $listing->id }}">
                            <input type="hidden" name="promotion_category" value="{{ $category->value }}">
                            <button class="text-xs font-medium text-primary-600 hover:underline">{{ $listing->promotion_category ? __('admin.promotion_products.move_here') : __('admin.promotion_products.convert') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="px-3 py-4 text-center text-gray-400">{{ __('admin.promotion_products.no_results') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold mb-3">{{ $category->value }} — {{ $category->label() }} ({{ $assigned->total() }})</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('admin.promotion_products.product') }}</th>
                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('admin.marketer_settings.country_column') }}</th>
                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('common.status') }}</th>
                <th></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($assigned as $listing)
                <tr>
                    <td class="px-3 py-2"><a class="text-primary-600 hover:underline" href="{{ route('admin.admin-listings.show', $listing) }}">{{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en }}</a></td>
                    <td class="px-3 py-2">{{ $listing->country?->name_en }}</td>
                    <td class="px-3 py-2">{{ $listing->status?->value }}</td>
                    <td class="px-3 py-2 text-end">
                        <form method="POST" action="{{ route('admin.promotion-products.remove', $listing) }}">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600 hover:underline">{{ __('admin.promotion_products.remove') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">{{ __('admin.promotion_products.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $assigned->links() }}</div>
    </div>
</div>
@endsection
