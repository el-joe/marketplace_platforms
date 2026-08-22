@php
    $launched = \App\Models\Country::where('is_launched', true)->count();
    $active = \App\Models\Country::where('is_active', true)->count();
    $last = \App\Models\Country::where('is_launched', true)
        ->orderByDesc('launched_at')
        ->select(['name_en', 'iso_code_2', 'launched_at'])
        ->first();
@endphp

<x-stat-card title="{{ __('admin.dashboard.launched_countries') }}" :value="$launched" :subtitle="__('admin.dashboard.total_active', ['count' => $active])" icon="globe-alt"
    color="success">
    @if ($last)
        <p class="text-xs text-gray-500 mt-1">
            {{ __('admin.dashboard.last_label') }}: <span class="font-medium text-gray-700">{{ $last->name_en }} ({{ $last->iso_code_2 }})</span>
            — {{ $last->launched_at?->diffForHumans() ?? '—' }}
        </p>
    @endif
    <div class="mt-2">
        <a href="{{ route('admin.countries.index') }}" class="text-xs text-primary-600 hover:underline">
            {{ __('admin.dashboard.manage_countries') }}
        </a>
    </div>
</x-stat-card>
