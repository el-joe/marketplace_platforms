@extends('layouts.storefront-mobile')

@section('title', $channel->name_ar . ' — الراديو')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
@endpush

@section('content')
<div class="h-full overflow-y-auto pb-6"
     x-data="radioPlayer(
         @js($channel->id),
         @js($channel->current_stream_url),
         @js($channel->type->value),
         @js($channel->name_ar),
         @js($channel->is_live)
     )"
     x-init="init()">

    {{-- Back bar --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-100 sticky top-0 z-10">
        <a href="{{ route('storefront.radio.index') }}" class="text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="font-bold text-gray-900 text-sm truncate">{{ $channel->name_ar }}</span>
        @if($channel->is_live)
        <span class="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full uppercase animate-pulse ml-auto">{{ __('common.storefront_nav.live') }}</span>
        @endif
    </div>

    {{-- Video player (shown for video type) --}}
    @if($channel->type === \App\Enums\RadioChannelType::Video)
    <div class="bg-black aspect-video w-full">
        <video id="radio-video" class="w-full h-full" controls playsinline
               x-ref="videoEl"
               @play="onPlay()" @pause="onPause()">
        </video>
    </div>
    @else
    {{-- Audio: show artwork --}}
    <div class="bg-gradient-to-br from-indigo-600 to-purple-700 aspect-square w-full max-w-xs mx-auto mt-6 rounded-3xl
                flex items-center justify-center shadow-2xl">
        @if($channel->thumbnail_path)
        <img src="{{ Storage::url($channel->thumbnail_path) }}" class="w-full h-full object-cover rounded-3xl">
        @else
        <span class="text-8xl">📻</span>
        @endif
    </div>
    <audio id="radio-audio" x-ref="audioEl" @play="onPlay()" @pause="onPause()" class="hidden"></audio>
    @endif

    {{-- Controls --}}
    <div class="px-6 mt-6 space-y-4">

        {{-- Now playing --}}
        <div class="text-center">
            <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">
                {{ $channel->is_live ? 'يبث الآن' : 'يشغل حالياً' }}
            </div>
            <div class="font-bold text-gray-900 text-lg" x-text="nowPlaying || @js($channel->name_ar)"></div>
            <div class="text-sm text-gray-500">{{ $channel->type === \App\Enums\RadioChannelType::Video ? 'بث مرئي' : 'بث صوتي' }}</div>
        </div>

        {{-- Play / Pause --}}
        <div class="flex items-center justify-center gap-6">
            <button @click="togglePlay()"
                    class="w-16 h-16 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center
                           shadow-lg transition-all active:scale-95">
                <template x-if="!playing">
                    <svg class="w-7 h-7 fill-current ml-1" viewBox="0 0 20 20">
                        <path d="M6.3 2.84A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.27l9.344-5.891a1.5 1.5 0 000-2.538L6.3 2.841z"/>
                    </svg>
                </template>
                <template x-if="playing">
                    <svg class="w-7 h-7 fill-current" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm7 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd"/>
                    </svg>
                </template>
            </button>
        </div>

        {{-- Volume --}}
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.814L4.846 14H3a1 1 0 01-1-1V7a1 1 0 011-1h1.846l3.537-2.814a1 1 0 011 .076zM12.707 7.293a1 1 0 011.414 1.414A3 3 0 0115 10a3 3 0 01-.879 2.121 1 1 0 11-1.414-1.414A1 1 0 0013 10a1 1 0 00-.293-.707 1 1 0 010-1.414z"/>
            </svg>
            <input type="range" min="0" max="1" step="0.05" x-model="volume"
                   @input="setVolume($event.target.value)"
                   class="flex-1 accent-indigo-600">
            <svg class="w-5 h-5 text-gray-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.814L4.846 14H3a1 1 0 01-1-1V7a1 1 0 011-1h1.846l3.537-2.814a1 1 0 011 .076zM13.536 5.05a1 1 0 011.414-.001A7 7 0 0117 10a7 7 0 01-2.05 4.95 1 1 0 01-1.414-1.414A5 5 0 0015 10a5 5 0 00-1.464-3.536 1 1 0 010-1.414z"/>
            </svg>
        </div>

    </div>

    {{-- Upcoming schedule --}}
    @if($upcoming->count())
    <div class="px-4 mt-8">
        <h3 class="text-sm font-bold text-gray-700 mb-3">قادم خلال 7 أيام</h3>
        <div class="space-y-2">
            @foreach($upcoming->take(10) as $occ)
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $occ['slot']->title }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        {{ \Carbon\Carbon::parse($occ['starts_at'])->translatedFormat('D d M · H:i') }}
                        — {{ \Carbon\Carbon::parse($occ['ends_at'])->format('H:i') }}
                    </div>
                </div>
                @if($occ['slot']->recurrence !== \App\Enums\RadioScheduleSlotRecurrence::Once)
                <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full">
                    {{ $occ['slot']->recurrence === \App\Enums\RadioScheduleSlotRecurrence::Daily ? 'يومي' : 'أسبوعي' }}
                </span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/storefront/radio-player.js') }}"></script>
@endpush
