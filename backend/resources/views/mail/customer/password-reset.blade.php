@php($isRtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.customer_password_reset.subject', ['app' => config('app.name')]) }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;">
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:{{ $isRtl ? 'right' : 'left' }};">
    <div style="background:#feee00;padding:24px 32px;text-align:center;">
        <h1 style="margin:0;color:#111827;font-size:20px;">{{ config('app.name') }}</h1>
    </div>

    <div style="padding:32px;color:#374151;line-height:1.7;">
        <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">{{ __('mail.customer_password_reset.heading') }}</h2>
        <p style="margin:0 0 12px;">
            {{ $recipientName !== '' ? __('mail.customer_password_reset.greeting', ['name' => $recipientName]) : __('mail.customer_password_reset.greeting_anonymous') }}
        </p>
        <p style="margin:0 0 12px;">{{ __('mail.customer_password_reset.intro', ['email' => $recipientEmail]) }}</p>

        <p style="text-align:center;margin:24px 0;">
            <a href="{{ $resetUrl }}" style="display:inline-block;background:#3866df;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:bold;">{{ __('mail.customer_password_reset.action') }}</a>
        </p>
        <p style="margin:0 0 16px;font-size:12px;color:#6b7280;">{{ __('mail.customer_password_reset.link_fallback') }}<br><span dir="ltr" style="word-break:break-all;">{{ $resetUrl }}</span></p>

        <p style="margin:0 0 8px;padding:10px 14px;background:#fef3c7;border-radius:6px;font-size:13px;color:#92400e;">{{ __('mail.customer_password_reset.expiry_notice', ['minutes' => $expiresInMinutes]) }}</p>
        <p style="margin:0;font-size:12px;color:#6b7280;">{{ __('mail.customer_password_reset.ignore_notice') }}</p>
    </div>
</div>
</body>
</html>
