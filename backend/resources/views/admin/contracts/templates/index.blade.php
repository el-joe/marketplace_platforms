@extends('layouts.admin')
@section('title', __('admin.contracts.templates_title'))

@section('content')
<div class="p-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.contracts.templates_title') }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ __('admin.contracts.templates_desc') }}</p>
        </div>
        <a href="{{ route('admin.contracts.templates.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            {{ __('admin.contracts.new_template') }}
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex gap-2 text-sm">
        @foreach ([null => 'filter_all', 'classified' => 'filter_classified', 'product' => 'filter_product'] as $value => $label)
            <a href="{{ route('admin.contracts.templates.index', $value ? ['scope' => $value] : []) }}"
               class="rounded-full px-3 py-1 {{ $scope === $value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                {{ __('admin.contracts.' . $label) }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.contracts.col_scope') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_status') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_assigned') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($templates as $template)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $template->name }}</td>
                        <td class="px-4 py-3">{{ __('admin.contracts.scope_' . $template->category_scope) }}</td>
                        <td class="px-4 py-3 text-center">v{{ $template->version }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($template->is_published)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('admin.contracts.status_published') }}</span>
                            @elseif ($template->is_active)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('admin.contracts.status_draft') }}</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('admin.contracts.status_retired') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">{{ $assignedCounts[$template->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap space-x-3">
                            <a href="{{ route('admin.contracts.templates.signatures', $template) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('admin.contracts.signatures') }}</a>
                            <a href="{{ route('admin.contracts.templates.edit', $template) }}" class="text-xs font-medium text-primary-600 hover:underline">{{ __('common.edit') }}</a>
                            @if (! $template->is_published && $template->is_active)
                                <form method="POST" action="{{ route('admin.contracts.templates.publish', $template) }}" class="inline"
                                      onsubmit="return confirm(@js(__('admin.contracts.publish_confirm')))">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('admin.contracts.publish') }}</button>
                                </form>
                            @endif
                            @if (! $template->is_published)
                                <form method="POST" action="{{ route('admin.contracts.templates.destroy', $template) }}" class="inline"
                                      onsubmit="return confirm(@js(__('admin.contracts.delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">{{ __('common.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('admin.contracts.no_templates') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
