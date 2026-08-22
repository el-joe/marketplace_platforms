@extends('layouts.admin')

@section('title', 'Promotion Settings')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Promotion Settings</h1>
        <p class="text-sm text-gray-500 mt-0.5">Configure commissions, timing, and monthly minimums for the promotion system.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.promotion.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ─── Financial ──────────────────────────────────────────────────── --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Financial</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="promotion_fixed_admin_commission" class="block text-sm font-medium text-gray-700 mb-1">
                        Fixed Admin Commission per Request
                    </label>
                    <input type="number" min="0" step="1" name="promotion_fixed_admin_commission"
                        id="promotion_fixed_admin_commission"
                        value="{{ old('promotion_fixed_admin_commission', $settings['promotion_fixed_admin_commission']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                    <p class="mt-1 text-xs text-gray-500">SAR base units, e.g. 2 = 2 SAR</p>
                </div>
                <div>
                    <label for="promotion_fee_per_celebrity" class="block text-sm font-medium text-gray-700 mb-1">
                        Promotion Fee per Celebrity
                    </label>
                    <input type="number" min="0" step="1" name="promotion_fee_per_celebrity"
                        id="promotion_fee_per_celebrity"
                        value="{{ old('promotion_fee_per_celebrity', $settings['promotion_fee_per_celebrity']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                    <p class="mt-1 text-xs text-gray-500">9 = 9 SAR per celebrity slot</p>
                </div>
            </div>
        </div>

        {{-- ─── Timing ─────────────────────────────────────────────────────── --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Timing</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="promotion_request_acceptance_window_hours" class="block text-sm font-medium text-gray-700 mb-1">
                        Acceptance Window (hours)
                    </label>
                    <input type="number" min="1" max="168" step="1" name="promotion_request_acceptance_window_hours"
                        id="promotion_request_acceptance_window_hours"
                        value="{{ old('promotion_request_acceptance_window_hours', $settings['promotion_request_acceptance_window_hours']->value ?? 24) }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                    <p class="mt-1 text-xs text-gray-500">Default 24 hours</p>
                </div>
                <div>
                    <label for="promotion_min_stock_multiplier" class="block text-sm font-medium text-gray-700 mb-1">
                        Min Stock Multiplier
                    </label>
                    <input type="number" min="1" step="1" name="promotion_min_stock_multiplier"
                        id="promotion_min_stock_multiplier"
                        value="{{ old('promotion_min_stock_multiplier', $settings['promotion_min_stock_multiplier']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                    <p class="mt-1 text-xs text-gray-500">Minimum stock = num_celebrities &times; this value</p>
                </div>
            </div>
        </div>

        {{-- ─── Monthly Minimums per Tier ──────────────────────────────────── --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Monthly Minimums per Tier</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="promotion_tier1_monthly_minimum" class="block text-sm font-medium text-gray-700 mb-1">
                        Tier 1 (Vendor Requests)
                    </label>
                    <input type="number" min="0" step="1" name="promotion_tier1_monthly_minimum"
                        id="promotion_tier1_monthly_minimum"
                        value="{{ old('promotion_tier1_monthly_minimum', $settings['promotion_tier1_monthly_minimum']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                </div>
                <div>
                    <label for="promotion_tier2_monthly_minimum" class="block text-sm font-medium text-gray-700 mb-1">
                        Tier 2 (Open Market)
                    </label>
                    <input type="number" min="0" step="1" name="promotion_tier2_monthly_minimum"
                        id="promotion_tier2_monthly_minimum"
                        value="{{ old('promotion_tier2_monthly_minimum', $settings['promotion_tier2_monthly_minimum']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                </div>
                <div>
                    <label for="promotion_tier3_monthly_minimum" class="block text-sm font-medium text-gray-700 mb-1">
                        Tier 3 (Admin Curated)
                    </label>
                    <input type="number" min="0" step="1" name="promotion_tier3_monthly_minimum"
                        id="promotion_tier3_monthly_minimum"
                        value="{{ old('promotion_tier3_monthly_minimum', $settings['promotion_tier3_monthly_minimum']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                </div>
                <div>
                    <label for="promotion_tier4_monthly_minimum" class="block text-sm font-medium text-gray-700 mb-1">
                        Tier 4 (Nawy Now)
                    </label>
                    <input type="number" min="0" step="1" name="promotion_tier4_monthly_minimum"
                        id="promotion_tier4_monthly_minimum"
                        value="{{ old('promotion_tier4_monthly_minimum', $settings['promotion_tier4_monthly_minimum']->value ?? '') }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="check" class="w-4 h-4" />
                Save Settings
            </button>
        </div>
    </form>

@endsection
