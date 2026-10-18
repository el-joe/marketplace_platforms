@extends('layouts.admin')
@section('title', $liveStream->title_en . ' — Studio')

@push('head')
<meta name="stream-key"    content="{{ $liveStream->stream_key }}">
<meta name="stream-status" content="{{ $liveStream->status->value }}">
<meta name="stream-id"     content="{{ $liveStream->id }}">
<meta name="turn-url"      content="{{ config('services.turn.url', '') }}">
<meta name="turn-user"     content="{{ config('services.turn.username', '') }}">
<meta name="turn-cred"     content="{{ config('services.turn.credential', '') }}">
<meta name="turns-url"     content="{{ config('services.turn.tls_url', '') }}">
<meta name="stream-media-mode" content="{{ $liveStream->media_mode }}">
@endpush

@section('content')
<div class="p-6 space-y-4">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.live-streams.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('admin.live_streams.studio_back') }}</a>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900">{{ $liveStream->title_en }}</h1>
            <span id="status-badge"
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ $liveStream->status->value === 'live'
                        ? 'bg-green-100 text-green-700'
                        : ($liveStream->status->value === 'scheduled'
                            ? 'bg-yellow-100 text-yellow-700'
                            : 'bg-gray-100 text-gray-600') }}">
                @if($liveStream->status->value === 'live')
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                @endif
                {{ $liveStream->status->label() }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if($liveStream->status->value !== 'ended')
                <button id="btn-go-live" type="button"
                        class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 flex items-center gap-2 disabled:opacity-60 {{ $liveStream->status->value === 'live' ? 'hidden' : '' }}">
                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                    <span class="label">{{ __('admin.live_streams.go_live') }}</span>
                </button>
                <button id="btn-end-stream" type="button"
                        class="px-5 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-60 {{ $liveStream->status->value === 'live' ? '' : 'hidden' }}">
                    {{ __('admin.live_streams.end_stream') }}
                </button>
            @endif
            <a href="{{ route('admin.live-streams.edit', $liveStream) }}"
               class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">
                {{ __('admin.live_streams.edit') }}
            </a>
        </div>
    </div>

    {{-- Persistent status strip --}}
    <div id="echo-missing-banner" class="hidden rounded-lg border border-red-300 bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">
        {{ __('admin.live_streams.echo_missing') }}
    </div>
    <div id="studio-error" class="hidden rounded-lg border border-red-300 bg-red-50 text-red-800 text-sm px-4 py-3" role="alert"></div>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
        <span id="conn-status" class="inline-flex items-center gap-1.5 font-medium">
            <span id="conn-dot" class="w-2 h-2 rounded-full bg-gray-400"></span>
            <span id="conn-text">{{ __('admin.live_streams.conn_connecting') }}</span>
        </span>
        <span id="ice-diag" class="truncate" title="{{ __('admin.live_streams.diag_title') }}"></span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- ── Studio column ── --}}
        <div class="lg:col-span-2 space-y-3">

            {{-- Video preview --}}
            <div class="bg-black rounded-2xl overflow-hidden aspect-video relative">
                <video id="local-video" autoplay muted playsinline
                       class="w-full h-full object-cover"></video>
                <canvas id="audio-meter" width="640" height="360"
                        class="absolute inset-0 w-full h-full hidden bg-gray-900"></canvas>
                <div id="audio-only-badge"
                     class="hidden absolute bottom-3 left-3 bg-black/60 text-white text-xs px-2.5 py-1 rounded-full">
                    {{ __('admin.live_streams.audio_only_preview') }}
                </div>
                <div id="no-preview"
                     class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                    <svg class="w-16 h-16 mb-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                    </svg>
                    <p class="text-sm text-center px-6">{{ __('admin.live_streams.preview_hint') }}</p>
                </div>
                <div class="absolute top-3 left-3 flex items-center gap-2">
                    <span id="viewer-count"
                          class="bg-black/60 text-white text-xs px-2.5 py-1 rounded-full font-medium hidden">
                        <span id="viewer-num">0</span> {{ __('admin.live_streams.watching') }}
                    </span>
                    <span id="live-indicator"
                          class="bg-red-600 text-white text-xs px-2.5 py-1 rounded-full font-bold
                            {{ $liveStream->status->value !== 'live' ? 'hidden' : '' }}">
                        LIVE
                    </span>
                </div>
            </div>

            {{-- Broadcast controls --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.live_streams.mode_label') }}</span>
                        <select id="sel-mode" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="both">{{ __('admin.live_streams.mode_camera_mic') }}</option>
                            <option value="video">{{ __('admin.live_streams.mode_camera') }}</option>
                            <option value="audio">{{ __('admin.live_streams.mode_mic') }}</option>
                            <option value="screen">{{ __('admin.live_streams.mode_screen') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.live_streams.camera_device') }}</span>
                        <select id="sel-camera" class="w-full rounded-lg border-gray-300 text-sm"></select>
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.live_streams.mic_device') }}</span>
                        <select id="sel-mic" class="w-full rounded-lg border-gray-300 text-sm"></select>
                    </label>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button id="btn-start-camera" type="button"
                            class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                        {{ __('admin.live_streams.start_preview') }}
                    </button>
                    <button id="btn-mute" type="button"
                            class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 hidden">
                        {{ __('admin.live_streams.mute') }}
                    </button>
                    <button id="btn-camera-toggle" type="button"
                            class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 hidden">
                        {{ __('admin.live_streams.camera_off') }}
                    </button>
                    <button id="btn-stop" type="button"
                            class="px-3 py-2 bg-red-50 text-red-700 rounded-lg text-sm font-medium hover:bg-red-100 hidden">
                        {{ __('admin.live_streams.stop_preview') }}
                    </button>
                    <div class="ms-auto text-sm text-gray-500">
                        <span id="likes-count">{{ $liveStream->likes_count }}</span> {{ __('admin.live_streams.likes_label') }}
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900" id="stat-viewers">
                        {{ number_format($liveStream->total_viewers) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('admin.static_text.admin_live_streams_show.total_viewers') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900" id="stat-likes">
                        {{ number_format($liveStream->likes_count) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('admin.live_streams.likes') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900" id="stat-comments">
                        {{ $comments->count() }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('admin.live_streams.comments') }}</div>
                </div>
            </div>
        </div>

        {{-- ── Comments panel ── --}}
        <div class="bg-white rounded-xl border border-gray-200 flex flex-col overflow-hidden"
             style="height: 600px">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-800 text-sm flex items-center justify-between">
                {{ __('admin.static_text.admin_live_streams_show.live_comments') }}
                <span class="text-xs text-gray-400 font-normal" id="comment-count">
                    {{ $comments->count() }}
                </span>
            </div>
            <div id="comments-list" class="flex-1 overflow-y-auto p-3 space-y-2">
                @foreach($comments as $comment)
                <div class="comment-item flex gap-2" data-id="{{ $comment->id }}">
                    <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-600 shrink-0">
                        {{ mb_substr($comment->guest_name ?? 'G', 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-xs font-semibold text-gray-700">
                            {{ $comment->guest_name ?? 'Guest' }}
                        </span>
                        <p class="text-xs text-gray-600 mt-0.5 leading-snug">{{ $comment->body }}</p>
                    </div>
                    <button class="delete-comment text-gray-300 hover:text-red-500 text-xs shrink-0"
                            data-id="{{ $comment->id }}">✕</button>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
  window.LIVE_STUDIO_I18N = @json(__('admin.live_streams'));
</script>
{{-- Deferred so it runs after the Vite module bundle has created window.Echo --}}
<script defer src="{{ asset('js/admin/live-stream-studio.js') }}?v={{ @filemtime(public_path('js/admin/live-stream-studio.js')) }}"></script>
@endpush
