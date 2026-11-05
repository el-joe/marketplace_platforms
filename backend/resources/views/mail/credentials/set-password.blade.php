{{-- Bilingual (Arabic + English): the recipient's language is not known when staff trigger the mail. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __("mail.credentials.kinds.{$kind}.subject", $placeholders, 'ar') }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;">
<div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <div style="background:#4f46e5;padding:28px 32px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:20px;">{{ config('app.name') }}</h1>
    </div>

    <div style="padding:32px;color:#374151;line-height:1.7;">
        @foreach (['ar', 'en'] as $loc)
            <div dir="{{ $loc === 'ar' ? 'rtl' : 'ltr' }}" style="text-align:{{ $loc === 'ar' ? 'right' : 'left' }};{{ $loop->last ? '' : 'padding-bottom:24px;margin-bottom:24px;border-bottom:1px solid #e5e7eb;' }}">
                <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">{{ __("mail.credentials.kinds.{$kind}.heading", $placeholders, $loc) }}</h2>
                <p style="margin:0 0 12px;">{{ __('mail.credentials.greeting', $placeholders, $loc) }}</p>
                <p style="margin:0 0 12px;">{{ __("mail.credentials.kinds.{$kind}.intro", $placeholders, $loc) }}</p>
                <p style="margin:0 0 12px;"><strong>{{ __('mail.credentials.email_label', [], $loc) }}:</strong> {{ $recipientEmail }}</p>

                @if ($setPasswordUrl)
                    <p style="text-align:center;margin:20px 0;">
                        <a href="{{ $setPasswordUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:15px;">{{ __("mail.credentials.kinds.{$kind}.action", [], $loc) }}</a>
                    </p>
                    <p style="margin:0 0 12px;font-size:12px;color:#6b7280;">{{ __('mail.credentials.link_fallback', [], $loc) }}<br><span dir="ltr" style="word-break:break-all;">{{ $setPasswordUrl }}</span></p>
                @else
                    <p style="margin:0 0 12px;">{{ __('mail.credentials.code_intro', [], $loc) }}</p>
                    <p dir="ltr" style="background:#f3f4f6;border-radius:8px;padding:12px 16px;font-family:monospace;word-break:break-all;">{{ $resetCode }}</p>
                @endif

                @if ($loginUrl)
                    <p style="margin:0 0 12px;font-size:13px;">{{ __('mail.credentials.login_label', [], $loc) }}: <span dir="ltr">{{ $loginUrl }}</span></p>
                @endif

                <p style="margin:0 0 8px;padding:10px 14px;background:#fef3c7;border-radius:6px;font-size:13px;color:#92400e;">{{ __('mail.credentials.expiry_notice', $placeholders, $loc) }}</p>
                <p style="margin:0;font-size:12px;color:#6b7280;">{{ __('mail.credentials.ignore_notice', [], $loc) }}</p>
            </div>
        @endforeach
    </div>
</div>
</body>
</html>
