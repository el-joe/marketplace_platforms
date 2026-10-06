@extends('layouts.admin')
@section('title', __('admin.contracts.all_signatures_title'))

@section('content')
<div class="space-y-5 p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.contracts.all_signatures_title') }}</h1>
        <p class="mt-0.5 text-sm text-gray-500">{{ __('admin.contracts.all_signatures_desc') }}</p>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 text-sm">
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_scope') }}</label>
            <select name="scope" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                <option value="classified" @selected($scope === 'classified')>{{ __('admin.contracts.scope_classified') }}</option>
                <option value="product" @selected($scope === 'product')>{{ __('admin.contracts.scope_product') }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_status') }}</label>
            <select name="status" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('admin.contracts.sig_active') }}</option>
                <option value="superseded" @selected($status === 'superseded')>{{ __('admin.contracts.sig_superseded') }}</option>
                <option value="revoked" @selected($status === 'revoked')>{{ __('admin.contracts.sig_revoked') }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_vendor') }}</label>
            <input type="text" name="q" value="{{ $search }}" class="form-input text-sm">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.contracts.apply') }}</button>
    </form>

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_vendor') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_contract') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_scope') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_category') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_language') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_by') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_signed_at') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($contracts as $contract)
                    @php($category = $contract->category_scope === 'product' ? $contract->productCategory : $contract->classifiedCategory)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><a href="{{ route('admin.vendors.contracts.index', $contract->vendor_id) }}" class="text-primary-600 hover:underline">{{ $contract->vendor?->store_name }}</a></td>
                        <td class="px-4 py-3">{{ $contract->contractTemplate?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ __('admin.contracts.scope_' . $contract->category_scope) }}</td>
                        <td class="px-4 py-3">{{ $category?->name_en ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">v{{ $contract->template_version }}</td>
                        <td class="px-4 py-3 uppercase">{{ $contract->language_signed }}</td>
                        <td class="px-4 py-3">{{ $contract->signer_name }}</td>
                        <td class="px-4 py-3">{{ $contract->signed_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ __('admin.contracts.sig_' . $contract->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-gray-400">{{ __('admin.contracts.no_signatures') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $contracts->links() }}</div>
</div>
@endsection
