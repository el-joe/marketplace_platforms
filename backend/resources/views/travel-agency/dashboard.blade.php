@extends('layouts.travel-agency')

@section('title', __('travel.dashboard.title'))

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-black text-gray-900"> {{ __('travel.dashboard.welcome_message') }} {{ $agency->name }}</h1>

    {{-- Summary KPI row --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-emerald-600">{{ $packageCounts['active'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.dashboard.active_packages') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-amber-600">{{ $packageCounts['pending_review'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.dashboard.pending_review') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-blue-600">{{ $totalBookings }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.dashboard.total_bookings') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-amber-600">{{ $pendingBookings }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.dashboard.pending_documents') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-rose-600">{{ $newInquiries }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.dashboard.new_inquiries') }}</p>
        </div>
    </div>

    {{-- Revenue by currency --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 mb-3">{{ __('travel.dashboard.total_revenue') }}</p>
        @if($revenueByCurrency->isNotEmpty())
        <div class="flex flex-wrap gap-6">
            @foreach($revenueByCurrency as $currency => $amount)
            <div>
                <p class="text-2xl font-black text-purple-600">{{ number_format($amount, 0) }} <span class="text-sm font-semibold text-gray-500">{{ $currency }}</span></p>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400">—</p>
        @endif
    </div>

    {{-- Package status breakdown --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @php
        $statuses = [
            'draft'          => ['label' => __('travel.dashboard.draft'),        'color' => 'bg-gray-100 text-gray-700'],
            'pending_review' => ['label' => __('travel.dashboard.pending_review'), 'color' => 'bg-amber-100 text-amber-700'],
            'active'         => ['label' => __('travel.dashboard.active'),         'color' => 'bg-emerald-100 text-emerald-700'],
            'sold_out'       => ['label' => __('travel.dashboard.sold_out'),       'color' => 'bg-purple-100 text-purple-700'],
            'expired'        => ['label' => __('travel.dashboard.expired'),       'color' => 'bg-gray-100 text-gray-500'],
        ];
        @endphp
        @foreach($statuses as $key => $meta)
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <p class="text-3xl font-black text-gray-900">{{ $packageCounts[$key] ?? 0 }}</p>
            <span class="mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $meta['color'] }}">
                {{ $meta['label'] }}
            </span>
        </div>
        @endforeach
    </div>

    {{-- Recent bookings --}}
    @if($recentBookings->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">{{ __('travel.dashboard.recent_bookings') }}</h2>
            <a href="{{ route('travel-agency.bookings.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('travel.dashboard.view_all') }}</a>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.booking_number') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.traveler_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_title') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.total_price') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentBookings as $bk)
                @php
                $bkColors = ['pending_documents'=>'bg-amber-100 text-amber-700','confirmed'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700','completed'=>'bg-blue-100 text-blue-700'];
                $bkLabels = ['pending_documents'=>__('travel.dashboard.status_pending_documents'),'confirmed'=>__('travel.dashboard.status_confirmed'),'cancelled'=>__('travel.dashboard.status_cancelled'),'completed'=>__('travel.dashboard.status_completed')];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $bk->booking_number }}</td>
                    <td class="px-4 py-3 text-gray-800">{{ $bk->customer->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $bk->package->title_ar ?: $bk->package->title_en }}</td>
                    <td class="px-4 py-3 font-medium">{{ $bk->totalFormatted() }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $bkColors[$bk->status->value] ?? '' }}">
                            {{ $bkLabels[$bk->status->value] ?? $bk->status->label() }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Recent inquiries --}}
    @if($recentInquiries->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">{{ __('travel.dashboard.recent_inquiries') }}</h2>
            <a href="{{ route('travel-agency.inquiries.index', ['status' => 'new']) }}" class="text-sm text-blue-600 hover:underline">{{ __('travel.dashboard.view_all') }}</a>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.inquiries.contact_name') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.title') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.bookings.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentInquiries as $inq)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $inq->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $inq->package->title_ar ?: $inq->package->title_en }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $inq->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Recent packages --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">{{ __('travel.dashboard.recent_packages') }}</h2>
            <a href="{{ route('travel-agency.packages.create') }}"
               class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-400">
                + {{ __('travel.dashboard.add_package') }}
            </a>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_title') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_destination') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_departure_date') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_price') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.dashboard.data_table.package_status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentPackages as $pkg)
                @php
                $colors = ['draft'=>'bg-gray-100 text-gray-600','pending_review'=>'bg-amber-100 text-amber-700','active'=>'bg-emerald-100 text-emerald-700','sold_out'=>'bg-purple-100 text-purple-700','expired'=>'bg-gray-100 text-gray-500'];
                $labels = ['draft'=>__('travel.dashboard.status_draft'),'pending_review'=>__('travel.dashboard.status_pending_review'),'active'=>__('travel.dashboard.status_active'),'sold_out'=>__('travel.dashboard.status_sold_out'),'expired'=>__('travel.dashboard.status_expired')];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $pkg->title_ar ?: $pkg->title_en }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $pkg->destination_country }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $pkg->departure_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $pkg->priceFormatted() }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$pkg->status->value] ?? '' }}">
                            {{ $labels[$pkg->status->value] ?? $pkg->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('travel-agency.packages.show', $pkg) }}" class="text-blue-600 text-xs hover:underline">{{ __('travel.dashboard.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">{{ __('travel.dashboard.no_packages') }} <a href="{{ route('travel-agency.packages.create') }}" class="text-blue-600 hover:underline">{{ __('travel.dashboard.add_first_package') }}</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
