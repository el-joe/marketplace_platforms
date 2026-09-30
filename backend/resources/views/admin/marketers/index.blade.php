@extends('layouts.admin')
@section('title', __('admin.marketers.page_title'))
@section('page-title', __('admin.marketers.page_title_manage'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
<div class="space-y-4" x-data="{ showCreateModal: {{ $errors->any() ? 'true' : 'false' }} }">

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.marketers.search_placeholder') }}"
                       class="border rounded-lg px-3 py-2 text-sm w-48">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.type') }}</label>
                <select name="type" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('admin.marketers.all') }}</option>
                    @foreach($marketerJobs as $job)
                        <option value="{{ $job->key }}" {{ request('type') === $job->key ? 'selected' : '' }}>{{ app()->getLocale() === 'ar' ? $job->name_ar : $job->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.status') }}</label>
                <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('admin.marketers.all') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('admin.marketers.status_pending') }} ({{ $pendingCount }})</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('admin.marketers.status_active') }}</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('admin.marketers.status_suspended') }}</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg">{{ __('admin.marketers.search') }}</button>
            @if(request()->hasAny(['search','type','status']))
                <a href="{{ route('admin.marketers.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg">{{ __('admin.marketers.reset') }}</a>
            @endif
            <button type="button" x-on:click="showCreateModal = true"
                    class="ms-auto px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                + {{ __('admin.marketers.add_marketer') }}
            </button>
        </form>
    </div>

    {{-- Create marketer modal --}}
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         x-on:keydown.escape.window="showCreateModal = false">
        <div class="bg-white rounded-xl w-full max-w-lg p-6 space-y-4" x-on:click.outside="showCreateModal = false">
            <h3 class="font-bold text-gray-800 text-lg">{{ __('admin.marketers.add_new_marketer') }}</h3>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                    <ul class="list-disc ps-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.marketers.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.jobs') }}</label>
                    <select name="marketer_jobs[]" multiple required data-select2-init class="border rounded-lg px-3 py-2 text-sm w-full">
                        @foreach($marketerJobs as $job)
                            <option value="{{ $job->id }}" {{ collect(old('marketer_jobs', []))->contains($job->id) ? 'selected' : '' }}>
                                {{ app()->getLocale() === 'ar' ? $job->name_ar : $job->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.country') }}</label>
                    <select name="country_id" class="border rounded-lg px-3 py-2 text-sm w-full">
                        <option value="">-</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ old('country_id') === $country->id ? 'selected' : '' }}>{{ $country->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketers.password') }}</label>
                    <input type="password" name="password" required minlength="8"
                           class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" x-on:click="showCreateModal = false"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">{{ __('admin.marketers.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700">{{ __('admin.marketers.create') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if($pendingCount > 0)
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-sm text-yellow-800 font-semibold">
        ⚠️ {{ $pendingCount }} {{ __('admin.marketers.pending_approval_notice') }}
    </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.marketers.col_name') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_type') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_country') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_status') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_campaigns') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_registered_at') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('admin.marketers.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($marketers as $marketer)
                @php
                    $statusCls = [
                        'pending'    => 'bg-yellow-100 text-yellow-700',
                        'active'     => 'bg-green-100 text-green-700',
                        'suspended'  => 'bg-red-100 text-red-700',
                        'rejected'   => 'bg-gray-100 text-gray-500',
                        'blacklisted'=> 'bg-red-200 text-red-800',
                    ][$marketer->global_status?->value] ?? 'bg-gray-100 text-gray-500';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.marketers.show', $marketer) }}" class="font-semibold text-gray-900 hover:text-blue-600">{{ $marketer->name }}</a>
                        <div class="text-xs text-gray-400">{{ $marketer->email }}</div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @forelse($marketer->marketerJobs as $job)
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $job->key === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ app()->getLocale() === 'ar' ? $job->name_ar : $job->name_en }}
                            </span>
                        @empty
                            <span class="text-gray-300 text-xs">-</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $marketer->country?->name_ar ?? '-' }}</td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs {{ $statusCls }}">{{ $marketer->global_status?->value }}</span></td>
                    <td class="px-4 py-3 text-center">{{ $marketer->invitations_count }}</td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $marketer->created_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($marketer->global_status?->value === 'pending')
                            <form method="POST" action="{{ route('admin.marketers.approve', $marketer) }}" class="inline">
                                @csrf
                                <button class="text-xs px-3 py-1 bg-green-500 text-white rounded-lg hover:bg-green-600">{{ __('admin.marketers.approve') }}</button>
                            </form>
                        @elseif($marketer->global_status?->value === 'active')
                            <form method="POST" action="{{ route('admin.marketers.suspend', $marketer) }}" class="inline">
                                @csrf
                                <input type="hidden" name="reason" value="Admin action">
                                <button class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">{{ __('admin.marketers.suspend') }}</button>
                            </form>
                        @elseif($marketer->global_status?->value === 'suspended')
                            <form method="POST" action="{{ route('admin.marketers.activate', $marketer) }}" class="inline">
                                @csrf
                                <button class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">{{ __('admin.marketers.activate') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('admin.marketers.no_results') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $marketers->withQueryString()->links() }}</div>
    </div>
</div>
@endsection
