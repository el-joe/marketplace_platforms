@extends('layouts.travel-agency')

@section('title', $ticket->subject)

@section('content')
@php
$statusColors = [
    'open'             => 'bg-blue-50 text-blue-700',
    'in_progress'      => 'bg-amber-50 text-amber-700',
    'waiting_customer' => 'bg-purple-50 text-purple-700',
    'resolved'         => 'bg-emerald-50 text-emerald-700',
    'closed'           => 'bg-gray-100 text-gray-500',
];
$isClosed = in_array($ticket->status->value, ['resolved', 'closed'], true);
@endphp

<div class="max-w-3xl space-y-5">
    <div class="flex items-start justify-between">
        <div>
            <a href="{{ route('travel-agency.support.index') }}" class="text-sm text-gray-500 hover:underline">
                {{ __('travel.support.back_to_list') }}
            </a>
            <h1 class="text-xl font-black text-gray-900 mt-1">{{ $ticket->subject }}</h1>
            <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $ticket->ticket_number }}</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-500' }}">
            {{ __('travel.support.status_' . $ticket->status->value) }}
        </span>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm text-emerald-700">
        {{ session('success') }}
    </div>
    @endif

    @if ($errors->any())
    <div class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach($messages as $message)
        <div class="px-5 py-4">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm font-semibold text-gray-800">
                    {{ $message->sender_type === \App\Models\TravelAgencyMember::class ? __('travel.support.reply') : __('travel.support.staff_label') }}
                </span>
                <span class="text-xs text-gray-400">{{ $message->created_at->format('d M Y, H:i') }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $message->message }}</p>
            @foreach($message->attachments as $attachment)
                @foreach($attachment->files as $file)
                <a href="{{ $file->full_path }}" target="_blank"
                   class="inline-flex items-center gap-1 mt-2 text-xs text-blue-600 hover:underline">
                    📎 {{ __('travel.support.attachment') }}
                </a>
                @endforeach
            @endforeach
        </div>
        @endforeach
    </div>

    @if($isClosed)
    <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-500">
        {{ __('travel.support.ticket_closed_notice') }}
    </div>
    @else
    <form method="POST" action="{{ route('travel-agency.support.reply', $ticket->ticket_number) }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        @csrf
        <label class="block text-sm font-semibold text-gray-700">{{ __('travel.support.reply') }}</label>
        <textarea name="message" rows="4" required maxlength="10000"
                  placeholder="{{ __('travel.support.reply_placeholder') }}"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none"></textarea>
        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
               class="text-sm text-gray-600 file:me-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-colors">
                {{ __('travel.support.send_reply') }}
            </button>
        </div>
    </form>
    @endif
</div>
@endsection
