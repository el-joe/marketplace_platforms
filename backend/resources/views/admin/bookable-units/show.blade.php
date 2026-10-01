@extends('layouts.admin')

@section('title', $unit->name)

@section('content')
<div class="p-6 space-y-6 max-w-5xl">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $unit->name }}</h1>
            <p class="text-sm text-gray-500">
                {{ $unit->agency?->name }}
                · {{ __('admin.bookable_units_section.type_'.($unit->type->value ?? $unit->type)) }}
            </p>
        </div>

        {{-- Status changer (always visible) --}}
        <form method="POST" action="{{ route('admin.travel.bookable-units.status', $unit) }}" class="flex items-center gap-2">
            @csrf
            @php
                $statusColors = [
                    'draft'    => 'gray',
                    'active'   => 'emerald',
                    'paused'   => 'yellow',
                    'archived' => 'gray',
                    'rejected' => 'red',
                ];
                $currentColor = $statusColors[$unit->status] ?? 'gray';
            @endphp
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $currentColor }}-100 text-{{ $currentColor }}-700">
                {{ __('admin.bookable_units_section.status_'.$unit->status) }}
            </span>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
                @foreach(['draft','active','paused','archived'] as $s)
                    <option value="{{ $s }}" @selected($unit->status === $s)>
                        {{ __('admin.bookable_units_section.status_'.$s) }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                {{ __('admin.bookable_units_section.save_status') }}
            </button>
        </form>
    </div>

    {{-- Rejection form (only shown when unit is not already rejected) --}}
    @if($unit->status !== 'rejected')
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <p class="text-xs font-medium text-gray-600 mb-2">{{ __('admin.bookable_units_section.reject_with_reason') }}</p>
        <form method="POST" action="{{ route('admin.travel.bookable-units.reject', $unit) }}" class="flex items-start gap-2">
            @csrf
            <textarea name="rejection_reason" required placeholder="{{ __('admin.bookable_units_section.rejection_reason') }}" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" rows="2"></textarea>
            <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                {{ __('admin.bookable_units_section.reject') }}
            </button>
        </form>
    </div>
    @endif

    @if($unit->status === 'rejected' && $unit->rejection_reason)
    <div class="bg-red-50 text-red-700 text-sm rounded-lg px-4 py-3">
        <strong>{{ __('admin.bookable_units_section.rejection_reason') }}:</strong> {{ $unit->rejection_reason }}
    </div>
    @endif

    @if(session('success'))
    <div class="bg-emerald-50 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
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
            <thead>
                <tr class="text-xs text-gray-500">
                    <th class="py-2 text-left font-medium">{{ __('admin.bookable_units_section.col_date') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_booking_number') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_customer') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_overnight') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_time_slot') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_price') }}</th>
                    <th class="text-left font-medium">{{ __('admin.bookable_units_section.col_status') }}</th>
                </tr>
            </thead>
            @forelse($recentBookingDays as $day)
            <tr>
                <td class="py-2">{{ $day->date }}</td>
                <td>{{ $day->travelBooking?->booking_number ?? '—' }}</td>
                <td>{{ $day->travelBooking?->customer?->name ?? '—' }}</td>
                <td>{{ $day->includes_overnight ? __('admin.bookable_units_section.yes') : __('admin.bookable_units_section.no') }}</td>
                <td>{{ $day->timeSlot?->slot_type ?? '—' }}</td>
                <td>{{ number_format($day->price / 100, 2) }}</td>
                <td>{{ $day->travelBooking?->status->value ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="py-4 text-gray-400">{{ __('admin.bookable_units_section.none') }}</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection
