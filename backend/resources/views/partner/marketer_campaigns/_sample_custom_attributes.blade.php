@if ($sample->customAttributeValues->isNotEmpty())
    <div class="mt-2 bg-purple-50 rounded-lg p-2 text-xs space-y-1">
        <strong class="text-purple-700">{{ __('partner.marketer_campaigns_my.sample_custom_details') }}:</strong>
        @foreach ($sample->customAttributeValues as $val)
            <div>{{ $val->label }}: <strong>{{ $val->value }}</strong> {{ $val->unit }}</div>
        @endforeach
    </div>
    <button type="button"
            @click="openFor('{{ $sample->id }}', @js($product->customAttributes), @js($sample->customAttributeValues))"
            class="text-xs text-purple-600 underline mt-1">
        {{ __('partner.marketer_campaigns_my.edit_sample_details') }}
    </button>
@else
    <button type="button"
            @click="openFor('{{ $sample->id }}', @js($product->customAttributes), [])"
            class="btn btn-sm btn-outline-purple mt-2">
        {{ __('partner.marketer_campaigns_my.fill_sample_details') }}
    </button>
@endif
