@php /** @var \App\Models\PageBlock|null $block */ @endphp

<form data-config-form data-block-id="{{ $block?->id }}">
    @csrf
    <x-form.textarea name="content_html_en" label="{{ __('admin.page_builder.config_forms.text_block.content_en') }}"  rows="6" :value="$config['content_html_en'] ?? ''" dir="ltr" />
    <x-form.textarea name="content_html_ar" label="{{ __('admin.page_builder.config_forms.text_block.content_ar') }}"  rows="6" :value="$config['content_html_ar'] ?? ''" dir="rtl" />

    <div class="grid grid-cols-2 gap-3 mt-3">
        <x-form.select name="text_align" label="{{ __('admin.page_builder.config_forms.text_block.text_align') }}" :value="$config['text_align'] ?? 'left'">
            <option value="left">{{ __('admin.page_builder.config_forms.text_block.align_left') }}</option>
            <option value="center">{{ __('admin.page_builder.config_forms.text_block.align_center') }}</option>
            <option value="right">{{ __('admin.page_builder.config_forms.text_block.align_right') }}</option>
            <option value="justify">{{ __('admin.page_builder.config_forms.text_block.align_justify') }}</option>
        </x-form.select>
        <x-form.input name="max_width" label="{{ __('admin.page_builder.config_forms.text_block.max_width') }}" :value="$config['max_width'] ?? '1200px'" />
    </div>

    @include('admin.page-builder.config-forms.partials.visibility', ['block' => $block])
</form>
