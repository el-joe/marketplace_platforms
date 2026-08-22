@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <x-form.select name="style" label="{{ __('admin.page_builder.config_forms.divider.style') }}" :value="$config['style'] ?? 'solid'">
            <option value="solid">{{ __('admin.page_builder.config_forms.divider.style_solid') }}</option>
            <option value="dashed">{{ __('admin.page_builder.config_forms.divider.style_dashed') }}</option>
            <option value="dotted">{{ __('admin.page_builder.config_forms.divider.style_dotted') }}</option>
            <option value="none">{{ __('admin.page_builder.config_forms.divider.style_spacer') }}</option>
        </x-form.select>
        <x-form.input name="color" type="color" label="{{ __('admin.page_builder.config_forms.divider.color') }}" :value="$config['color'] ?? '#e5e7eb'" />
    </div>

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.input name="margin_top"    type="number" label="{{ __('admin.page_builder.config_forms.divider.margin_top') }}"    :value="$config['margin_top']    ?? 16" min="0" />
        <x-form.input name="margin_bottom" type="number" label="{{ __('admin.page_builder.config_forms.divider.margin_bottom') }}" :value="$config['margin_bottom'] ?? 16" min="0" />
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
