@extends('layouts.partner')
@section('title', __('partner.contracts.history_title'))
@section('page-title', __('partner.contracts.history_title'))

@section('content')
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-start text-xs font-medium uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_name') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_version') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_scope') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_category') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_language') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_status') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_accepted_at') }}</th>
                <th class="px-4 py-3">{{ __('partner.contracts.col_ip') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($contracts as $contract)
                @php($category = $contract->category_scope === 'product' ? $contract->productCategory : $contract->classifiedCategory)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500">{{ $loop->iteration + ($contracts->currentPage() - 1) * $contracts->perPage() }}</td>
                    <td class="px-4 py-3">
                        @if ($contract->contractTemplate)
                            <a href="{{ route('partner.contracts.signed', $contract) }}" class="text-blue-600 hover:underline">{{ $contract->contractTemplate->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3">v{{ $contract->template_version }}</td>
                    <td class="px-4 py-3">{{ __('partner.contracts.scope_' . $contract->category_scope) }}</td>
                    <td class="px-4 py-3">{{ $contract->product?->name_en ?? $category?->name_en ?? "—" }}</td>
                    <td class="px-4 py-3 uppercase">{{ $contract->language_signed }}</td>
                    <td class="px-4 py-3">{{ __('partner.contracts.status_' . $contract->status) }}</td>
                    <td class="px-4 py-3">{{ format_tz(vendor_tz($contract->signed_at)) }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $contract->signed_ip ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('partner.contracts.history_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $contracts->links() }}</div>
@endsection
