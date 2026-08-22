@extends('layouts.admin')

@section('title', __('admin.vendor_product_certifications.details'))

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-red-100 text-red-700',
        'expired' => 'bg-gray-100 text-gray-500',
    ];
@endphp

<div class="p-6 space-y-4">
    <div class="mb-2">
        <a href="{{ route('admin.vendor-product-certifications.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.vendor_product_certifications.back') }}</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-2.5">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.vendor_product_certifications.details') }}</h1>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$certification->status] ?? '' }}">
                {{ __('admin.vendor_product_certifications.' . $certification->status) }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-8 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.product') }}</dt>
                        <dd class="mt-1 flex items-center gap-2">
                            @if($certification->product?->primaryImage?->first()?->url)
                                <img src="{{ $certification->product->primaryImage->first()->url }}" alt="" class="w-8 h-8 rounded object-cover border border-gray-200">
                            @endif
                            <span class="font-medium text-gray-900">{{ $certification->product?->name_en ?? '—' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.vendor') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $certification->vendor?->store_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.country_column') }}</dt>
                        <dd class="mt-1 text-gray-700">{{ $certification->country?->name_en ?? '—' }}</dd>
                    </div>
                    @if($certification->cert_name)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.cert_name') }}</dt>
                            <dd class="mt-1 text-gray-700">{{ $certification->cert_name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.uploaded_at') }}</dt>
                        <dd class="mt-1 text-gray-700">{{ $certification->created_at->format('d M Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.uploaded_file') }}</dt>
                        <dd class="mt-1">
                            <a href="{{ $downloadUrl }}" class="text-primary-600 font-medium hover:underline">
                                {{ $certification->original_filename ?? __('admin.vendor_product_certifications.download') }}
                            </a>
                        </dd>
                    </div>
                    @if($certification->status === 'approved' && $certification->expires_at)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.expires_at') }}</dt>
                            <dd class="mt-1 text-gray-700">{{ $certification->expires_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if($certification->status === 'rejected' && $certification->rejection_reason)
                        <div class="col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.rejection_reason') }}</dt>
                            <dd class="mt-1 text-red-700">{{ $certification->rejection_reason }}</dd>
                        </div>
                    @endif
                    @if($certification->reviewed_at)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.reviewed_by') }}</dt>
                            <dd class="mt-1 text-gray-700">{{ $certification->reviewedByAdmin?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.vendor_product_certifications.reviewed_at') }}</dt>
                            <dd class="mt-1 text-gray-700">{{ $certification->reviewed_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if($certification->status === 'pending')
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.vendor_product_certifications.approve') }}</h2>
                <form method="POST" action="{{ route('admin.vendor-product-certifications.approve', $certification->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_product_certifications.expires_at_label') }}</label>
                        <input type="date" name="expires_at" min="{{ now()->addDay()->format('Y-m-d') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-full">
                        @error('expires_at')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">
                        {{ __('admin.vendor_product_certifications.approve') }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.vendor_product_certifications.reject') }}</h2>
                <form method="POST" action="{{ route('admin.vendor-product-certifications.reject', $certification->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_product_certifications.rejection_reason_label') }}</label>
                        <textarea name="rejection_reason" rows="3" maxlength="500" required
                                  placeholder="{{ __('admin.vendor_product_certifications.rejection_reason_placeholder') }}"
                                  class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-full resize-none">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">
                        {{ __('admin.vendor_product_certifications.reject') }}
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
