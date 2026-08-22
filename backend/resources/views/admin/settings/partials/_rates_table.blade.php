{{-- Partial: AJAX-refreshable rates table tbody --}}
@foreach($currencies as $currency)
    <tr data-currency-code="{{ $currency->code }}">
        <td class="py-2 pr-4 font-medium text-gray-900">{{ $currency->name }}</td>
        <td class="py-2 pr-4 font-mono text-gray-600">{{ $currency->code }}</td>
        <td class="py-2 pr-4">
            <input type="number" class="form-input w-32 text-sm rate-input" value="{{ $currency->exchange_rate_to_base }}"
                step="0.000001" min="0.000001" data-currency-code="{{ $currency->code }}">
        </td>
        <td class="py-2 pr-4 text-xs text-gray-500">
            @if($currency->is_manually_overridden)
                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs bg-amber-100 text-amber-700">{{ __('admin.settings_section.manual') }}</span>
            @else
                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs bg-green-100 text-green-700">{{ __('admin.settings_section.api') }}</span>
            @endif
            @if($currency->rate_updated_at)
                <br><span class="text-gray-400">{{ $currency->rate_updated_at->diffForHumans() }}</span>
            @endif
        </td>
        <td class="py-2">
            <button type="button" class="btn-save-rate text-xs font-medium text-blue-600 hover:text-blue-800"
                data-currency-code="{{ $currency->code }}">
                {{ __('admin.settings_section.save_rate') }}
            </button>
        </td>
    </tr>
@endforeach