<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;margin:0;padding:24px;color:#333;line-height:1.8;">
@php($isNotice = $template->acceptance_mode === 'notice')
@foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction)
    <div dir="{{ $direction }}" style="max-width:640px;margin:0 auto 24px;background:#fff;border-radius:8px;padding:28px 32px;box-shadow:0 2px 12px rgba(0,0,0,.08);">
        <h2 style="margin:0 0 12px;font-size:20px;">{{ __('mail.seller_agreement_updated.header', [], $locale) }}</h2>
        <p>{{ __('mail.seller_agreement_updated.greeting', ['name' => $vendorName], $locale) }}</p>
        <p><strong>{{ $template->name }}</strong> · v{{ $template->version }}</p>
        @if ($isNotice)
            <p>{{ __('mail.seller_agreement_updated.notice_body', ['date' => $template->effective_at?->format('d/m/Y')], $locale) }}</p>
        @else
            <p>{{ __('mail.seller_agreement_updated.click_wrap_body', [], $locale) }}</p>
        @endif
        @if ($template->change_summary)
            <p style="background:#fffbf0;border:1px solid #f0b429;border-radius:6px;padding:12px 16px;white-space:pre-line;">{{ $template->change_summary }}</p>
        @endif
        <p><a href="{{ route('partner.legal.index') }}">{{ __('mail.seller_agreement_updated.open', [], $locale) }}</a></p>
    </div>
@endforeach
</body>
</html>
