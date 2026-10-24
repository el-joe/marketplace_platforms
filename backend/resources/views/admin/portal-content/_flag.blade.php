@php($iso = strtolower((string) $country->iso_code_2))
<span class="inline-flex items-center justify-center w-5 h-4 shrink-0 overflow-hidden rounded-sm bg-gray-100 text-[11px] leading-none">
    @if($iso !== '')
        <img src="https://f.nooncdn.com/s/app/com/common/images/flags/{{ $iso }}.svg" alt="" loading="lazy"
             class="w-5 h-4 object-cover"
             onerror="this.replaceWith(document.createTextNode(this.dataset.emoji))" data-emoji="{{ $country->flag_emoji ?: strtoupper($iso) }}">
    @else
        {{ $country->flag_emoji ?: '🏳' }}
    @endif
</span>
