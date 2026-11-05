@extends('layouts.admin')
@section('title', __('contracts.customer.list_title'))

@section('content')
<div class="space-y-5 p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('contracts.customer.list_title') }}</h1>
        <p class="mt-0.5 text-sm text-gray-500">{{ __('contracts.customer.list_desc') }}</p>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 text-sm">
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.customer.search') }}</label>
            <input type="text" name="q" value="{{ $search }}" class="form-input text-sm" placeholder="{{ __('contracts.customer.search_placeholder') }}">
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.admin.col_country') }}</label>
            <select name="country" class="form-input text-sm">
                <option value="">{{ __('contracts.admin.all_countries_filter') }}</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected($countryId === $country->id)>{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.sections.filter_label') }}</label>
            <select name="category" class="form-input text-sm">
                <option value="">{{ __('contracts.sections.filter_all') }}</option>
                @foreach ($categories->whereNull('parent_id') as $root)
                    <option value="{{ $root->id }}" @selected($categoryId === $root->id)>{{ app()->getLocale() === 'ar' ? $root->name_ar : $root->name_en }}</option>
                    @foreach ($categories->where('parent_id', $root->id) as $child)
                        <option value="{{ $child->id }}" @selected($categoryId === $child->id)>— {{ app()->getLocale() === 'ar' ? $child->name_ar : $child->name_en }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('admin.contracts.col_status') }}</label>
            <select name="status" class="form-input text-sm">
                <option value="">{{ __('admin.contracts.filter_all') }}</option>
                @foreach (['active', 'superseded', 'cancelled'] as $value)
                    <option value="{{ $value }}" @selected($status === $value)>{{ __('contracts.customer.status_'.$value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.customer.date_from') }}</label>
            <input type="date" name="from" value="{{ $from }}" class="form-input text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-600">{{ __('contracts.customer.date_to') }}</label>
            <input type="date" name="to" value="{{ $to }}" class="form-input text-sm">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.contracts.apply') }}</button>
        <a href="{{ route('admin.contracts.customer.index') }}" class="btn btn-ghost btn-sm">{{ __('contracts.customer.reset') }}</a>
    </form>

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('contracts.customer.col_customer') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.customer.col_listing') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_contract') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_category') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.admin.col_country') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.customer.col_purpose') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.customer.col_signed_at') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($contracts as $contract)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $contract->customer_name }}<div class="text-xs text-gray-400">{{ $contract->customer_email }}</div></td>
                        <td class="px-4 py-3">{{ $contract->listing_number }}<div class="max-w-[14rem] truncate text-xs text-gray-400">{{ $contract->listing_title }}</div></td>
                        <td class="px-4 py-3">{{ $contract->template?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->classifiedCategory ? (app()->getLocale() === 'ar' ? $contract->classifiedCategory->name_ar : $contract->classifiedCategory->name_en) : '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->country ? (app()->getLocale() === 'ar' ? $contract->country->name_ar : $contract->country->name_en) : '—' }}</td>
                        <td class="px-4 py-3">{{ __('contracts.customer.purpose_'.$contract->purpose) }}</td>
                        <td class="px-4 py-3 text-center">v{{ $contract->template_version }}</td>
                        <td class="px-4 py-3">{{ $contract->signed_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">{{ __('contracts.customer.status_'.$contract->status) }}</td>
                        <td class="px-4 py-3 text-end"><a href="{{ route('admin.contracts.customer.show', $contract) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('contracts.customer.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">{{ __('contracts.customer.no_rows') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $contracts->links() }}</div>
</div>
@endsection
