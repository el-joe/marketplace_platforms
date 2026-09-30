@extends('layouts.marketer')
@section('title', __('marketer.support.create_title'))
@section('page-title', __('marketer.support.create_page_title'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border p-6">
    <form id="form-create-ticket">
        @csrf
        <div class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.support.subject_label') }} <span class="text-red-500">*</span></label>
                <input type="text" name="subject" maxlength="255" required
                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.support.category_label') }} <span class="text-red-500">*</span></label>
                    <select name="category" required class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">{{ __('marketer.support.choose_category') }}</option>
                        <option value="order_issue">{{ __('marketer.support.category_order_issue') }}</option>
                        <option value="payment_issue">{{ __('marketer.support.category_payment_issue') }}</option>
                        <option value="payout">{{ __('marketer.support.category_payout') }}</option>
                        <option value="catalog">{{ __('marketer.support.category_catalog') }}</option>
                        <option value="account">{{ __('marketer.support.category_account') }}</option>
                        <option value="technical">{{ __('marketer.support.category_technical') }}</option>
                        <option value="policy">{{ __('marketer.support.category_policy') }}</option>
                        <option value="other">{{ __('marketer.support.category_other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.support.priority_label') }}</label>
                    <select name="priority" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="low">{{ __('marketer.support.priority_low') }}</option>
                        <option value="normal" selected>{{ __('marketer.support.priority_normal') }}</option>
                        <option value="high">{{ __('marketer.support.priority_high') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.support.description_label') }} <span class="text-red-500">*</span></label>
                <textarea name="description" rows="6" maxlength="5000" required
                          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-y"></textarea>
            </div>
        </div>

        <div id="ticket-error" class="hidden mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('marketer.support.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">{{ __('marketer.support.cancel_button') }}</a>
            <button type="submit" id="btn-create-ticket" class="rounded-lg bg-yellow-500 px-5 py-2 text-sm font-semibold text-gray-900">{{ __('marketer.support.create_ticket_button') }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('form-create-ticket').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('btn-create-ticket');
    const errorBox = document.getElementById('ticket-error');
    errorBox.classList.add('hidden');
    btn.disabled = true;

    const formData = new FormData(this);

    fetch('{{ route('marketer.support.store') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
        body: formData,
    })
        .then(async (res) => {
            const data = await res.json();
            if (!res.ok) throw data;
            window.location.href = data.redirect;
        })
        .catch((err) => {
            btn.disabled = false;
            const messages = err?.errors ? Object.values(err.errors).flat().join(' ') : (err?.message || '{{ __('marketer.support.error_default') }}');
            errorBox.textContent = messages;
            errorBox.classList.remove('hidden');
        });
});
</script>
@endpush
@endsection
