{{--
    Admin media field (client item #27) — drag & drop / click upload, instant preview,
    replace / remove, progress, client-side type+size checks, "recent uploads" library,
    optional URL paste, and grouped Desktop/Mobile × EN/AR slots with "use the same image for all".
    Behaviour: resources/js/components/media-field.js (delegated, works in AJAX-injected HTML).

    Single slot:
        <x-form.media-field context="promo-tiles" name="image_url" :value="$url" />
    Grid (slot key = "{device}_{locale}", or just "{locale}" / "{device}"):
        <x-form.media-field context="slides" store="id"
            :devices="['desktop', 'mobile']" :locales="['en', 'ar']"
            name-pattern="{device}_file_id_{locale}" id-pattern="slide-{device}-file-id-{locale}" />

    store="url" → the hidden input holds the public image URL (promo tiles, section backgrounds, posters).
    store="id"  → the hidden input holds the `files.id` (slides, ad images, custom page image).
    Inputs other than the hidden value inputs have no `name`, so serialisers (page builder autosave) ignore them.
--}}
@props([
    'context',
    'label' => null,
    'hint' => null,
    'store' => 'url',
    'name' => null,
    'value' => null,
    'url' => null,
    'inputId' => null,
    'devices' => [],
    'locales' => [],
    'namePattern' => null,
    'idPattern' => null,
    'values' => [],
    'urls' => [],
    'sizeHints' => [],
    'sameToggle' => true,
    'library' => true,
    'allowUrl' => null,
    'aspect' => 'aspect-[16/7]',
    'columns' => null,
])

@php
    $mediaContext = \App\Enums\MediaContext::from($context);
    $maxKb = $mediaContext->maxKilobytes();
    $allowUrl = $allowUrl ?? $store === 'url';

    // Normalise to a list of slots.
    $slots = [];
    if ($namePattern) {
        $deviceList = $devices ?: [null];
        $localeList = $locales ?: [null];
        foreach ($deviceList as $device) {
            foreach ($localeList as $locale) {
                $key = implode('_', array_filter([$device, $locale]));
                $replace = ['{device}' => (string) $device, '{locale}' => (string) $locale];
                $slotValue = $values[$key] ?? null;
                $slots[] = [
                    'key' => $key,
                    'device' => $device,
                    'locale' => $locale,
                    'name' => strtr($namePattern, $replace),
                    'id' => $idPattern ? strtr($idPattern, $replace) : null,
                    'value' => $slotValue,
                    'url' => $urls[$key] ?? ($store === 'url' ? $slotValue : null),
                ];
            }
        }
    } else {
        $slots[] = [
            'key' => 'main', 'device' => null, 'locale' => null, 'name' => $name, 'id' => $inputId,
            'value' => $value, 'url' => $url ?? ($store === 'url' ? $value : null),
        ];
    }

    $multi = count($slots) > 1;
    $filled = array_values(array_filter(array_map(fn ($s) => (string) $s['value'], $slots), fn ($v) => $v !== ''));
    // On when every slot is empty (new item: one upload fills all) or every slot holds the same image.
    $sameOn = $multi && $sameToggle && ($filled === [] || (count($filled) === count($slots) && count(array_unique($filled)) === 1));
    $gridCols = $columns ?? (count($slots) >= 4 ? 'grid-cols-2' : (count($slots) === 1 ? 'grid-cols-1' : 'grid-cols-2'));

    $i18n = [
        'dropOrClick' => __('media_library.drop_or_click'),
        'uploading' => __('media_library.uploading'),
        'uploaded' => __('media_library.uploaded'),
        'errorType' => __('media_library.error_type', ['types' => 'JPG, PNG, WEBP, GIF, AVIF']),
        'errorSize' => __('media_library.error_size', ['max' => ($maxKb / 1024).' MB']),
        'errorUpload' => __('media_library.error_upload'),
        'errorForbidden' => __('media_library.error_forbidden'),
        'urlInvalid' => __('media_library.url_invalid'),
        'libraryTitle' => __('media_library.library_title'),
        'librarySearch' => __('media_library.library_search'),
        'libraryEmpty' => __('media_library.library_empty'),
        'libraryLoadMore' => __('media_library.library_load_more'),
        'libraryLoading' => __('media_library.library_loading'),
        'libraryFailed' => __('media_library.library_failed'),
        'close' => __('media_library.close'),
    ];
@endphp

<div data-media-field
     data-context="{{ $mediaContext->value }}"
     data-store="{{ $store }}"
     data-upload-url="{{ route('admin.media.upload') }}"
     data-library-url="{{ $library ? route('admin.media.library') : '' }}"
     data-max-kb="{{ $maxKb }}"
     data-i18n='@json($i18n)'
     {{ $attributes->class(['space-y-2']) }}>

    @if($label || $multi)
        <div class="flex flex-wrap items-center justify-between gap-2">
            @if($label)
                <span class="block text-sm font-medium text-gray-700">{{ $label }}</span>
            @endif
            @if($multi && $sameToggle)
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer select-none" title="{{ __('media_library.same_for_all_hint') }}">
                    <input type="checkbox" data-media-same class="rounded border-gray-300 text-primary-600" @checked($sameOn)>
                    {{ __('media_library.same_for_all') }}
                </label>
            @endif
        </div>
    @endif

    <div class="grid {{ $gridCols }} gap-3">
        @foreach($slots as $index => $slot)
            @php
                $slotLabel = implode(' · ', array_filter([
                    $slot['device'] ? __('media_library.'.$slot['device']) : null,
                    $slot['locale'] ? __('media_library.'.$slot['locale']) : null,
                ]));
                $hasImage = filled($slot['url']);
                $isSecondary = $index > 0;
            @endphp
            <div data-media-slot="{{ $slot['key'] }}" data-device="{{ $slot['device'] }}" data-locale="{{ $slot['locale'] }}"
                 @class(['min-w-0 space-y-1.5', 'hidden' => $sameOn && $isSecondary, 'col-span-full' => $sameOn && ! $isSecondary])>
                @if($slotLabel !== '')
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span @class(['inline-flex items-center gap-1 font-medium text-gray-600', 'invisible' => $sameOn && ! $isSecondary]) data-media-slot-label>
                            @if($slot['device'] === 'mobile')
                                <x-heroicon name="device-phone-mobile" class="w-3.5 h-3.5 text-gray-400" />
                            @elseif($slot['device'] === 'desktop')
                                <x-heroicon name="computer-desktop" class="w-3.5 h-3.5 text-gray-400" />
                            @endif
                            <span data-media-slot-text data-single="{{ $slotLabel }}">{{ $slotLabel }}</span>
                        </span>
                        @if(! empty($sizeHints[$slot['device'] ?? 'main']))
                            <span class="text-[11px] text-gray-400" dir="ltr">{{ $sizeHints[$slot['device'] ?? 'main'] }}</span>
                        @endif
                    </div>
                @endif

                <div data-media-drop role="button" tabindex="0" aria-label="{{ __('media_library.drop_or_click') }}"
                     class="group relative {{ $aspect }} w-full overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 cursor-pointer transition hover:border-primary-400 hover:bg-primary-50/40 focus:outline-none focus:ring-2 focus:ring-primary-300">
                    <img data-media-preview src="{{ $hasImage ? $slot['url'] : '' }}" alt=""
                         @class(['absolute inset-0 h-full w-full object-contain bg-white', 'hidden' => ! $hasImage])>
                    <div data-media-empty @class(['absolute inset-0 flex flex-col items-center justify-center gap-1 px-3 text-center text-xs text-gray-500', 'hidden' => $hasImage])>
                        <x-heroicon name="arrow-up-tray" class="w-5 h-5 text-gray-400 group-hover:text-primary-500" />
                        <span data-media-empty-text>{{ __('media_library.drop_or_click') }}</span>
                        <span class="text-[10px] text-gray-400">{{ __('media_library.allowed', ['types' => 'JPG · PNG · WEBP · GIF · AVIF', 'max' => ($maxKb / 1024).' MB']) }}</span>
                    </div>
                    <div data-media-progress class="hidden absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/80 px-4">
                        <span data-media-progress-text class="text-xs font-medium text-gray-700"></span>
                        <div class="h-1.5 w-full max-w-[12rem] overflow-hidden rounded-full bg-gray-200">
                            <div data-media-progress-bar class="h-full w-0 bg-primary-600 transition-all"></div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    <button type="button" data-media-action="pick" class="font-medium text-primary-600 hover:text-primary-700">
                        <span data-media-has-image @class(['hidden' => ! $hasImage])>{{ __('media_library.replace') }}</span>
                        <span data-media-no-image @class(['hidden' => $hasImage])>{{ __('media_library.upload') }}</span>
                    </button>
                    @if($library)
                        <button type="button" data-media-action="library" class="text-gray-600 hover:text-gray-900">{{ __('media_library.library') }}</button>
                    @endif
                    @if($allowUrl)
                        <button type="button" data-media-action="url" class="text-gray-600 hover:text-gray-900">{{ __('media_library.use_url') }}</button>
                    @endif
                    <button type="button" data-media-action="remove" @class(['ms-auto text-danger-600 hover:text-danger-700', 'hidden' => ! $hasImage])>{{ __('media_library.remove') }}</button>
                </div>

                @if($allowUrl)
                    <div data-media-url-row class="hidden flex items-center gap-2">
                        <input type="text" data-media-url-input dir="ltr" placeholder="{{ __('media_library.url_placeholder') }}"
                               value="{{ $store === 'url' ? $slot['value'] : '' }}"
                               class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-xs font-mono">
                        <button type="button" data-media-action="apply-url" class="rounded bg-gray-900 px-2 py-1 text-xs text-white">{{ __('media_library.apply') }}</button>
                    </div>
                @endif

                <p data-media-meta class="hidden truncate text-[11px] text-gray-400" dir="ltr"></p>
                <p data-media-error role="alert" class="hidden text-xs text-danger-600"></p>

                <input type="hidden" data-media-value name="{{ $slot['name'] }}" value="{{ $slot['value'] }}"@if($slot['id']) id="{{ $slot['id'] }}"@endif>
                <input type="file" data-media-file accept="image/jpeg,image/png,image/webp,image/gif,image/avif" class="sr-only" tabindex="-1" aria-hidden="true">
            </div>
        @endforeach
    </div>

    @if($hint)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
