{{-- Vendor chooser: picking a vendor reloads this same tab with ?vendor= so the form is prefilled server-side. --}}
<form method="GET" action="{{ route('admin.commissions.index') }}" class="my-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="{{ $activeKey }}">
    <div class="min-w-[16rem] flex-1">
        <label for="vendor-picker" class="{{ $label }}">{{ __('commissions.vendor_form.choose_vendor') }}</label>
        <select id="vendor-picker" name="vendor" class="{{ $input }}" onchange="this.form.submit()">
            <option value="">—</option>
            @foreach ($editor['vendors'] as $v)
                <option value="{{ $v->id }}" @selected($editor['vendor']?->id === $v->id)>{{ $v->store_name }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.vendor_form.load') }}</button></noscript>
</form>
@if ($editor['vendors']->count() >= \App\Services\Admin\Commissions\CommissionEditorData::OPTION_LIMIT)
    <p class="-mt-3 mb-3 text-xs text-amber-800">{{ __('commissions.vendor_form.list_capped', ['limit' => \App\Services\Admin\Commissions\CommissionEditorData::OPTION_LIMIT]) }}</p>
@endif
