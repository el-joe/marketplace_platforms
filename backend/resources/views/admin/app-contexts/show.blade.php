@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/app-contexts.js'])
@endpush

@section('title', $context->name_en)

@section('content')
<div class="p-6">

    <div class="mb-6">
        <a href="{{ route('admin.app-contexts.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('admin.app_contexts.back_to_contexts') }}
        </a>
    </div>

    {{-- Header --}}
    <x-card padding="lg" class="mb-6">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center overflow-hidden shrink-0"
                 style="background-color: {{ $context->color_hex ?: '#E5E7EB' }}">
                @if ($context->icon_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($context->icon_path) }}"
                         class="w-10 h-10 object-contain">
                @else
                    <span class="text-white font-bold text-2xl">{{ mb_substr($context->name_en, 0, 1) }}</span>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $context->name_en }}</h1>
                    <span class="badge {{ $context->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $context->is_active ? __('admin.app_contexts.active') : __('admin.app_contexts.inactive') }}
                    </span>
                </div>
                <div class="text-sm text-gray-500 font-arabic mt-0.5" dir="rtl">{{ $context->name_ar }}</div>
                <div class="text-xs text-gray-400 mt-1 font-mono">{{ $context->key }}</div>
            </div>
        </div>
    </x-card>

    <div x-data="{ tab: 'general' }" data-context-key="{{ $context->key }}">

        {{-- Tabs --}}
        <div class="mb-6">
            <nav class="inline-flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                <button type="button" @click="tab = 'general'"
                        :class="tab === 'general' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="whitespace-nowrap py-2 px-4 rounded-lg font-medium text-sm transition-colors">
                    {{ __('admin.app_contexts.general_settings') }}
                </button>
                <button type="button" @click="tab = 'countries'"
                        :class="tab === 'countries' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="whitespace-nowrap py-2 px-4 rounded-lg font-medium text-sm transition-colors">
                    {{ __('admin.app_contexts.country_assignment') }}
                </button>
                <button type="button" @click="tab = 'nav'"
                        :class="tab === 'nav' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="whitespace-nowrap py-2 px-4 rounded-lg font-medium text-sm transition-colors">
                    {{ __('admin.app_contexts.bottom_navigation') }}
                </button>
            </nav>
        </div>

        {{-- TAB 1: General Settings --}}
        <div x-show="tab === 'general'">
            <x-card padding="lg" class="max-w-2xl">
                <form id="general-settings-form" method="POST" action="{{ route('admin.app-contexts.update', $context) }}"
                      enctype="multipart/form-data" class="space-y-5">
                    @csrf @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">{{ __('admin.app_contexts.name_english') }}</label>
                            <input type="text" name="name_en" value="{{ old('name_en', $context->name_en) }}" dir="ltr"
                                   class="form-input w-full" required>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.app_contexts.name_arabic') }}</label>
                            <input type="text" name="name_ar" value="{{ old('name_ar', $context->name_ar) }}" dir="rtl"
                                   class="form-input w-full font-arabic" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.app_contexts.icon') }}</label>
                        @if ($context->icon_path)
                            <div class="mb-2">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($context->icon_path) }}"
                                     class="w-14 h-14 object-contain rounded-lg border border-gray-200 p-1">
                            </div>
                        @endif
                        <input type="file" name="icon" id="context-icon-filepond" accept="image/*">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">{{ __('admin.app_contexts.color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="color_hex" value="{{ old('color_hex', $context->color_hex ?: '#0F172A') }}"
                                       class="h-10 w-14 rounded border border-gray-300">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.app_contexts.sort_order') }}</label>
                            <input type="number" name="sort_order" value="{{ old('sort_order', $context->sort_order) }}"
                                   class="form-input w-full">
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               @checked(old('is_active', $context->is_active)) class="rounded border-gray-300 text-primary-600">
                        <label for="is_active" class="text-sm text-gray-700">{{ __('admin.app_contexts.active') }}</label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary">{{ __('admin.app_contexts.save_changes') }}</button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- TAB 2: Country Assignment & Home Pages --}}
        <div x-show="tab === 'countries'">
            <x-card padding="none" class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>{{ __('admin.app_contexts.country') }}</th>
                            <th>{{ __('admin.app_contexts.active') }}</th>
                            <th>{{ __('admin.app_contexts.home_page') }}</th>
                            <th>{{ __('admin.app_contexts.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($countries as $country)
                            @php $assignment = $assignments->get($country->id) @endphp
                            <tr class="js-country-row" data-country-id="{{ $country->id }}">
                                <td class="font-medium text-gray-800">
                                    {{ $country->flag_emoji ? $country->flag_emoji . ' ' : '' }}{{ $country->name_en }}
                                </td>
                                <td>
                                    <input type="checkbox" class="js-country-active rounded border-gray-300 text-primary-600"
                                           @checked($assignment?->is_active)>
                                </td>
                                <td>
                                    <select class="js-country-home-page form-select" data-select2-init style="min-width: 220px;">
                                        <option value="">{{ __('admin.app_contexts.no_home_page') }}</option>
                                        @foreach ($pages as $page)
                                            <option value="{{ $page->id }}" @selected($assignment?->home_page_id === $page->id)>
                                                {{ $page->name }} ({{ $page->page_type }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary js-save-country-assignment">
                                        {{ __('admin.app_contexts.save') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>

        {{-- TAB 3: Bottom Navigation --}}
        <div x-show="tab === 'nav'" class="space-y-6">

            <x-card padding="lg">
                <div class="flex items-center gap-2 mb-4">
                    <span class="badge bg-primary-100 text-primary-700">
                        {{ __('admin.app_contexts.default_bottom_nav') }}
                    </span>
                </div>
                <div class="flex gap-3 js-nav-slots" data-country-id="">
                    @for ($position = 1; $position <= 5; $position++)
                        @php $item = optional($navItems->get('default'))->firstWhere('position', $position) @endphp
                        @include('admin.app-contexts.partials.nav-slot', ['item' => $item, 'position' => $position])
                    @endfor
                </div>
            </x-card>

            @foreach ($navItems->except('default') as $countryId => $items)
                @php $country = $countries->firstWhere('id', $countryId) @endphp
                <x-card padding="lg">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-1.5">
                        {{ optional($country)->flag_emoji }} {{ optional($country)->name_en }} {{ __('admin.app_contexts.country') }}
                    </h3>
                    <div class="flex gap-3 js-nav-slots" data-country-id="{{ $countryId }}">
                        @for ($position = 1; $position <= 5; $position++)
                            @php $item = $items->firstWhere('position', $position) @endphp
                            @include('admin.app-contexts.partials.nav-slot', ['item' => $item, 'position' => $position])
                        @endfor
                    </div>
                </x-card>
            @endforeach

            <button type="button" class="btn btn-secondary js-add-country-override">
                {{ __('admin.app_contexts.add_country_override') }}
            </button>
        </div>
    </div>

    @include('admin.app-contexts.partials.nav-item-modal')
    @include('admin.app-contexts.partials.country-override-modal')

    <script>
        window.APP_CONTEXT = {
            key: @json($context->key),
            saveCountryUrl: @json(route('admin.app-contexts.countries.save', $context)),
            saveNavUrl: @json(route('admin.app-contexts.nav.save', $context)),
            updateNavUrlBase: @json(route('admin.app-contexts.nav.save', $context)),
            countries: @json($countries->map(fn ($c) => ['id' => $c->id, 'name_en' => $c->name_en])->values()),
        };
    </script>

</div>
@endsection
