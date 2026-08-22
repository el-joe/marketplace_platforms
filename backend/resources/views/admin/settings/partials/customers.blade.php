{{-- Customers Settings Partial --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.customers_loyalty') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.loyalty_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

{{-- Tier Thresholds --}}
<x-card>
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.loyalty_tier_thresholds') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.loyalty_tier_desc') }}</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        @php
        $tiers = [
            'loyalty_tier_silver_points'   => ['label' => '🥈 ' . __('admin.settings_section.tier_silver'),   'key' => 'loyalty_tier_silver_points'],
            'loyalty_tier_gold_points'     => ['label' => '🥇 ' . __('admin.settings_section.tier_gold'),     'key' => 'loyalty_tier_gold_points'],
            'loyalty_tier_platinum_points' => ['label' => '💎 ' . __('admin.settings_section.tier_platinum'), 'key' => 'loyalty_tier_platinum_points'],
        ];
        @endphp

        @foreach($tiers as $tierKey => $tier)
            @php $setting = $settings->firstWhere('key', $tierKey); @endphp
            @if($setting)
            <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
                <label for="setting-{{ $tierKey }}" class="block text-sm font-semibold text-gray-800 mb-2">
                    {{ $tier['label'] }}
                </label>
                <input
                    type="number"
                    id="setting-{{ $tierKey }}"
                    name="settings[{{ $tierKey }}]"
                    value="{{ $setting->value }}"
                    min="0"
                    step="1"
                    class="form-input w-full text-sm"
                >
                @if($setting->description)
                    <p class="mt-1 text-xs text-gray-500">{{ $setting->description }}</p>
                @endif
            </div>
            @endif
        @endforeach

    </div>
</x-card>
