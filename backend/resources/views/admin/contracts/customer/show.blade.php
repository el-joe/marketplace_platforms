@extends('layouts.admin')
@section('title', __('contracts.customer.show_title'))

@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-6">
    <div>
        <a href="{{ route('admin.contracts.customer.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $contract->template?->name }} <span class="text-base font-normal text-gray-500">v{{ $contract->template_version }}</span></h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('contracts.customer.kind_note') }}</p>
    </div>

    @php($countryName = $contract->country ? (app()->getLocale() === 'ar' ? $contract->country->name_ar : $contract->country->name_en) : '—')
    @php($categoryName = $contract->classifiedCategory ? (app()->getLocale() === 'ar' ? $contract->classifiedCategory->name_ar : $contract->classifiedCategory->name_en) : '—')
    <dl class="grid gap-4 rounded-xl border bg-white p-5 text-sm sm:grid-cols-2">
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.col_customer') }}</dt><dd class="font-medium text-gray-900">{{ $contract->customer_name }} <span class="font-normal text-gray-500">{{ $contract->customer_email }}</span></dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.col_signer') }}</dt><dd class="font-medium text-gray-900">{{ $contract->signer_name }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.col_listing') }}</dt><dd class="font-medium text-gray-900">{{ $contract->listing_number }} · {{ $contract->listing_title }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.col_purpose') }}</dt><dd class="font-medium text-gray-900">{{ __('contracts.customer.purpose_'.$contract->purpose) }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('admin.contracts.col_category') }}</dt><dd class="font-medium text-gray-900">{{ $categoryName }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.admin.col_country') }}</dt><dd class="font-medium text-gray-900">{{ $countryName }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.col_signed_at') }}</dt><dd class="font-medium text-gray-900">{{ $contract->signed_at?->format('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('admin.contracts.col_status') }}</dt><dd class="font-medium text-gray-900">{{ __('contracts.customer.status_'.$contract->status) }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('admin.contracts.col_language') }}</dt><dd class="font-medium uppercase text-gray-900">{{ $contract->language_signed }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.ip') }}</dt><dd class="font-medium text-gray-900">{{ $contract->signed_ip ?? '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ __('contracts.customer.user_agent') }}</dt><dd class="break-all text-gray-700">{{ $contract->signed_user_agent ?? '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ __('contracts.customer.hash') }}</dt><dd class="break-all font-mono text-xs text-gray-700">{{ $contract->rendered_content_hash }}</dd></div>
        @if ($contract->classified_inquiry_id)
            <div><dt class="text-xs text-gray-500">{{ __('contracts.customer.inquiry') }}</dt><dd class="text-gray-700">{{ $contract->inquiry?->created_at?->format('Y-m-d H:i') }}</dd></div>
        @endif
    </dl>

    <div class="rounded-xl border bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold text-gray-800">{{ __('contracts.customer.accepted_text') }}</h2>
        @if ($contract->hasSignedFile())
            <a href="{{ route('admin.contracts.customer.file', $contract) }}" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:underline">{{ __('admin.contracts.download_signed') }}: {{ $contract->signed_file_name }}</a>
        @else
            <div class="prose max-w-none text-sm" @if ($contract->language_signed === 'ar') dir="rtl" @endif>{!! $contract->rendered_content !!}</div>
        @endif
    </div>
</div>
@endsection
