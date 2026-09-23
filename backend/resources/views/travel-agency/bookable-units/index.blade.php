@extends('layouts.travel-agency')

@section('title', __('travel.bookable_units.title'))

@section('content')
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-gray-900">{{ __('travel.bookable_units.title') }}</h1>
            <a href="{{ route('travel-agency.bookable-units.create') }}"
                class="px-5 py-2.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-400 transition-colors">
                + {{ __('travel.bookable_units.create_unit') }}
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('travel.bookable_units.name') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('travel.bookable_units.type') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('travel.bookable_units.capacity') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('travel.bookable_units.reservations') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $unit)
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-3 font-medium">{{ $unit->name }}</td>
                            <td class="px-4 py-3">{{ $unit->type->value ?? $unit->type }}</td>
                            <td class="px-4 py-3">{{ $unit->capacity }}</td>
                            <td class="px-4 py-3">{{ $unit->reservations_count }}</td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('travel-agency.bookable-units.show', $unit) }}" class="text-blue-600 font-medium">{{ __('travel.bookable_units.manage_calendar') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('travel.bookable_units.none') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $units->links() }}
    </div>
@endsection
