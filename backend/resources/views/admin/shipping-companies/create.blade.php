@extends('layouts.admin')

@section('title', __('admin.shipping_section.add_company'))

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.shipping-companies.index') }}"
       class="text-sm text-indigo-600 hover:underline">{{ __('admin.shipping_section.back_to_shipping_companies') }}</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ __('admin.shipping_section.add_company') }}</h1>
</div>

<div class="bg-white rounded-xl border border-gray-200 max-w-2xl">
    <form id="company-create-form" enctype="multipart/form-data">
        @csrf

        <div class="px-6 py-5 space-y-5">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                    {{ __('admin.shipping_section.section_basic_info') }}
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.company_name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" class="form-input w-full" required>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.legal_name') }}
                        </label>
                        <input type="text" name="legal_name" class="form-input w-full">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.contact_email') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="contact_email" class="form-input w-full" required>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.phone') }}
                        </label>
                        <input type="text" name="contact_phone" class="form-input w-full">
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                    {{ __('admin.shipping_section.section_geography') }}
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.home_country') }}
                        </label>
                        <select name="country_id" class="form-input w-full">
                            <option value="">— {{ __('admin.shipping_section.select_country') }} —</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.status_label') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="status" class="form-input w-full" required>
                            <option value="pending">{{ __('admin.shipping_section.company_status_pending') }}</option>
                            <option value="active">{{ __('admin.shipping_section.company_status_active') }}</option>
                            <option value="suspended">{{ __('admin.shipping_section.company_status_suspended') }}</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.served_countries') }}
                        </label>
                        <select name="served_countries[]" multiple class="form-input w-full" style="min-height: 100px;">
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">
                                    {{ $country->name_en }} ({{ $country->currency_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                    {{ __('admin.shipping_section.section_logo') }}
                </h4>
                <input type="file" name="logo" accept="image/*" class="form-input w-full text-sm">
                <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.logo_hint') }}</p>
            </div>

            <div>
                <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="can_supervisors_receive_all_notifications"
                           value="1" class="rounded border-gray-300 text-primary-600" checked>
                    {{ __('admin.shipping_section.can_supervisors_receive_all_notifications_label') }}
                </label>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
            <a href="{{ route('admin.shipping-companies.index') }}" class="btn btn-ghost btn-sm">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                {{ __('admin.shipping_section.create_company') }}
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('company-create-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const res  = await fetch(@json(route('admin.shipping-companies.store')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join('\n') : data.message;
            alert(errors || @json(__('admin.shipping_section.failed_to_save')));
        }
    } catch {
        alert(@json(__('admin.shipping_section.failed_to_save')));
    }
});
</script>
@endpush
