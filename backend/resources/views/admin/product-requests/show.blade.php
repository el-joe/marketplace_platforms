@extends('layouts.admin')

@section('title', __('admin.product_requests.details') . ' — ' . $productRequest->request_number)

@section('content')
@php
    $isAr = app()->getLocale() === 'ar';
    $canReview = auth('admin')->user()?->can('product_requests.review');
    $requester = $productRequest->requestedBy;
    $imageUrls = $productRequest->imageUrls();
@endphp

<div class="p-6 space-y-4">
    <div class="mb-2">
        <a href="{{ route('admin.product-requests.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.product_requests.back') }}</a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $productRequest->displayName() }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="font-mono text-xs text-gray-600">{{ $productRequest->request_number }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $productRequest->status->color() }}">
                    {{ $productRequest->status->label() }}
                </span>
            </div>
        </div>
    </div>

    @if($existingGtinProduct)
        <div class="rounded-lg bg-warning-50 border border-warning-200 text-warning-800 text-sm px-4 py-3">
            {{ __('admin.product_requests.gtin_exists_warning', ['name' => $isAr ? ($existingGtinProduct->name_ar ?: $existingGtinProduct->name_en) : $existingGtinProduct->name_en]) }}
            <a href="{{ route('admin.products.edit', $existingGtinProduct->id) }}" class="font-medium underline ms-1">{{ __('admin.product_requests.open_product') }}</a>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        {{-- ─── Request details ─────────────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-8 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.name_en') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900" dir="ltr">{{ $productRequest->name_en }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.name_ar') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900" dir="rtl">{{ $productRequest->name_ar }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.category') }}</dt>
                        <dd class="mt-1 text-gray-700">{{ $isAr ? ($productRequest->category?->name_ar ?: $productRequest->category?->name_en) : ($productRequest->category?->name_en ?? '—') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.brand') }}</dt>
                        <dd class="mt-1 text-gray-700">
                            @if($productRequest->brand)
                                {{ $isAr ? ($productRequest->brand->name_ar ?: $productRequest->brand->name_en) : $productRequest->brand->name_en }}
                            @elseif($productRequest->brand_name)
                                {{ $productRequest->brand_name }}
                                <span class="text-xs text-amber-600">({{ __('admin.product_requests.brand_not_listed') }})</span>
                            @else
                                <span class="text-gray-400">{{ __('admin.product_requests.no_brand') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.gtin') }}</dt>
                        <dd class="mt-1 font-mono text-gray-700" dir="ltr">{{ $productRequest->gtin ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.model_number') }}</dt>
                        <dd class="mt-1 text-gray-700" dir="ltr">{{ $productRequest->model_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.country') }}</dt>
                        <dd class="mt-1 text-gray-700">{{ $productRequest->country ? ($isAr ? ($productRequest->country->name_ar ?: $productRequest->country->name_en) : $productRequest->country->name_en) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.reference_url') }}</dt>
                        <dd class="mt-1 text-gray-700 break-all">
                            @if($productRequest->linkableReferenceUrl())
                                <a href="{{ $productRequest->linkableReferenceUrl() }}" target="_blank" rel="noopener noreferrer nofollow" class="text-primary-600 hover:underline" dir="ltr">{{ $productRequest->reference_url }}</a>
                            @elseif($productRequest->reference_url)
                                <span dir="ltr">{{ $productRequest->reference_url }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.description') }}</dt>
                        <dd class="mt-1 text-gray-700 whitespace-pre-line">{{ $productRequest->description }}</dd>
                    </div>
                    @if($productRequest->variants_note)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.variants_note') }}</dt>
                            <dd class="mt-1 text-gray-700 whitespace-pre-line">{{ $productRequest->variants_note }}</dd>
                        </div>
                    @endif
                    @if($productRequest->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('product_requests.fields.notes') }}</dt>
                            <dd class="mt-1 text-gray-700 whitespace-pre-line">{{ $productRequest->notes }}</dd>
                        </div>
                    @endif
                    @if($productRequest->rejection_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.product_requests.rejection_reason') }}</dt>
                            <dd class="mt-1 text-red-700 whitespace-pre-line">{{ $productRequest->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- ─── Images ──────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.product_requests.images') }}</h2>
                @if($imageUrls->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('admin.product_requests.no_images') }}</p>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($imageUrls as $imageUrl)
                            <a href="{{ $imageUrl }}" target="_blank" rel="noopener" class="block aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                                <img src="{{ $imageUrl }}" alt="{{ $productRequest->name_en }}" class="w-full h-full object-contain" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ─── Sidebar: requester, review ─────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('admin.product_requests.requester') }}</dt>
                        <dd class="font-medium text-gray-900 text-end">
                            @if($productRequest->isFromVendor() && $productRequest->vendor_id)
                                <a href="{{ route('admin.vendors.show', $productRequest->vendor_id) }}" class="text-primary-600 hover:underline">{{ $productRequest->requesterName() }}</a>
                            @elseif($productRequest->isFromMarketer() && $productRequest->marketer_id)
                                <a href="{{ route('admin.marketers.show', $productRequest->marketer_id) }}" class="text-primary-600 hover:underline">{{ $productRequest->requesterName() }}</a>
                            @else
                                {{ $productRequest->requesterName() }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('admin.product_requests.requester_type') }}</dt>
                        <dd class="text-gray-900">{{ __('product_requests.requester_types.' . $productRequest->requester_type) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('admin.product_requests.submitted_by') }}</dt>
                        <dd class="text-gray-900 text-end">
                            {{ $requester?->name ?? '—' }}
                            @if($requester?->email)
                                <div class="text-xs text-gray-500" dir="ltr">{{ $requester->email }}</div>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('admin.product_requests.submitted_at') }}</dt>
                        <dd class="text-gray-900">{{ admin_tz($productRequest->created_at)?->format('d M Y H:i') }}</dd>
                    </div>
                    @if($productRequest->reviewed_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.product_requests.reviewed_by') }}</dt>
                            <dd class="text-gray-900">{{ $productRequest->reviewer?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.product_requests.reviewed_at') }}</dt>
                            <dd class="text-gray-900">{{ admin_tz($productRequest->reviewed_at)?->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                    @if($productRequest->product)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.product_requests.created_product') }}</dt>
                            <dd class="text-end">
                                <a href="{{ route('admin.products.edit', $productRequest->product->id) }}" class="text-primary-600 font-medium hover:underline">
                                    {{ $isAr ? ($productRequest->product->name_ar ?: $productRequest->product->name_en) : $productRequest->product->name_en }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if($productRequest->isPending() && $canReview)
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_requests.review_actions') }}</h2>
                    @if(auth('admin')->user()?->can('products.create'))
                        <a href="{{ route('admin.product-requests.approve', $productRequest->id) }}"
                           class="block w-full text-center px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
                            {{ __('admin.product_requests.approve') }}
                        </a>
                        <p class="text-xs text-gray-500">{{ __('admin.product_requests.approve_hint') }}</p>
                    @endif
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ open: {{ $errors->has('rejection_reason') ? 'true' : 'false' }} }">
                    <button type="button" x-show="!open" @click="open = true"
                            class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50">
                        {{ __('admin.product_requests.reject') }}
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('admin.product-requests.reject', $productRequest->id) }}" class="space-y-3"
                          onsubmit="return confirm(@js(__('admin.product_requests.reject_confirm')))">
                        @csrf
                        <div>
                            <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.product_requests.rejection_reason') }}</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="4" minlength="10" maxlength="1000" required
                                      placeholder="{{ __('admin.product_requests.rejection_reason_placeholder') }}"
                                      class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-full resize-none">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="open = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('admin.product_requests.cancel') }}</button>
                            <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">{{ __('admin.product_requests.reject') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
