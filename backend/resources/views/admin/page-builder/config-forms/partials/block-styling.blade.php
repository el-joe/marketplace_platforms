{{--
    Shared block styling partial — included in every block config form.
    Renders before the visibility partial.
    Saves into config['background_color'] / config['padding_top'] / config['padding_bottom'].
--}}
@php
    /** @var array $config */
    $bg = $config['background_color'] ?? '';
@endphp

<section class="pt-4 mt-4 border-t border-gray-200 space-y-3">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.static_text.admin_page_builder_config_forms_partials_block_styling.block_styling') }}</h4>

    {{-- Background color --}}
    <div class="grid grid-cols-2 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.static_text.admin_page_builder_config_forms_partials_block_styling.background_color') }}</label>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    id="block-bg-color-picker"
                    class="w-9 h-9 rounded border border-gray-300 cursor-pointer p-0.5 flex-shrink-0"
                    value="{{ $bg ?: '#ffffff' }}"
                    oninput="document.getElementById('block-bg-color-text').value = this.value;
                             document.getElementById('block-bg-color-hidden').value = this.value;"
                />
                <input
                    type="text"
                    id="block-bg-color-text"
                    class="input w-full font-mono text-sm"
                    placeholder="#ffffff or transparent"
                    value="{{ $bg }}"
                    maxlength="30"
                    oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){
                        document.getElementById('block-bg-color-picker').value = this.value;
                    }
                    document.getElementById('block-bg-color-hidden').value = this.value;"
                />
                <input type="hidden" name="background_color" id="block-bg-color-hidden" value="{{ $bg }}" />
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('admin.static_text.admin_page_builder_config_forms_partials_block_styling.use_a_hex_color') }} <code>transparent</code>{{ __('admin.static_text.admin_page_builder_config_forms_partials_block_styling.or_leave_blank_to_inherit_the') }}</p>
        </div>
    </div>
</section>
