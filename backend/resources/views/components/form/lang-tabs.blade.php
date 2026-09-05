@props(['id' => null])

@php
    $tabsId = $id ?? 'lt-' . \Illuminate\Support\Str::random(8);
@endphp

<div class="lang-tabs" data-lang-tabs data-tabs-id="{{ $tabsId }}">
    <div class="flex gap-2 mb-3 border-b border-gray-200" role="tablist">
        <button type="button" data-lang-tab="en" data-tabs-id="{{ $tabsId }}"
            class="lang-tab-btn active px-3 py-1.5 text-sm font-medium border-b-2 border-primary-500 text-primary-600">
            English
        </button>
        <button type="button" data-lang-tab="ar" data-tabs-id="{{ $tabsId }}"
            class="lang-tab-btn px-3 py-1.5 text-sm font-medium border-b-2 border-transparent text-gray-500">
            العربية
        </button>
    </div>

    <div data-lang-panel="en" data-tabs-id="{{ $tabsId }}" class="lang-panel space-y-3" dir="ltr">
        {{ $en }}
    </div>
    <div data-lang-panel="ar" data-tabs-id="{{ $tabsId }}" class="lang-panel space-y-3 hidden" dir="rtl">
        {{ $ar }}
    </div>
</div>
