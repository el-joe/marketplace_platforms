@extends('layouts.marketer')
@section('title', 'الدعم الفني')
@section('page-title', 'تذاكر الدعم الفني')

@section('content')
@php
    $statusLabels = [
        'open' => ['label' => 'مفتوحة', 'color' => 'bg-blue-100 text-blue-700'],
        'in_progress' => ['label' => 'قيد المعالجة', 'color' => 'bg-yellow-100 text-yellow-700'],
        'waiting_customer' => ['label' => 'بانتظار ردك', 'color' => 'bg-amber-100 text-amber-700'],
        'resolved' => ['label' => 'تم الحل', 'color' => 'bg-green-100 text-green-700'],
        'closed' => ['label' => 'مغلقة', 'color' => 'bg-gray-100 text-gray-500'],
    ];
@endphp
<div class="space-y-5">
    <div class="flex justify-end">
        <a href="{{ route('marketer.support.create') }}" class="px-4 py-2 bg-yellow-500 text-gray-900 text-sm font-semibold rounded-lg">
            + تذكرة جديدة
        </a>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">رقم التذكرة</th>
                    <th class="px-4 py-3 text-start">الموضوع</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                    <th class="px-4 py-3 text-center">التاريخ</th>
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
                            <a href="{{ route('marketer.support.show', $ticket->ticket_number) }}" class="text-xs font-medium text-yellow-600 hover:underline">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">لا توجد تذاكر بعد</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
