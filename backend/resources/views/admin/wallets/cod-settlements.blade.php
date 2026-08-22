@extends('layouts.admin')

@section('title', __('admin.wallets.cod_settlements_title'))

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.wallets.cod_settlements_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.wallets.cod_settlements_subtitle') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Run Settlement Form --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 mb-4">{{ __('admin.wallets.run_new_settlement') }}</h2>
        <form method="POST" action="{{ route('admin.wallets.cod-settlements.run') }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.agent') }}</label>
                <select name="agent_id" required class="form-select text-sm rounded-lg border-gray-300 min-w-48">
                    <option value="">{{ __('admin.wallets.select_agent') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.period_start') }}</label>
                <input type="date" name="period_start" required class="form-input text-sm rounded-lg border-gray-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.wallets.period_end') }}</label>
                <input type="date" name="period_end" required class="form-input text-sm rounded-lg border-gray-300">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">{{ __('admin.wallets.run_settlement') }}</button>
        </form>
    </div>

    {{-- Status Filter --}}
    <div class="flex gap-2">
        @foreach(['' => __('admin.wallets.all'), 'pending' => __('admin.wallets.pending'), 'settled' => __('admin.wallets.settled'), 'disputed' => __('admin.wallets.disputed')] as $s => $label)
            <a href="{{ route('admin.wallets.cod-settlements', ['status' => $s]) }}"
               class="px-3 py-1.5 text-sm rounded-lg {{ request('status', '') === $s ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Settlements Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.agent_col') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.period') }}</th>
                        <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.cod_collected') }}</th>
                        <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.earnings_owed') }}</th>
                        <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.net_to_remit') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">{{ __('admin.wallets.status') }}</th>
                        <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($settlements as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $s->agent?->name }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $s->period_start->format('d M') }} – {{ $s->period_end->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ number_format($s->total_cod_collected, 2) }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ number_format($s->total_earnings_owed, 2) }}</td>
                            <td class="px-4 py-3 text-end font-semibold {{ $s->net_to_remit >= 0 ? 'text-red-600' : 'text-green-700' }}">
                                {{ $s->net_to_remit >= 0 ? '+' : '' }}{{ number_format($s->net_to_remit, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php $colors = ['pending'=>'bg-yellow-100 text-yellow-700','settled'=>'bg-green-100 text-green-700','disputed'=>'bg-red-100 text-red-700']; @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$s->status->value] ?? '' }}">{{ __('admin.wallets.' . $s->status->value) }}</span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if($s->status === \App\Enums\DeliveryAgentCodSettlementStatus::Pending)
                                    <form method="POST" action="{{ route('admin.wallets.cod-settlements.settle', $s) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">{{ __('admin.wallets.mark_settled') }}</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">{{ $s->settled_at?->format('d M Y') ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ __('admin.wallets.no_settlements_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $settlements->withQueryString()->links() }}</div>
    </div>

</div>
@endsection
