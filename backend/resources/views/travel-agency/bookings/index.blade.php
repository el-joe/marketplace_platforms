@extends('layouts.travel-agency')

@section('title', __('travel.bookings.title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.bookings.title') }}</h1>
        <a href="{{ route('travel-agency.bookings.create') }}"
           class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-500">
            + {{ __('travel.bookings.create_booking') }}
        </a>
    </div>

    {{-- Status filter tabs --}}
    @php
    $statuses = [
        ''                   => __('travel.bookings.all_statuses'),
        'pending_documents'  => __('travel.bookings.status_pending_documents'),
        'confirmed'          => __('travel.bookings.status_confirmed'),
        'cancelled'          => __('travel.bookings.status_cancelled'),
        'completed'          => __('travel.bookings.status_completed'),
    ];
    $current = request('status', '');
    @endphp
    <div class="flex gap-2 flex-wrap">
        @foreach($statuses as $val => $label)
        <a href="{{ route('travel-agency.bookings.index', array_filter(['status' => $val ?: null, 'date_from' => request('date_from'), 'date_to' => request('date_to')])) }}"
           class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors
                  {{ $current === $val ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Search + date range filter --}}
    <form method="GET" action="{{ route('travel-agency.bookings.index') }}" class="flex items-end gap-3 flex-wrap">
        @if($current)
        <input type="hidden" name="status" value="{{ $current }}">
        @endif
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="{{ __('travel.bookings.booking_number') }}"
                   class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.packages.title') }}</label>
            <select name="package_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.inquiries.all_packages') }}</option>
                @foreach($packages as $pkg)
                <option value="{{ $pkg->id }}" {{ request('package_id') === $pkg->id ? 'selected' : '' }}>
                    {{ $pkg->title_ar ?: $pkg->title_en }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
            {{ __('travel.bookings.filter') }}
        </button>
        <div class="ms-auto flex gap-2 text-xs">
            <a href="{{ route('travel-agency.bookings.export', request()->query()) }}" class="text-blue-600 hover:underline">{{ __('travel.reports.export_csv') }}</a>
            <a href="{{ route('travel-agency.bookings.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_excel') }}</a>
            <a href="{{ route('travel-agency.bookings.export', array_merge(request()->query(), ['format' => 'word'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_word') }}</a>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.booking_number') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.traveler_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.title') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.travelers_count') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.total_price') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.status') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.date') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($bookings as $bk)
                @php
                $colors = ['pending_documents'=>'bg-amber-100 text-amber-700','confirmed'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700','completed'=>'bg-blue-100 text-blue-700'];
                $labels = ['pending_documents'=>__('travel.bookings.status_pending_documents'),'confirmed'=>__('travel.bookings.status_confirmed'),'cancelled'=>__('travel.bookings.status_cancelled'),'completed'=>__('travel.bookings.status_completed')];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $bk->booking_number }}</td>
                    <td class="px-4 py-3 text-gray-800">{{ $bk->customer->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $bk->package->title_ar ?: $bk->package->title_en }}</td>
                    <td class="px-4 py-3 text-center text-gray-700">{{ $bk->travelers_count }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $bk->totalFormatted() }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$bk->status->value] ?? '' }}">
                            {{ $labels[$bk->status->value] ?? $bk->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $bk->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('travel-agency.bookings.show', $bk) }}" class="text-blue-600 text-xs hover:underline">{{ __('travel.bookings.details') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">{{ __('travel.bookings.no_bookings') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
