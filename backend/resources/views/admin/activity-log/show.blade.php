@extends('layouts.admin')

@section('title', __('admin.activity_log_section.entry_title'))

@section('content')

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.activity-log.index') }}" class="hover:text-primary-600">{{ __('admin.activity_log_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">{{ __('admin.activity_log_section.entry_detail') }}</span>
    </nav>

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.activity_log_section.activity_detail') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ $entry->id }}</p>
        </div>
        <a href="{{ route('admin.activity-log.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.activity_log_section.back') }}</a>
    </div>

    @php
        $eventColor = match ($entry->event) {
            'created' => 'bg-green-100 text-green-700 border-green-200',
            'updated' => 'bg-blue-100 text-blue-700 border-blue-200',
            'deleted' => 'bg-red-100 text-red-700 border-red-200',
            default   => 'bg-gray-100 text-gray-600 border-gray-200',
        };
        $causerShort = class_basename($entry->causer_type ?? '');
        $subjectShort = class_basename($entry->subject_type ?? '');
        $oldData  = $entry->properties['old'] ?? null;
        $newData  = $entry->properties['attributes'] ?? $entry->properties['new'] ?? null;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        {{-- ─── Who ──────────────────────────────────────────────────────────── --}}
        <x-card>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.activity_log_section.actor') }}</h3>
            <div class="space-y-2">
                @if($entry->causer_type)
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ match($causerShort) {
                                'Admin'    => 'bg-indigo-100 text-indigo-700',
                                'Vendor'   => 'bg-purple-100 text-purple-700',
                                'Customer' => 'bg-teal-100 text-teal-700',
                                default    => 'bg-gray-100 text-gray-600',
                            } }}">
                            {{ $causerShort }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-700 font-mono break-all">{{ $entry->causer_id }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">{{ __('admin.activity_log_section.system_automated') }}</p>
                @endif
            </div>
        </x-card>

        {{-- ─── What ─────────────────────────────────────────────────────────── --}}
        <x-card>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.activity_log_section.action') }}</h3>
            <div class="space-y-2">
                @if($entry->event)
                    <span class="inline-flex items-center px-2 py-0.5 rounded border text-sm font-semibold {{ $eventColor }}">
                        {{ ucfirst($entry->event) }}
                    </span>
                @endif
                <p class="text-sm text-gray-700">{{ $entry->description }}</p>
                @if($entry->log_name)
                    <p class="text-xs text-gray-400 font-mono">{{ __('admin.activity_log_section.log') }}: {{ $entry->log_name }}</p>
                @endif
            </div>
        </x-card>

        {{-- ─── When / Subject ──────────────────────────────────────────────── --}}
        <x-card>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{!! __('admin.activity_log_section.subject_and_time') !!}</h3>
            <div class="space-y-2">
                @if($entry->subject_type)
                    <div class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                            {{ $subjectShort }}
                        </span>
                        <span class="text-xs font-mono text-gray-500">{{ $entry->subject_id }}</span>
                    </div>
                @endif
                <p class="text-sm text-gray-700">{{ $entry->created_at->format('M d, Y H:i:s') }}</p>
                <p class="text-xs text-gray-400">{{ $entry->created_at->diffForHumans() }}</p>
                @if($entry->ip_address)
                    <p class="text-xs font-mono text-gray-400">{{ __('admin.activity_log_section.ip_label') }}: {{ $entry->ip_address }}</p>
                @endif
            </div>
        </x-card>

    </div>

    {{-- ─── Changes diff ────────────────────────────────────────────────────── --}}
    @if($newData || $oldData)
        <x-card>
            <h3 class="text-sm font-semibold text-gray-800 mb-4">
                @if($entry->event === 'created') {{ __('admin.activity_log_section.created_data') }}
                @elseif($entry->event === 'deleted') {{ __('admin.activity_log_section.deleted_data') }}
                @else {{ __('admin.activity_log_section.changes') }}
                @endif
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                            <th class="pb-2 pr-6 w-1/4">{{ __('admin.activity_log_section.field') }}</th>
                            @if($entry->event === 'updated' && $oldData)
                                <th class="pb-2 pr-6 w-5/12">{{ __('admin.activity_log_section.before') }}</th>
                                <th class="pb-2 w-5/12">{{ __('admin.activity_log_section.after') }}</th>
                            @else
                                <th class="pb-2 w-3/4">{{ __('admin.activity_log_section.value') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $fields = array_unique(array_merge(
                                array_keys($oldData ?? []),
                                array_keys($newData ?? [])
                            ));
                        @endphp
                        @foreach($fields as $field)
                            @php
                                $old = $oldData[$field] ?? null;
                                $new = $newData[$field] ?? null;
                                $changed = $entry->event === 'updated' && $old !== $new;
                            @endphp
                            <tr class="{{ $changed ? 'bg-yellow-50' : '' }}">
                                <td class="py-2.5 pr-6 font-mono text-xs text-gray-700 align-top">{{ $field }}</td>
                                @if($entry->event === 'updated' && $oldData)
                                    <td class="py-2.5 pr-6 align-top">
                                        @if($old === null)
                                            <span class="text-gray-300 italic text-xs">null</span>
                                        @elseif(is_bool($old))
                                            <span class="text-xs font-mono">{{ $old ? 'true' : 'false' }}</span>
                                        @elseif(is_array($old))
                                            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all">{{ json_encode($old, JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            <span class="text-xs break-all {{ $changed ? 'text-red-700 line-through' : 'text-gray-700' }}">{{ $old }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 align-top">
                                        @if($new === null)
                                            <span class="text-gray-300 italic text-xs">null</span>
                                        @elseif(is_bool($new))
                                            <span class="text-xs font-mono">{{ $new ? 'true' : 'false' }}</span>
                                        @elseif(is_array($new))
                                            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all">{{ json_encode($new, JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            <span class="text-xs break-all {{ $changed ? 'text-green-700 font-medium' : 'text-gray-700' }}">{{ $new }}</span>
                                        @endif
                                    </td>
                                @else
                                    <td class="py-2.5 align-top">
                                        @php $val = $new ?? $old; @endphp
                                        @if($val === null)
                                            <span class="text-gray-300 italic text-xs">null</span>
                                        @elseif(is_bool($val))
                                            <span class="text-xs font-mono">{{ $val ? 'true' : 'false' }}</span>
                                        @elseif(is_array($val))
                                            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all">{{ json_encode($val, JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            <span class="text-xs break-all text-gray-700">{{ $val }}</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- ─── Raw properties (for non-diff entries) ───────────────────────────── --}}
    @if($entry->properties && !$newData && !$oldData)
        <x-card>
            <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.activity_log_section.properties') }}</h3>
            <pre class="text-xs text-gray-600 bg-gray-50 rounded p-4 overflow-x-auto">{{ json_encode($entry->properties, JSON_PRETTY_PRINT) }}</pre>
        </x-card>
    @endif

@endsection
