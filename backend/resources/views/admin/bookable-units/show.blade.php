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
        <div class="flex items-start gap-3">
            <form method="POST" action="{{ route('admin.travel.bookable-units.approve', $unit) }}">
                @csrf
                <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">{{ __('admin.bookable_units_section.approve') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.travel.bookable-units.reject', $unit) }}" class="flex items-start gap-2">
                @csrf
                <textarea name="rejection_reason" required placeholder="{{ __('admin.bookable_units_section.rejection_reason') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64" rows="1"></textarea>
                <button class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">{{ __('admin.bookable_units_section.reject') }}</button>
            </form>
        </div>
        @endif
    </div>

    @if($unit->status === 'rejected' && $unit->rejection_reason)
    <div class="bg-red-50 text-red-700 text-sm rounded-lg px-4 py-3">
        <strong>{{ __('admin.bookable_units_section.rejection_reason') }}:</strong> {{ $unit->rejection_reason }}
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.bookable_units_section.calendar_overview') }}</h3>
            <span class="text-xs text-gray-500">{{ $calendarMonth }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1 text-xs text-center text-gray-400 mb-1">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd)
                <div>{{ $wd }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7 gap-1 text-xs">
            @for($i = 0; $i < $firstWeekday; $i++)
                <div></div>
            @endfor
            @foreach($calendarDays as $day)
            <div class="rounded p-1.5 border text-center
                {{ $day->is_available
                    ? 'border-emerald-200 bg-emerald-50'
                    : ($day->has_row ? 'border-red-200 bg-red-50 text-red-400' : 'border-gray-100 bg-gray-50 text-gray-300') }}">
                <div class="font-medium">{{ $day->date->format('j') }}</div>
                @if($day->price_day_only !== null)
                    <div class="text-[10px] opacity-70">{{ number_format($day->price_day_only) }}</div>
                @endif
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
