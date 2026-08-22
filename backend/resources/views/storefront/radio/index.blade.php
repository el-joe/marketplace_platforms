@extends('layouts.storefront-mobile')

@section('title', 'الراديو')

@section('content')
<div class="h-full overflow-y-auto pb-20" x-data>

    {{-- Header --}}
    <div class="bg-gradient-to-br from-indigo-600 to-purple-700 px-4 pt-10 pb-8 text-white">
        <div class="flex items-center gap-3 mb-2">
            <span class="text-3xl">📻</span>
            <h1 class="text-2xl font-black">الراديو</h1>
        </div>
        <p class="text-indigo-200 text-sm">استمع أو شاهد أثناء تصفحك</p>
    </div>

    {{-- Channels --}}
    <div class="px-4 py-5 space-y-3">

        @forelse($channels as $channel)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center gap-4 p-4">

                {{-- Thumbnail --}}
                <div class="relative shrink-0">
                    @if($channel->thumbnail_path)
                    <img src="{{ Storage::url($channel->thumbnail_path) }}"
                         class="w-16 h-16 rounded-xl object-cover">
                    @else
                    <div class="w-16 h-16 rounded-xl
                        {{ $channel->type === \App\Enums\RadioChannelType::Video ? 'bg-purple-100' : 'bg-blue-100' }}
                        flex items-center justify-center text-2xl">
                        {{ $channel->type === \App\Enums\RadioChannelType::Video ? '📺' : '📻' }}
                    </div>
                    @endif
                    @if($channel->is_live)
                    <span class="absolute -top-1 -right-1 px-1.5 py-0.5 bg-red-500 text-white text-[9px] font-bold rounded-full uppercase animate-pulse">
                        {{ __('common.storefront_nav.live') }}
                    </span>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-900 truncate">{{ $channel->name_ar }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $channel->type === \App\Enums\RadioChannelType::Video ? 'بث مرئي' : 'بث صوتي' }}
                        @if($channel->country) · {{ $channel->country->name_ar ?? $channel->country->name_en }} @endif
                    </div>
                    @if($channel->now_playing)
                    <div class="text-xs text-indigo-600 font-medium mt-1 truncate">▶ {{ $channel->now_playing }}</div>
                    @endif
                </div>

                {{-- Play Button --}}
                <a href="{{ route('storefront.radio.player', $channel) }}"
                   class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center
                       {{ $channel->is_live ? 'bg-red-500 hover:bg-red-600' : 'bg-indigo-600 hover:bg-indigo-700' }}
                       text-white transition-colors">
                    <svg class="w-4 h-4 fill-current ml-0.5" viewBox="0 0 20 20">
                        <path d="M6.3 2.84A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.27l9.344-5.891a1.5 1.5 0 000-2.538L6.3 2.841z"/>
                    </svg>
                </a>

            </div>
        </div>
        @empty
        <div class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-3">📻</div>
            <p>لا توجد قنوات راديو متاحة حالياً</p>
        </div>
        @endforelse

    </div>
</div>
@endsection
