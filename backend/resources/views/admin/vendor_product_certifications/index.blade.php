@extends('layouts.admin')

@section('title', __('admin.vendor_product_certifications.title'))

@section('content')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.vendor_product_certifications.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendor_product_certifications.subtitle') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-2.5">
            {{ session('success') }}
        </div>
    @endif

    {{-- ─── Status Tabs ─────────────────────────────────────────────────────── --}}
    <div class="w-full gap-1 border-b border-gray-200">
        @php
            $tabs = [
                'all' => __('admin.vendor_product_certifications.all'),
                'pending' => __('admin.vendor_product_certifications.pending') . " ({$pendingCount})",
                'approved' => __('admin.vendor_product_certifications.approved'),
                'rejected' => __('admin.vendor_product_certifications.rejected'),
                'expired' => __('admin.vendor_product_certifications.expired'),
            ];
            $currentStatus = request('status', 'pending');
        @endphp
        @foreach($tabs as $tabValue => $tabLabel)
            <a href="{{ route('admin.vendor-product-certifications.index', array_filter(array_merge(request()->except('page'), ['status' => $tabValue]))) }}"
               class="inline-block px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $currentStatus === $tabValue ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="status" value="{{ $currentStatus }}">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('admin.vendor_product_certifications.search_placeholder') }}"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-64">
        <select name="country_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ __('admin.vendor_product_certifications.any_country') }}</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}" {{ (string) request('country_id') === (string) $country->id ? 'selected' : '' }}>
                    {{ $country->name_en }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">{{ __('admin.vendor_product_certifications.filter') }}</button>
        <a href="{{ route('admin.vendor-product-certifications.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('admin.vendor_product_certifications.reset') }}</a>
    </form>

    <form id="bulk-approve-form" method="POST" action="{{ route('admin.vendor-product-certifications.bulk-approve') }}">
        @csrf
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @if($currentStatus === 'pending')
                                <th class="px-4 py-3 w-8">
                                    <input type="checkbox" id="select-all-pending" onclick="document.querySelectorAll('.cert-checkbox').forEach(c => c.checked = this.checked)">
                                </th>
                            @endif
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.vendor_column') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.product_column') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.country_column') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.status_column') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.uploaded_at_column') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.vendor_product_certifications.reviewed_by_column') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $statusColors = [
                                'pending' => 'bg-amber-100 text-amber-700',
                                'approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected' => 'bg-red-100 text-red-700',
                                'expired' => 'bg-gray-100 text-gray-500',
                            ];
                        @endphp
                        @forelse($certifications as $cert)
                        <tr class="hover:bg-gray-50">
                            @if($currentStatus === 'pending')
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $cert->id }}" class="cert-checkbox">
                                </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $cert->vendor?->store_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $cert->product?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $cert->country?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$cert->status] ?? '' }}">
                                    {{ __('admin.vendor_product_certifications.' . $cert->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $cert->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $cert->reviewedByAdmin?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.vendor-product-certifications.show', $cert->id) }}"
                                   class="text-xs text-primary-600 font-medium hover:underline">{{ __('admin.vendor_product_certifications.view') }}</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">{{ __('admin.vendor_product_certifications.no_records') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                @if($currentStatus === 'pending' && $certifications->count() > 0)
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm"
                            onclick="return confirm('{{ __('admin.vendor_product_certifications.bulk_approve') }}?')">
                        {{ __('admin.vendor_product_certifications.bulk_approve') }}
                    </button>
                @else
                    <span></span>
                @endif
                {{ $certifications->withQueryString()->links() }}
            </div>
        </div>
    </form>
</div>
@endsection
