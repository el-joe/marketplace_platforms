@extends('layouts.travel-agency')

@section('title', __('travel.packages.title'))

@section('content')
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-gray-900">{{ __('travel.packages.title') }}</h1>
            <a href="{{ route('travel-agency.packages.create') }}"
                class="px-5 py-2.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-400 transition-colors">
                + {{ __('travel.packages.create_package') }}
            </a>
        </div>

        <form method="GET" action="{{ route('travel-agency.packages.index') }}" class="flex items-end gap-3 flex-wrap">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.packages.status') }}</label>
                <select name="status" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('travel.bookings.all_statuses') }}</option>
                    @foreach(['draft', 'pending_review', 'active', 'sold_out', 'expired'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_from') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_to') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-gray-300 text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
                {{ __('travel.bookings.filter') }}
            </button>
            <div class="ms-auto flex gap-2 text-xs">
                <a href="{{ route('travel-agency.packages.export', request()->query()) }}" class="text-blue-600 hover:underline">{{ __('travel.reports.export_csv') }}</a>
                <a href="{{ route('travel-agency.packages.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_excel') }}</a>
                <a href="{{ route('travel-agency.packages.export', array_merge(request()->query(), ['format' => 'word'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_word') }}</a>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.title') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.destination_country') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.departure_date') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.price_per_person') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.available_seats') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('travel.packages.status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($packages as $pkg)
                        @php
                            $colors = ['draft' => 'bg-gray-100 text-gray-600', 'pending_review' => 'bg-amber-100 text-amber-700', 'active' => 'bg-emerald-100 text-emerald-700', 'sold_out' => 'bg-purple-100 text-purple-700', 'expired' => 'bg-gray-100 text-gray-500'];
                            $labels = ['draft' => __('travel.packages.status_draft'), 'pending_review' => __('travel.packages.status_pending_review'), 'active' => __('travel.packages.status_active'), 'sold_out' => __('travel.packages.status_sold_out'), 'expired' => __('travel.packages.status_expired')];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $pkg->title_ar ?: $pkg->title_en }}</p>
                                <p class="text-xs text-gray-400">{{ $pkg->title_en }}</p>
                                @if($pkg->status === \App\Enums\TravelPackageStatus::Draft && $pkg->rejection_reason)
                                <p class="mt-1 text-xs text-red-600 bg-red-50 border border-red-200 rounded px-2 py-1 max-w-xs">
                                    {{ __('travel.packages.rejected') }}: {{ $pkg->rejection_reason }}
                                </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($pkg->destinationCountry)
                                    {{ $pkg->destinationCountry->flag_emoji }}
                                    {{ $pkg->destinationCountry->name_en }}{{ $pkg->destinationCity ? ', ' . $pkg->destinationCity->name_en : '' }}
                                @else
                                    {{ $pkg->destination_country }}{{ $pkg->destination_city ? ', ' . $pkg->destination_city : '' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $pkg->departure_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $pkg->priceFormatted() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $pkg->seats_booked }} / {{ $pkg->available_seats ?? '∞' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$pkg->status->value] ?? '' }}">
                                    {{ $labels[$pkg->status->value] ?? $pkg->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2 items-center flex-wrap">
                                    <a href="{{ route('travel-agency.packages.show', $pkg) }}"
                                        class="text-blue-600 text-xs hover:underline">{{ __('travel.packages.view') }}</a>

                                    @if($pkg->status === \App\Enums\TravelPackageStatus::Draft)
                                        <a href="{{ route('travel-agency.packages.edit', $pkg) }}"
                                            class="text-amber-600 text-xs hover:underline">
                                            {{ $pkg->rejection_reason ? __('travel.packages.edit_resubmit') : __('travel.packages.edit') }}
                                        </a>
                                        <form method="POST" action="{{ route('travel-agency.packages.submit', $pkg) }}">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 text-xs hover:underline">
                                                {{ __('travel.packages.submit_for_review') }}
                                            </button>
                                        </form>
                                    @elseif($pkg->status === \App\Enums\TravelPackageStatus::PendingReview)
                                        <a href="{{ route('travel-agency.packages.edit', $pkg) }}"
                                            class="text-amber-600 text-xs hover:underline">{{ __('travel.packages.edit') }}</a>
                                        <form method="POST" action="{{ route('travel-agency.packages.withdraw', $pkg) }}">
                                            @csrf
                                            <button type="submit" class="text-red-600 text-xs hover:underline">
                                                {{ __('travel.packages.withdraw') }}
                                            </button>
                                        </form>
                                        <span class="text-xs text-gray-400">{{ __('travel.packages.awaiting_approval') }}</span>
                                    @endif
                                </div>
                                @error('submission')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                {{ __('travel.packages.no_packages') }}
                                <a href="{{ route('travel-agency.packages.create') }}" class="text-blue-600 hover:underline">{{ __('travel.packages.add_first_package') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $packages->links() }}
            </div>
        </div>
    </div>
@endsection
