@extends('layouts.marketer')
@section('title', __('marketer.conversations'))
@section('page-title', __('marketer.conversations'))

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 divide-y">
        @forelse($conversations as $c)
        <a href="{{ route('marketer.conversations.show', $c->id) }}"
           class="block p-3 hover:bg-gray-50 {{ $active?->id === $c->id ? 'bg-gray-50' : '' }}">
            <div class="flex items-center justify-between">
                <span class="font-medium text-sm">
                    @if($c->marketer_has_unread)<span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>@endif
                    {{ $c->customer?->name }}
                </span>
                <span class="text-xs text-gray-400">{{ $c->last_message_at?->diffForHumans() }}</span>
            </div>
            <p class="text-xs text-gray-500 truncate">{{ $c->latestMessage?->body }}</p>
        </a>
        @empty
        <p class="p-4 text-sm text-gray-500">{{ __('marketer.no_conversations') }}</p>
        @endforelse
    </div>

    <div class="md:col-span-2 bg-white rounded-xl border border-gray-200 flex flex-col min-h-[400px]">
        @if($active)
        <div class="p-3 border-b text-sm font-semibold">{{ $active->customer?->name }}
            @if($active->classifiedListing)<span class="text-gray-400 font-normal">- {{ $active->classifiedListing->title }}</span>@endif
        </div>
        <div class="flex-1 p-4 space-y-2 overflow-y-auto">
            @foreach($messages as $m)
            <div class="flex {{ $m->sender_type === 'marketer' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%] px-3 py-2 rounded-2xl text-sm {{ $m->sender_type === 'marketer' ? 'bg-teal-600 text-white' : 'bg-gray-200 text-gray-800' }}">
                    {{ $m->body }}
                    <div class="text-[10px] opacity-70 mt-1">{{ $m->created_at->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('marketer.conversations.message', $active->id) }}" class="p-3 border-t flex gap-2">@csrf
            <input name="body" required maxlength="5000" placeholder="{{ __('marketer.type_message') }}" class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <button class="px-4 py-2 bg-teal-600 text-white rounded-lg text-sm">{{ __('marketer.send') }}</button>
        </form>
        @else
        <p class="p-6 text-sm text-gray-500">{{ __('marketer.conversations') }}</p>
        @endif
    </div>
</div>
@endsection
