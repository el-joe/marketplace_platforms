@extends('layouts.admin')

@section('title', __('admin.packaging_show_request_section.title', ['number' => $req->request_number]))

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.packaging.requests') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.packaging_show_request_section.back_to_orders') }}</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ __('admin.packaging_show_request_section.request_number', ['number' => $req->request_number]) }}</h1>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $req->statusBadgeClass() }}">
            {{ $req->status->label() }}
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Left column --}}
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('admin.packaging_show_request_section.order_items') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 uppercase">{{ __('admin.packaging_show_request_section.col_item') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600 uppercase">{{ __('admin.packaging_show_request_section.col_qty') }}</th>
                                <th class="px-3 py-2 text-end text-xs font-semibold text-gray-600 uppercase">{{ __('admin.packaging_show_request_section.col_unit_cost') }}</th>
                                <th class="px-3 py-2 text-end text-xs font-semibold text-gray-600 uppercase">{{ __('admin.packaging_show_request_section.col_line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($req->items as $item)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $item->supply->name_en ?? '—' }}</div>
                                        @if($item->supply)
                                            <div class="text-xs text-gray-400" dir="rtl">{{ $item->supply->name_ar }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ $item->quantity }}</td>
                                    <td class="px-3 py-2 text-end">{{ number_format($item->unit_cost) }}</td>
                                    <td class="px-3 py-2 text-end">{{ $item->line_total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200">
                                <td colspan="3" class="px-3 py-2 text-end text-gray-500">{{ __('admin.packaging_show_request_section.subtotal') }}</td>
                                <td class="px-3 py-2 text-end font-medium">{{ number_format($req->total_cost) }} {{ $req->currency }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-end text-gray-500">{{ __('admin.packaging_show_request_section.delivery_fee') }}</td>
                                <td class="px-3 py-2 text-end font-medium">{{ number_format($req->delivery_fee) }} {{ $req->currency }}</td>
                            </tr>
                            <tr class="border-t border-gray-200">
                                <td colspan="3" class="px-3 py-2 text-end font-semibold text-gray-900">{{ __('admin.packaging_show_request_section.grand_total') }}</td>
                                <td class="px-3 py-2 text-end font-bold text-gray-900">{{ $req->grand_total_formatted }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($req->notes)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-2">{{ __('admin.packaging_show_request_section.notes') }}</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $req->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-5">
            {{-- Status timeline --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('admin.packaging_show_request_section.status_timeline') }}</h2>
                <ol class="relative border-s border-gray-200 ms-2 space-y-4">
                    <li class="ms-4">
                        <div class="absolute w-2.5 h-2.5 bg-primary-500 rounded-full mt-1.5 -start-1.25"></div>
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.packaging_show_request_section.timeline_requested') }}</p>
                        <p class="text-xs text-gray-400">{{ $req->created_at->format('d M Y H:i') }}</p>
                    </li>
                    @if($req->approved_at)
                    <li class="ms-4">
                        <div class="absolute w-2.5 h-2.5 bg-blue-500 rounded-full mt-1.5 -start-1.25"></div>
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.packaging_show_request_section.timeline_approved') }}</p>
                        <p class="text-xs text-gray-400">{{ $req->approved_at->format('d M Y H:i') }} @if($req->approvedBy){{ __('admin.packaging_show_request_section.by') }} {{ $req->approvedBy->name }}@endif</p>
                    </li>
                    @endif
                    @if($req->shipped_at)
                    <li class="ms-4">
                        <div class="absolute w-2.5 h-2.5 bg-indigo-500 rounded-full mt-1.5 -start-1.25"></div>
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.packaging_show_request_section.timeline_shipped') }}</p>
                        <p class="text-xs text-gray-400">{{ $req->shipped_at->format('d M Y H:i') }}</p>
                    </li>
                    @endif
                    @if($req->delivered_at)
                    <li class="ms-4">
                        <div class="absolute w-2.5 h-2.5 bg-emerald-500 rounded-full mt-1.5 -start-1.25"></div>
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.packaging_show_request_section.timeline_delivered') }}</p>
                        <p class="text-xs text-gray-400">{{ $req->delivered_at->format('d M Y H:i') }}</p>
                    </li>
                    @endif
                    @if($req->status->value === 'rejected')
                    <li class="ms-4">
                        <div class="absolute w-2.5 h-2.5 bg-red-500 rounded-full mt-1.5 -start-1.25"></div>
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.packaging_show_request_section.timeline_rejected') }}</p>
                        <p class="text-xs text-gray-400">{{ $req->updated_at->format('d M Y H:i') }}</p>
                    </li>
                    @endif
                </ol>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900 mb-2">{{ __('admin.packaging_show_request_section.actions') }}</h2>

                @if($req->status->value === 'pending')
                    <button type="button" id="btn-approve" class="btn btn-primary w-full">{{ __('admin.packaging_show_request_section.approve') }}</button>
                    <button type="button" id="btn-reject" class="btn btn-danger w-full">{{ __('admin.packaging_show_request_section.reject') }}</button>
                @elseif($req->status->value === 'approved')
                    <button type="button" class="btn btn-primary w-full js-action" data-action="ship">{{ __('admin.packaging_show_request_section.mark_as_shipped') }}</button>
                @elseif($req->status->value === 'shipped')
                    <button type="button" class="btn btn-primary w-full js-action" data-action="deliver">{{ __('admin.packaging_show_request_section.mark_as_delivered') }}</button>
                @else
                    <p class="text-sm text-gray-400">{{ __('admin.packaging_show_request_section.no_further_actions') }}</p>
                @endif

                <div id="action-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
            </div>

            {{-- Vendor info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.packaging_show_request_section.vendor') }}</h2>
                <p class="text-sm font-medium text-gray-900">{{ $req->vendor->store_name ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $req->vendor->contact_email ?? $req->vendor->email ?? '' }}</p>
                <p class="text-xs text-gray-500">{{ $req->vendor->contact_phone ?? $req->vendor->phone ?? '' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Reject modal --}}
<x-modal id="reject-modal" title="{{ __('admin.packaging_show_request_section.reject_request_title') }}" size="sm">
    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_show_request_section.reason') }} <span class="text-red-500">*</span></label>
    <textarea id="reject-notes" rows="3" maxlength="1000" class="form-input w-full text-sm" placeholder="{{ __('admin.packaging_show_request_section.reject_reason_placeholder') }}"></textarea>
    <div id="reject-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.packaging_show_request_section.cancel') }}</button>
        <button type="button" id="btn-confirm-reject" class="btn btn-danger">{{ __('admin.packaging_show_request_section.reject') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
(function () {
    const TRANSLATIONS = {
        couldNotApprove: @json(__('admin.packaging_show_request_section.could_not_approve')),
        reasonRequired: @json(__('admin.packaging_show_request_section.reason_required')),
        couldNotReject: @json(__('admin.packaging_show_request_section.could_not_reject')),
        actionFailed: @json(__('admin.packaging_show_request_section.action_failed')),
    };
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const requestId = '{{ $req->id }}';
    const base = '{{ url('packaging/requests') }}/' + requestId;
    const errEl = document.getElementById('action-error');

    function openModal(id) { window.jQuery ? window.jQuery('#' + id).modal('open') : document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { window.jQuery ? window.jQuery('#' + id).modal('close') : document.getElementById(id)?.classList.add('hidden'); }

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    }

    document.getElementById('btn-approve')?.addEventListener('click', async () => {
        const { ok, data } = await post(`${base}/approve`);
        if (ok) { window.location.reload(); }
        else { errEl.textContent = data.message ?? TRANSLATIONS.couldNotApprove; errEl.classList.remove('hidden'); }
    });

    document.getElementById('btn-reject')?.addEventListener('click', () => {
        document.getElementById('reject-notes').value = '';
        document.getElementById('reject-error').classList.add('hidden');
        openModal('reject-modal');
    });

    document.getElementById('btn-confirm-reject')?.addEventListener('click', async () => {
        const notes = document.getElementById('reject-notes').value.trim();
        const rErr = document.getElementById('reject-error');
        if (!notes) { rErr.textContent = TRANSLATIONS.reasonRequired; rErr.classList.remove('hidden'); return; }
        const { ok, data } = await post(`${base}/reject`, { notes });
        if (ok) { window.location.reload(); }
        else { rErr.textContent = data.message ?? TRANSLATIONS.couldNotReject; rErr.classList.remove('hidden'); }
    });

    document.querySelectorAll('.js-action').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            const { ok, data } = await post(`${base}/${action === 'ship' ? 'ship' : 'deliver'}`);
            if (ok) { window.location.reload(); }
            else { errEl.textContent = data.message ?? TRANSLATIONS.actionFailed; errEl.classList.remove('hidden'); }
        });
    });
})();
</script>
@endpush
@endsection
