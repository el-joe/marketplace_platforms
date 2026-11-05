@extends('layouts.admin')
@section('title', __('admin.vendor_contracts.title', ['vendor' => $vendor->store_name]))

@section('content')
<div class="space-y-6 p-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.vendor_contracts.title', ['vendor' => $vendor->store_name]) }}</h1>
        <p class="text-sm text-gray-500">{{ __('admin.vendor_contracts.subtitle') }}</p>
    </div>

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-gray-700">{{ __('admin.vendor_contracts.enrollments') }}</h2>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_scope') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_category') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.vendor_contracts.col_signed_at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($enrollments as $enrollment)
                        @php($category = $enrollment->category_scope === 'product' ? $enrollment->productCategory : $enrollment->classifiedCategory)
                        <tr>
                            <td class="px-4 py-3">{{ __('admin.contracts.scope_' . $enrollment->category_scope) }}</td>
                            <td class="px-4 py-3">{{ $category?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3">{{ __('admin.vendor_contracts.enrollment_' . $enrollment->status) }}</td>
                            <td class="px-4 py-3">{{ $enrollment->signed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('admin.vendor_contracts.no_enrollments') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-gray-700">{{ __('admin.vendor_contracts.signatures') }}</h2>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_contract') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_scope') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_category') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_language') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_by') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_at') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_ip') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_method') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($contracts as $contract)
                        @php($category = $contract->category_scope === 'product' ? $contract->productCategory : $contract->classifiedCategory)
                        <tr>
                            <td class="px-4 py-3">{{ $contract->contractTemplate?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">v{{ $contract->template_version }}</td>
                            <td class="px-4 py-3">{{ __('admin.contracts.scope_' . $contract->category_scope) }}</td>
                            <td class="px-4 py-3">{{ $contract->product?->name_en ?? $category?->name_en ?? "—" }}</td>
                            <td class="px-4 py-3 uppercase">{{ $contract->language_signed }}</td>
                            <td class="px-4 py-3">{{ $contract->signer_name }}</td>
                            <td class="px-4 py-3">{{ $contract->signed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $contract->signed_ip ?? '—' }}</td>
                            <td class="px-4 py-3">{{ __('admin.contracts.sig_' . $contract->status) }}</td>
                            <td class="px-4 py-3">{{ __('admin.contracts.method_' . $contract->acceptance_method) }}</td>
                            <td class="px-4 py-3 text-end">@if ($contract->hasSignedFile())<a href="{{ route('admin.contracts.signatures.download', $contract) }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('admin.contracts.download_signed') }}</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-6 text-center text-gray-400">{{ __('admin.vendor_contracts.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $contracts->links() }}</div>
    </section>

    <section class="space-y-3" data-testid="vendor-marketer-contracts">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('contracts.marketer.vendor_profile_title') }}</h2>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600"><tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_marketer') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_campaign') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_signed_at') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_status') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($marketerContracts as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->template?->name }} <span class="text-xs text-gray-400">v{{ $row->template_version }}</span></td>
                            <td class="px-4 py-3">{{ $row->marketer?->name ?? __('contracts.marketer.open_call') }}</td>
                            <td class="px-4 py-3">{{ $row->campaign?->title ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row->signed_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center">{{ __('contracts.marketer.status_'.$row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('contracts.marketer.no_signed_seller') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $marketerContracts->links() }}</div>
    </section>
</div>
@endsection
