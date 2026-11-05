{{-- Integrations Settings Partial --}}

{{-- Security notice --}}
<div class="mb-5 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
    <x-heroicon name="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
    <p>{!! str_replace(':encrypted', '<strong>' . __('admin.settings_section.encrypted_at_rest') . '</strong>', __('admin.settings_section.security_notice')) !!}</p>
</div>

{{-- WhatsApp --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.whatsapp') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.whatsapp_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings->whereIn('key', [
            'whatsapp_provider',
            'whatsapp_meta_phone_number_id',
            'whatsapp_meta_access_token',
        ]) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

{{-- Email --}}
@php
    $mailDiagnostics = app(\App\Services\Mail\MailDiagnostics::class);
    $mailWarnings = $mailDiagnostics->warnings();
    $mailSummary = $mailDiagnostics->summary();
@endphp
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.email') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.email_desc') }}</p>
    </div>
    @if(count($mailWarnings))
        <div id="mail-warning-banner" class="mb-4 flex gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <x-heroicon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
            <div>
                <p class="font-semibold">{{ __('admin.mail_diagnostics.warning_title') }}</p>
                <ul class="mt-1 list-disc space-y-1 ps-5">
                    @foreach($mailWarnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    <p class="mb-4 text-xs text-gray-500">
        {{ __('admin.mail_diagnostics.override_note', ['mailer' => $mailSummary['mailer'], 'host' => $mailSummary['host'] ?? '—', 'from' => $mailSummary['from_address'] ?: '—']) }}
    </p>

    <div class="divide-y divide-gray-100">

        @foreach($settings->whereIn('key', [
            'mail_mailer', 'mail_host', 'mail_port', 'mail_encryption',
            'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name',
        ]) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>

    @if(auth('admin')->user()?->can('settings.edit'))
        <div class="mt-5 border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.mail_diagnostics.send_test_title') }}</h3>
            <p class="mb-3 text-xs text-gray-500">{{ __('admin.mail_diagnostics.send_test_desc') }}</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="email" id="mail-test-recipient" maxlength="255"
                    class="form-input w-full text-sm sm:max-w-sm"
                    placeholder="{{ __('admin.mail_diagnostics.send_test_placeholder') }}">
                <button type="button" id="btn-send-test-email" data-url="{{ route('admin.settings.test-email') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60">
                    <x-heroicon name="envelope" class="h-4 w-4" />
                    {{ __('admin.mail_diagnostics.send_test_button') }}
                </button>
            </div>
            <p id="mail-test-result" class="mt-2 hidden text-sm"></p>
        </div>

        @push('scripts')
            <script>
                (function () {
                    const btn = document.getElementById('btn-send-test-email');
                    if (!btn) { return; }
                    const input = document.getElementById('mail-test-recipient');
                    const result = document.getElementById('mail-test-result');
                    const label = btn.innerHTML;

                    btn.addEventListener('click', async function () {
                        btn.disabled = true;
                        btn.textContent = @json(__('admin.mail_diagnostics.sending'));
                        result.className = 'mt-2 hidden text-sm';

                        try {
                            const res = await fetch(btn.dataset.url, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ to: input.value.trim() || null }),
                            });
                            const data = await res.json().catch(() => ({}));
                            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : null;
                            result.textContent = errors || data.message || @json(__('admin.mail_diagnostics.unexpected_error'));
                            result.className = 'mt-2 text-sm ' + (res.ok && data.success ? 'text-green-700' : 'text-red-700');
                        } catch (e) {
                            result.textContent = @json(__('admin.mail_diagnostics.unexpected_error'));
                            result.className = 'mt-2 text-sm text-red-700';
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = label;
                        }
                    });
                })();
            </script>
        @endpush
    @endif
</x-card>

{{-- Currency Exchange Rates --}}
<x-card>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.exchange_rates') }}</h2>
            <p class="text-sm text-gray-500">{{ __('admin.settings_section.exchange_rates_desc') }}</p>
        </div>
        <button
            type="button"
            id="btn-refresh-rates"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
        >
            <x-heroicon name="arrow-path" class="w-3.5 h-3.5" />
            {{ __('admin.settings_section.refresh_from_api') }}
        </button>
    </div>

    <div id="rates-table-wrapper" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200">
                <tr class="text-start text-xs font-medium text-gray-500">
                    <th class="pb-2 pr-4">{{ __('admin.currency') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.code_col') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.rate_to_base_col') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.source') }}</th>
                    <th class="pb-2">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody id="rates-table-body" class="divide-y divide-gray-100">
                @foreach($currencies as $currency)
                <tr data-currency-code="{{ $currency->code }}">
                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $currency->name }}</td>
                    <td class="py-2 pr-4 font-mono text-gray-600">{{ $currency->code }}</td>
                    <td class="py-2 pr-4">
                        <input
                            type="number"
                            class="form-input w-32 text-sm rate-input"
                            value="{{ $currency->exchange_rate_to_base }}"
                            step="0.000001"
                            min="0.000001"
                            data-currency-code="{{ $currency->code }}"
                        >
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
                        <button
                            type="button"
                            class="btn-save-rate text-xs font-medium text-blue-600 hover:text-blue-800"
                            data-currency-code="{{ $currency->code }}"
                        >
                            {{ __('admin.settings_section.save_rate') }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
