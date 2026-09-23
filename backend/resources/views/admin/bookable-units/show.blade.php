@extends('layouts.admin')

@section('title', $unit->name)

@section('content')
<div class="p-6 space-y-6 max-w-5xl">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $unit->name }}</h1>
            <p class="text-sm text-gray-500">{{ $unit->agency?->name }} · {{ __('admin.bookable_units_section.type_'.($unit->type->value ?? $unit->type)) }} · {{ __('admin.bookable_units_section.status_'.$unit->status) }}</p>
        </div>
        @if($unit->status !== 'active')
        <form method="POST" action="{{ route('admin.travel.bookable-units.approve', $unit) }}">
            @csrf
            <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">{{ __('admin.bookable_units_section.approve') }}</button>
        </form>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.bookable_units_section.calendar_overview') }}</h3>
        <div class="grid grid-cols-7 gap-1 text-xs">
            @foreach($availability as $day)
            <div class="rounded p-2 border {{ $day->is_available ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-100 text-gray-400' }}">
                <div>{{ $day->date->format('m-d') }}</div>
                <div>{{ $day->price_day_only ?? '' }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.bookable_units_section.reservations') }}</h3>
        <table class="min-w-full text-sm divide-y divide-gray-100">
            @forelse($reservations as $r)
            <tr>
                <td class="py-2">{{ $r->reservation_number }}</td>
                <td>{{ $r->date_from->toDateString() }} → {{ $r->date_to->toDateString() }}</td>
                <td>{{ $r->total_price }} {{ $r->currency }}</td>
                <td>{{ $r->status->value ?? $r->status }}</td>
            </tr>
            @empty
            <tr><td class="py-4 text-gray-400">{{ __('admin.bookable_units_section.none') }}</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection
