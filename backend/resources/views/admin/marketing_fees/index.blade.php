@extends('layouts.admin')

@section('title', __('marketing_fees.title'))

@section('content')
    @php
        $canEdit = auth('admin')->user()->can('marketer_fee_settings.edit');
        $input = 'rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500';
        $btn = 'inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('marketing_fees.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('marketing_fees.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-data="{ tab: '{{ $tab }}' }" data-marketing-fees>
        <div class="mb-4 flex gap-2 border-b border-gray-200">
            @foreach (['fixed', 'variable', 'report'] as $t)
                <button type="button" @click="tab = '{{ $t }}'"
                    :class="tab === '{{ $t }}' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-medium">{{ __('marketing_fees.tab_'.$t) }}</button>
            @endforeach
        </div>

        {{-- Fixed fees --}}
        <div x-show="tab === 'fixed'" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-xs text-gray-500 mb-4">{{ __('marketing_fees.fixed_hint') }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.country') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.fixed_amount') }}</th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.fixed_percentage') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($countries as $country)
                            @php $row = $countrySettings->get($country->id); @endphp
                            <tr>
                                <form method="POST" action="{{ route('admin.marketing-fees.update-fixed') }}" class="contents">
                                    @csrf
                                    <input type="hidden" name="country_id" value="{{ $country->id }}">
                                    <td class="px-3 py-2 text-gray-900">{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }} <span class="text-xs text-gray-400">{{ $country->currency_code }}</span></td>
                                    <td class="px-3 py-2"><input type="number" min="0" step="0.0001" name="fixed_amount" value="{{ $row?->fixed_amount ?? 0 }}" class="w-28 {{ $input }}" @disabled(! $canEdit)></td>
                                    <td class="px-3 py-2"><input type="number" min="0" max="100" step="0.0001" name="fixed_percentage" value="{{ $row?->fixed_percentage ?? 0 }}" class="w-28 {{ $input }}" @disabled(! $canEdit)></td>
                                    <td class="px-3 py-2">@if ($canEdit)<button type="submit" class="{{ $btn }}">{{ __('common.save') }}</button>@endif</td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Variable fees --}}
        <div x-show="tab === 'variable'" x-cloak class="space-y-6">
            @if ($canEdit)
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('marketing_fees.add_rule') }}</h2>
                    <p class="text-xs text-gray-500 mb-4">{{ __('marketing_fees.variable_hint') }}</p>
                    <form method="POST" action="{{ route('admin.marketing-fees.update-variable') }}" class="grid gap-4 md:grid-cols-3" x-data="{ all: false }">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.country') }}</label>
                            <select name="country_id" required class="w-full {{ $input }}">
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected(old('country_id') === $country->id)>{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <x-form.async-select name="category_id" :label="__('marketing_fees.category')" :placeholder="__('marketing_fees.category_placeholder')"
                                :search-url="route('admin.marketing-fees.categories')" :min-length="0" required />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.type') }}</label>
                            <select name="fulfillment_type" :disabled="all" class="w-full {{ $input }}">
                                <option value="">{{ __('marketing_fees.type_placeholder') }}</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}" @selected(old('fulfillment_type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <label class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" name="all_types" value="1" x-model="all" class="rounded border-gray-300"> {{ __('marketing_fees.all_types') }}
                            </label>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.fixed_amount') }}</label>
                            <input type="number" min="0" step="0.0001" name="fixed_amount" value="{{ old('fixed_amount', 0) }}" required class="w-full {{ $input }}">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.percentage') }}</label>
                            <input type="number" min="0" max="100" step="0.0001" name="percentage" value="{{ old('percentage', 0) }}" required class="w-full {{ $input }}">
                        </div>
                        <div class="md:col-span-3"><button type="submit" class="{{ $btn }}">{{ __('common.save') }}</button></div>
                    </form>
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('marketing_fees.existing_rules') }}</h2>
                    <form method="GET" action="{{ route('admin.marketing-fees.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="variable">
                        <select name="country_id" onchange="this.form.submit()" class="{{ $input }}">
                            <option value="">{{ __('marketing_fees.all_countries') }}</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected(request('country_id') === $country->id)>{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.country') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.category') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.type') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.fixed_amount') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketing_fees.percentage') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($variableSettings as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ app()->getLocale() === 'ar' ? $row->country?->name_ar : $row->country?->name_en }}</td>
                                    <td class="px-3 py-2">{{ $row->category?->name }}</td>
                                    <td class="px-3 py-2">{{ $row->fulfillment_type->label() }}</td>
                                    <td class="px-3 py-2">{{ $row->fixed_amount }}</td>
                                    <td class="px-3 py-2">{{ $row->percentage }}%</td>
                                    <td class="px-3 py-2">
                                        @if ($canEdit)
                                            <form method="POST" action="{{ route('admin.marketing-fees.destroy-variable', $row) }}" onsubmit="return confirm(@js(__('marketing_fees.delete_confirm')))">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800">{{ __('common.delete') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('marketing_fees.no_rules') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Report (stub until P8) --}}
        <div x-show="tab === 'report'" x-cloak class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm text-sm text-gray-600" data-report-stub>
            {{ __('marketing_fees.report_stub') }}
        </div>
    </div>

    {{-- Live calculator --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm mt-6" data-fee-calculator
        x-data="feeCalculator(@js(route('admin.marketing-fees.calculate')))">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('marketing_fees.calculator_title') }}</h2>
        <p class="text-xs text-gray-500 mb-4">{{ __('marketing_fees.calculator_hint') }}</p>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.mode') }}</label>
                <div class="inline-flex rounded-md border border-gray-300 overflow-hidden text-sm">
                    <button type="button" @click="mode = 'influencer'; run()" :class="mode === 'influencer' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700'" class="px-3 py-1.5">{{ __('marketing_fees.mode_influencer') }}</button>
                    <button type="button" @click="mode = 'affiliate'; run()" :class="mode === 'affiliate' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700'" class="px-3 py-1.5">{{ __('marketing_fees.mode_affiliate') }}</button>
                </div>
                <label x-show="mode === 'affiliate'" x-cloak class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" x-model="firstOrder" @change="run()" class="rounded border-gray-300"> {{ __('marketing_fees.first_order') }}
                </label>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1" x-text="mode === 'influencer' ? @js(__('marketing_fees.subtotal_influencer')) : @js(__('marketing_fees.subtotal_affiliate'))"></label>
                <input type="number" min="0" step="0.01" x-model="subtotal" @input.debounce.300ms="run()" class="w-full {{ $input }}">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.country') }}</label>
                <select x-model="country" @change="run()" class="w-full {{ $input }}">
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}">{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }} ({{ $country->currency_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <x-form.async-select name="calc_category_id" :label="__('marketing_fees.category')" :placeholder="__('marketing_fees.category_placeholder')"
                    :search-url="route('admin.marketing-fees.categories')" :min-length="0" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('marketing_fees.type') }}</label>
                <select x-model="type" @change="run()" class="w-full {{ $input }}">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="button" @click="run()" class="{{ $btn }}">{{ __('marketing_fees.calculate') }}</button>
            <span x-show="error" x-cloak class="text-xs text-red-600">{{ __('marketing_fees.calc_error') }}</span>
        </div>
        <dl x-show="result" x-cloak class="mt-4 grid max-w-md grid-cols-2 gap-y-1 text-sm">
            <dt class="text-gray-500">{{ __('marketing_fees.result_subtotal') }}</dt><dd class="font-medium" x-text="result?.subtotal.toFixed(2)"></dd>
            <dt class="text-gray-500">{{ __('marketing_fees.result_fixed') }}</dt><dd class="font-medium" x-text="result?.fixed_fee.toFixed(2)"></dd>
            <dt class="text-gray-500">{{ __('marketing_fees.result_variable') }}</dt><dd class="font-medium" x-text="result?.variable_fee.toFixed(2)"></dd>
            <dt class="text-gray-500">{{ __('marketing_fees.result_total') }}</dt><dd class="font-semibold" x-text="result?.marketing_fees.toFixed(2)"></dd>
            <dt class="text-gray-500">{{ __('marketing_fees.result_grand') }}</dt><dd class="font-bold text-gray-900" x-text="result?.grand_total.toFixed(2)"></dd>
        </dl>
    </div>

    <script>
        function feeCalculator(url) {
            return {
                mode: 'influencer', subtotal: 18.9, country: @js($countries->first()?->id), type: 'fbn', firstOrder: true,
                result: null, error: false,
                async run() {
                    this.error = false;
                    const cat = document.getElementById('calc_category_id');
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                            body: JSON.stringify({ mode: this.mode, subtotal: this.subtotal, country_id: this.country, category_id: cat?.value || null, fulfillment_type: this.type, first_order: this.firstOrder }),
                        });
                        if (!res.ok) throw new Error('bad');
                        this.result = await res.json();
                    } catch (e) { this.result = null; this.error = true; }
                },
                init() {
                    const cat = document.getElementById('calc_category_id');
                    if (cat && window.jQuery) { window.jQuery(cat).on('change', () => this.run()); }
                },
            };
        }
    </script>
@endsection
