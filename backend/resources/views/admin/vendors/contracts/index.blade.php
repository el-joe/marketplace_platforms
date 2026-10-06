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
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($contracts as $contract)
                        @php($category = $contract->category_scope === 'product' ? $contract->productCategory : $contract->classifiedCategory)
                        <tr>
                            <td class="px-4 py-3">{{ $contract->contractTemplate?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">v{{ $contract->template_version }}</td>
                            <td class="px-4 py-3">{{ __('admin.contracts.scope_' . $contract->category_scope) }}</td>
                            <td class="px-4 py-3">{{ $category?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3 uppercase">{{ $contract->language_signed }}</td>
                            <td class="px-4 py-3">{{ $contract->signer_name }}</td>
                            <td class="px-4 py-3">{{ $contract->signed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $contract->signed_ip ?? '—' }}</td>
                            <td class="px-4 py-3">{{ __('admin.contracts.sig_' . $contract->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">{{ __('admin.vendor_contracts.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $contracts->links() }}</div>
    </section>
</div>
@endsection
