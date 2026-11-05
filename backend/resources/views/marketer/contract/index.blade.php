@extends('layouts.marketer')
@section('title', __('contracts.marketer.my_contracts'))
@section('page-title', __('contracts.marketer.my_contracts'))

@section('content')
@php($isAr = app()->getLocale() === 'ar')
<div class="space-y-6">
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end text-sm">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_type') }}</label>
            <select name="contract_type" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('contracts.marketer.all') }}</option>
                @foreach (\App\Services\Marketer\MarketerProfileContractsService::TYPES as $type)
                    <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __('contracts.marketer.type_'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_status') }}</label>
            <select name="contract_status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('contracts.marketer.all') }}</option>
                @foreach (\App\Services\Marketer\MarketerProfileContractsService::STATUSES as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('contracts.marketer.state_'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_from') }}</label>
            <input type="date" name="contract_from" value="{{ $filters['from'] }}" class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_to') }}</label>
            <input type="date" name="contract_to" value="{{ $filters['to'] }}" class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">{{ __('contracts.marketer.apply') }}</button>
    </form>

    @if ($onboarding)
        <div class="bg-white rounded-xl border border-gray-200 p-5" data-testid="onboarding-contract">
            <h3 class="font-semibold text-gray-900 mb-2">{{ __('contracts.marketer.type_onboarding') }}</h3>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span>{{ $isAr ? ($onboarding['version']->title_ar ?: $onboarding['version']->title_en) : ($onboarding['version']->title_en ?: $onboarding['version']->title_ar) }} v{{ $onboarding['version']->version_number }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $onboarding['accepted'] ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $onboarding['accepted'] ? __('contracts.marketer.accepted') : __('contracts.marketer.not_accepted') }}</span>
                <a class="text-blue-600" href="{{ route('marketer.contract.show') }}">{{ __('contracts.marketer.open') }}</a>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto" data-testid="applicable-contracts">
        <div class="px-5 py-3 border-b font-semibold text-gray-900">{{ __('contracts.marketer.applicable_title') }}</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600"><tr>
                <th class="p-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                <th class="p-3 text-start">{{ __('contracts.marketer.category') }}</th>
                <th class="p-3 text-start">{{ __('contracts.marketer.effective') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($applicable as $template)
                <tr class="border-t">
                    <td class="p-3">{{ $template->name }} <span class="text-xs text-gray-400">v{{ $template->version }}</span></td>
                    <td class="p-3 text-xs">{{ $template->category ? ($isAr ? $template->category->name_ar : $template->category->name_en) : __('contracts.marketer.category_any') }}</td>
                    <td class="p-3 text-xs">{{ $template->effective_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-6 text-center text-gray-500">{{ __('contracts.marketer.no_applicable') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-4 py-2">{{ $applicable->links() }}</div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto" data-testid="signed-contracts">
        <div class="px-5 py-3 border-b font-semibold text-gray-900">{{ __('contracts.marketer.signed_title') }}</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600"><tr>
                <th class="p-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                <th class="p-3 text-start">{{ __('contracts.marketer.col_seller') }}</th>
                <th class="p-3 text-start">{{ __('contracts.marketer.col_signed_at') }}</th>
                <th class="p-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                <th class="p-3"></th>
            </tr></thead>
            <tbody>
            @forelse ($signed as $row)
                <tr class="border-t">
                    <td class="p-3">{{ $row->template?->name }} <span class="text-xs text-gray-400">v{{ $row->template_version }}</span></td>
                    <td class="p-3">{{ $row->vendor?->store_name }}</td>
                    <td class="p-3 text-xs">{{ $row->signed_at?->format('Y-m-d') }}</td>
                    <td class="p-3 text-xs">{{ __('contracts.marketer.status_'.$row->status) }}</td>
                    <td class="p-3 text-end"><a class="text-blue-600" href="{{ route('marketer.contracts.collaboration', $row->id) }}">{{ __('contracts.marketer.view') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-gray-500">{{ __('contracts.marketer.no_signed') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-4 py-2">{{ $signed->links() }}</div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between text-sm">
        <span>{{ __('marketer.exclusive_contracts') }}: {{ $exclusive->total() }}</span>
        <a class="text-blue-600" href="{{ route('marketer.exclusive-contracts.index') }}">{{ __('contracts.marketer.open') }}</a>
    </div>
</div>
@endsection
