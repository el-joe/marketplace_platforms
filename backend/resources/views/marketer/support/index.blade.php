@extends('layouts.marketer')
@section('title', __('marketer.support.index_title'))
@section('page-title', __('marketer.support.index_page_title'))

@section('content')
@php
    $statusLabels = [
        'open'             => ['label' => __('marketer.support.status_open'),            'color' => 'bg-blue-100 text-blue-700'],
        'in_progress'      => ['label' => __('marketer.support.status_in_progress'),     'color' => 'bg-yellow-100 text-yellow-700'],
        'waiting_customer' => ['label' => __('marketer.support.status_waiting_customer'),'color' => 'bg-amber-100 text-amber-700'],
        'resolved'         => ['label' => __('marketer.support.status_resolved'),        'color' => 'bg-green-100 text-green-700'],
        'closed'           => ['label' => __('marketer.support.status_closed'),          'color' => 'bg-gray-100 text-gray-500'],
    ];
@endphp
<div class="space-y-5">
    <div class="flex justify-end">
        <a href="{{ route('marketer.support.create') }}" class="px-4 py-2 bg-yellow-500 text-gray-900 text-sm font-semibold rounded-lg">
            {{ __('marketer.support.new_ticket_button') }}
        </a>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('marketer.support.ticket_number_header') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('marketer.support.subject_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.support.status_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.support.date_header') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tickets as $ticket)
                    @php $st = $statusLabels[$ticket->status->value] ?? ['label' => $ticket->status->value, 'color' => 'bg-gray-100 text-gray-600']; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $ticket->ticket_number }}</td>
                        <td class="px-4 py-3">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $st['color'] }}">{{ $st['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-400">{{ $ticket->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('marketer.support.show', $ticket->ticket_number) }}" class="text-xs font-medium text-yellow-600 hover:underline">{{ __('marketer.support.view_button') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">{{ __('marketer.support.no_tickets') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
