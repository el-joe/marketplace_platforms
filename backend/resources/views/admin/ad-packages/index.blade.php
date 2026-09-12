@extends('layouts.admin')

@section('title', 'Nawi Ads — Packages')

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nawi Ads — Packages</h1>
            <p class="text-sm text-gray-500 mt-0.5">Set the monthly price for each promotional tier.</p>
        </div>
        <button type="button" id="btn-create-package" class="btn btn-primary btn-sm">New Package</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8" id="packages-grid">
        @forelse($packages as $package)
            @php
                $tierLabel = $package->tier === 'serious_featured' ? 'إعلان جاد ومميز (Serious + Featured)' : 'إعلان جاد (Serious)';
            @endphp
            <div class="bg-white rounded-2xl border-2 border-gray-200 p-5 flex flex-col">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <span class="text-xs font-bold rounded-full px-2.5 py-0.5 bg-indigo-100 text-indigo-700">{{ $tierLabel }}</span>
                        <p class="text-sm font-semibold text-gray-800 mt-2">{{ $package->name_en }}</p>
                        <p class="text-xs text-gray-400" dir="rtl">{{ $package->name_ar }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $package->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $package->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <p class="text-2xl font-extrabold text-gray-900 mb-1">
                    {{ number_format($package->price_monthly) }}
                    <span class="text-sm font-medium text-gray-400">{{ $package->currency }}/mo</span>
                </p>

                <div class="flex gap-2 pt-3 border-t border-gray-100 mt-3">
                    <button type="button" class="flex-1 btn btn-ghost btn-xs btn-edit-package"
                        data-package="{{ json_encode($package) }}">Edit</button>
                    <button type="button"
                        class="btn btn-xs btn-toggle-package {{ $package->is_active ? 'btn-warning' : 'btn-success' }}"
                        data-id="{{ $package->id }}">
                        {{ $package->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-3 bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-400">
                No ad packages yet.
            </div>
        @endforelse
    </div>

    <div id="package-modal" class="modal-backdrop hidden">
        <div class="modal-box modal-lg p-6">
            <h3 class="font-bold text-lg mb-5" id="package-modal-title">New Package</h3>
            <input type="hidden" id="pkg-id">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="label-sm">Tier <span class="text-red-500">*</span></label>
                    <select id="pkg-tier" class="form-input w-full text-sm">
                        <option value="serious">serious — إعلان جاد (pin to top)</option>
                        <option value="serious_featured">serious_featured — إعلان جاد ومميز (pin to top + popup)</option>
                    </select>
                </div>
                <div>
                    <label class="label-sm">Name (EN) <span class="text-red-500">*</span></label>
                    <input type="text" id="pkg-name-en" class="form-input w-full text-sm" dir="ltr">
                </div>
                <div>
                    <label class="label-sm">Name (AR) <span class="text-red-500">*</span></label>
                    <input type="text" id="pkg-name-ar" class="form-input w-full text-sm" dir="rtl">
                </div>
                <div class="col-span-2">
                    <label class="label-sm">Description (EN)</label>
                    <textarea id="pkg-desc-en" rows="2" class="form-input w-full text-sm" dir="ltr"></textarea>
                </div>
                <div class="col-span-2">
                    <label class="label-sm">Description (AR)</label>
                    <textarea id="pkg-desc-ar" rows="2" class="form-input w-full text-sm" dir="rtl"></textarea>
                </div>
                <div>
                    <label class="label-sm">Price / month (smallest unit) <span class="text-red-500">*</span></label>
                    <input type="number" id="pkg-price" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="label-sm">Currency <span class="text-red-500">*</span></label>
                    <input type="text" id="pkg-currency" class="form-input w-full text-sm" value="AED" maxlength="3">
                </div>
                <div>
                    <label class="label-sm">Sort order</label>
                    <input type="number" id="pkg-sort-order" class="form-input w-full text-sm" value="0" min="0">
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="package-modal-cancel" class="btn btn-ghost btn-sm">Cancel</button>
                <button type="button" id="package-modal-save" class="btn btn-primary btn-sm px-8">Save</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tok = '{{ csrf_token() }}';

            function refreshPage() { location.reload(); }

            $('#btn-create-package').on('click', function() {
                $('#pkg-id').val('');
                $('#package-modal-title').text('New Package');
                $('#pkg-tier').val('serious');
                ['name-en', 'name-ar', 'desc-en', 'desc-ar'].forEach(id => $('#pkg-' + id).val(''));
                $('#pkg-price').val('');
                $('#pkg-currency').val('AED');
                $('#pkg-sort-order').val('0');
                $('#pkg-tier').prop('disabled', false);
                $('#package-modal').modal('open');
            });

            $(document).on('click', '.btn-edit-package', function() {
                const p = $(this).data('package');
                $('#pkg-id').val(p.id);
                $('#package-modal-title').text('Edit — ' + p.name_en);
                $('#pkg-tier').val(p.tier).prop('disabled', true);
                $('#pkg-name-en').val(p.name_en);
                $('#pkg-name-ar').val(p.name_ar);
                $('#pkg-desc-en').val(p.description_en);
                $('#pkg-desc-ar').val(p.description_ar);
                $('#pkg-price').val(p.price_monthly);
                $('#pkg-currency').val(p.currency);
                $('#pkg-sort-order').val(p.sort_order ?? 0);
                $('#package-modal').modal('open');
            });

            $('#package-modal-cancel').on('click', () => $('#package-modal').modal('close'));

            $('#package-modal-save').on('click', function() {
                const id = $('#pkg-id').val();
                const payload = {
                    tier: $('#pkg-tier').val(),
                    name_en: $('#pkg-name-en').val(),
                    name_ar: $('#pkg-name-ar').val(),
                    description_en: $('#pkg-desc-en').val(),
                    description_ar: $('#pkg-desc-ar').val(),
                    price_monthly: parseInt($('#pkg-price').val()) || 0,
                    currency: $('#pkg-currency').val(),
                    sort_order: parseInt($('#pkg-sort-order').val()) || 0,
                };

                const url = id ? '/ad-packages/' + id : '{{ route('admin.ad-packages.store') }}';
                const method = id ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        window.Toast.success(data.message);
                        $('#package-modal').modal('close');
                        refreshPage();
                    } else {
                        window.Toast.error(data.message ?? 'Error');
                    }
                });
            });

            $(document).on('click', '.btn-toggle-package', function() {
                const id = $(this).data('id');
                fetch('/ad-packages/' + id + '/toggle-active', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: '{}',
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        window.Toast.success(data.message);
                        refreshPage();
                    } else {
                        window.Toast.error(data.message);
                    }
                });
            });
        });
    </script>
@endpush
