@extends('layouts.admin')
@section('title', __('admin.live_streams.title'))

@section('content')
<div class="p-6 space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.live_streams.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.live_streams.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.live-streams.create') }}"
           class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            + {{ __('admin.live_streams.new_stream') }}
        </a>
    </div>

    @if(session('success'))
    <div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">Stream</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">Scheduled At</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">Viewers</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">Likes</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($streams as $stream)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if($stream->thumbnail_path)
                                <img src="{{ Storage::url($stream->thumbnail_path) }}"
                                     class="w-12 h-9 rounded-lg object-cover">
                            @else
                                <div class="w-12 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">📹</div>
                            @endif
                            <div>
                                <div class="font-medium text-gray-900">{{ $stream->title_en }}</div>
                                <div class="text-xs text-gray-500">{{ $stream->title_ar }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                            {{ $stream->status->value === 'live'
                                ? 'bg-green-100 text-green-700'
                                : ($stream->status->value === 'scheduled'
                                    ? 'bg-yellow-100 text-yellow-700'
                                    : 'bg-gray-100 text-gray-600') }}">
                            @if($stream->status->value === 'live')
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            @endif
                            {{ $stream->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $stream->scheduled_at?->format('d M Y, H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ number_format($stream->total_viewers) }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ number_format($stream->likes_count) }}</td>
                    <td class="px-4 py-3 text-end">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.live-streams.show', $stream) }}"
                               class="px-3 py-1 bg-primary-50 text-primary-700 rounded text-xs font-medium hover:bg-primary-100">
                                {{ $stream->status->value === 'live' ? '🔴 Studio' : 'Open' }}
                            </a>
                            <a href="{{ route('admin.live-streams.edit', $stream) }}"
                               class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-xs font-medium hover:bg-gray-200">
                                Edit
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                        {{ __('admin.live_streams.no_streams') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($streams->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $streams->links() }}</div>
        @endif
    </div>

</div>
@endsection
