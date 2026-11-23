{{--
    Animated "wave" heading text.

    Latin-only text waves letter by letter. Any text containing a script that needs contextual shaping or RTL
    ordering (Arabic, Hebrew, Indic, ...) waves word by word instead: splitting such text into one element per
    character breaks the joins between letters (تصميم renders as ت ص م ي م) and can scramble their order.

    The animation is toggled by the parent Alpine scope's `play` flag, and is switched off entirely for visitors
    who prefer reduced motion. Screen readers get the phrase once, not one letter at a time.
--}}
@props([
    'text',
    'stagger' => 0.05,
])

@php
    $text = (string) $text;
    // Letters only when every character is Latin or script-neutral (digits, punctuation, spaces, marks).
    $perLetter = ! preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $text);
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $index = 0;
@endphp

@once
    @push('head')
        <style>
            @keyframes wavyText {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-5px); }
            }
            .animate-wavy {
                animation: wavyText 0.6s ease-in-out forwards;
            }
            @media (prefers-reduced-motion: reduce) {
                .animate-wavy { animation: none; }
            }
        </style>
    @endpush
@endonce

<span {{ $attributes->merge(['class' => 'wavy-text']) }} data-wavy-mode="{{ $perLetter ? 'letters' : 'words' }}">
    <span class="sr-only">{{ $text }}</span>
    <span aria-hidden="true">
        @foreach ($words as $word)
            @if ($perLetter)
                <span class="inline-block whitespace-nowrap">@foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $letter)<span data-wavy-segment class="inline-block" :class="play ? 'animate-wavy' : ''" style="animation-delay: {{ round($index++ * $stagger, 3) }}s">{{ $letter }}</span>@endforeach</span>
            @else
                <span data-wavy-segment class="inline-block whitespace-nowrap" :class="play ? 'animate-wavy' : ''" style="animation-delay: {{ round($index++ * $stagger * 3, 3) }}s">{{ $word }}</span>
            @endif
        @endforeach
    </span>
</span>
