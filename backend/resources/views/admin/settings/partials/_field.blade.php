{{--
Universal settings field renderer.

Variables expected:
$setting — App\Models\Setting instance (with 'value' JSON-cast decoded)

Renders the appropriate input type based on the setting's decoded PHP value.
--}}
@php
    $key = $setting->key;
    $inputId = 'setting-' . $key;
    $inputName = 'settings[' . $key . ']';
    $display = $setting->getDisplayValue();
    $label = ucwords(str_replace('_', ' ', $key));

    // Detect type from decoded value
    $raw = $setting->value;
    $isBool = is_bool($raw);
    $isNum = !$isBool && (is_int($raw) || is_float($raw));
    $isArray = is_array($raw);
    $isColor = str_contains($key, 'color') && !$isBool;
    $isEmail = str_contains($key, 'email') && !$isBool;
    $isUrl = (str_contains($key, 'url') || str_contains($key, 'host')) && !$isBool;
    $isCents = (str_contains($key, 'cents') || str_contains($key, 'amount')) && $isNum;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 py-5 first:pt-0 last:pb-0">

    {{-- Label column --}}
    <div class="sm:col-span-1">
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-800">
            {{ $label }}
            @if($setting->is_encrypted)
                <span
                    class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">
                    {{ __('admin.settings_section.encrypted') }}
                </span>
            @endif
            @if(!$setting->is_public)
                <span
                    class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">
                    {{ __('admin.settings_section.private') }}
                </span>
            @endif
        </label>
        @if($setting->description)
            <p class="mt-1 text-xs text-gray-500">{{ $setting->description }}</p>
        @endif
        <p class="mt-1 text-xs font-mono text-gray-400">{{ $key }}</p>
    </div>

    {{-- Input column --}}
    <div class="sm:col-span-2">

        @if($setting->is_encrypted)
            {{-- ── Encrypted password field with show/hide toggle ── --}}
            <div class="relative flex items-center gap-2" x-data="{ show: false }">
                <div class="relative flex-1">
                    <input :type="show ? 'text' : 'password'" id="{{ $inputId }}" name="{{ $inputName }}"
                        value="{{ $display }}" autocomplete="new-password"
                        placeholder="{{ $display === '●●●●●●●●' ? __('admin.settings_section.leave_blank_keep_current') : __('admin.settings_section.enter_value') }}"
                        class="form-input w-full pr-10 text-sm font-mono">
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
                        :title="show ? '{{ __('admin.settings_section.hide_value') }}' : '{{ __('admin.settings_section.show_value') }}'">
                        <x-heroicon name="eye" class="w-4 h-4" />
                    </button>
                </div>
            </div>

        @elseif($isBool)
            {{-- ── Boolean toggle (Alpine) ── --}}
            @php $checked = (bool) $raw; @endphp
            <div x-data="{ enabled: {{ $checked ? 'true' : 'false' }} }" class="flex items-center gap-3">
                <button type="button" dir="ltr" @click="enabled = !enabled" :class="enabled ? 'bg-blue-600' : 'bg-gray-200'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    :aria-checked="enabled.toString()" role="switch">
                    <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform"></span>
                </button>
                <span x-text="enabled ? '{{ __('admin.settings_section.enabled_label') }}' : '{{ __('admin.settings_section.disabled_label') }}'" class="text-sm text-gray-600"></span>
                <input type="hidden" name="{{ $inputName }}" :value="enabled ? '1' : '0'">
            </div>

        @elseif($isColor)
            {{-- ── Color picker + hex text input (bidirectional) ── --}}
            <div x-data="{ color: '{{ $display ?? '#000000' }}' }" class="flex items-center gap-3"
                data-color-key="{{ $key }}">
                <input type="color" x-model="color" class="h-10 w-14 cursor-pointer rounded-md border border-gray-300 p-0.5"
                    aria-label="{{ $label }} color picker">
                <input type="text" id="{{ $inputId }}" name="{{ $inputName }}" x-model="color" maxlength="7"
                    placeholder="#0284c7" class="form-input w-32 font-mono text-sm">
                {{-- Live preview swatch --}}
                <div class="h-8 w-16 rounded-md border border-gray-200 shadow-inner" :style="'background-color: ' + color"
                    data-color-preview="{{ $key }}"></div>
            </div>

        @elseif($isEmail)
            <input type="email" id="{{ $inputId }}" name="{{ $inputName }}" value="{{ $display }}"
                class="form-input w-full sm:max-w-sm text-sm">

        @elseif($isUrl)
            <input type="url" id="{{ $inputId }}" name="{{ $inputName }}" value="{{ $display }}"
                class="form-input w-full sm:max-w-sm text-sm" placeholder="https://">

        @elseif($isNum)
            <div class="flex items-center gap-3">
                <input type="number" id="{{ $inputId }}" name="{{ $inputName }}" value="{{ $display }}"
                    step="{{ is_float($raw) ? '0.01' : '1' }}" class="form-input w-40 text-sm">
                @if($isCents)
                    <span class="text-xs text-gray-500">
                        = <strong class="js-cents-display"
                            data-value="{{ $display }}">{{ number_format(($display ?? 0), 2) }}</strong> ({{ __('admin.settings_section.currency_units_suffix') }})
                    </span>
                @endif
            </div>

        @elseif($isArray)
            <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="4" class="form-input w-full font-mono text-xs"
                placeholder="{{ __('admin.settings_section.json_placeholder') }}">{{ json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>

        @else
            {{-- Default: text input --}}
            <input type="text" id="{{ $inputId }}" name="{{ $inputName }}" value="{{ $display }}"
                class="form-input w-full sm:max-w-sm text-sm">
        @endif

    </div>
</div>