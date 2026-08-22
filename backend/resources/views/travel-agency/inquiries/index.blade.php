@extends('layouts.travel-agency')

@section('title', __('travel.inquiries.title'))

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.inquiries.interested_clients') }}</h1>
        <p class="text-sm text-gray-400">{{ __('travel.inquiries.subtitle') }}</p>
    </div>

    {{-- Filters --}}
    @php
    $statuses = [
        ''          => __('travel.inquiries.all_statuses'),
        'new'       => __('travel.inquiries.status_new'),
        'contacted' => __('travel.inquiries.status_contacted'),
        'converted' => __('travel.inquiries.status_converted'),
        'closed'    => __('travel.inquiries.status_closed'),
    ];
    $currentStatus  = request('status', '');
    $currentPackage = request('package_id', '');
    @endphp

    <div class="flex flex-wrap gap-3 items-center">
        <div class="flex gap-2 flex-wrap">
            @foreach($statuses as $val => $label)
            <a href="{{ route('travel-agency.inquiries.index', array_filter(['status' => $val, 'package_id' => $currentPackage])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors
                      {{ $currentStatus === $val ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('travel-agency.inquiries.index') }}" class="flex gap-2 items-center flex-wrap ms-auto">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-300 outline-none">
            @if($packages->isNotEmpty())
            <select name="package_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-300 outline-none bg-white">
                <option value="">{{ __('travel.inquiries.all_packages') }}</option>
                @foreach($packages as $pkg)
                <option value="{{ $pkg->id }}" {{ $currentPackage === $pkg->id ? 'selected' : '' }}>
                    {{ $pkg->title_ar ?: $pkg->title_en }}
                </option>
                @endforeach
            </select>
            @endif
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700">
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700">
            <button type="submit" class="px-4 py-1.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                {{ __('travel.bookings.filter') }}
            </button>
            <a href="{{ route('travel-agency.inquiries.export', request()->query()) }}" class="text-xs text-blue-600 hover:underline">{{ __('travel.reports.export_csv') }}</a>
            <a href="{{ route('travel-agency.inquiries.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="text-xs text-blue-600 hover:underline">{{ __('common.export_excel') }}</a>
            <a href="{{ route('travel-agency.inquiries.export', array_merge(request()->query(), ['format' => 'word'])) }}" class="text-xs text-blue-600 hover:underline">{{ __('common.export_word') }}</a>
        </form>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm text-emerald-700">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.inquiries.contact_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.inquiries.contact_phone') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.title') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.travelers_count') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.status') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.date') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($inquiries as $inq)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ $inq->name }}</p>
                        @if($inq->email)
                        <p class="text-xs text-gray-400">{{ $inq->email }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $inq->phone }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $inq->package->title_ar ?: $inq->package->title_en }}</td>
                    <td class="px-4 py-3 text-center text-gray-700">{{ $inq->travelers_count ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $inq->statusColor() }}">
                            {{ $inq->statusLabel() }}
                        </span>
                        @if($inq->contacted_at)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $inq->contacted_at->format('d M') }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $inq->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 items-center justify-end flex-wrap">

                            @if($inq->status === \App\Enums\TravelPackageInquiryStatus::New)
                            <form method="POST" action="{{ route('travel-agency.inquiries.contacted', $inq) }}">
                                @csrf
                                <button type="submit"
                                        class="px-2.5 py-1 text-xs font-medium text-amber-700 border border-amber-300 rounded-lg hover:bg-amber-50 transition-colors">
                                    {{ __('travel.inquiries.mark_contacted') }}
                                </button>
                            </form>
                            @endif

                            @if(in_array($inq->status, [\App\Enums\TravelPackageInquiryStatus::New, \App\Enums\TravelPackageInquiryStatus::Contacted]))
                            <form method="POST" action="{{ route('travel-agency.inquiries.convert', $inq) }}">
                                @csrf
                                <button type="submit"
                                        class="px-2.5 py-1 text-xs font-medium text-emerald-700 border border-emerald-300 rounded-lg hover:bg-emerald-50 transition-colors">
                                    {{ __('travel.inquiries.convert_to_booking') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('travel-agency.inquiries.close', $inq) }}">
                                @csrf
                                <button type="submit"
                                        class="px-2.5 py-1 text-xs font-medium text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    {{ __('travel.inquiries.close_inquiry') }}
                                </button>
                            </form>
                            @endif

                            @if($inq->status === \App\Enums\TravelPackageInquiryStatus::Converted && $inq->converted_to_booking_id)
                            <a href="{{ route('travel-agency.bookings.show', $inq->converted_to_booking_id) }}"
                               class="text-xs text-blue-600 hover:underline">{{ __('travel.inquiries.view_booking') }}</a>
                            @endif

                        </div>
                        @if($inq->message)
                        <p class="mt-1.5 text-xs text-gray-400 italic max-w-xs truncate" title="{{ $inq->message }}">
                            "{{ $inq->message }}"
                        </p>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                        {{ __('travel.inquiries.no_inquiries') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $inquiries->links() }}
        </div>
    </div>
</div>
@endsection
