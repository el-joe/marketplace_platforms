@extends('layouts.admin')

@section('title', __('admin.gift_cards_section.batches_title'))

@section('content')

<div class="mb-6 flex items-center justify-between gap-4">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.gift_cards_section.batches_title') }}</h1>
    <div class="flex items-center gap-3">
        <button type="button" id="expire-stale-btn" class="btn btn-secondary btn-sm">
            {{ __('admin.gift_cards_section.expire_stale') }}
        </button>
        @if(auth('admin')->user()->can('gift_cards.create'))
            <a href="{{ route('admin.gift-cards.batches.create') }}" class="btn btn-primary btn-sm">
                {{ __('admin.gift_cards_section.add_batch') }}
            </a>
        @endif
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('admin.gift_cards_section.total_batches'), 'value' => $stats['total_batches'], 'color' => 'gray-700'],
        ['label' => __('admin.gift_cards_section.total_issued'), 'value' => $stats['total_issued'], 'color' => 'gray-700'],
        ['label' => __('admin.gift_cards_section.total_redeemed'), 'value' => $stats['total_redeemed'], 'color' => 'blue-600'],
        ['label' => __('admin.gift_cards_section.total_active'), 'value' => $stats['total_active'], 'color' => 'emerald-600'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-black text-{{ $stat['color'] }}">{{ $stat['value'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.gift_cards_section.name') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.gift_cards_section.currency_code') }}</th>
                    <th class="px-6 py-3 text-end font-semibold">{{ __('admin.gift_cards_section.amount') }}</th>
                    <th class="px-6 py-3 text-end font-semibold">{{ __('admin.gift_cards_section.quantity') }}</th>
                    <th class="px-6 py-3 text-end font-semibold">{{ __('admin.gift_cards_section.active_count') }}</th>
                    <th class="px-6 py-3 text-end font-semibold">{{ __('admin.gift_cards_section.redeemed_count') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.gift_cards_section.expiry') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.gift_cards_section.created_by') }}</th>
                    <th class="px-6 py-3 text-end font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($batches as $batch)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $batch->name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $batch->currency_code }}</td>
                    <td class="px-6 py-4 text-end text-gray-700">{{ (int) $batch->amount }} {{ $batch->currency_code }}</td>
                    <td class="px-6 py-4 text-end text-gray-700">{{ $batch->quantity }}</td>
                    <td class="px-6 py-4 text-end text-gray-700">{{ $batch->active_count }}</td>
                    <td class="px-6 py-4 text-end text-gray-700">{{ $batch->redeemed_count }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $batch->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $batch->createdByAdmin?->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-end">
                        <a href="{{ route('admin.gift-cards.batches.show', $batch->id) }}"
                           class="text-indigo-600 hover:underline text-xs font-medium">{{ __('admin.gift_cards_section.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-400 text-sm">{{ __('admin.gift_cards_section.no_batches') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($batches->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $batches->links() }}
    </div>
    @endif
</div>

@endsection

@push('scripts')
    <script>
        document.getElementById('expire-stale-btn')?.addEventListener('click', function () {
            if (!confirm(@json(__('admin.gift_cards_section.expire_stale_confirm')))) return;

            fetch(@json(route('admin.gift-cards.expire-stale')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then((res) => res.json())
                .then((data) => {
                    window.Toast
                        ? window.Toast.success(@json(__('admin.gift_cards_section.stale_expired')).replace(':count', data.expired_count))
                        : alert('Expired: ' + data.expired_count);
                    window.location.reload();
                });
        });
    </script>
@endpush
