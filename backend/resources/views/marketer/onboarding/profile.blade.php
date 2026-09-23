@extends('layouts.marketer')

@section('content')
<div class="max-w-2xl mx-auto py-8">
    <h1 class="text-xl font-bold mb-4">{{ __('marketer.complete_profile') }}</h1>
    @error('onboarding') <p class="text-red-600 text-sm mb-3">{{ $message }}</p> @enderror
    <form method="POST" action="{{ route('marketer.onboarding.profile.save') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @php $inp = 'w-full border border-gray-300 rounded-lg px-4 py-2 text-sm'; @endphp

        <div class="grid grid-cols-2 gap-3">
            <input class="{{ $inp }}" name="specialty_ar" placeholder="التخصص" value="{{ old('specialty_ar', $profile?->specialty_ar) }}">
            <input class="{{ $inp }}" name="specialty_en" placeholder="Specialty" value="{{ old('specialty_en', $profile?->specialty_en) }}">
            <textarea class="{{ $inp }}" name="bio_ar" rows="3" placeholder="نبذة">{{ old('bio_ar', $profile?->bio_ar) }}</textarea>
            <textarea class="{{ $inp }}" name="bio_en" rows="3" placeholder="Bio">{{ old('bio_en', $profile?->bio_en) }}</textarea>
        </div>
        @error('bio_ar') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

        @if ($isInfluencer)
            <div class="space-y-2">
                @foreach ($platforms as $p)
                    <input class="{{ $inp }}" type="url" name="social_links[{{ $p }}]" placeholder="{{ __('marketer.social_'.$p) }}"
                           value="{{ old('social_links.'.$p, $profile?->social_links[$p] ?? '') }}">
                @endforeach
                @error('social_links') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                @error('social_links.*') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($isBroker)
            <div class="space-y-2">
                <label class="text-sm font-semibold">{{ __('marketer.specialty_areas') }}</label>
                <select name="broker_category_id" class="{{ $inp }}" required>
                    <option value="">--</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('broker_category_id', $profile?->broker_category_id) == $c->id)>{{ $c->name_ar }}</option>
                    @endforeach
                </select>
                @error('broker_category_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <label class="flex gap-2 text-sm"><input type="checkbox" name="broker_serves_all_cities" value="1" @checked(old('broker_serves_all_cities', $profile?->broker_serves_all_cities))> {{ __('marketer.serves_all_cities') }}</label>
                <select name="broker_city_id" class="{{ $inp }}">
                    <option value="">--</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c->id }}" @selected(old('broker_city_id', $profile?->broker_city_id) == $c->id)>{{ $c->name_ar }}</option>
                    @endforeach
                </select>
                @error('broker_city_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <label class="text-sm font-semibold">{{ __('marketer.cv_upload') }}</label>
                <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="{{ $inp }}">
                @error('cv') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <label class="text-sm font-semibold">{{ __('marketer.certifications_upload') }}</label>
                <input type="file" name="certifications[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="{{ $inp }}">
                @error('certifications') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                @error('certifications.*') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <input type="file" name="avatar" accept="image/*" class="{{ $inp }}">
            <input type="file" name="banner" accept="image/*" class="{{ $inp }}">
        </div>
        @error('avatar') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
        @error('banner') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

        <button class="bg-yellow-400 text-gray-900 font-bold rounded-lg px-6 py-2">{{ __('marketer.complete_profile') }}</button>
    </form>
</div>
@endsection
