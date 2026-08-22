@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.subscriptions.subscription_plans_title'))

@section('content')


    {{-- ─── Page Header ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.subscriptions.subscription_plans_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.subscriptions.subscription_plans_subtitle') }}</p>
        </div>
        <button type="button" id="btn-create-plan"
            class="btn btn-primary btn-sm">{{ __('admin.subscriptions.new_plan') }}</button>
    </div>

    {{-- ─── Plans Grid ──────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8" id="plans-grid">
        @forelse($plans as $plan)
            @php
                $tierColors = [
                    'bronze' => ['border' => 'border-orange-300', 'badge' => 'bg-orange-100 text-orange-700'],
                    'silver' => ['border' => 'border-gray-300', 'badge' => 'bg-gray-100 text-gray-700'],
                    'gold' => ['border' => 'border-yellow-400', 'badge' => 'bg-yellow-100 text-yellow-700'],
                    'platinum' => ['border' => 'border-purple-400', 'badge' => 'bg-purple-100 text-purple-700'],
                ];
                $key = strtolower($plan->name_en);
                $tc = $tierColors[$key] ?? ['border' => 'border-gray-200', 'badge' => 'bg-gray-100 text-gray-700'];
            @endphp
            <div class="bg-white rounded-2xl border-2 {{ $tc['border'] }} p-5 flex flex-col">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <span
                            class="text-xs font-bold rounded-full px-2.5 py-0.5 {{ $tc['badge'] }}">{{ $plan->name_en }}</span>
                        <p class="text-xs text-gray-400 mt-1">{{ $plan->name_ar }}</p>
                    </div>
                    <span
                        class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $plan->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $plan->is_active ? __('admin.subscriptions.active') : __('admin.subscriptions.inactive') }}
                    </span>
                </div>

                <p class="text-2xl font-extrabold text-gray-900 mb-1">
                    {{ number_format($plan->price) }}
                    <span class="text-sm font-medium text-gray-400">{{ $plan->currency }}/mo</span>
                </p>

                <ul class="space-y-1 text-xs text-gray-600 mb-4 flex-1">
                    <li>
                        📦
                        <strong>{{ $plan->hasUnlimitedListings() ? __('admin.subscriptions.unlimited') : number_format($plan->max_listings) }}</strong>
                        {{ __('admin.subscriptions.listings_word') }}
                    </li>
                    <li>
                        💸 {{ __('admin.subscriptions.commission_discount_label') }}
                        <strong>{{ $plan->commission_discount_pct }}%</strong>
                    </li>
                    <li>
                        🚚 {{ __('admin.subscriptions.free_shipping_label') }}
                        <strong>{{ $plan->free_shipping_included ? __('admin.subscriptions.yes') : __('admin.subscriptions.no') }}</strong>
                    </li>
                    @foreach ($plan->features ?? [] as $feature)
                        <li>✓ {{ ucwords(str_replace('_', ' ', $feature)) }}</li>
                    @endforeach
                </ul>

                <div class="flex gap-2 pt-3 border-t border-gray-100">
                    <button type="button" class="flex-1 btn btn-ghost btn-xs btn-edit-plan"
                        data-plan="{{ json_encode($plan) }}">{{ __('admin.subscriptions.edit') }}</button>
                    <button type="button"
                        class="btn btn-xs btn-toggle-plan {{ $plan->is_active ? 'btn-warning' : 'btn-success' }}"
                        data-id="{{ $plan->id }}" data-active="{{ $plan->is_active ? 1 : 0 }}">
                        {{ $plan->is_active ? __('admin.subscriptions.deactivate') : __('admin.subscriptions.activate') }}
                    </button>
                    <button type="button" class="btn btn-xs btn-danger btn-delete-plan"
                        data-id="{{ $plan->id }}">{{ __('admin.subscriptions.delete_short') }}</button>
                </div>
            </div>
        @empty
            <div class="col-span-4 bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-400">
                {{ __('admin.subscriptions.no_plans_yet') }}
            </div>
        @endforelse
    </div>

    {{-- ─── Plan Modal ──────────────────────────────────────────────────────────── --}}
    <div id="plan-modal" class="modal-backdrop hidden">
        <div class="modal-box modal-lg p-6">
            <h3 class="font-bold text-lg mb-5" id="plan-modal-title">{{ __('admin.subscriptions.new_subscription_plan') }}
            </h3>
            <input type="hidden" id="plan-modal-id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.name_en') }} <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="pm-name-en" class="form-input w-full text-sm" dir="ltr">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.name_ar') }} <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="pm-name-ar" class="form-input w-full text-sm" dir="rtl">
                </div>
                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.subscriptions.description_en') }}</label>
                    <textarea id="pm-desc-en" rows="2" class="form-input w-full text-sm" dir="ltr"></textarea>
                </div>
                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.subscriptions.description_ar') }}</label>
                    <textarea id="pm-desc-ar" rows="2" class="form-input w-full text-sm" dir="rtl"></textarea>
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.price_smallest_unit') }} <span
                            class="text-red-500">*</span></label>
                    <input type="number" id="pm-price" class="form-input w-full text-sm"
                        placeholder="{{ __('admin.subscriptions.price_placeholder') }}">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.currency') }} <span
                            class="text-red-500">*</span></label>
                    <input type="text" id="pm-currency" class="form-input w-full text-sm" value="EGP" maxlength="3">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.max_listings') }}</label>
                    <input type="number" id="pm-max-listings" class="form-input w-full text-sm" min="1">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.commission_discount_pct') }}</label>
                    <input type="number" id="pm-commission-discount" class="form-input w-full text-sm" min="0"
                        max="100" step="0.01" value="0">
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <input type="checkbox" id="pm-free-shipping" class="w-4 h-4">
                    <label for="pm-free-shipping"
                        class="text-sm font-medium text-gray-700">{{ __('admin.subscriptions.free_shipping_included') }}</label>
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.sort_order') }}</label>
                    <input type="number" id="pm-sort-order" class="form-input w-full text-sm" value="0"
                        min="0">
                </div>
                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.subscriptions.features_one_per_line') }}</label>
                    <textarea id="pm-features" rows="3" class="form-input w-full text-sm font-mono"
                        placeholder="{{ __('admin.subscriptions.features_placeholder') }}"></textarea>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="plan-modal-cancel"
                    class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.cancel') }}</button>
                <button type="button" id="plan-modal-save"
                    class="btn btn-primary btn-sm px-8">{{ __('admin.subscriptions.save_plan') }}</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            newSubscriptionPlan: @json(__('admin.subscriptions.new_subscription_plan')),
            editPlanPrefix: @json(__('admin.subscriptions.edit_plan_prefix')),
            error: @json(__('admin.subscriptions.error')),
            deletePlanTitle: @json(__('admin.subscriptions.delete_plan_confirm_title')),
            deletePlanText: @json(__('admin.subscriptions.delete_plan_confirm_text')),
        });

        document.addEventListener('DOMContentLoaded', function() {
            const tok = '{{ csrf_token() }}';

            function refreshPage() {
                location.reload();
            }

            // ── Open create modal ──────────────────────────────────────────────────────
            $('#btn-create-plan').on('click', function() {
                $('#plan-modal-id').val('');
                $('#plan-modal-title').text(window.TRANSLATIONS.newSubscriptionPlan);
                ['name-en', 'name-ar', 'desc-en', 'desc-ar', 'features'].forEach(id => $('#pm-' + id).val(
                    ''));
                $('#pm-price').val('');
                $('#pm-currency').val('EGP');
                $('#pm-max-listings').val('');
                $('#pm-commission-discount').val('0');
                $('#pm-free-shipping').prop('checked', false);
                $('#pm-sort-order').val('0');
                $('#plan-modal').modal('open');
            });

            // ── Open edit modal ────────────────────────────────────────────────────────
            $(document).on('click', '.btn-edit-plan', function() {
                const p = $(this).data('plan');
                $('#plan-modal-id').val(p.id);
                $('#plan-modal-title').text(window.TRANSLATIONS.editPlanPrefix + ' ' + p.name_en);
                $('#pm-name-en').val(p.name_en);
                $('#pm-name-ar').val(p.name_ar);
                $('#pm-desc-en').val(p.description_en);
                $('#pm-desc-ar').val(p.description_ar);
                $('#pm-price').val(p.price);
                $('#pm-currency').val(p.currency);
                $('#pm-max-listings').val(p.max_listings ?? '');
                $('#pm-commission-discount').val(p.commission_discount_pct);
                $('#pm-free-shipping').prop('checked', !!p.free_shipping_included);
                $('#pm-sort-order').val(p.sort_order ?? 0);
                $('#pm-features').val((p.features ?? []).join('\n'));
                $('#plan-modal').modal('open');
            });

            $('#plan-modal-cancel').on('click', () => $('#plan-modal').modal('close'));

            // ── Save plan ──────────────────────────────────────────────────────────────
            $('#plan-modal-save').on('click', function() {
                const id = $('#plan-modal-id').val();
                const features = $('#pm-features').val().split('\n').map(s => s.trim()).filter(Boolean);
                const payload = {
                    name_en: $('#pm-name-en').val(),
                    name_ar: $('#pm-name-ar').val(),
                    description_en: $('#pm-desc-en').val(),
                    description_ar: $('#pm-desc-ar').val(),
                    price: parseInt($('#pm-price').val()) || 0,
                    currency: $('#pm-currency').val(),
                    max_listings: $('#pm-max-listings').val() || null,
                    commission_discount_pct: parseFloat($('#pm-commission-discount').val()) || 0,
                    free_shipping_included: $('#pm-free-shipping').is(':checked') ? 1 : 0,
                    sort_order: parseInt($('#pm-sort-order').val()) || 0,
                    features,
                };

                const url = id ? '/subscriptions/plans/' + id :
                    '{{ route('admin.subscriptions.plans.store') }}';
                const method = id ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': tok,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        window.Toast.success(data.message);
                        $('#plan-modal').modal('close');
                        refreshPage();
                    } else {
                        window.Toast.error(data.message ?? window.TRANSLATIONS.error);
                    }
                });
            });

            // ── Toggle active ──────────────────────────────────────────────────────────
            $(document).on('click', '.btn-toggle-plan', function() {
                const id = $(this).data('id');
                fetch('/subscriptions/plans/' + id + '/toggle-active', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': tok,
                        'Content-Type': 'application/json'
                    },
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

            // ── Delete plan ────────────────────────────────────────────────────────────
            $(document).on('click', '.btn-delete-plan', function() {
                const id = $(this).data('id');
                window.confirmDelete(window.TRANSLATIONS.deletePlanText, {
                    title: window.TRANSLATIONS.deletePlanTitle,
                }).then((confirmed) => {
                    if (!confirmed) return;
                    fetch('/subscriptions/plans/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': tok,
                            'Content-Type': 'application/json'
                        },
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
        });
    </script>
@endpush
