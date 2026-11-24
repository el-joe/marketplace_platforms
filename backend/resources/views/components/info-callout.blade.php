{{--
    Inline "where to find it" pointer for admin pages.
    <x-info-callout :title="..." :href="route(...)" :link-label="...">Body text</x-info-callout>
    tone: info (blue, default) | warning (amber, for "this has no visible effect yet").
--}}
@props(['title' => null, 'href' => null, 'linkLabel' => null, 'tone' => 'info'])

@php
    $palette = $tone === 'warning'
        ? ['box' => 'border-amber-200 bg-amber-50', 'icon' => 'text-amber-500', 'title' => 'text-amber-900', 'body' => 'text-amber-800', 'link' => 'text-amber-900 hover:text-amber-700']
        : ['box' => 'border-sky-200 bg-sky-50', 'icon' => 'text-sky-500', 'title' => 'text-sky-900', 'body' => 'text-sky-800', 'link' => 'text-sky-900 hover:text-sky-700'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 '.$palette['box']]) }} role="note">
    <x-heroicon :name="$tone === 'warning' ? 'exclamation-triangle' : 'information-circle'" class="mt-0.5 h-5 w-5 shrink-0 {{ $palette['icon'] }}" />
    <div class="min-w-0 flex-1 text-sm">
        @if($title)
            <p class="font-semibold {{ $palette['title'] }}">{{ $title }}</p>
        @endif
        <div class="mt-0.5 leading-relaxed {{ $palette['body'] }}">{{ $slot }}</div>
        @if($href && $linkLabel)
            <a href="{{ $href }}" class="mt-1.5 inline-flex items-center gap-1 font-semibold underline underline-offset-2 {{ $palette['link'] }}">
                {{ $linkLabel }}
                <x-heroicon name="chevron-right" class="h-4 w-4 rtl:rotate-180" />
            </a>
        @endif
    </div>
</div>
