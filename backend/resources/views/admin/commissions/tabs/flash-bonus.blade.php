@php
    $canEdit = auth('admin')->user()->can('flash_sales.edit');
    $saleName = fn ($s) => $s->name_en ?: $s->name_ar;
    $enumValue = fn ($v) => $v instanceof \BackedEnum ? $v->value : (string) $v;
@endphp

<p class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="note">{{ __('commissions.flash_form.scope_note') }}</p>
<p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">{{ __('commissions.flash_form.override_not_here') }}</p>

@if ($canEdit)
    <form method="POST" id="fb-form" action="#" data-ajax data-confirm="{{ __('commissions.flash_form.confirm_invite') }}" class="my-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
        @csrf
        <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ __('commissions.flash_form.heading') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.flash_form.help') }}</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="fb-sale" class="{{ $label }}">{{ __('commissions.columns.flash_sale') }}</label>
                <select id="fb-sale" required class="{{ $input }}">
                    <option value="" data-action="#">—</option>
                    @foreach ($editor['flashSales'] as $sale)
                        <option value="{{ $sale->id }}" data-action="{{ route('admin.flash-sales.invite-marketers', $sale->id) }}">{{ $saleName($sale) }} ({{ $enumValue($sale->status) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fb-marketers" class="{{ $label }}">{{ __('commissions.columns.marketer') }}</label>
                <select id="fb-marketers" name="marketer_ids[]" multiple required size="4" class="{{ $input }}">
                    @foreach ($editor['marketers'] as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                    @endforeach
                </select>
                <p data-error-for="marketer_ids" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="fb-rate" class="{{ $label }}">{{ __('commissions.marketer_form.rate') }}</label>
                <input id="fb-rate" type="number" name="extra_commission_rate" min="0" max="100" step="0.01" class="{{ $input }}">
                <p data-error-for="extra_commission_rate" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="fb-flat" class="{{ $label }}">{{ __('commissions.marketer_form.flat') }}</label>
                <input id="fb-flat" type="number" name="extra_commission_flat_amount" min="0" step="1" class="{{ $input }}">
                <p data-error-for="extra_commission_flat_amount" class="hidden text-xs text-red-700"></p>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">{{ __('commissions.notices.flash_bonus_rules') }}</p>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.flash_form.invite') }}</button>
            <span data-status role="status" class="text-xs"></span>
        </div>
    </form>
    <script>
    (() => {
        const form = document.getElementById('fb-form');
        const sale = document.getElementById('fb-sale');
        sale.addEventListener('change', () => { form.action = sale.selectedOptions[0].dataset.action || '#'; });
        form.addEventListener('submit', (e) => { if (form.action.endsWith('#')) { e.stopImmediatePropagation(); e.preventDefault(); sale.focus(); } }, true);
    })();
    </script>
@else
    <p class="my-4 text-xs text-gray-500">{{ __('commissions.rule_form.read_only') }}</p>
@endif

@foreach ($content['tables'] as $table)
    @include('admin.commissions.partials.table', ['table' => $table])
@endforeach
