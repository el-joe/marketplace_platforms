@extends('layouts.admin')

@section('title', __('admin.bookable_units_section.bookable_units'))

@section('content')
<div class="p-6 space-y-6">
    <h1 class="text-xl font-bold text-gray-900">{{ __('admin.bookable_units_section.bookable_units') }}</h1>

    <form method="GET" class="flex gap-3">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">{{ __('admin.bookable_units_section.all_statuses') }}</option>
            @foreach(['draft','active','paused','archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('admin.bookable_units_section.status_'.$s) }}</option>
            @endforeach
        </select>
        <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">{{ __('admin.bookable_units_section.all_types') }}</option>
            @foreach(['chalet','hotel_room','apartment','other'] as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ __('admin.bookable_units_section.type_'.$t) }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">{{ __('admin.bookable_units_section.filter') }}</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.bookable_units_section.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.bookable_units_section.agency') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.bookable_units_section.type') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.bookable_units_section.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.bookable_units_section.reservations') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($units as $unit)
                <tr>
                    <td class="px-4 py-3">{{ $unit->name_ar && app()->getLocale() === 'ar' ? $unit->name_ar : $unit->name }}</td>
                    <td class="px-4 py-3">{{ $unit->agency?->name }}</td>
                    <td class="px-4 py-3">{{ __('admin.bookable_units_section.type_'.($unit->type->value ?? $unit->type)) }}</td>
                    <td class="px-4 py-3">{{ __('admin.bookable_units_section.status_'.$unit->status) }}</td>
                    <td class="px-4 py-3">{{ $unit->reservations_count }}</td>
                    <td class="px-4 py-3 text-end"><a class="text-blue-600" href="{{ route('admin.travel.bookable-units.show', $unit) }}">{{ __('admin.bookable_units_section.view') }}</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('admin.bookable_units_section.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $units->links() }}
</div>
@endsection
