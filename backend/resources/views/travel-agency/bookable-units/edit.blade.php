@extends('layouts.travel-agency')

@section('title', __('travel.bookable_units.edit_unit'))

@section('content')
    <div class="max-w-xl space-y-5">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.bookable_units.edit_unit') }}</h1>

        <form method="POST" action="{{ route('travel-agency.bookable-units.update', $unit) }}" class="space-y-4 bg-white p-6 rounded-xl border border-gray-100">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $unit->name) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.type') }}</label>
                <select name="type" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach(['chalet', 'hotel_room', 'other'] as $type)
                        <option value="{{ $type }}" {{ old('type', $unit->type->value ?? $unit->type) === $type ? 'selected' : '' }}>
                            {{ __('travel.bookable_units.types.' . $type) }}
                        </option>
                    @endforeach
                </select>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.capacity') }}</label>
                <input type="number" name="capacity" min="1" value="{{ old('capacity', $unit->capacity) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                @error('capacity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookable_units.description') }}</label>
                <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $unit->description) }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-5 py-2.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-400 transition-colors">
                {{ __('common.save') }}
            </button>
        </form>
    </div>
@endsection
