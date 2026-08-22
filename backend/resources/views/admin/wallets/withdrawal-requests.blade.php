@extends('layouts.admin')

@section('title', __('admin.wallets.withdrawal_requests_title'))

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.wallets.withdrawal_requests_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.wallets.withdrawal_requests_subtitle') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Status filter --}}
    <div class="flex gap-2">
        @foreach(['' => __('admin.wallets.all'), 'pending' => __('admin.wallets.pending'), 'approved' => __('admin.wallets.approved'), 'processed' => __('admin.wallets.processed'), 'rejected' => __('admin.wallets.rejected')] as $s => $label)
            <a href="{{ route('admin.wallets.withdrawals', ['status' => $s]) }}"
               class="px-3 py-1.5 text-sm rounded-lg {{ request('status', '') === $s ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.owner') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.bank') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.iban') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.amount') }}</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">{{ __('admin.wallets.status') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-600">{{ __('admin.wallets.requested') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-600">{{ __('admin.wallets.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $wr)
                    @php $owner = $wr->wallet?->owner; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $owner?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $wr->wallet?->owner_type ? __('admin.wallets.' . $wr->wallet->owner_type->value) : '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $wr->bank_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $wr->bank_iban ?? '—' }}</td>
                        <td class="px-4 py-3 text-end font-semibold text-gray-900">{{ number_format($wr->amount, 2) }} {{ $wr->currency }}</td>
                        <td class="px-4 py-3 text-center">
                            @php $colors = ['pending'=>'bg-yellow-100 text-yellow-700','approved'=>'bg-blue-100 text-blue-700','processed'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700']; $wrStatus = $wr->status->value; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$wrStatus] ?? '' }}">{{ __('admin.wallets.' . $wrStatus) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $wr->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-end">
                            <div class="flex justify-end gap-2">
                                @if($wrStatus === 'pending')
                                    <form method="POST" action="{{ route('admin.wallets.withdrawals.approve', $wr) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">{{ __('admin.wallets.approve') }}</button>
                                    </form>
                                    <button onclick="showRejectModal('{{ $wr->id }}')" class="text-xs px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">{{ __('admin.wallets.reject') }}</button>
                                @elseif($wrStatus === 'approved')
                                    <form method="POST" action="{{ route('admin.wallets.withdrawals.processed', $wr) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ __('admin.wallets.mark_processed') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ __('admin.wallets.no_withdrawal_requests_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $requests->withQueryString()->links() }}</div>
    </div>

</div>

{{-- Reject Modal --}}
<div id="reject-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('admin.wallets.reject_withdrawal') }}</h3>
        <form id="reject-form" method="POST">
            @csrf @method('PATCH')
            <textarea name="reason" required rows="3" placeholder="{{ __('admin.wallets.rejection_reason_placeholder') }}" class="w-full form-textarea text-sm rounded-lg border-gray-300 mb-4"></textarea>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600">{{ __('admin.wallets.cancel') }}</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700">{{ __('admin.wallets.reject') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    window.TRANSLATIONS = window.TRANSLATIONS || {};
    Object.assign(window.TRANSLATIONS, {});

function showRejectModal(id) {
    document.getElementById('reject-form').action = `/admin/wallets/withdrawals/${id}/reject`;
    document.getElementById('reject-modal').classList.remove('hidden');
}
</script>
@endpush
@endsection
