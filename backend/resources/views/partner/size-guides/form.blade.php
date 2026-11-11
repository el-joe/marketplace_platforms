@extends('layouts.partner')

@php
    $isEdit = $guide !== null;
    $state = [
        'columns' => old('columns', $initial['columns'] ?? []),
        'rows' => old('rows', $initial['rows'] ?? []),
    ];
    $field = fn (string $key) => old($key, $initial[$key] ?? '');
    $inputClass = 'block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent';
@endphp

@section('title', $isEdit ? __('partner.size_guides.edit') : __('partner.size_guides.create'))
@section('page-title', $isEdit ? __('partner.size_guides.edit') : __('partner.size_guides.create'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('partner.size-guides.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('partner.size_guides.title') }}</a>
    </div>

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
            <ul class="list-disc ps-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $isEdit ? route('partner.size-guides.update', $guide) : route('partner.size-guides.store') }}"
          x-data="sizeGuideEditor(@js($state), @js($template))"
          x-ref="form"
          class="space-y-4 max-w-5xl">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="columns" :value="JSON.stringify(columns)">
        <input type="hidden" name="rows" :value="JSON.stringify(rows)">

        {{-- Basics --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="255" value="{{ $field('name') }}" class="{{ $inputClass }}">
                    <p class="text-xs text-gray-400 mt-1">{{ __('partner.size_guides.name_hint') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.title_en') }}</label>
                    <input type="text" name="title_en" x-ref="title_en" maxlength="255" value="{{ $field('title_en') }}" class="{{ $inputClass }}" dir="ltr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.title_ar') }}</label>
                    <input type="text" name="title_ar" x-ref="title_ar" maxlength="255" value="{{ $field('title_ar') }}" class="{{ $inputClass }}" dir="rtl">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.intro_en') }}</label>
                    <textarea name="intro_en" x-ref="intro_en" rows="3" maxlength="3000" class="{{ $inputClass }}" dir="ltr">{{ $field('intro_en') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.intro_ar') }}</label>
                    <textarea name="intro_ar" x-ref="intro_ar" rows="3" maxlength="3000" class="{{ $inputClass }}" dir="rtl">{{ $field('intro_ar') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Size table --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-gray-800">{{ __('partner.size_guides.table') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.size_guides.range_hint') }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('partner.size_guides.unit') }}</label>
                        <select name="unit" x-model="unit" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            @foreach(\App\Models\SizeGuide::UNITS as $unit)
                                <option value="{{ $unit }}" @selected($field('unit') === $unit)>{{ __('partner.size_guides.units.'.$unit) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" @click="loadTemplate(@js(__('partner.size_guides.load_template_confirm')))"
                            class="border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2 rounded-xl">
                        {{ __('partner.size_guides.load_template') }}
                    </button>
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-3">{{ __('partner.size_guides.unit_hint') }}</p>

            <div class="overflow-x-auto -mx-5 px-5">
                <table class="min-w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="sticky start-0 bg-white px-2 py-2 text-start align-bottom text-xs font-semibold text-gray-600 min-w-[90px]">
                                {{ __('partner.size_guides.size') }}
                            </th>
                            <template x-for="(column, ci) in columns" :key="column.key">
                                <th class="px-2 py-2 align-bottom min-w-[150px]">
                                    <div class="space-y-1 bg-gray-50 rounded-xl p-2">
                                        <input type="text" x-model="column.label_en" placeholder="{{ __('partner.size_guides.column_en') }}" dir="ltr"
                                               class="w-full rounded-lg border border-gray-200 px-2 py-1 text-xs font-medium">
                                        <input type="text" x-model="column.label_ar" placeholder="{{ __('partner.size_guides.column_ar') }}" dir="rtl"
                                               class="w-full rounded-lg border border-gray-200 px-2 py-1 text-xs font-medium">
                                        <div class="flex items-center justify-between gap-1">
                                            <label class="flex items-center gap-1 text-[11px] font-normal text-gray-500">
                                                <input type="checkbox" x-model="column.is_measurement" class="rounded">
                                                {{ __('partner.size_guides.is_measurement') }}
                                            </label>
                                            <div class="flex items-center gap-1 text-gray-400">
                                                <button type="button" @click="moveColumn(ci, -1)" :disabled="ci === 0" class="hover:text-gray-700 disabled:opacity-30 px-1">&lsaquo;</button>
                                                <button type="button" @click="moveColumn(ci, 1)" :disabled="ci === columns.length - 1" class="hover:text-gray-700 disabled:opacity-30 px-1">&rsaquo;</button>
                                                <button type="button" @click="removeColumn(ci)" class="hover:text-red-500 px-1">&times;</button>
                                            </div>
                                        </div>
                                    </div>
                                </th>
                            </template>
                            <th class="px-2 py-2 align-bottom">
                                <button type="button" @click="addColumn()" class="whitespace-nowrap text-xs text-primary-600 hover:text-primary-700 font-medium">
                                    + {{ __('partner.size_guides.add_column') }}
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, ri) in rows" :key="ri">
                            <tr>
                                <td class="sticky start-0 bg-white px-2 py-1">
                                    <input type="text" x-model="row.size_label" maxlength="50" required
                                           class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm font-semibold">
                                </td>
                                <template x-for="column in columns" :key="column.key">
                                    <td class="px-2 py-1">
                                        <input type="text" x-model="row.values[column.key]" maxlength="40" dir="ltr"
                                               class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                    </td>
                                </template>
                                <td class="px-2 py-1 whitespace-nowrap text-gray-400">
                                    <button type="button" @click="moveRow(ri, -1)" :disabled="ri === 0" class="hover:text-gray-700 disabled:opacity-30 px-1">&uarr;</button>
                                    <button type="button" @click="moveRow(ri, 1)" :disabled="ri === rows.length - 1" class="hover:text-gray-700 disabled:opacity-30 px-1">&darr;</button>
                                    <button type="button" @click="rows.splice(ri, 1)" class="hover:text-red-500 px-1">&times;</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" @click="addRow()" class="mt-3 text-sm text-primary-600 hover:text-primary-700 font-medium">
                + {{ __('partner.size_guides.add_row') }}
            </button>
        </div>

        {{-- How to measure --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.how_to_measure_en') }}</label>
                    <textarea name="how_to_measure_en" x-ref="how_to_measure_en" rows="5" maxlength="5000" class="{{ $inputClass }}" dir="ltr">{{ $field('how_to_measure_en') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">{{ __('partner.size_guides.how_to_measure_hint') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.how_to_measure_ar') }}</label>
                    <textarea name="how_to_measure_ar" x-ref="how_to_measure_ar" rows="5" maxlength="5000" class="{{ $inputClass }}" dir="rtl">{{ $field('how_to_measure_ar') }}</textarea>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.size_guides.image') }}</label>
                @if($isEdit && $guide->image_path)
                    <div class="flex items-center gap-3 mb-2">
                        <img src="{{ $guide->imageUrl() }}" alt="" class="h-24 rounded-lg border object-contain bg-gray-50">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_image" value="1" class="rounded">
                            {{ __('partner.size_guides.remove_image') }}
                        </label>
                    </div>
                @endif
                <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="text-sm">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold px-6 py-2.5 rounded-xl text-sm">
                {{ __('partner.size_guides.save') }}
            </button>
        </div>
    </form>

    <script>
        function sizeGuideEditor(state, template) {
            const normaliseRow = (row) => ({
                size_label: row.size_label ?? '',
                values: Object.assign({}, row.values ?? {}),
            });

            return {
                unit: @js($field('unit') ?: 'cm'),
                columns: (state.columns ?? []).map((c) => ({ ...c, is_measurement: c.is_measurement !== false })),
                rows: (state.rows ?? []).map(normaliseRow),

                addColumn() {
                    this.columns.push({ key: 'c' + Date.now().toString(36), label_en: '', label_ar: '', is_measurement: true });
                },
                removeColumn(index) {
                    const [removed] = this.columns.splice(index, 1);
                    this.rows.forEach((row) => delete row.values[removed.key]);
                },
                moveColumn(index, delta) {
                    const [column] = this.columns.splice(index, 1);
                    this.columns.splice(index + delta, 0, column);
                },
                addRow() {
                    this.rows.push({ size_label: '', values: {} });
                },
                moveRow(index, delta) {
                    const [row] = this.rows.splice(index, 1);
                    this.rows.splice(index + delta, 0, row);
                },
                loadTemplate(message) {
                    const hasContent = this.rows.some((r) => r.size_label) || this.columns.length > 0;
                    if (hasContent && !confirm(message)) {
                        return;
                    }
                    this.unit = template.unit;
                    this.columns = template.columns.map((c) => ({ ...c }));
                    this.rows = template.rows.map(normaliseRow);
                    ['title_en', 'title_ar', 'intro_en', 'intro_ar', 'how_to_measure_en', 'how_to_measure_ar'].forEach((key) => {
                        if (this.$refs[key]) {
                            this.$refs[key].value = template[key] ?? '';
                        }
                    });
                },
            };
        }
    </script>
@endsection
