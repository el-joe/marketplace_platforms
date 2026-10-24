@extends('layouts.admin')

@section('title', __('admin.portal_content.title_with_page', ['page' => $pageLabel]))

@section('content')
@php($locale = app()->getLocale() === 'ar' ? 'ar' : 'en')
@php($dir = $locale === 'ar' ? 'rtl' : 'ltr')
@php($countryName = fn ($c) => $locale === 'ar' ? ($c->name_ar ?: $c->name_en) : $c->name_en)
<div class="p-6 space-y-5" dir="{{ $dir }}"
     x-data="{ dirty: false, lightbox: null, lightboxLabel: '' }"
     x-init="window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } })"
     @keydown.escape.window="lightbox = null">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $pageLabel }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.portal_content.edit_subtitle_country', ['country' => $countryName($country)]) }}</p>
        </div>
        <a href="{{ route('admin.portal-content.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <x-heroicon name="arrow-uturn-left" class="w-4 h-4" />
            {{ __('admin.portal_content.back_to_pages') }}
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Country switcher --}}
    <div class="flex flex-wrap items-center gap-1.5" data-country-switcher>
        <span class="text-xs font-semibold text-gray-500 me-1">{{ __('admin.portal_content.country') }}</span>
        @foreach($countries as $c)
            <a href="{{ route('admin.portal-content.page', [$c->site_code, $pageKey]) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium',
                   'border-blue-600 bg-blue-50 text-blue-700' => $c->id === $country->id,
                   'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $c->id !== $country->id,
               ])>
                @include('admin.portal-content._flag', ['country' => $c])
                {{ $countryName($c) }}
            </a>
        @endforeach
    </div>

    {{-- Page switcher --}}
    <div class="flex items-center gap-2">
        <label for="pc-page-switch" class="text-xs font-semibold text-gray-500">{{ __('admin.portal_content.page') }}</label>
        <select id="pc-page-switch" class="rounded-lg border-gray-300 text-sm py-1"
                onchange="if (this.value) window.location = this.value">
            @foreach($pages as $key => $label)
                <option value="{{ route('admin.portal-content.page', [$country->site_code, $key]) }}" @selected($key === $pageKey)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Section anchor nav --}}
    @if(count($sections))
        <nav class="sticky top-0 z-20 -mx-6 px-6 py-2 bg-gray-50/95 backdrop-blur border-b border-gray-200 flex gap-1.5 overflow-x-auto" aria-label="{{ __('admin.portal_content.sections') }}">
            @foreach($sections as $i => $section)
                <a href="#section-{{ $section['block'] }}"
                   class="shrink-0 rounded-full border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">{{ $i + 1 }}. {{ $section['label'] }}</a>
            @endforeach
        </nav>
    @endif

    <form method="POST" action="{{ route('admin.portal-content.save', [$country->site_code, $pageKey]) }}" class="space-y-6" enctype="multipart/form-data"
          @input="dirty = true" @change="dirty = true" @submit="dirty = false">
        @csrf

        @php($otherShown = false)
        @forelse($sections as $section)
            @if($section['other'] && ! $otherShown)
                @php($otherShown = true)
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider pt-2">{{ __('admin.portal_content.other_sections') }}</h2>
            @endif

            <section id="section-{{ $section['block'] }}" data-section="{{ $section['block'] }}"
                     class="scroll-mt-16 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">{{ $section['label'] }}</h2>
                    <span class="text-xs font-mono text-gray-400" dir="ltr">{{ $section['block'] }}</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:divide-x lg:divide-gray-100 rtl:lg:divide-x-reverse">
                    {{-- Screenshot column --}}
                    <div class="p-4 bg-gray-50/50">
                        <button type="button" class="block w-full cursor-zoom-in rounded-lg border border-gray-200 bg-white overflow-hidden"
                                @click="lightbox = @js($section['image']); lightboxLabel = @js($section['label'])">
                            <img src="{{ $section['image'] }}" alt="{{ $section['label'] }}" loading="lazy" class="w-full h-auto max-h-96 object-contain object-top">
                        </button>
                        <p class="mt-1.5 text-[11px] text-gray-400 text-center">{{ __('admin.portal_content.click_to_enlarge') }}</p>
                    </div>

                    {{-- Fields column --}}
                    <div class="divide-y divide-gray-100">
                        @foreach($section['rows'] as $row)
                            <div class="px-4 py-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-mono text-gray-400" dir="ltr">{{ $row->field_key }} <span class="ms-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">{{ $row->type->value }}</span></div>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                        <input type="checkbox" name="fields[{{ $row->id }}][is_active]" value="1" {{ $row->is_active ? 'checked' : '' }} class="rounded border-gray-300">
                                        {{ __('common.active') }}
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.english') }}</label>
                                        @if($row->type === \App\Enums\PortalContentType::RichText)
                                            <textarea name="fields[{{ $row->id }}][value_en]" rows="4" class="w-full rounded-lg border-gray-300 text-sm" dir="ltr">{{ old("fields.{$row->id}.value_en", $row->value_en) }}</textarea>
                                        @else
                                            <input type="text" name="fields[{{ $row->id }}][value_en]" value="{{ old("fields.{$row->id}.value_en", $row->value_en) }}" class="w-full rounded-lg border-gray-300 text-sm" dir="ltr">
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.arabic') }}</label>
                                        @if($row->type === \App\Enums\PortalContentType::RichText)
                                            <textarea name="fields[{{ $row->id }}][value_ar]" rows="4" class="w-full rounded-lg border-gray-300 text-sm" dir="rtl">{{ old("fields.{$row->id}.value_ar", $row->value_ar) }}</textarea>
                                        @else
                                            <input type="text" name="fields[{{ $row->id }}][value_ar]" value="{{ old("fields.{$row->id}.value_ar", $row->value_ar) }}" class="w-full rounded-lg border-gray-300 text-sm" dir="rtl">
                                        @endif
                                    </div>
                                </div>

                                @if($row->type === \App\Enums\PortalContentType::Link)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.url') }}</label>
                                        <input type="text" name="fields[{{ $row->id }}][value_url]" value="{{ old("fields.{$row->id}.value_url", $row->value_url) }}" placeholder="{{ __('admin.portal_content.url_hint') }}" class="w-full rounded-lg border-gray-300 text-sm font-mono" dir="ltr">
                                    </div>
                                @endif

                                @if($row->type === \App\Enums\PortalContentType::Image)
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.image_url') }}</label>
                                            <input type="text" name="fields[{{ $row->id }}][value_url]" value="{{ old("fields.{$row->id}.value_url", $row->value_url) }}" placeholder="{{ __('admin.portal_content.image_url_hint') }}" class="w-full rounded-lg border-gray-300 text-sm font-mono" dir="ltr">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.image_file') }}</label>
                                            <input type="file" name="fields[{{ $row->id }}][value_file]" accept="image/*" class="w-full rounded-lg border-gray-300 text-sm">
                                            @error("fields.{$row->id}.value_file")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @if($row->value_url)
                                            <div class="flex items-center gap-3">
                                                <img src="{{ \App\Models\PortalContent::resolveUrl($row->value_url) }}" alt="" class="h-16 rounded border border-gray-200 object-cover">
                                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                                    <input type="checkbox" name="fields[{{ $row->id }}][reset_image]" value="1" class="rounded border-gray-300">
                                                    {{ __('admin.portal_content.reset_image') }}
                                                </label>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center text-sm text-gray-400">
                {{ __('admin.portal_content.no_fields') }}
            </div>
        @endforelse

        <div class="sticky bottom-0 z-10 -mx-6 border-t border-gray-200 bg-white px-6 py-4 shadow-md flex items-center justify-between gap-3">
            <span x-show="dirty" x-cloak class="text-xs font-medium text-amber-600">{{ __('admin.portal_content.unsaved_changes') }}</span>
            <span x-show="!dirty" class="text-xs text-gray-400">{{ __('admin.portal_content.saving_for', ['country' => $countryName($country)]) }}</span>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700">
                <x-heroicon name="check-circle" class="w-4 h-4" />
                {{ __('admin.portal_content.save_page') }}
            </button>
        </div>
    </form>

    {{-- Screenshot lightbox --}}
    <div x-show="lightbox" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         @click="lightbox = null" role="dialog" aria-modal="true">
        <figure class="max-w-5xl max-h-full overflow-auto rounded-lg bg-white p-2" @click.stop>
            <img :src="lightbox" :alt="lightboxLabel" class="w-full h-auto">
            <figcaption class="p-2 text-center text-sm text-gray-600" x-text="lightboxLabel"></figcaption>
        </figure>
        <button type="button" class="absolute top-4 end-4 text-white text-3xl leading-none" @click="lightbox = null" aria-label="{{ __('common.close') }}">&times;</button>
    </div>

</div>
@endsection
