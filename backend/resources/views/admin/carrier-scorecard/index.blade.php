@extends('layouts.admin')

@section('title', __('admin.carriers_section.scorecard_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.carriers_section.scorecard_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.carriers_section.scorecard_desc') }}</p>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <label class="text-sm text-gray-600">{{ __('admin.carriers_section.period_label') }}</label>
            <select name="period" onchange="this.form.submit()" class="input-sm">
                @foreach(['week' => __('admin.carriers_section.this_week'), 'month' => __('admin.carriers_section.this_month'), 'quarter' => __('admin.carriers_section.this_quarter'), 'year' => __('admin.carriers_section.this_year')] as $val => $label)
                    <option value="{{ $val }}" @selected($period === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ─── Fleet-wide stats ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card title="{{ __('admin.carriers_section.active_carriers_stat') }}"
            :value="number_format($stats['carrier_count'])"
            icon="trophy" iconBg="bg-primary-100 text-primary-600" />
        <x-stat-card title="{{ __('admin.carriers_section.fleet_avg_rating_stat') }}"
            :value="$stats['fleet_avg_rating'] ? $stats['fleet_avg_rating'] . ' ★' : '—'"
            icon="star" iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.carriers_section.total_claims_stat') }}"
            :value="number_format($stats['total_claims'])"
            icon="exclamation-triangle" iconBg="bg-orange-100 text-orange-600" />
        <x-stat-card title="{{ __('admin.carriers_section.total_compensated_stat') }}"
            :value="number_format($stats['total_compensated'] / 100, 2)"
            icon="banknotes" iconBg="bg-red-100 text-red-600" />
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="th">{{ __('admin.carriers_section.rank_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.carrier_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.avg_rating_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.on_time_pct_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.claims_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.approved_pct_col') }}</th>
                        <th class="th">{{ __('admin.carriers_section.compensated_col') }}</th>
                        <th class="th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($scorecards as $i => $row)
                        @php
                            $sc = $row['scorecard'];
                            $company = $row['company'];
                            $medal = [1 => '🥇', 2 => '🥈', 3 => '🥉'][$i + 1] ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="td">
                                @if($medal)
                                    <span class="text-base">{{ $medal }}</span>
                                @else
                                    <span class="text-gray-400 font-semibold">{{ $i + 1 }}</span>
                                @endif
                            </td>
                            <td class="td">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center text-xs font-semibold shrink-0">
                                        {{ Str::of($company->name)->substr(0, 1)->upper() }}
                                    </div>
                                    <span class="font-medium text-gray-900">{{ $company->name }}</span>
                                </div>
                            </td>
                            <td class="td">
                                @if($sc['avg_rating'])
                                    <div class="flex items-center gap-1">
                                        <span class="font-semibold">{{ $sc['avg_rating'] }}</span>
                                        <span class="text-yellow-400">★</span>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="td">
                                @if($sc['on_time_pct'] !== null)
                                    <div class="flex items-center gap-2 min-w-[6rem]">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full {{ $sc['on_time_pct'] >= 85 ? 'bg-green-500' : ($sc['on_time_pct'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                                 style="width: {{ min(100, $sc['on_time_pct']) }}%"></div>
                                        </div>
                                        <span class="{{ $sc['on_time_pct'] >= 85 ? 'text-green-600' : ($sc['on_time_pct'] >= 60 ? 'text-yellow-600' : 'text-red-600') }} font-medium text-xs w-10 text-end">
                                            {{ $sc['on_time_pct'] }}%
                                        </span>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="td text-gray-700">{{ $sc['total_claims'] }}</td>
                            <td class="td">
                                @if($sc['claims_approved_pct'] !== null)
                                    {{ $sc['claims_approved_pct'] }}%
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="td text-gray-700">{{ number_format($sc['total_compensated'] / 100, 2) }}</td>
                            <td class="td text-end">
                                <a href="{{ route('admin.carrier-scorecard.show', $company) }}"
                                   class="text-primary-600 hover:underline text-xs font-medium">{{ __('admin.carriers_section.details_link') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="td text-center text-gray-400 py-10">{{ __('admin.carriers_section.no_active_carriers') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
