@extends('layouts.admin')
@section('title', __('admin.contracts.signatures_title', ['name' => $contractTemplate->name, 'version' => $contractTemplate->version]))

@section('content')
<div class="space-y-5 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.contracts.signatures_title', ['name' => $contractTemplate->name, 'version' => $contractTemplate->version]) }}</h1>
            <p class="text-sm text-gray-500">{{ __('admin.contracts.signatures_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.contracts.templates.index', ['scope' => $contractTemplate->category_scope]) }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-xl border bg-white p-4"><p class="text-xs text-gray-500">{{ __('admin.contracts.summary_total') }}</p><p class="text-2xl font-semibold">{{ $summary['total'] }}</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs text-gray-500">{{ __('admin.contracts.summary_signed') }}</p><p class="text-2xl font-semibold text-emerald-700">{{ $summary['signed'] }}</p></div>
        <div class="rounded-xl border bg-white p-4"><p class="text-xs text-gray-500">{{ __('admin.contracts.summary_pending') }}</p><p class="text-2xl font-semibold text-amber-600">{{ $summary['pending'] }}</p></div>
    </div>

    <div class="flex gap-2 text-sm">
        @foreach (['all' => 'filter_all', 'signed' => 'filter_signed', 'pending' => 'filter_pending'] as $value => $label)
            <a href="{{ route('admin.contracts.templates.signatures', ['contractTemplate' => $contractTemplate, 'status' => $value]) }}"
               class="rounded-full px-3 py-1 {{ $filter === $value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ __('admin.contracts.' . $label) }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_vendor') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_email') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_listings') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_by') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_language') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_at') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_ip') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    @php($signature = $row['signature'])
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row['vendor']->store_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row['vendor']->email }}</td>
                        <td class="px-4 py-3 text-center">{{ $row['listings'] }}</td>
                        <td class="px-4 py-3">
                            @if ($signature)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('admin.contracts.status_signed') }}</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('admin.contracts.status_pending') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $signature?->signer_name ?? '—' }}</td>
                        <td class="px-4 py-3 uppercase">{{ $signature?->language_signed ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $signature?->signed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $signature?->signed_ip ?? '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.vendors.contracts.index', $row['vendor']) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('admin.vendor_contracts.button') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('admin.contracts.signatures_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
