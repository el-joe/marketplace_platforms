@extends('layouts.admin')

@section('title', __('admin.products.add_product'))

@push('styles')
@vite([
    'resources/js/components/rich-editor.js',
    'resources/js/components/file-upload.js',
    'resources/js/components/slug-input.js',
    'resources/js/components/select2.js',
    'resources/js/admin/products.js',
])
@endpush

@section('content')
<form
    id="product-form"
    method="POST"
    action="{{ route('admin.products.store') }}"
    data-validate-url="{{ route('admin.products.validate') }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @isset($productRequest)
        {{-- Completing a vendor/marketer product request: saving the product approves it --}}
        <input type="hidden" name="product_request_id" value="{{ $productRequest->id }}">
        <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-medium">{{ __('admin.product_requests.completing_banner', ['number' => $productRequest->request_number, 'requester' => $productRequest->requesterName()]) }}</p>
                <p class="text-xs text-primary-700 mt-0.5">{{ __('admin.product_requests.completing_banner_hint') }}</p>
            </div>
            <a href="{{ route('admin.product-requests.show', $productRequest->id) }}" class="text-sm font-medium underline whitespace-nowrap">{{ __('admin.product_requests.view_request') }}</a>
        </div>
    @endisset
    @include('admin.products._form', ['mode' => 'create', 'prefill' => $prefill ?? [], 'prefillImages' => $prefillImages ?? []])
</form>
@endsection
