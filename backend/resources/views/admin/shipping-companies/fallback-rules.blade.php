@extends('layouts.admin')

@section('title', __('admin.shipping_section.fallback_rules_title'))

@section('content')

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <a href="{{ route('admin.shipping-companies.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('admin.shipping_section.back_to_shipping_companies') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ __('admin.shipping_section.fallback_rules_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.shipping_section.fallback_rules_desc') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ─── Add Rule Form ───────────────────────────────────────────────────── --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">{{ __('admin.shipping_section.add_rule') }}</h2>
            <form method="POST" action="{{ route('admin.shipping-companies.fallback-rules.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">{{ __('admin.shipping_section.unserved_city_col') }}</label>
                    <select name="unserved_city_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">{{ __('admin.shipping_section.select_city') }}</option>
                        @foreach($cities as $city)
                        <option value="{{ $city->id }}">{{ $city->name_en }} ({{ $city->name_ar }})</option>
                        @endforeach
                    </select>
                    @error('unserved_city_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">{{ __('admin.shipping_section.fallback_carrier_col') }}</label>
                    <select name="fallback_shipping_company_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">{{ __('admin.shipping_section.select_company') }}</option>
                        @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('fallback_shipping_company_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">{{ __('admin.shipping_section.priority') }} <span class="text-gray-400 font-normal">{{ __('admin.shipping_section.priority_hint') }}</span></label>
                    <input type="number" name="priority" value="{{ old('priority', 1) }}" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('priority')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full btn btn-primary btn-sm">{{ __('admin.shipping_section.add_rule') }}</button>
            </form>
        </div>
    </div>

    {{-- ─── Rules Table ─────────────────────────────────────────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.unserved_city_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.fallback_carrier_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.priority_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.actions_col') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($rules as $rule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">
                                {{ $rule->unservedCity?->name_en ?? '—' }}
                                @if($rule->unservedCity?->name_ar)
                                <span class="text-xs text-gray-400 mr-1">({{ $rule->unservedCity->name_ar }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-gray-700">{{ $rule->fallbackShippingCompany?->name ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-indigo-100 text-indigo-700">
                                    {{ $rule->priority }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <form method="POST"
                                      action="{{ route('admin.shipping-companies.fallback-rules.destroy', $rule->id) }}"
                                      onsubmit="return confirm('{{ __('admin.shipping_section.delete_rule_confirm') }}')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:underline text-xs font-medium">{{ __('admin.shipping_section.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-400 text-sm">{{ __('admin.no_fallback_rules') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rules->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $rules->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
