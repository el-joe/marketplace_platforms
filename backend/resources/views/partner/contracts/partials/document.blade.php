{{-- Shared contract viewer. Needs $doc (CategoryContractService::present) and an Alpine `lang` ('en'|'ar') in the parent scope. --}}
@php($height = $height ?? 'h-96')
@if ($doc['content_mode'] === 'file')
    @foreach (['en', 'ar'] as $language)
        @php($file = $doc['files'][$language] ?? ($doc['files']['ar'] ?? $doc['files']['en'] ?? null))
        <div x-show="lang === '{{ $language }}'" @if ($language !== 'en') x-cloak @endif class="space-y-2">
            @if ($file)
                @if ($file['inline'])
                    <iframe src="{{ $file['url'] }}" title="{{ $file['name'] }}" class="w-full {{ $height }} rounded-lg border"></iframe>
                @else
                    <div class="rounded-lg border bg-gray-50 p-4 text-sm text-gray-600">{{ __('partner.legal.no_inline_preview') }}</div>
                @endif
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                    <span>{{ $file['name'] }} · {{ number_format($file['size'] / 1024, 0) }} KB</span>
                    <a href="{{ $file['url'] }}?download=1" class="font-medium text-blue-600 hover:underline">{{ __('partner.legal.download_document') }}</a>
                </div>
            @endif
        </div>
    @endforeach
@else
    <div x-show="lang === 'en'" dir="ltr" class="max-h-96 overflow-y-auto whitespace-pre-line rounded-lg border p-4 text-sm leading-relaxed text-gray-700">{!! $doc['content_en'] !!}</div>
    <div x-show="lang === 'ar'" x-cloak dir="rtl" class="max-h-96 overflow-y-auto whitespace-pre-line rounded-lg border p-4 text-sm leading-relaxed text-gray-700">{!! $doc['content_ar'] !!}</div>
@endif
