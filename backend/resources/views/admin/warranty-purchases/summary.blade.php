@extends('layouts.admin')

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endpush

@section('title', __('admin.warranty_purchases_section.summary_title'))

@section('content')

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.warranty-purchases.index') }}" class="text-gray-400 hover:text-gray-600">{{ __('admin.warranty_purchases_section.back_to_list') }}</a>
        <span class="text-gray-300">/</span>
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.warranty_purchases_section.summary_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warranty_purchases_section.summary_subtitle') }}</p>
        </div>
    </div>

    {{-- ─── Revenue by currency + status stats ─────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach($revenueByCurrency as $row)
            <x-stat-card title="{{ __('admin.warranty_purchases_section.total_revenue') }} ({{ $row->currency }})"
                :value="number_format($row->total, 2)"
                iconBg="bg-primary-100 text-primary-600" />
        @endforeach
        <x-stat-card title="{{ __('admin.warranty_purchases_section.active') }}" :value="number_format($stats['active'])"
            iconBg="bg-green-100 text-green-600" />
        <x-stat-card title="{{ __('admin.warranty_purchases_section.pending') }}" :value="number_format($stats['pending'])"
            iconBg="bg-yellow-100 text-yellow-600" />
        <x-stat-card title="{{ __('admin.warranty_purchases_section.expired') }}" :value="number_format($stats['expired'])"
            iconBg="bg-gray-100 text-gray-500" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ─── Monthly revenue chart ────────────────────────────────────── --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.monthly_revenue_chart') }}</h2>
            <canvas id="monthlyRevenueChart" height="180"></canvas>
        </div>

        {{-- ─── Revenue by plan table ────────────────────────────────────── --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('admin.warranty_purchases_section.revenue_by_plan') }}</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-100">
                        <th class="px-5 py-2">{{ __('admin.warranty_purchases_section.plan_col') }}</th>
                        <th class="px-5 py-2">{{ __('admin.warranty_purchases_section.sales_count') }}</th>
                        <th class="px-5 py-2">{{ __('admin.warranty_purchases_section.total_revenue_col') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($revenueByPlan as $row)
                        <tr>
                            <td class="px-5 py-2.5 text-gray-700">{{ $row->plan_name ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-gray-700">{{ number_format($row->sales_count) }}</td>
                            <td class="px-5 py-2.5 text-gray-700">{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-6 text-center text-gray-400">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const monthlyRevenue = @json($monthlyRevenue);
        const revenueLabel = @json(__('admin.warranty_purchases_section.total_revenue'));
        new Chart(document.getElementById('monthlyRevenueChart'), {
            type: 'bar',
            data: {
                labels: monthlyRevenue.map(r => r.month),
                datasets: [{
                    label: revenueLabel,
                    data: monthlyRevenue.map(r => r.total),
                    backgroundColor: 'rgba(99,102,241,0.6)',
                    borderColor: 'rgb(99,102,241)',
                    borderRadius: 4,
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>

@endsection
