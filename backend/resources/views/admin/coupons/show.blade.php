@extends('layouts.admin')

@section('title', __('admin.coupons_section.coupon_detail') . ': ' . $coupon['code'])

@push('styles')
    @vite(['resources/js/admin/coupons-show.js'])
@endpush

@section('content')
    @php
        $scopeClasses = [
            'platform' => 'bg-blue-100 text-blue-700',
            'category' => 'bg-green-100 text-green-700',
            'vendor' => 'bg-amber-100 text-amber-700',
            'product' => 'bg-purple-100 text-purple-700',
        ];
        $scopeClass = $scopeClasses[$coupon['scope']] ?? 'bg-gray-100 text-gray-700';
    @endphp

    <div class="space-y-6" x-data="{ tab: 'analytics' }">

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <code class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ $coupon['code'] }}</code>
                    <span class="badge {{ $scopeClass }}">{{ __('admin.coupons_section.' . $coupon['scope']) }}</span>
                    @unless($isAdminManaged)
                        <span class="text-xs text-gray-400">{{ __('admin.coupons_section.vendor_owned') }} — {{ __('admin.coupons_section.view_only') }}</span>
                    @endunless
                </div>
                <h1 class="text-xl font-semibold text-gray-900 mt-1">{{ $coupon['name'] }}</h1>
            </div>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-secondary">{{ __('admin.coupons_section.back_to_list') }}</a>
        </div>

        <div class="border-b border-gray-200 flex gap-6">
            <button type="button" @click="tab = 'analytics'" :class="tab === 'analytics' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500'" class="pb-3 border-b-2 text-sm font-medium">
                {{ __('admin.coupons_section.analytics') }}
            </button>
            @if($isAdminManaged)
                <button type="button" @click="tab = 'edit'" :class="tab === 'edit' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500'" class="pb-3 border-b-2 text-sm font-medium">
                    {{ __('admin.coupons_section.edit_tab') }}
                </button>
            @endif
        </div>

        {{-- ═══════════════ Analytics tab ═══════════════ --}}
        <div x-show="tab === 'analytics'">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-gray-500">{{ __('admin.coupons_section.total_discount_granted') }}</p>
                    <p class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($totalDiscountGranted, 2) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-gray-500">{{ __('admin.coupons_section.used') }}</p>
                    <p class="text-xl font-semibold text-gray-900 mt-1">
                        {{ number_format($coupon['times_used']) }} / {{ $coupon['usage_limit_total'] ? number_format($coupon['usage_limit_total']) : '∞' }}
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                    <p class="text-xs text-gray-500">{{ __('admin.coupons_section.remaining_capacity') }}</p>
                    <p class="text-xl font-semibold text-gray-900 mt-1">
                        {{ $coupon['remaining_capacity'] !== null ? number_format($coupon['remaining_capacity']) : __('admin.coupons_section.unlimited') }}
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.daily_redemptions') }}</h2>
                </div>
                <div class="px-5 py-5">
                    <canvas id="coupon-usage-chart" height="80"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.recent_usages') }}</h2>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-5 py-2 text-start">{{ __('admin.coupons_section.customer') }}</th>
                            <th class="px-5 py-2 text-start">{{ __('admin.coupons_section.order') }}</th>
                            <th class="px-5 py-2 text-end">{{ __('admin.coupons_section.discount_amount') }}</th>
                            <th class="px-5 py-2 text-end">{{ __('admin.coupons_section.used_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentUsages as $usage)
                            <tr>
                                <td class="px-5 py-2">{{ $usage['customer'] ?? '—' }}</td>
                                <td class="px-5 py-2">{{ $usage['order_number'] ?? '—' }}</td>
                                <td class="px-5 py-2 text-end">{{ number_format($usage['discount_amount'], 2) }}</td>
                                <td class="px-5 py-2 text-end text-gray-500">{{ \Illuminate\Support\Carbon::parse($usage['used_at'])->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-6 text-center text-gray-400">{{ __('admin.coupons_section.no_usages_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════════ Edit tab (admin-managed scopes only) ═══════════════ --}}
        @if($isAdminManaged)
            <div x-show="tab === 'edit'">
                <form id="coupon-form" method="POST" action="{{ route('admin.coupons.update', $coupon['id']) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('admin.coupons._form', ['mode' => 'edit', 'coupon' => $couponModel, 'categories' => $categories, 'usageCount' => $usageCount])
                </form>
            </div>
        @endif

    </div>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            generateCodeFailed: @json(__('admin.coupons_section.generate_code_failed')),
            saving: @json(__('admin.coupons_section.saving')),
            couponSaved: @json(__('admin.coupons_section.coupon_saved')),
            saveFailed: @json(__('admin.coupons_section.save_failed')),
            networkErrorRetry: @json(__('admin.coupons_section.network_error_retry')),
            editValueWarningTitle: @json(__('admin.coupons_section.edit_value_warning_title')),
            editValueWarningBody: @json(__('admin.coupons_section.edit_value_warning_body', ['count' => ':count'])),
            proceedAnyway: @json(__('admin.coupons_section.proceed_anyway')),
            dailyRedemptions: @json(__('admin.coupons_section.daily_redemptions')),
        });
        window.COUPON_DAILY_REDEMPTIONS = @json($dailyRedemptions);
    </script>
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js', 'resources/js/admin/coupons.js'])
@endpush
