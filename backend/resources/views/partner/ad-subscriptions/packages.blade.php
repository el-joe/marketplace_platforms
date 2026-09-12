@extends('layouts.partner')
@section('title', 'Nawi Ads')
@section('page-title', 'Nawi Ads — Boost your listings')

@section('content')

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        @foreach ($packages as $package)
            <div class="bg-white rounded-2xl border-2 border-gray-200 p-5">
                <span class="text-xs font-bold rounded-full px-2.5 py-0.5 bg-indigo-100 text-indigo-700">
                    {{ $package->tier === 'serious_featured' ? 'إعلان جاد ومميز' : 'إعلان جاد' }}
                </span>
                <p class="text-sm font-semibold text-gray-800 mt-2">{{ $package->name_en }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $package->description_en }}</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-3">
                    {{ number_format($package->price_monthly) }}
                    <span class="text-sm font-medium text-gray-400">{{ $package->currency }}/mo</span>
                </p>
                <button type="button" class="btn btn-primary btn-sm w-full mt-3 btn-choose-package"
                    data-id="{{ $package->id }}" data-tier="{{ $package->tier }}" data-name="{{ $package->name_en }}">
                    Choose
                </button>
            </div>
        @endforeach
    </div>

    <div id="subscribe-modal" class="modal-backdrop hidden">
        <div class="modal-box modal-lg p-6">
            <h3 class="font-bold text-lg mb-5">Subscribe listing to <span id="sm-package-name"></span></h3>
            <input type="hidden" id="sm-package-id">

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="label-sm">Listing <span class="text-red-500">*</span></label>
                    <select id="sm-listing-id" class="form-input w-full text-sm">
                        @foreach ($listings as $listing)
                            <option value="{{ $listing->id }}">{{ $listing->productVariant?->product?->name_en }} ({{ $listing->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div id="sm-popup-fields" class="hidden space-y-3 border-t border-gray-100 pt-3">
                    <p class="text-xs text-gray-500">Popup shown to customers (serious_featured only):</p>
                    <input type="text" id="sm-popup-title-en" class="form-input w-full text-sm" placeholder="Popup title (EN)">
                    <input type="text" id="sm-popup-title-ar" class="form-input w-full text-sm" dir="rtl" placeholder="Popup title (AR)">
                    <textarea id="sm-popup-body-en" rows="2" class="form-input w-full text-sm" placeholder="Popup body (EN)"></textarea>
                    <textarea id="sm-popup-body-ar" rows="2" class="form-input w-full text-sm" dir="rtl" placeholder="Popup body (AR)"></textarea>
                    <input type="file" id="sm-popup-image" class="form-input w-full text-sm" accept="image/*">
                    <input type="url" id="sm-popup-cta-url" class="form-input w-full text-sm" placeholder="https://... (CTA link)">
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="subscribe-modal-cancel" class="btn btn-ghost btn-sm">Cancel</button>
                <button type="button" id="subscribe-modal-save" class="btn btn-primary btn-sm px-8">Subscribe</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tok = '{{ csrf_token() }}';

            $(document).on('click', '.btn-choose-package', function() {
                $('#sm-package-id').val($(this).data('id'));
                $('#sm-package-name').text($(this).data('name'));
                $('#sm-popup-fields').toggleClass('hidden', $(this).data('tier') !== 'serious_featured');
                $('#subscribe-modal').modal('open');
            });

            $('#subscribe-modal-cancel').on('click', () => $('#subscribe-modal').modal('close'));

            $('#subscribe-modal-save').on('click', function() {
                const fd = new FormData();
                fd.append('ad_package_id', $('#sm-package-id').val());
                fd.append('vendor_listing_id', $('#sm-listing-id').val());
                fd.append('popup_title_en', $('#sm-popup-title-en').val());
                fd.append('popup_title_ar', $('#sm-popup-title-ar').val());
                fd.append('popup_body_en', $('#sm-popup-body-en').val());
                fd.append('popup_body_ar', $('#sm-popup-body-ar').val());
                fd.append('popup_cta_url', $('#sm-popup-cta-url').val());
                const file = $('#sm-popup-image')[0].files[0];
                if (file) fd.append('popup_image', file);

                fetch('{{ route('partner.ad-subscriptions.subscribe') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok },
                    body: fd,
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        window.Toast.success(data.message);
                        location.href = '{{ route('partner.ad-subscriptions.index') }}';
                    } else {
                        window.Toast.error(data.message ?? 'Error');
                    }
                });
            });
        });
    </script>
@endpush
