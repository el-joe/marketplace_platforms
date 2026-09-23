@extends('layouts.marketer')
@section('title', __('marketer.add_wanted_listing'))
@section('page-title', __('marketer.add_wanted_listing'))

@section('content')
<form method="POST" action="{{ route('marketer.wanted-listings.store') }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 max-w-2xl text-sm">
    @csrf
    @if($errors->any())<div class="p-3 bg-red-50 text-red-700 rounded-lg"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <label class="block">{{ __('marketer.category') }}
        <select name="classified_category_id" required class="mt-1 w-full border-gray-300 rounded-lg">
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('classified_category_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select></label>
    <label class="block">{{ __('marketer.country') }}
        <select name="country_id" required class="mt-1 w-full border-gray-300 rounded-lg">
            @foreach($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id') == $c->id)>{{ $c->name_ar ?? $c->name_en ?? $c->id }}</option>@endforeach
        </select></label>
    <label class="block">{{ __('marketer.title_ar') }}<input name="title_ar" value="{{ old('title_ar') }}" required class="mt-1 w-full border-gray-300 rounded-lg"></label>
    <label class="block">{{ __('marketer.title_en') }}<input name="title_en" value="{{ old('title_en') }}" class="mt-1 w-full border-gray-300 rounded-lg"></label>
    <label class="block">{{ __('marketer.description') }}<textarea name="description_ar" rows="3" class="mt-1 w-full border-gray-300 rounded-lg">{{ old('description_ar') }}</textarea></label>
    <div class="grid grid-cols-2 gap-3">
        <label class="block">{{ __('marketer.budget_min') }}<input type="number" min="0" step="1" name="budget_min" value="{{ old('budget_min') }}" class="mt-1 w-full border-gray-300 rounded-lg"></label>
        <label class="block">{{ __('marketer.budget_max') }}<input type="number" min="0" step="1" name="budget_max" value="{{ old('budget_max') }}" class="mt-1 w-full border-gray-300 rounded-lg"></label>
    </div>
    <label class="block">{{ __('marketer.expires_at') }}<input type="date" name="expires_at" value="{{ old('expires_at') }}" class="mt-1 w-full border-gray-300 rounded-lg"></label>
    <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg">{{ __('marketer.save') }}</button>
</form>
@endsection
