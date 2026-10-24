@extends('layouts.admin')
@section('title', $liveStream->title_en)

@section('content')
<div class="p-6 space-y-4 max-w-4xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.live-streams.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('admin.live_streams.studio_back') }}</a>
            <span class="text-gray-300">|</span>
            <h1 class="text-xl font-bold text-gray-900">{{ $liveStream->title_en }}</h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                {{ $liveStream->status->value === 'live' ? 'bg-green-100 text-green-700' : ($liveStream->status->value === 'scheduled' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                {{ $liveStream->status->label() }}
            </span>
            <span class="inline-flex rounded-full bg-primary-100 text-primary-700 text-xs font-semibold px-2.5 py-0.5">📻 {{ __('admin.live_streams.radio_badge') }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if($liveStream->status->value === 'live')
                <button type="button" id="btn-end-channel"
                        data-url="{{ route('admin.live-streams.end', $liveStream) }}"
                        data-confirm="{{ __('admin.live_streams.confirm_end') }}"
                        class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    {{ __('admin.live_streams.end_stream') }}
                </button>
            @endif
            <a href="{{ route('admin.live-streams.edit', $liveStream) }}" class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">{{ __('admin.live_streams.edit') }}</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <p class="text-sm text-gray-600">{{ __('admin.live_streams.radio_auto') }}</p>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-gray-500">{{ __('admin.live_streams.scheduled_at') }}</dt><dd class="font-semibold">{{ $liveStream->scheduled_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">{{ __('admin.live_streams.ends_at') }}</dt><dd class="font-semibold">{{ $liveStream->scheduled_end_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">{{ __('admin.live_streams.duration') }}</dt><dd class="font-semibold">{{ gmdate(($liveStream->duration_seconds ?? 0) >= 3600 ? 'H:i:s' : 'i:s', $liveStream->duration_seconds ?? 0) }}</dd></div>
            <div><dt class="text-gray-500">{{ __('admin.live_streams.viewers') }}</dt><dd class="font-semibold">{{ number_format($liveStream->total_viewers) }}</dd></div>
        </dl>

        @if($liveStream->media_path)
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1">{{ __('admin.live_streams.radio_preview') }}</p>
                @if($liveStream->media_type === 'video')
                    <video controls preload="metadata" class="w-full max-w-xl rounded-lg bg-black" src="{{ Storage::url($liveStream->media_path) }}"></video>
                @else
                    <audio controls preload="metadata" class="w-full max-w-xl" src="{{ Storage::url($liveStream->media_path) }}"></audio>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 text-sm mb-3">{{ __('admin.live_streams.comments') }} ({{ $comments->count() }})</h2>
        <ul class="space-y-2 text-sm">
            @forelse($comments as $c)
                <li><span class="font-semibold">{{ $c->guest_name ?? 'Customer' }}:</span> {{ $c->body }}</li>
            @empty
                <li class="text-gray-400">—</li>
            @endforelse
        </ul>
    </div>
</div>
<script>
document.getElementById('btn-end-channel')?.addEventListener('click', async (e) => {
    const b = e.currentTarget;
    if (!confirm(b.dataset.confirm)) return;
    b.disabled = true;
    await fetch(b.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
    location.reload();
});
</script>
@endsection
