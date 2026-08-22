@extends('layouts.partner')

@section('title', __('partner.packaging_supplies.my_requests_title'))
@section('page-title', __('partner.packaging_supplies.my_requests_title'))

@section('content')

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('partner.packaging_supplies.my_requests_title') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.packaging_supplies.subtitle') }}</p>
        </div>
        <a href="{{ route('partner.packaging-supplies.request') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ __('partner.packaging_supplies.new_request') }}
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <th class="px-4 py-3 font-semibold tracking-wide">{{ __('partner.packaging_supplies.request_number') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide">{{ __('partner.packaging_supplies.items') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide">{{ __('partner.packaging_supplies.total_cost') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide">{{ __('common.status') }}</th>
                    <th class="px-4 py-3 font-semibold tracking-wide">{{ __('common.date') }}</th>
                    <th class="px-4 py-3 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($supplyRequests as $req)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $req->request_number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ __('partner.packaging_supplies.items_count', ['count' => $req->items->count()]) }}</td>
                        <td class="px-4 py-3 font-medium">{{ $req->total_cost_formatted }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $req->statusBadgeClass() }}">{{ ucfirst($req->status->value) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $req->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('partner.packaging-supplies.show-request', $req) }}"
                               class="text-primary-600 hover:underline text-xs font-medium">{{ __('common.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                            {{ __('partner.packaging_supplies.no_requests_yet') }}
                            <a href="{{ route('partner.packaging-supplies.request') }}" class="text-primary-600 hover:underline">{{ __('partner.packaging_supplies.make_first_request') }}</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $supplyRequests->links() }}</div>

@endsection
