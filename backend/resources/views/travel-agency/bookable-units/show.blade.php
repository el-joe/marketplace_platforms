@extends('layouts.travel-agency')

@section('title', $unit->name)

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-gray-900">{{ $unit->name }}</h1>
                <p class="text-sm text-gray-500">{{ __('travel.bookable_units.types.' . ($unit->type->value ?? $unit->type)) }} · {{ __('travel.bookable_units.capacity') }}: {{ $unit->capacity }}</p>
            </div>
            <a href="{{ route('travel-agency.bookable-units.edit', $unit) }}" class="text-sm font-medium text-blue-600">{{ __('common.edit') }}</a>
        </div>

        @if (session('success'))
            <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-2">{{ session('success') }}</div>
        @endif

        {{-- ── Calendar month nav ─────────────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('travel-agency.bookable-units.show', ['bookableUnit' => $unit, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}"
                class="text-sm text-gray-500">&larr; {{ __('common.previous') }}</a>
            <h2 class="font-bold text-gray-900">{{ $month->translatedFormat('F Y') }}</h2>
            <a href="{{ route('travel-agency.bookable-units.show', ['bookableUnit' => $unit, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}"
                class="text-sm text-gray-500">{{ __('common.next') }} &rarr;</a>
        </div>

        {{-- ── Per-day availability/price grid ────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ __('travel.bookable_units.date') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('travel.bookable_units.available') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('travel.bookable_units.capacity_override') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('travel.bookable_units.price_day_only') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('travel.bookable_units.price_with_overnight') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @for ($day = $month->copy(); $day->month === $month->month; $day->addDay())
                        @php $row = $availability->get($day->toDateString()); @endphp
                        <tr class="border-t border-gray-100">
                            <form method="POST" action="{{ route('travel-agency.bookable-units.availability.upsert', $unit) }}" class="contents">
                                @csrf
                                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                                <td class="px-3 py-2 font-medium">{{ $day->format('D, d M') }}</td>
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="is_available" value="1" {{ ($row->is_available ?? true) ? 'checked' : '' }}>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" name="capacity_override" min="1" value="{{ $row->capacity_override ?? '' }}" class="w-20 rounded border-gray-300 text-xs">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" name="price_day_only" min="0" value="{{ $row->price_day_only ?? '' }}" class="w-24 rounded border-gray-300 text-xs">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" name="price_with_overnight" min="0" value="{{ $row->price_with_overnight ?? '' }}" class="w-24 rounded border-gray-300 text-xs">
                                </td>
                                <td class="px-3 py-2">
                                    <button type="submit" class="text-xs font-medium text-blue-600">{{ __('common.save') }}</button>
                                </td>
                            </form>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        {{-- ── Bulk range set (nice-to-have) ──────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <h3 class="font-bold text-gray-900 mb-3">{{ __('travel.bookable_units.bulk_set') }}</h3>
            <form method="POST" action="{{ route('travel-agency.bookable-units.availability.bulk-upsert', $unit) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.date_from') }}</label>
                    <input type="date" name="date_from" required class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.date_to') }}</label>
                    <input type="date" name="date_to" required class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.price_day_only') }}</label>
                    <input type="number" name="price_day_only" min="0" class="w-28 rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.price_with_overnight') }}</label>
                    <input type="number" name="price_with_overnight" min="0" class="w-28 rounded-lg border-gray-300 text-sm">
                </div>
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="is_available" value="1" checked> {{ __('travel.bookable_units.available') }}
                </label>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                    {{ __('common.save') }}
                </button>
            </form>
        </div>

        {{-- ── Time slots ──────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <h3 class="font-bold text-gray-900 mb-3">{{ __('travel.bookable_units.time_slots') }}</h3>

            <table class="w-full text-sm mb-4">
                <thead class="text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-2 py-1 text-start">{{ __('travel.bookable_units.slot_type') }}</th>
                        <th class="px-2 py-1 text-start">{{ __('travel.bookable_units.starts_at') }}</th>
                        <th class="px-2 py-1 text-start">{{ __('travel.bookable_units.ends_at') }}</th>
                        <th class="px-2 py-1 text-start">{{ __('travel.bookable_units.price') }}</th>
                        <th class="px-2 py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timeSlots as $slot)
                        <tr class="border-t border-gray-100">
                            <td class="px-2 py-1">{{ $slot->slot_type->value ?? $slot->slot_type }}</td>
                            <td class="px-2 py-1">{{ $slot->starts_at }}</td>
                            <td class="px-2 py-1">{{ $slot->ends_at }}</td>
                            <td class="px-2 py-1">{{ $slot->price }}</td>
                            <td class="px-2 py-1">
                                <form method="POST" action="{{ route('travel-agency.bookable-units.time-slots.destroy', [$unit, $slot]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500">{{ __('common.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('travel-agency.bookable-units.time-slots.store', $unit) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.slot_type') }}</label>
                    <select name="slot_type" class="rounded-lg border-gray-300 text-sm">
                        <option value="morning">{{ __('travel.bookable_units.slot_types.morning') }}</option>
                        <option value="evening">{{ __('travel.bookable_units.slot_types.evening') }}</option>
                        <option value="custom">{{ __('travel.bookable_units.slot_types.custom') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.starts_at') }}</label>
                    <input type="time" name="starts_at" required class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.ends_at') }}</label>
                    <input type="time" name="ends_at" required class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('travel.bookable_units.price') }}</label>
                    <input type="number" name="price" min="0" required class="w-28 rounded-lg border-gray-300 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                    {{ __('common.save') }}
                </button>
            </form>
        </div>
    </div>
@endsection
