@extends('layouts.admin')
@section('title', __('ad_packages.ad_packages'))

@section('content')
@php $editing = $package->exists; @endphp
<div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold mb-4">{{ $editing ? __('ad_packages.edit') : __('ad_packages.new_package') }}</h1>
    @if($errors->any())<div class="p-3 mb-4 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>@endif
    <form method="POST" class="bg-white rounded-xl border p-6 space-y-4"
          action="{{ $editing ? route('admin.marketer-ad-packages.update', $package->id) : route('admin.marketer-ad-packages.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        @foreach([['name_ar','text'],['name_en','text'],['price','number'],['currency','text'],['vat_pct','number'],['duration_days','number'],['sort_order','number']] as [$f,$t])
            <div><label class="block text-sm mb-1">{{ __('ad_packages.f_'.$f) }}</label>
            <input type="{{ $t }}" name="{{ $f }}" value="{{ old($f, $package->$f) }}" class="w-full border rounded-lg px-3 py-2"></div>
        @endforeach
        <div><label class="block text-sm mb-1">{{ __('ad_packages.f_description_ar') }}</label>
            <textarea name="description_ar" rows="3" class="w-full border rounded-lg px-3 py-2">{{ old('description_ar', $package->description_ar) }}</textarea></div>
        <div><label class="block text-sm mb-1">{{ __('ad_packages.target') }}</label>
            <select name="target_type" class="w-full border rounded-lg px-3 py-2">
                @foreach(['all','influencer','affiliate','broker'] as $t)<option value="{{ $t }}" @selected(old('target_type', $package->target_type) === $t)>{{ $t }}</option>@endforeach
            </select></div>
        <div><label class="block text-sm mb-1">{{ __('ad_packages.f_features') }}</label>
            <textarea name="features_text" rows="4" class="w-full border rounded-lg px-3 py-2">{{ old('features_text', implode("\n", $package->features ?? [])) }}</textarea></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> {{ __('ad_packages.active') }}</label>
        <button class="bg-primary-600 text-white rounded-lg px-5 py-2">{{ __('ad_packages.save') }}</button>
    </form>
</div>
@endsection
