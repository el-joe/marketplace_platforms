@extends('layouts.admin')

@section('title', __('admin.notifications_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.notifications_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $customer->name }} &middot; {{ $customer->email }}</p>
        </div>
        <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-secondary btn-sm">
            {{ __('admin.customers_section.title') }}
        </a>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.type_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.channel_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.title_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.read_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.sent_at_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4">{{ $notification->type }}</td>
                            <td class="py-2 pr-4">{{ $notification->channel?->value }}</td>
                            <td class="py-2 pr-4">{{ $notification->data['title'] ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if($notification->read_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">{{ __('common.yes') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('common.no') }}</span>
                                @endif
                            </td>
                            <td class="py-2">{{ $notification->sent_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

@endsection
