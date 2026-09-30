@extends('layouts.admin')
@section('title', __('admin.marketer_jobs.title'))
@section('page-title', __('admin.marketer_jobs.title'))

@section('content')
<div class="space-y-4" x-data="{ showCreateModal: {{ $errors->any() ? 'true' : 'false' }} }">

    <div class="flex justify-end">
        <button type="button" x-on:click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
            + {{ __('admin.marketer_jobs.add_job') }}
        </button>
    </div>

    {{-- Create job modal --}}
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         x-on:keydown.escape.window="showCreateModal = false">
        <div class="bg-white rounded-xl w-full max-w-lg p-6 space-y-4" x-on:click.outside="showCreateModal = false">
            <h3 class="font-bold text-gray-800 text-lg">{{ __('admin.marketer_jobs.add_new_job') }}</h3>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                    <ul class="list-disc ps-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.marketer-jobs.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_jobs.key') }}</label>
                    <input type="text" name="key" value="{{ old('key') }}" required dir="ltr"
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_jobs.name_ar') }}</label>
                    <input type="text" name="name_ar" value="{{ old('name_ar') }}" required
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_jobs.name_en') }}</label>
                    <input type="text" name="name_en" value="{{ old('name_en') }}" required dir="ltr"
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_jobs.sort_order') }}</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        {{ __('admin.marketer_jobs.active') }}
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="showCreateModal = false"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">{{ __('admin.marketer_jobs.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700">{{ __('admin.marketer_jobs.create') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.marketer_jobs.col_key') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.marketer_jobs.col_name_ar') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.marketer_jobs.col_name_en') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketer_jobs.col_sort_order') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketer_jobs.col_marketers_count') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketer_jobs.col_status') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketer_jobs.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($marketerJobs as $job)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500" dir="ltr">{{ $job->key }}</td>
                    <td class="px-4 py-3">{{ $job->name_ar }}</td>
                    <td class="px-4 py-3" dir="ltr">{{ $job->name_en }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $job->sort_order }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $job->marketers_count }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $job->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $job->is_active ? __('admin.marketer_jobs.active') : __('admin.marketer_jobs.inactive') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('admin.marketer-jobs.edit', $job) }}" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __('admin.marketer_jobs.edit') }}</a>
                            <form method="POST" action="{{ route('admin.marketer-jobs.toggle-active', $job) }}" class="inline">
                                @csrf
                                <button class="text-xs px-3 py-1 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200">
                                    {{ $job->is_active ? __('admin.marketer_jobs.deactivate') : __('admin.marketer_jobs.activate') }}
                                </button>
                            </form>
                            @if($job->marketers_count === 0)
                            <form method="POST" action="{{ route('admin.marketer-jobs.destroy', $job) }}" class="inline" onsubmit="return confirm('{{ __('admin.marketer_jobs.delete_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">{{ __('admin.marketer_jobs.delete') }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('admin.marketer_jobs.no_jobs') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
