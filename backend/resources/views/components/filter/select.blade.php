{{--
  Usage (inside the form wrapped by <x-filter-card>):
    <x-filter.select name="status" label="Status" :options="['active' => 'Active', 'paused' => 'Paused']" />

  Props:
    name        (string) — request input name
    label       (string)
    options     (array)  — value => label
    placeholder (string) — default option label (default: __('common.all'))
    value       (mixed)  — fallback selected value if not present in request()
--}}
@props([
    'name',
    'label'       => null,
    'options'     => [],
    'placeholder' => null,
    'value'       => null,
])

<div>
    <label for="filter-{{ $name }}" class="block text-xs font-medium text-gray-500 mb-1">
        {{ $label }}
    </label>
    <select id="filter-{{ $name }}" name="{{ $name }}" class="form-select text-sm w-full">
        <option value="">{{ $placeholder ?? __('common.all') }}</option>
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" {{ (string) request($name, $value) === (string) $optValue ? 'selected' : '' }}>
                {{ $optLabel }}
            </option>
        @endforeach
    </select>
</div>
