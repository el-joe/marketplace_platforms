@extends('layouts.partner')

@section('title', __('partner.shipping_companies.title'))
@section('page-title', __('partner.shipping_companies.title'))

@section('content')

    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">{{ __('partner.shipping_companies.description') }}</p>
        <button id="btn-add-company"
                class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('partner.shipping_companies.add_company') }}
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('partner.shipping_companies.name') }}</th>
                    <th class="text-start px-4 py-3">{{ __('partner.shipping_companies.contact') }}</th>
                    <th class="text-start px-4 py-3">{{ __('partner.shipping_companies.visibility') }}</th>
                    <th class="text-start px-4 py-3">{{ __('partner.shipping_companies.status') }}</th>
                    <th class="text-end px-4 py-3">{{ __('partner.shipping_companies.actions') }}</th>
                </tr>
            </thead>
            <tbody id="companies-tbody" class="divide-y divide-gray-100">
                @foreach($companies as $company)
                    <tr data-id="{{ $company->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $company->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $company->contact_email }}</td>
                        <td class="px-4 py-3">
                            @if($company->owner_vendor_id)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                    {{ __('partner.shipping_companies.private') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    {{ __('partner.shipping_companies.public') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $company->status->label() }}</td>
                        <td class="px-4 py-3 text-end">
                            @if($company->owner_vendor_id)
                                <button type="button" class="btn-edit text-xs text-primary-600 hover:text-primary-800 font-medium me-3"
                                        data-id="{{ $company->id }}" data-name="{{ $company->name }}"
                                        data-email="{{ $company->contact_email }}" data-phone="{{ $company->contact_phone }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button" class="btn-delete text-xs text-red-600 hover:text-red-800 font-medium" data-id="{{ $company->id }}">
                                    {{ __('common.delete') }}
                                </button>
                            @else
                                <span class="text-xs text-gray-300">{{ __('partner.shipping_companies.read_only') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($companies->isEmpty())
            <div class="py-16 text-center text-gray-400 text-sm">{{ __('partner.shipping_companies.no_companies') }}</div>
        @endif
    </div>

    {{-- Add/Edit Modal --}}
    <div id="company-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 id="company-modal-title" class="font-semibold text-gray-900">{{ __('partner.shipping_companies.add_company') }}</h3>
                <button id="company-modal-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="company-form" class="p-5 space-y-4">
                <input type="hidden" name="id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('partner.shipping_companies.name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ __('partner.shipping_companies.contact') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="contact_email" required
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.shipping_companies.phone') }}</label>
                    <input type="text" name="contact_phone"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                </div>
                <div id="company-form-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('common.save') }}
                    </button>
                    <button type="button" id="company-modal-cancel" class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const storeUrl = @json(route('partner.shipping-companies.store'));
    const updateBaseUrl = @json(url('partner/shipping-companies'));
    const deleteBaseUrl = @json(url('partner/shipping-companies'));

    const modal = document.getElementById('company-modal');
    const form = document.getElementById('company-form');
    const errorBox = document.getElementById('company-form-error');
    const title = document.getElementById('company-modal-title');

    function openModal(edit) {
        errorBox.classList.add('hidden');
        form.reset();
        form.id.value = '';
        title.textContent = edit ? @json(__('partner.shipping_companies.edit_company')) : @json(__('partner.shipping_companies.add_company'));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('btn-add-company').addEventListener('click', () => openModal(false));
    document.getElementById('company-modal-close').addEventListener('click', closeModal);
    document.getElementById('company-modal-cancel').addEventListener('click', closeModal);

    document.getElementById('companies-tbody').addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit');
        const delBtn = e.target.closest('.btn-delete');

        if (editBtn) {
            openModal(true);
            form.id.value = editBtn.dataset.id;
            form.name.value = editBtn.dataset.name || '';
            form.contact_email.value = editBtn.dataset.email || '';
            form.contact_phone.value = editBtn.dataset.phone || '';
        }

        if (delBtn) {
            if (!confirm(@json(__('partner.shipping_companies.confirm_delete')))) return;
            fetch(`${deleteBaseUrl}/${delBtn.dataset.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.message || 'Error');
                    return;
                }
                window.location.reload();
            });
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        errorBox.classList.add('hidden');

        const id = form.id.value;
        const payload = {
            name: form.name.value,
            contact_email: form.contact_email.value,
            contact_phone: form.contact_phone.value,
        };

        const url = id ? `${updateBaseUrl}/${id}` : storeUrl;
        const method = id ? 'PUT' : 'POST';

        fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        }).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || 'Error');
                errorBox.textContent = msg;
                errorBox.classList.remove('hidden');
                return;
            }
            window.location.reload();
        });
    });
})();
</script>
@endpush
