@extends('layouts.partner')
@section('title', __('contracts.marketer.partner_title'))
@section('page-title', __('contracts.marketer.partner_title'))

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('partner.legal.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>

    @if ($general->isNotEmpty())
        <section class="space-y-3 rounded-2xl bg-white p-6 shadow-sm" data-testid="general-contracts">
            <h2 class="text-base font-semibold text-gray-900">{{ __('contracts.marketer.general_title') }}</h2>
            <p class="text-xs text-gray-500">{{ __('contracts.marketer.general_help') }}</p>
            @foreach ($general as $entry)
                <details class="rounded-lg border border-gray-200">
                    <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-gray-800">{{ $entry['template']->name }} <span class="text-xs font-normal text-gray-500">v{{ $entry['template']->version }}</span></summary>
                    <div class="px-4 py-3 text-sm text-gray-700 max-h-80 overflow-y-auto">{!! app()->getLocale() === 'ar' ? $entry['doc']['content_ar'] : $entry['doc']['content_en'] !!}</div>
                </details>
            @endforeach
        </section>
    @endif

    <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" data-testid="signed-collaborations">
        <h2 class="text-base font-semibold text-gray-900">{{ __('contracts.marketer.signed_by_me') }}</h2>
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.col_marketer') }}</label>
                <input type="text" name="marketer" value="{{ $search }}" class="border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.contracts.col_status') }}</label>
                <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('contracts.marketer.all') }}</option>
                    @foreach (['active', 'superseded', 'revoked'] as $value)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __('contracts.marketer.status_'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_from') }}</label>
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('contracts.marketer.filter_to') }}</label>
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2 text-sm">
            </div>
            <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">{{ __('contracts.marketer.apply') }}</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600"><tr>
                    <th class="p-3 text-start">{{ __('admin.contracts.col_name') }}</th>
                    <th class="p-3 text-start">{{ __('contracts.marketer.col_marketer') }}</th>
                    <th class="p-3 text-start">{{ __('contracts.marketer.col_campaign') }}</th>
                    <th class="p-3 text-start">{{ __('contracts.marketer.col_signed_at') }}</th>
                    <th class="p-3 text-start">{{ __('admin.contracts.col_status') }}</th>
                    <th class="p-3"></th>
                </tr></thead>
                <tbody>
                @forelse ($signed as $row)
                    <tr class="border-t">
                        <td class="p-3">{{ $row->template?->name }} <span class="text-xs text-gray-400">v{{ $row->template_version }}</span></td>
                        <td class="p-3">{{ $row->marketer?->name ?? __('contracts.marketer.open_call') }}</td>
                        <td class="p-3">{{ $row->campaign?->displayTitle() ?? '—' }}</td>
                        <td class="p-3 text-xs">{{ format_tz(vendor_tz($row->signed_at)) }}</td>
                        <td class="p-3 text-xs">{{ __('contracts.marketer.status_'.$row->status) }}</td>
                        <td class="p-3 text-end"><a class="text-blue-600" href="{{ route('partner.legal.collaborations.show', $row->id) }}">{{ __('contracts.marketer.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">{{ __('contracts.marketer.no_signed_seller') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $signed->links() }}</div>
    </section>
</div>
@endsection
