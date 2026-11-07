@php
    $arabic = $locale === 'ar';
    $signedAt = $contract->signed_at?->copy()->setTimezone($timezone);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $arabic ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contract->template?->name ?? __('contracts.customer.download_title') }} — {{ $contract->listing_number }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; color: #111827; margin: 0; padding: 32px; line-height: 1.6; }
        .wrap { max-width: 820px; margin: 0 auto; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 12px; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 20px 0; }
        dt { color: #6b7280; font-size: 12px; }
        dd { margin: 0; font-weight: 600; font-size: 14px; word-break: break-word; }
        .content { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 14px; }
        .hash { font-family: monospace; font-size: 11px; word-break: break-all; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $contract->template?->name ?? __('contracts.customer.download_title') }}</h1>
    <p class="muted">{{ __('contracts.customer.download_version', ['version' => $contract->template_version]) }} · {{ __('contracts.customer.status_'.$contract->status) }}</p>

    <dl>
        <div><dt>{{ __('contracts.customer.col_listing') }}</dt><dd>{{ collect([$contract->listing_title, $contract->listing_number])->filter()->implode(' · ') }}</dd></div>
        <div><dt>{{ __('contracts.customer.col_purpose') }}</dt><dd>{{ __('contracts.customer.purpose_'.$contract->purpose) }}</dd></div>
        @if ($contract->classifiedCategory)
            <div><dt>{{ __('contracts.customer.download_category') }}</dt><dd>{{ $arabic ? $contract->classifiedCategory->name_ar : $contract->classifiedCategory->name_en }}</dd></div>
        @endif
        @if ($contract->country)
            <div><dt>{{ __('contracts.customer.download_country') }}</dt><dd>{{ $arabic ? $contract->country->name_ar : $contract->country->name_en }}</dd></div>
        @endif
        <div><dt>{{ __('contracts.customer.col_signer') }}</dt><dd>{{ $contract->signer_name }}</dd></div>
        <div><dt>{{ __('contracts.customer.col_signed_at') }}</dt><dd>{{ format_tz($signedAt, 'Y-m-d H:i') }}</dd></div>
        <div style="grid-column: 1 / -1"><dt>{{ __('contracts.customer.hash') }}</dt><dd class="hash">{{ $contract->rendered_content_hash }}</dd></div>
    </dl>

    <h2 style="font-size:16px">{{ __('contracts.customer.accepted_text') }}</h2>
    <div class="content" dir="{{ $contract->language_signed === 'ar' ? 'rtl' : 'ltr' }}">{!! $contract->rendered_content !!}</div>

    <p class="muted">{{ __('contracts.customer.download_timezone_note', ['timezone' => $timezone.' — '.tz_label($signedAt)]) }}</p>
    <p class="muted">{{ __('contracts.customer.download_generated', ['date' => format_tz(now()->setTimezone($timezone), 'Y-m-d H:i')]) }}</p>
</div>
</body>
</html>
