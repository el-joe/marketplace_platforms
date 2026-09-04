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
            </label>
            <input type="datetime-local" name="scheduled_at"
                   value="{{ old('scheduled_at', isset($liveStream) ? $liveStream->scheduled_at?->format('Y-m-d\TH:i') : '') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
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
@endsection
