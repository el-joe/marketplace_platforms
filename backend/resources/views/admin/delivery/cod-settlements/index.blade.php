@extends('layouts.admin')

@section('title', __('admin.cod.title'))

@section('content')

{{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.cod.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.cod.subtitle') }}</p>
    </div>
    <button type="button" id="generate-btn" class="btn btn-primary btn-sm">{{ __('admin.cod.generate_settlement_btn') }}</button>
</div>

{{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.cod.cash_in_agents_custody') }}</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ number_format($pendingCashCents, 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.cod.platform_money_not_remitted') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.cod.settled_this_month') }}</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ number_format($settledThisMonthCents, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.cod.disputed') }}</p>
        <p class="mt-1 text-2xl font-bold text-yellow-600">{{ $disputedCount }}</p>
    </div>
</div>

{{-- ─── Agent Table ─────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('admin.cod.col_agent') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('admin.cod.col_pending_cash') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('admin.cod.col_last_settlement') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('admin.cod.col_status') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('admin.cod.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($agents as $agent)
                    @php
                        $lastSettlement = $agent->codSettlements->first();
                        $pendingCod = $agentPendingCod[$agent->id] ?? 0;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $agent->name }}</div>
                            <div class="text-xs text-gray-500">{{ $agent->phone ?? $agent->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-end">
                            @if($pendingCod > 0)
                                <span class="font-semibold text-red-600">{{ number_format($pendingCod, 2) }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($lastSettlement)
                                <div class="text-xs">
                                    {{ $lastSettlement->period_start->format('M d') }} – {{ $lastSettlement->period_end->format('M d, Y') }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ __('admin.cod.net_label') }} {{ number_format($lastSettlement->net_to_remit, 2) }}
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">{{ __('admin.cod.no_settlements_yet') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($lastSettlement)
                                @php
                                    $badgeClass = match($lastSettlement->status) {
                                        \App\Enums\DeliveryAgentCodSettlementStatus::Settled  => 'badge-success',
                                        \App\Enums\DeliveryAgentCodSettlementStatus::Disputed => 'badge-warning',
                                        default    => 'badge-gray',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} text-xs capitalize">{{ $lastSettlement->status->label() }}</span>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end space-x-2">
                            @if($pendingCod > 0)
                                <button type="button"
                                    class="btn btn-xs btn-outline gen-settlement-btn"
                                    data-agent-id="{{ $agent->id }}"
                                    data-agent-name="{{ $agent->name }}">
                                    {{ __('admin.cod.generate_settlement') }}
                                </button>
                            @endif
                            @if($lastSettlement)
                                <a href="{{ route('admin.delivery.cod-settlements.show', $lastSettlement) }}"
                                   class="btn btn-xs btn-secondary">{{ __('admin.cod.view_history') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('admin.cod.no_agents_with_cod') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ─── Generate Modal ──────────────────────────────────────────────────────── --}}
<div id="generate-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('admin.cod.generate_cod_settlement') }}</h2>
        <form id="generate-form" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.cod.agent_label') }}</label>
                <select name="agent_id" id="gen-agent-id" required class="form-input w-full">
                    <option value="">{{ __('admin.cod.select_agent_placeholder') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.cod.period_start') }}</label>
                    <input type="date" name="period_start" required class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.cod.period_end') }}</label>
                    <input type="date" name="period_end" required class="form-input w-full">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="gen-cancel" class="btn btn-secondary btn-sm">{{ __('admin.cod.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.cod.generate') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    generationFailed: @json(__('admin.cod.generation_failed')),
    requestFailed: @json(__('admin.cod.request_failed')),
});

(function () {
    const GENERATE_URL = @json(route('admin.delivery.cod-settlements.generate'));

    function token() { return document.querySelector('meta[name="csrf-token"]').content; }

    // Open generate modal
    document.getElementById('generate-btn').addEventListener('click', () => {
        document.getElementById('generate-modal').classList.remove('hidden');
    });

    // Per-agent "Generate Settlement" button pre-fills the agent
    document.querySelectorAll('.gen-settlement-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('gen-agent-id').value = btn.dataset.agentId;
            document.getElementById('generate-modal').classList.remove('hidden');
        });
    });

    document.getElementById('gen-cancel').addEventListener('click', () => {
        document.getElementById('generate-modal').classList.add('hidden');
    });

    document.getElementById('generate-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const data = new FormData(this);
        data.set('_token', token());

        fetch(GENERATE_URL, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    document.getElementById('generate-modal').classList.add('hidden');
                    if (res.settlement?.show_url) {
                        window.location.href = res.settlement.show_url;
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.Toast?.error(res.message ?? window.TRANSLATIONS.generationFailed);
                }
            })
            .catch(() => window.Toast?.error(window.TRANSLATIONS.requestFailed));
    });
})();
</script>
@endpush
