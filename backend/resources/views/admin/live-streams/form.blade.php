@extends('layouts.admin')
@section('title', isset($liveStream)
    ? __('admin.live_streams.edit_stream')
    : __('admin.live_streams.new_stream'))

@section('content')
<div class="p-6 max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('admin.live-streams.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700">
            {{ __('admin.live_streams.back_to_streams') }}
        </a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">
            {{ isset($liveStream)
                ? __('admin.live_streams.edit_stream')
                : __('admin.live_streams.new_stream') }}
        </h1>
    </div>

    <form method="POST"
          enctype="multipart/form-data"
          action="{{ isset($liveStream)
              ? route('admin.live-streams.update', $liveStream)
              : route('admin.live-streams.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf
        @if(isset($liveStream)) @method('PUT') @endif

        @if($errors->any())
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        @php
            $isEdit = isset($liveStream);
            $source = old('source', $isEdit ? $liveStream->source : 'live');
            $lockSchedule = $isEdit && $liveStream->isRecorded() && $liveStream->status->value !== 'scheduled';
        @endphp

        <div x-data="{ source: '{{ $source }}' }" class="space-y-5">
        <input type="hidden" name="source" :value="source">

        {{-- Type --}}
        @unless($isEdit)
        <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="source = 'live'"
                    :class="source === 'live' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-300 text-gray-600'"
                    class="border rounded-lg px-4 py-3 text-start text-sm">
                <span class="block font-semibold">📹 {{ __('admin.live_streams.type_live') }}</span>
                <span class="block text-xs mt-0.5 opacity-80">{{ __('admin.live_streams.type_live_hint') }}</span>
            </button>
            <button type="button" @click="source = 'recorded'"
                    :class="source === 'recorded' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-300 text-gray-600'"
                    class="border rounded-lg px-4 py-3 text-start text-sm">
                <span class="block font-semibold">📻 {{ __('admin.live_streams.type_radio') }}</span>
                <span class="block text-xs mt-0.5 opacity-80">{{ __('admin.live_streams.type_radio_hint') }}</span>
            </button>
        </div>
        @endunless

        {{-- Titles --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('admin.live_streams.title_en') }} <span class="text-red-500">*</span>
                </label>
                <input name="title_en"
                       value="{{ old('title_en', $liveStream->title_en ?? '') }}"
                       required dir="ltr"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('admin.live_streams.title_ar') }} <span class="text-red-500">*</span>
                </label>
                <input name="title_ar"
                       value="{{ old('title_ar', $liveStream->title_ar ?? '') }}"
                       required dir="rtl"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
        </div>

        {{-- Scheduled At --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.live_streams.scheduled_at') }}
                <span class="text-red-500" x-show="source === 'recorded'">*</span>
            </label>
            <input type="datetime-local" name="scheduled_at" id="scheduled-at"
                   value="{{ old('scheduled_at', isset($liveStream) ? $liveStream->scheduled_at?->format('Y-m-d\TH:i') : '') }}"
                   @disabled($lockSchedule)
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm disabled:bg-gray-100">
            @error('scheduled_at') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Radio channel: uploaded audio/video file + auto-calculated timing --}}
        <div x-show="source === 'recorded'" x-cloak class="rounded-lg border border-primary-200 bg-primary-50/40 p-4 space-y-3">
            @if($isEdit && $liveStream->isRecorded() && $liveStream->media_path)
            <p class="text-xs text-gray-600">
                {{ __('admin.live_streams.current_file') }}:
                <span class="font-semibold">{{ basename($liveStream->media_path) }}</span>
                ({{ $liveStream->media_type }}, {{ gmdate($liveStream->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $liveStream->duration_seconds) }})
            </p>
            @endif
            @unless($lockSchedule)
            <label class="block text-sm font-medium text-gray-700">
                {{ __('admin.live_streams.media_file') }} <span class="text-red-500" x-show="source === 'recorded'">*</span>
            </label>
            <input type="file" name="media" id="media-file"
                   accept="audio/*,video/*,.mp3,.wav,.ogg,.m4a,.aac,.mp4,.webm,.mov"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
            @error('media') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            <input type="hidden" name="duration_seconds" id="duration-seconds" value="{{ old('duration_seconds') }}">
            <p class="text-xs text-gray-500">{{ __('admin.live_streams.media_hint') }}</p>
            @else
            <p class="text-xs text-gray-500">{{ __('admin.live_streams.locked_hint') }}</p>
            @endunless
            <div id="timing-preview" class="hidden text-sm text-gray-800 bg-white border border-gray-200 rounded-lg px-3 py-2"
                 data-l-duration="{{ __('admin.live_streams.duration') }}"
                 data-l-ends="{{ __('admin.live_streams.ends_at') }}"
                 data-existing="{{ $isEdit && $liveStream->isRecorded() ? $liveStream->duration_seconds : '' }}"></div>
        </div>

        {{-- Descriptions --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('admin.live_streams.description_en') }}
                </label>
                <textarea name="description_en" rows="4" dir="ltr"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('description_en', $liveStream->description_en ?? '') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('admin.live_streams.description_ar') }}
                </label>
                <textarea name="description_ar" rows="4" dir="rtl"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('description_ar', $liveStream->description_ar ?? '') }}</textarea>
            </div>
        </div>

        {{-- Thumbnail --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('admin.live_streams.thumbnail') }}
            </label>
            @if(isset($liveStream) && $liveStream->thumbnail_path)
            <img src="{{ Storage::url($liveStream->thumbnail_path) }}"
                 class="w-40 h-24 object-cover rounded-lg mb-2 border border-gray-200">
            @endif
            <input type="file" name="thumbnail" accept="image/*"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        </div>{{-- /x-data --}}

        {{-- Actions --}}
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.live-streams.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                {{ isset($liveStream) ? 'Update Stream' : 'Schedule Stream' }}
            </button>
        </div>
    </form>

</div>
<script>
(function () {
    const file = document.getElementById('media-file');
    const at = document.getElementById('scheduled-at');
    const out = document.getElementById('timing-preview');
    const hidden = document.getElementById('duration-seconds');
    if (!out) return;
    let seconds = parseInt(out.dataset.existing || hidden?.value || '', 10) || 0;

    const fmt = (s) => {
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return (h ? h + ':' : '') + String(m).padStart(h ? 2 : 1, '0') + ':' + String(x).padStart(2, '0');
    };
    const render = () => {
        if (!seconds) { out.classList.add('hidden'); return; }
        let html = '<strong>' + out.dataset.lDuration + ':</strong> ' + fmt(seconds);
        if (at && at.value) {
            const end = new Date(new Date(at.value).getTime() + seconds * 1000);
            html += ' &nbsp;·&nbsp; <strong>' + out.dataset.lEnds + ':</strong> ' + end.toLocaleString();
        }
        out.innerHTML = html;
        out.classList.remove('hidden');
    };
    render();
    at && at.addEventListener('input', render);
    file && file.addEventListener('change', () => {
        const f = file.files[0];
        if (!f) return;
        const el = document.createElement(f.type.startsWith('video') ? 'video' : 'audio');
        el.preload = 'metadata';
        const url = URL.createObjectURL(f);
        el.onloadedmetadata = () => {
            seconds = Math.ceil(el.duration) || 0;
            if (hidden) hidden.value = seconds;
            URL.revokeObjectURL(url);
            render();
        };
        el.src = url;
    });
})();
</script>
@endsection
