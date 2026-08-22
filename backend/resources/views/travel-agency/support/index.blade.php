@extends('layouts.travel-agency')

@section('title', __('travel.support.title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.support.title') }}</h1>
        <a href="{{ route('travel-agency.support.create') }}"
           class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-colors">
            {{ __('travel.support.new_ticket') }}
        </a>
    </div>

    @php
    $statuses = [
        ''             => __('travel.support.all_statuses'),
        'open'         => __('travel.support.status_open'),
        'in_progress'  => __('travel.support.status_in_progress'),
        'resolved'     => __('travel.support.status_resolved'),
        'closed'       => __('travel.support.status_closed'),
    ];
    $statusColors = [
        'open'             => 'bg-blue-50 text-blue-700',
        'in_progress'      => 'bg-amber-50 text-amber-700',
        'waiting_customer' => 'bg-purple-50 text-purple-700',
        'resolved'         => 'bg-emerald-50 text-emerald-700',
        'closed'           => 'bg-gray-100 text-gray-500',
    ];
    $currentStatus = request('status', '');
    @endphp

    <div class="flex flex-wrap gap-3 items-center">
        <div class="flex gap-2 flex-wrap">
            @foreach($statuses as $val => $label)
            <a href="{{ route('travel-agency.support.index', array_filter(['status' => $val])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors
                      {{ $currentStatus === $val ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('travel-agency.support.index') }}" class="flex gap-2 items-center ms-auto">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-300 outline-none">
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-300 outline-none">
            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                {{ __('travel.support.filter') }}
            </button>
        </form>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm text-emerald-700">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.support.ticket_number') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.support.subject') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.support.status') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.support.priority') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.support.created_at') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tickets as $ticket)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $ticket->ticket_number }}</td>
                    <td class="px-4 py-3 text-gray-900 font-medium">{{ $ticket->subject }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ __('travel.support.status_' . $ticket->status->value) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ ucfirst($ticket->priority) }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $ticket->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-end">
                        <a href="{{ route('travel-agency.support.show', $ticket->ticket_number) }}"
                           class="text-xs text-blue-600 hover:underline">{{ __('travel.support.reply') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                        {{ __('travel.support.no_tickets') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
