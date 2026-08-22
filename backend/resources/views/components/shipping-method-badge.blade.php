{{--
    Renders a shipping method as a colored badge pill.
    Props:
      method       — ShippingMethod|null
      isDefault    — bool, show a "default" indicator dot (optional)
      fallbackText — text shown when method is null (optional)
--}}
@props(['method' => null, 'isDefault' => false, 'fallbackText' => '—'])

@if($method)
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
          style="background-color: {{ $method->badge_color_hex ?? '#e5e7eb' }}; color: {{ $method->badge_text_color_hex ?? '#374151' }};">
        {{ $method->badge_label_en ?: $method->name }}
    </span>
    @if($isDefault)
        <span class="ml-1 text-[10px] uppercase tracking-wide text-gray-400">{{ __('common.default') }}</span>
    @endif
@else
    <span class="text-xs text-gray-400">{{ $fallbackText }}</span>
@endif
