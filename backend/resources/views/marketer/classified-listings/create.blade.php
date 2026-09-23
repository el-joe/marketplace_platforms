@extends('layouts.marketer')
@php $editing = isset($listing); @endphp
@section('title', $editing ? __('marketer.edit') : __('marketer.add_classified_listing'))
@section('page-title', $editing ? __('marketer.edit') : __('marketer.add_classified_listing'))

@section('content')
@php
    $locale = app()->getLocale();
    $catData = $categories->flatMap(fn ($c) => collect([$c])->concat($c->children))->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'icon' => $c->icon,
        'schema' => $c->attribute_schema ?? [],
    ])->values();
    $rules = $openMarketRules->map(fn ($r) => [
        'base' => $r->base_price, 'override' => (bool) $r->allow_marketer_override, 'min' => $r->min_price, 'max' => $r->max_price,
    ]);
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('marketer.classified-listings.update', $listing) : route('marketer.classified-listings.store') }}"
      x-data="{
          cats: @js($catData), rules: @js($rules), locale: @js($locale),
          cat: @js(old('classified_category_id', $editing ? $listing->classified_category_id : '')),
          attrs: @js(old('attributes', $editing ? ($listing->attributes ?? []) : [])),
          get current() { return this.cats.find(c => c.id === this.cat) },
          get rule() { return this.rules[this.cat] || null },
          get fixed() { return this.rule && !this.rule.override },
      }"
      class="space-y-6 max-w-3xl">
    @csrf
    @if($editing) @method('PUT') @endif
    @if($errors->any())<div class="p-3 bg-red-50 text-red-700 rounded-lg text-sm"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-bold mb-3">{{ __('marketer.choose_category') }}</h3>
        <input type="hidden" name="classified_category_id" :value="cat">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <template x-for="c in cats" :key="c.id">
                <button type="button" @click="cat = c.id; attrs = {}"
                        :class="cat === c.id ? 'border-yellow-500 bg-yellow-50' : 'border-gray-200'"
                        class="border rounded-lg p-3 text-sm font-semibold text-start" x-text="c.name"></button>
            </template>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 p-5 space-y-3" x-show="current && current.schema.length">
        <h3 class="font-bold">{{ __('marketer.fill_attributes') }}</h3>
        <template x-for="a in (current ? current.schema : [])" :key="a.key">
            <label class="block text-sm">
                <span x-text="locale === 'ar' ? (a.label_ar || a.key) : (a.label_en || a.key)"></span>
                <input type="text" :name="'attributes[' + a.key + ']'" x-model="attrs[a.key]" class="mt-1 w-full border-gray-300 rounded-lg">
            </label>
        </template>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <h3 class="font-bold">{{ __('marketer.price_and_purpose') }}</h3>
        <label class="block text-sm">{{ __('marketer.title_ar') }}
            <input name="title_ar" value="{{ old('title_ar', $listing->title_ar ?? '') }}" required class="mt-1 w-full border-gray-300 rounded-lg"></label>
        <label class="block text-sm">{{ __('marketer.title_en') }}
            <input name="title_en" value="{{ old('title_en', $listing->title_en ?? '') }}" class="mt-1 w-full border-gray-300 rounded-lg"></label>
        <label class="block text-sm">{{ __('marketer.description') }} (AR)
            <textarea name="description_ar" rows="3" class="mt-1 w-full border-gray-300 rounded-lg">{{ old('description_ar', $listing->description_ar ?? '') }}</textarea></label>
        <label class="block text-sm">{{ __('marketer.description') }} (EN)
            <textarea name="description_en" rows="3" class="mt-1 w-full border-gray-300 rounded-lg">{{ old('description_en', $listing->description_en ?? '') }}</textarea></label>
        <label class="block text-sm">{{ __('marketer.country') }}
            <select name="country_id" required class="mt-1 w-full border-gray-300 rounded-lg">
                @foreach($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id', $listing->country_id ?? '') == $c->id)>{{ $c->name_ar ?? $c->name_en ?? $c->id }}</option>@endforeach
            </select></label>
        <div class="text-sm">{{ __('marketer.purpose') }}:
            <label class="mx-2"><input type="radio" name="listing_purpose" value="sale" @checked(old('listing_purpose', $listing->listing_purpose ?? 'sale') === 'sale')> {{ __('marketer.listing_purpose_sale') }}</label>
            <label><input type="radio" name="listing_purpose" value="rent" @checked(old('listing_purpose', $listing->listing_purpose ?? '') === 'rent')> {{ __('marketer.listing_purpose_rent') }}</label>
        </div>
        <div class="text-sm">
            <template x-if="fixed">
                <p class="text-gray-700"><span x-text="Number(rule.base).toLocaleString()"></span> — {{ __('marketer.price_override_not_allowed') }}</p>
            </template>
            <template x-if="!fixed">
                <label class="block">{{ __('marketer.price') }}
                    <span class="text-xs text-gray-500" x-show="rule && (rule.min !== null || rule.max !== null)">
                        ({{ __('marketer.price_range_hint') }}: <span x-text="(rule.min ?? 0) + ' - ' + (rule.max ?? '∞')"></span>)</span>
                    <input type="number" name="price" min="0" step="1" value="{{ old('price', $listing->price ?? '') }}" class="mt-1 w-full border-gray-300 rounded-lg">
                </label>
            </template>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="price_negotiable" value="1" @checked(old('price_negotiable', $listing->price_negotiable ?? false))> {{ __('marketer.price_negotiable') }}</label>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-bold mb-3">{{ __('marketer.upload_images') }}</h3>
        <input type="file" name="images[]" multiple accept="image/*" class="text-sm">
    </section>

    <div class="flex gap-3">
        <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">{{ __('marketer.save') }}</button>
        <a href="{{ route('marketer.classified-listings.index') }}" class="px-5 py-2 border rounded-lg text-sm">{{ __('marketer.cancel') }}</a>
    </div>
</form>
@endsection
