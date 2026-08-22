@extends('layouts.carrier')

@section('title', __('carrier.agents.create.title'))

@section('content')

<div class="mb-6">
    <a href="{{ route('carrier.agents.index') }}" class="text-indigo-600 hover:underline text-sm">← {{ __('carrier.agents.create.back_to_agents') }}</a>
    <h1 class="text-2xl font-black text-gray-900 mt-2">{{ __('carrier.agents.create.title') }}</h1>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-8 max-w-2xl">
    <form method="POST" action="{{ route('carrier.agents.store') }}" class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.full_name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-400 @enderror">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('phone') border-red-400 @enderror">
                @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.agents.vehicle_type') }}</label>
                <select name="vehicle_type" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ __('carrier.common.select') }}</option>
                    @foreach([
                        'motorcycle' => __('carrier.vehicle_types.motorcycle'),
                        'car'        => __('carrier.vehicle_types.car'),
                        'van'        => __('carrier.vehicle_types.van'),
                        'bicycle'    => __('carrier.vehicle_types.bicycle'),
                    ] as $val=>$label)
                    <option value="{{ $val }}" @selected(old('vehicle_type')===$val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('vehicle_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.agents.create.national_id') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    {{ __('carrier.agents.zone') }}
                </label>
                <select name="zone_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— {{ __('carrier.agents.no_zone') }} —</option>
                    @foreach($zones as $zone)
                        @php
                            $full = $zone->max_active_agents && $zone->agents_count >= $zone->max_active_agents;
                        @endphp
                        <option value="{{ $zone->id }}"
                                {{ old('zone_id') === $zone->id ? 'selected' : '' }}
                                {{ $full ? 'disabled' : '' }}>
                            {{ $zone->name }}
                            @if($zone->max_active_agents)
                                ({{ $zone->agents_count }}/{{ $zone->max_active_agents }})
                                {{ $full ? '— Full' : '' }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('zone_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">{{ __('carrier.agents.zone_note') }}</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.password') }}</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.confirm_password') }}</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg transition text-sm">
                {{ __('carrier.agents.create.submit') }}
            </button>
            <a href="{{ route('carrier.agents.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-6 py-2.5 rounded-lg transition text-sm">
                {{ __('carrier.common.cancel') }}
            </a>
        </div>
    </form>
</div>

@endsection
