@extends('layouts.admin')
@push('scripts')
    @vite(['resources/js/components/rich-editor.js'])
@endpush

@section('title', __('admin.contracts.template_form_title'))

@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-6">
    <div>
        <a href="{{ route('admin.contracts.templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $template ? __('admin.contracts.edit_template') : __('admin.contracts.new_template') }}</h1>
        @if ($template && $template->is_published)
            <p class="mt-1 text-sm text-amber-700">{{ __('admin.contracts.published_edit_note', ['version' => $template->version + 1]) }}</p>
        @endif
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $template ? route('admin.contracts.templates.update', $template) : route('admin.contracts.templates.store') }}"
          x-data="{
              mode: @js(old('content_mode', $template?->content_mode ?? 'editor')),
              scope: @js(old('category_scope', $template?->category_scope ?? 'classified')),
              acceptance: @js(old('acceptance_mode', $template?->acceptance_mode ?? 'click_wrap')),
              country: @js((string) old('country_id', $template?->country_id ?? '')),
              picked: @js(array_values(old('classified_category_ids', $selectedSections))),
              takenAll: { classified: @js($takenSections), customer: @js($takenCustomerSections) },
              pickedMarketers: @js($selectedMarketers),
              marketerQuery: '',
              marketerResults: [],
              searchMarketers() {
                  if (this.marketerQuery.length < 2) { this.marketerResults = []; return; }
                  fetch(@js(route('admin.contracts.templates.marketer-search')) + '?q=' + encodeURIComponent(this.marketerQuery), { headers: { 'Accept': 'application/json' } })
                      .then(r => r.json()).then(rows => { this.marketerResults = rows.filter(r => !this.pickedMarketers.some(p => p.id === r.id)); });
              },
              addMarketer(m) { this.pickedMarketers.push(m); this.marketerResults = []; this.marketerQuery = ''; },
              removeMarketer(id) { this.pickedMarketers = this.pickedMarketers.filter(m => m.id !== id); },
              takenBy(id) { const taken = this.takenAll[this.scope === 'customer' ? 'customer' : 'classified']; return (taken[this.country || 'legacy'] || {})[id]?.name || null; },
              effective() { return this.picked.filter(id => !this.takenBy(id)); },
          }"
          class="space-y-5 rounded-xl border bg-white p-6">
        @csrf
        @if ($template) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.col_name') }}</label>
                <input type="text" name="name" value="{{ old('name', $template?->name) }}" required maxlength="200"
                       class="form-input w-full text-sm" {{ $template && $template->is_published ? 'readonly' : '' }}>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.col_scope') }}</label>
                @if ($template)
                    <input type="text" value="{{ __('admin.contracts.scope_' . $template->category_scope) }}" readonly class="form-input w-full bg-gray-50 text-sm">
                @else
                    <select name="category_scope" x-model="scope" required class="form-input w-full text-sm">
                        <option value="classified">{{ __('admin.contracts.scope_classified') }}</option>
                        <option value="product">{{ __('admin.contracts.scope_product') }}</option>
                        <option value="platform">{{ __('admin.contracts.scope_platform') }}</option>
                        <option value="marketer">{{ __('admin.contracts.scope_marketer') }}</option>
                        <option value="customer">{{ __('admin.contracts.scope_customer') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope === 'platform'">{{ __('admin.contracts.scope_platform_help') }}</p>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope === 'marketer'" x-cloak>{{ __('contracts.marketer.scope_help') }}</p>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope === 'customer'" x-cloak>{{ __('contracts.customer.scope_help') }}</p>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope !== 'platform' && scope !== 'marketer' && scope !== 'customer'" x-cloak>{{ __('admin.contracts.scope_category_help') }}</p>
                @endif
            </div>
        </div>

        {{-- Country: which sellers this contract is for. Empty = legacy / all countries without a contract of their own. --}}
        @php($lockedCountry = $template && $template->is_published)
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('contracts.admin.col_country') }}</label>
                <select name="country_id" x-model="country" class="form-input w-full text-sm" @disabled($lockedCountry)>
                    <option value="">{{ __('contracts.admin.all_countries_option') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected(old('country_id', $template?->country_id) === $country->id)>{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ $lockedCountry ? __('contracts.admin.country_locked_help') : __('contracts.admin.country_help') }}</p>
                @error('country_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="scope !== 'platform' && scope !== 'marketer' && scope !== 'customer'" x-cloak>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('contracts.admin.default_label') }}</label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_country_default" value="0">
                    <input type="checkbox" name="is_country_default" value="1" @checked(old('is_country_default', $template?->is_country_default)) @disabled($lockedCountry)>
                    {{ __('contracts.admin.default_flag') }}
                </label>
                <p class="mt-1 text-xs text-gray-500">{{ __('contracts.admin.default_help') }}</p>
                @error('is_country_default')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>


        {{-- Classified sections this contract applies to (classified scope only). Saved as links the resolver reads. --}}
        <div x-show="scope === 'classified' || scope === 'customer'" x-cloak class="space-y-3 rounded-lg border border-indigo-100 bg-indigo-50/40 p-4">
            <input type="hidden" name="sections_submitted" value="1">
            <template x-for="id in effective()" :key="id"><input type="hidden" name="classified_category_ids[]" :value="id"></template>
            <div>
                <p class="text-sm font-semibold text-gray-800">{{ __('contracts.sections.title') }}</p>
                <p class="mt-0.5 text-xs text-gray-600" x-show="scope !== 'customer'">{{ __('contracts.sections.help') }}</p>
                <p class="mt-0.5 text-xs text-gray-600" x-show="scope === 'customer'" x-cloak>{{ __('contracts.customer.sections_help') }}</p>
                <p class="mt-0.5 text-xs text-gray-500" x-show="country === ''">{{ __('contracts.sections.legacy_note') }}</p>
            </div>
            @error('classified_category_ids')<div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</div>@enderror
            @error('classified_category_ids.*')<div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</div>@enderror
            @if ($sectionTree->isEmpty())
                <p class="text-xs text-gray-500">{{ __('contracts.sections.no_sections') }}</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($sectionTree as $root)
                        <div class="rounded-lg border bg-white p-3">
                            @include('admin.contracts.partials.section-checkbox', ['category' => $root, 'child' => false])
                            @foreach ($root->children as $child)
                                @include('admin.contracts.partials.section-checkbox', ['category' => $child, 'child' => true])
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="text-xs text-gray-500" x-show="scope !== 'customer'">{{ __('contracts.sections.draft_note') }}</p>
            <p class="text-xs text-gray-500" x-show="scope === 'customer'" x-cloak>{{ __('contracts.customer.sections_draft_note') }}</p>
        </div>

        {{-- Seller-marketer collaboration contracts: where they are shown and whom they cover --}}
        <div x-show="scope === 'marketer'" x-cloak class="space-y-4 rounded-lg border border-purple-100 bg-purple-50/40 p-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">{{ __('contracts.marketer.display_on') }}</p>
                <p class="mt-0.5 text-xs text-gray-600">{{ __('contracts.marketer.display_on_help') }}</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($destinations as $destination)
                    <label class="flex items-start gap-2 rounded border bg-white px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox" name="destination_ids[]" value="{{ $destination->id }}" class="mt-0.5 rounded border-gray-300"
                               @checked(in_array($destination->id, (array) old('destination_ids', $selectedDestinationIds), true))>
                        <span>{{ $destination->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('destination_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('destination_ids.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('contracts.marketer.category') }}</label>
                    <select name="classified_category_id" class="form-input w-full text-sm">
                        <option value="">{{ __('contracts.marketer.category_any') }}</option>
                        @foreach ($sectionTree as $root)
                            <option value="{{ $root->id }}" @selected(old('classified_category_id', $template?->classified_category_id) === $root->id)>{{ app()->getLocale() === 'ar' ? $root->name_ar : $root->name_en }}</option>
                            @foreach ($root->children as $child)
                                <option value="{{ $child->id }}" @selected(old('classified_category_id', $template?->classified_category_id) === $child->id)>— {{ app()->getLocale() === 'ar' ? $child->name_ar : $child->name_en }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('contracts.marketer.category_help') }}</p>
                    @error('classified_category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('contracts.marketer.public_label') }}</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="show_on_public_profile" value="0">
                        <input type="checkbox" name="show_on_public_profile" value="1" @checked(old('show_on_public_profile', $template?->show_on_public_profile))>
                        {{ __('contracts.marketer.public_flag') }}
                    </label>
                    <p class="mt-1 text-xs text-gray-500">{{ __('contracts.marketer.public_help') }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('contracts.marketer.specific_marketers') }}</label>
                <p class="mb-2 text-xs text-gray-500">{{ __('contracts.marketer.specific_marketers_help') }}</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="m in pickedMarketers" :key="m.id">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs text-gray-800 ring-1 ring-purple-200">
                            <input type="hidden" name="marketer_ids[]" :value="m.id">
                            <span x-text="m.text"></span>
                            <button type="button" class="text-red-600" @click="removeMarketer(m.id)" aria-label="{{ __('common.delete') }}">&times;</button>
                        </span>
                    </template>
                </div>
                <div class="relative mt-2 sm:w-80">
                    <input type="text" x-model="marketerQuery" @input.debounce.300ms="searchMarketers()" placeholder="{{ __('contracts.marketer.search_marketers') }}" class="form-input w-full text-sm">
                    <ul x-show="marketerResults.length > 0" x-cloak class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border bg-white text-sm shadow">
                        <template x-for="m in marketerResults" :key="m.id">
                            <li><button type="button" class="block w-full px-3 py-2 text-start hover:bg-gray-50" @click="addMarketer(m)" x-text="m.text"></button></li>
                        </template>
                    </ul>
                </div>
                @error('marketer_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('marketer_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.content_mode') }}</label>
            <input type="hidden" name="content_mode" :value="mode">
            <div class="inline-flex rounded-lg border bg-gray-50 p-1 text-sm">
                <button type="button" @click="mode = 'editor'" :class="mode === 'editor' ? 'bg-white shadow text-primary-700' : 'text-gray-600'" class="rounded-md px-4 py-1.5 font-medium">{{ __('admin.contracts.mode_editor') }}</button>
                <button type="button" @click="mode = 'file'" :class="mode === 'file' ? 'bg-white shadow text-primary-700' : 'text-gray-600'" class="rounded-md px-4 py-1.5 font-medium">{{ __('admin.contracts.mode_file') }}</button>
            </div>
            <p class="mt-1 text-xs text-gray-500" x-show="mode === 'file'" x-cloak>{{ __('admin.contracts.mode_file_help', ['mb' => $maxMb]) }}</p>
        </div>

        <div x-show="scope === 'platform'" x-cloak class="space-y-3 rounded-lg border border-blue-100 bg-blue-50/50 p-4">
            <p class="text-sm font-semibold text-gray-800">{{ __('admin.contracts.amendment_title') }}</p>
            <p class="text-xs text-gray-600">{{ __('admin.contracts.amendment_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.acceptance_mode') }}</label>
                    <select name="acceptance_mode" x-model="acceptance" class="form-input w-full text-sm">
                        <option value="click_wrap">{{ __('admin.contracts.acceptance_click_wrap') }}</option>
                        <option value="notice">{{ __('admin.contracts.acceptance_notice') }}</option>
                    </select>
                </div>
                <div x-show="acceptance === 'notice'" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.notice_days') }}</label>
                    <input type="number" name="notice_days" min="1" max="90" value="{{ old('notice_days', $template?->notice_days ?? 14) }}" class="form-input w-full text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.change_summary') }}</label>
                <textarea name="change_summary" rows="3" maxlength="2000" class="form-input w-full text-sm">{{ old('change_summary', $template?->change_summary) }}</textarea>
            </div>
        </div>

        <div x-data="{ open: false }" x-show="mode === 'editor'">
            <button type="button" @click="open = !open" class="text-xs text-primary-600 underline">{{ __('admin.contracts.variables_helper') }} ▾</button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-1 gap-2 rounded-lg border bg-gray-50 p-3 sm:grid-cols-2">
                @foreach ($variables as $variable => $description)
                    <div class="flex items-center justify-between rounded border bg-white px-2 py-1 text-xs">
                        <code class="select-all text-primary-700">{{ $variable }}</code>
                        <span class="ms-2 text-gray-500">{{ $description }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div x-show="mode === 'editor'" class="space-y-5">
        <x-form.rich-editor
            name="content_en"
            label="{{ __('admin.contracts.body_en') }}"
            :required="true"
            profile="default"
            :value="$template?->editorHtml('content_en') ?? ''"
            helpText="{{ __('admin.contracts.editor_help') }}"
        />
        <x-form.rich-editor
            name="content_ar"
            label="{{ __('admin.contracts.body_ar') }}"
            :required="true"
            profile="default"
            :value="$template?->editorHtml('content_ar') ?? ''"
        />
        </div>

        <div x-show="mode === 'file'" x-cloak class="space-y-4">
            @foreach (['ar' => __('admin.contracts.file_ar'), 'en' => __('admin.contracts.file_en')] as $language => $label)
                @php($current = $template?->files[$language] ?? null)
                <div class="rounded-lg border p-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ $label }}</label>
                    @if ($current)
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                            <span><strong>{{ $current['name'] }}</strong> · {{ number_format($current['size'] / 1024, 0) }} KB · <code>{{ substr($current['sha256'], 0, 12) }}</code></span>
                            <span class="space-x-3">
                                <a href="{{ route('admin.contracts.templates.file', [$template, $language]) }}" target="_blank" class="font-medium text-primary-600 hover:underline">{{ __('admin.contracts.view_file') }}</a>
                                <a href="{{ route('admin.contracts.templates.file', [$template, $language, 'download' => 1]) }}" class="font-medium text-primary-600 hover:underline">{{ __('admin.contracts.download_file') }}</a>
                                <label class="inline-flex items-center gap-1 text-red-600"><input type="checkbox" name="remove_file[]" value="{{ $language }}"> {{ __('admin.contracts.remove_file') }}</label>
                            </span>
                        </div>
                    @endif
                    <input type="file" name="file_{{ $language }}" accept=".pdf,.doc,.docx" class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700">
                    <p class="mt-1 text-xs text-gray-500">{{ $current ? __('admin.contracts.replace_file_help') : __('admin.contracts.upload_file_help') }}</p>
                </div>
            @endforeach
            <p class="text-xs text-gray-500">{{ __('admin.contracts.file_fallback_help') }}</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.contracts.templates.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
@endsection
