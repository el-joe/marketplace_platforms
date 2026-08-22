@extends('layouts.admin')

@section('title', __('admin.radio.channels'))

@section('content')
<div class="p-6 space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.radio.channels') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.radio.channels_desc') }}</p>
        </div>
        <a href="{{ route('admin.radio.channels.create') }}"
           class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            + {{ __('admin.radio.new_channel') }}
        </a>
    </div>

    @if(session('success'))
    <div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.radio.channel') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.radio.type') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.country') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.radio.stream') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($channels as $channel)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if($channel->thumbnail_path)
                            <img src="{{ Storage::url($channel->thumbnail_path) }}" class="w-10 h-10 rounded-lg object-cover">
                            @else
                            <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center text-primary-600">
                                @if($channel->type === \App\Enums\RadioChannelType::Video) 📺 @else 📻 @endif
                            </div>
                            @endif
                            <div>
                                <div class="font-medium text-gray-900">{{ $channel->name_en }}</div>
                                <div class="text-xs text-gray-500">{{ $channel->name_ar }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium
                            {{ $channel->type === \App\Enums\RadioChannelType::Video ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ __('admin.radio.' . $channel->type->value) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $channel->country?->name_en ?? __('admin.radio.all_countries') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($channel->is_active)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                        @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('common.inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                        {{ $channel->stream_url ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.radio.schedule', $channel) }}"
                               class="text-xs px-3 py-1.5 border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50">
                                📅 {{ __('admin.radio.schedule') }}
                            </a>
                            <a href="{{ route('admin.radio.channels.edit', $channel) }}"
                               class="text-xs px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                {{ __('common.edit') }}
                            </a>
                            <form method="POST" action="{{ route('admin.radio.channels.destroy', $channel) }}"
                                  onsubmit="return confirm('{{ __('admin.radio.delete_channel_confirm') }}')">
                                @csrf @method('DELETE')
                                <button class="text-xs px-3 py-1.5 border border-red-200 text-red-600 rounded-lg hover:bg-red-50">
                                    {{ __('common.delete') }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-400">{{ __('admin.radio.no_channels') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($channels->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $channels->links() }}</div>
        @endif
    </div>

</div>
@endsection
