@extends('layouts.admin')

@section('title', __('admin.shipping_section.shipping_methods_tab'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.shipping_section.shipping_methods_tab') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.shipping_section.methods_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.shipping-settings.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                <x-heroicon name="cog-6-tooth" class="w-4 h-4" />
                {{ __('admin.shipping_section.shipping_settings_title') }}
            </a>
            <a href="{{ route('admin.shipping-methods.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.shipping_section.new_shipping_method') }}
            </a>
        </div>
    </div>

    <x-card padding="none">
        <div class="overflow-x-auto">
            <table class="table-base w-full">
                <thead>
                    <tr>
                        <th>{{ __('admin.shipping_section.name_label') }}</th>
                        <th>{{ __('admin.shipping_section.code_label') }}</th>
                        <th>{{ __('admin.shipping_section.badge_col') }}</th>
                        <th class="text-center">{{ __('admin.shipping_section.express_col') }}</th>
                        <th class="text-center">{{ __('admin.shipping_section.display_priority_col') }}</th>
                        <th class="text-center">{{ __('common.active') }}</th>
                        <th class="text-center">{{ __('admin.shipping_section.categories_count_col') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shippingMethods as $method)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $method->name }}</td>
                            <td><code class="text-xs text-gray-500 font-mono">{{ $method->code }}</code></td>
                            <td>
                                @if($method->badge_label_en)
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                          style="background-color: {{ $method->badge_color_hex }}; color: {{ $method->badge_text_color_hex }};">
                                        {{ $method->badge_label_en }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('admin.shipping_section.no_badge_configured') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($method->is_express_type)
                                    <x-heroicon name="check-circle" class="w-4 h-4 text-success-600 inline" />
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center text-gray-500">{{ $method->display_priority }}</td>
                            <td class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                             {{ $method->is_active ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $method->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td class="text-center text-gray-500">{{ $method->category_shipping_methods_count }}</td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.shipping-methods.edit', $method->id) }}"
                                       class="p-1 rounded text-gray-400 hover:text-primary-600">
                                        <x-heroicon name="pencil-square" class="w-4 h-4" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.shipping-methods.destroy', $method->id) }}"
                                          onsubmit="return confirm('{{ __('admin.shipping_section.delete_shipping_method_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded text-gray-400 hover:text-danger-600">
                                            <x-heroicon name="trash" class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state title="{{ __('admin.shipping_section.no_shipping_methods_title') }}" description="{{ __('admin.shipping_section.no_shipping_methods_desc') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

@endsection
