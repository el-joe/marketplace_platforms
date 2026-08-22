@extends('layouts.admin')

@section('title', __('admin.travel_agency_change_requests.title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.travel_agency_change_requests.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.travel_agency_change_requests.subtitle') }}</p>
    </div>

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel_agency_change_requests.status') }}</label>
                <select name="status" class="form-input w-full text-sm" onchange="this.form.submit()">
                    <option value="">{{ __('admin.travel_agency_change_requests.any_status') }}</option>
                    @foreach(['pending', 'approved', 'rejected', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ __('admin.travel_agency_change_requests.' . $status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="w-56">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.travel_agency_change_requests.travel_agency') }}</label>
                <select name="travel_agency_id" class="form-input w-full text-sm" onchange="this.form.submit()">
                    <option value="">{{ __('admin.travel_agency_change_requests.any_travel_agency') }}</option>
                    @foreach($travelAgencies as $agency)
                        <option value="{{ $agency->id }}" @selected(request('travel_agency_id') === $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.travel.change-requests.index') }}" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.travel_agency_change_requests.reset') }}</a>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.travel_agency_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.section_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.type_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.requested_by_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.requested_at_column') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.travel_agency_change_requests.status_column') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($requests as $r)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.travel.agencies.show', $r->travel_agency_id) }}" class="text-sm font-medium text-primary-600 hover:underline">
                                    {{ $r->travelAgency?->name ?? '—' }}
                                </a>
                            </td>
                            <td class="py-3 pr-4 text-sm text-gray-700">{{ __('admin.travel_agency_change_requests.section_' . $r->section) }}</td>
                            <td class="py-3 pr-4 text-xs text-gray-500">{{ ucfirst($r->request_type) }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-700">{{ $r->requestedBy?->name ?? '—' }}</td>
                            <td class="py-3 pr-4 text-xs text-gray-500 whitespace-nowrap">{{ $r->created_at->format('M d, Y H:i') }}</td>
                            <td class="py-3 pr-4">
                                <x-badge :color="match($r->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'gray' }">
                                    {{ __('admin.travel_agency_change_requests.' . $r->status) }}
                                </x-badge>
                            </td>
                            <td class="py-3">
                                <a href="{{ route('admin.travel.change-requests.show', $r->id) }}" class="btn btn-xs btn-secondary">
                                    {{ __('admin.travel_agency_change_requests.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-gray-400">
                                {{ __('admin.travel_agency_change_requests.no_requests') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="pt-4">{{ $requests->onEachSide(1)->links() }}</div>
        @endif
    </x-card>

@endsection
