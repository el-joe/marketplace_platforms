@extends('layouts.admin')
@section('title', __('admin.contracts.signatures_title', ['name' => $contractTemplate->name, 'version' => $contractTemplate->version]))

@section('content')
<div class="space-y-5 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.contracts.signatures_title', ['name' => $contractTemplate->name, 'version' => $contractTemplate->version]) }}</h1>
            <p class="text-sm text-gray-500">{{ __('contracts.marketer.signatures_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.contracts.templates.index', ['scope' => $contractTemplate->category_scope]) }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
    </div>

    <div class="flex gap-2 text-sm">
        @foreach (['all', 'active', 'superseded', 'revoked'] as $value)
            <a href="{{ route('admin.contracts.templates.signatures', ['contractTemplate' => $contractTemplate, 'status' => $value]) }}"
               class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ __('contracts.marketer.status_'.$value) }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_seller') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_marketer') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_campaign') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_version') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('contracts.marketer.col_signed_at') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.contracts.col_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($signatures as $signature)
                    <tr>
                        <td class="px-4 py-3">{{ $signature->vendor?->store_name }}</td>
                        <td class="px-4 py-3">{{ $signature->marketer?->name ?? __('contracts.marketer.open_call') }}</td>
                        <td class="px-4 py-3">{{ $signature->campaign?->title ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">v{{ $signature->template_version }}</td>
                        <td class="px-4 py-3">{{ $signature->signed_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-center">{{ __('contracts.marketer.status_'.$signature->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('contracts.marketer.no_signatures') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $signatures->links() }}</div>
</div>
@endsection
