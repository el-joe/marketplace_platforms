@extends('layouts.partner')

@section('title', __('partner.product_certifications.index.title'))
@section('page-title', __('partner.product_certifications.index.title'))

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.product_certifications.index.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('partner.product_certifications.index.subtitle') }}</p>
        </div>

        @if($hasIssues)
            <div class="mb-4 flex items-start gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                <span aria-hidden="true">⚠</span>
                <span>{{ __('partner.product_certifications.index.banner') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_product') }}</th>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_country') }}</th>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_status') }}</th>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_cert_name') }}</th>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_uploaded_at') }}</th>
                        <th class="px-4 py-3">{{ __('partner.product_certifications.index.table_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row['product_name'] }}</td>
                            <td class="px-4 py-3">{{ $row['country_name'] }}</td>
                            <td class="px-4 py-3">
                                @switch($row['status'])
                                    @case('pending')
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                            {{ __('partner.product_certifications.index.status_pending') }}
                                        </span>
                                        @break
                                    @case('approved')
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                            {{ __('partner.product_certifications.index.status_approved') }}
                                        </span>
                                        @if($row['cert']?->expires_at)
                                            <span class="ml-1 text-xs text-gray-500">({{ $row['cert']->expires_at->format('Y-m-d') }})</span>
                                        @endif
                                        @break
                                    @case('rejected')
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                            {{ __('partner.product_certifications.index.status_rejected') }}
                                        </span>
                                        @if($row['cert']?->rejection_reason)
                                            <p class="mt-1 text-xs text-red-600">{{ $row['cert']->rejection_reason }}</p>
                                        @endif
                                        @break
                                    @default
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                            {{ __('partner.product_certifications.index.status_missing') }}
                                        </span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3">{{ $row['cert']?->cert_name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['cert']?->created_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if(in_array($row['status'], ['missing', 'rejected'], true))
                                    <button type="button"
                                            class="js-cert-upload-btn text-sm font-semibold text-yellow-600 hover:text-yellow-700"
                                            data-product-id="{{ $row['product_id'] }}"
                                            data-country-id="{{ $row['country_id'] }}"
                                            data-replace-id="{{ $row['cert']?->id }}">
                                        {{ __('partner.product_certifications.index.upload') }}
                                    </button>
                                @elseif($row['status'] === 'approved')
                                    <button type="button"
                                            class="js-cert-upload-btn text-sm font-semibold text-gray-600 hover:text-gray-800"
                                            data-product-id="{{ $row['product_id'] }}"
                                            data-country-id="{{ $row['country_id'] }}"
                                            data-replace-id="{{ $row['cert']->id }}">
                                        {{ __('partner.product_certifications.index.replace') }}
                                    </button>
                                @else
                                    <span class="text-sm text-gray-400">{{ __('partner.product_certifications.index.pending_review') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Upload modal --}}
    <div id="cert-upload-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('partner.product_certifications.index.modal_title') }}</h2>

            <form id="cert-upload-form" class="mt-4 space-y-4" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="product_id" id="cert-product-id">
                <input type="hidden" name="country_id" id="cert-country-id">
                <input type="hidden" name="replace_id" id="cert-replace-id">

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('partner.product_certifications.index.cert_name_label') }}</label>
                    <input type="text" name="cert_name"
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('partner.product_certifications.index.file_label') }}</label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="filepond mt-1 w-full text-sm">
                </div>

                <p class="js-cert-error hidden text-sm text-red-600"></p>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="js-cert-cancel-btn rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('partner.product_certifications.index.cancel') }}
                    </button>
                    <button type="submit" class="rounded-lg bg-yellow-400 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-yellow-500">
                        {{ __('partner.product_certifications.index.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('cert-upload-modal');
            const form = document.getElementById('cert-upload-form');
            const errorEl = form.querySelector('.js-cert-error');

            function openModal(productId, countryId, replaceId) {
                document.getElementById('cert-product-id').value = productId || '';
                document.getElementById('cert-country-id').value = countryId || '';
                document.getElementById('cert-replace-id').value = replaceId || '';
                form.reset();
                errorEl.classList.add('hidden');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.querySelectorAll('.js-cert-upload-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    openModal(btn.dataset.productId, btn.dataset.countryId, btn.dataset.replaceId);
                });
            });

            document.querySelector('.js-cert-cancel-btn').addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                errorEl.classList.add('hidden');

                const replaceId = document.getElementById('cert-replace-id').value;
                const url = replaceId
                    ? '{{ url('product-certifications') }}/' + replaceId + '/replace'
                    : '{{ route('partner.product-certifications.store') }}';

                const formData = new FormData(form);

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData,
                    });
                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        errorEl.textContent = data.message || 'Upload failed.';
                        errorEl.classList.remove('hidden');
                        return;
                    }

                    window.location.reload();
                } catch (err) {
                    errorEl.textContent = 'Upload failed.';
                    errorEl.classList.remove('hidden');
                }
            });
        })();
    </script>
@endpush
