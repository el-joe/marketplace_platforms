@extends('layouts.admin')

@section('title', __('admin.carriers_section.claims_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.carriers_section.claims_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.carriers_section.claims_desc') }}</p>
        </div>
        {{-- Admin submits on behalf if needed --}}
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.carriers_section.submitted') }}"    :value="number_format($stats['submitted'])"    iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.carriers_section.under_review') }}" :value="number_format($stats['under_review'])" iconBg="bg-blue-100 text-blue-600" />
        <x-stat-card title="{{ __('admin.carriers_section.approved') }}"     :value="number_format($stats['approved'])"     iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('admin.carriers_section.compensated') }}"  :value="number_format($stats['compensated'])"  iconBg="bg-emerald-100 text-emerald-600" />
        <x-stat-card title="{{ __('admin.carriers_section.rejected') }}"     :value="number_format($stats['rejected'])"     iconBg="bg-red-100 text-red-600" />
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────── --}}
    @php
        $statusLabels = [
            'submitted'    => __('admin.carriers_section.submitted'),
            'under_review' => __('admin.carriers_section.under_review'),
            'approved'     => __('admin.carriers_section.approved'),
            'compensated'  => __('admin.carriers_section.compensated'),
            'rejected'     => __('admin.carriers_section.rejected'),
        ];
        $claimTypeLabels = [
            'lost'       => __('admin.carriers_section.claim_type_lost'),
            'damaged'    => __('admin.carriers_section.claim_type_damaged'),
            'delayed'    => __('admin.carriers_section.claim_type_delayed'),
            'wrong_item' => __('admin.carriers_section.claim_type_wrong_item'),
            'other'      => __('admin.carriers_section.claim_type_other'),
        ];
    @endphp
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        <select name="status" class="input-sm">
            <option value="">{{ __('admin.carriers_section.all_statuses') }}</option>
            @foreach($statusLabels as $s => $label)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="shipping_company_id" class="input-sm">
            <option value="">{{ __('admin.carriers_section.all_carriers') }}</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(request('shipping_company_id') === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>

        <select name="claim_type" class="input-sm">
            <option value="">{{ __('admin.carriers_section.all_types') }}</option>
            @foreach($claimTypeLabels as $t => $label)
                <option value="{{ $t }}" @selected(request('claim_type') === $t)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn-sm btn-primary">{{ __('admin.carriers_section.filter') }}</button>
        <a href="{{ route('admin.carrier-claims.index') }}" class="btn-sm btn-secondary">{{ __('admin.carriers_section.reset') }}</a>
    </form>

    {{-- ─── Table ───────────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="th">{{ __('admin.carriers_section.claim_number_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.type_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.carrier_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.claimed_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.status_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.submitted_col') }}</th>
                        <th class="th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($claims as $claim)
                        <tr class="hover:bg-gray-50">
                            <td class="td font-mono text-xs">{{ $claim->claim_number }}</td>
                            <td class="td">{{ $claimTypeLabels[$claim->claim_type->value] ?? Str::title(str_replace('_',' ',$claim->claim_type->value)) }}</td>
                            <td class="td text-gray-700">{{ $claim->shippingCompany?->name ?? '—' }}</td>
                            <td class="td font-medium">{{ number_format($claim->claimed_amount, 2) }}</td>
                            <td class="td">
                                <span class="badge {{ $claim->statusBadgeClass() }}">
                                    {{ $statusLabels[$claim->status->value] ?? Str::title(str_replace('_',' ',$claim->status->value)) }}
                                </span>
                            </td>
                            <td class="td text-gray-500 text-xs">{{ $claim->created_at->format('d M Y') }}</td>
                            <td class="td text-end">
                                <a href="{{ route('admin.carrier-claims.show', $claim) }}"
                                   class="text-primary-600 hover:underline text-xs font-medium">{{ __('admin.carriers_section.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="td text-center text-gray-400 py-10">{{ __('admin.carriers_section.no_claims') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $claims->links() }}
    </div>

@endsection
