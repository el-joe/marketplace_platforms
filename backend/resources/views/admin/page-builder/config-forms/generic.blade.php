{{-- Generic / fallback config form (no specific partial defined) --}}
<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        {!! str_replace(':label', '<strong>' . e($blockType->label_en) . '</strong>', __('admin.page_builder.config_forms.generic.no_config_ui')) !!}
    </div>

    <x-form.textarea name="__raw_json"
                     label="{{ __('admin.page_builder.config_forms.generic.raw_config_json') }}"
                     rows="10"
                     :value="json_encode($config, JSON_PRETTY_PRINT)" />

    @include('admin.page-builder.config-forms.partials.block-styling', ['config' => $config])
    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
