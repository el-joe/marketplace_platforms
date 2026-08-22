@extends('layouts.partner')

@section('title', __('partner.claims.index_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.claims.index_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.claims.index_subtitle') }}</p>
        </div>
        <a href="{{ route('partner.claims.create') }}" class="btn btn-primary">{{ __('partner.claims.new_claim') }}</a>
    </div>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">{{ __('partner.claims.table.number') }}</th>
                    <th class="th">{{ __('partner.claims.table.type') }}</th>
                    <th class="th">{{ __('partner.claims.table.carrier') }}</th>
                    <th class="th">{{ __('partner.claims.table.claimed') }}</th>
                    <th class="th">{{ __('partner.claims.table.compensated') }}</th>
                    <th class="th">{{ __('partner.claims.table.status') }}</th>
                    <th class="th">{{ __('partner.claims.table.date') }}</th>
                    <th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($claims as $claim)
                    <tr class="hover:bg-gray-50">
                        <td class="td font-mono text-xs">{{ $claim->claim_number }}</td>
                        <td class="td">{{ Str::title(str_replace('_',' ',$claim->claim_type->value)) }}</td>
                        <td class="td text-gray-700">{{ $claim->shippingCompany?->name ?? '—' }}</td>
                        <td class="td">{{ $claim->claimed_amount_formatted }}</td>
                        <td class="td">{{ $claim->compensated_amount_formatted }}</td>
                        <td class="td">
                            <span class="badge {{ $claim->statusBadgeClass() }}">
                                {{ Str::title(str_replace('_',' ',$claim->status->value)) }}
                            </span>
                        </td>
                        <td class="td text-gray-500 text-xs">{{ $claim->created_at->format('d M Y') }}</td>
                        <td class="td text-right">
                            <a href="{{ route('partner.claims.show', $claim) }}"
                               class="text-primary-600 hover:underline text-xs font-medium">{{ __('partner.claims.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="td text-center text-gray-400 py-10">{{ __('partner.claims.no_claims') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $claims->links() }}</div>

@endsection
