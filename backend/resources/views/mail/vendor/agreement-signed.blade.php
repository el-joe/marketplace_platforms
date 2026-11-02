<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;margin:0;padding:24px;color:#333;line-height:1.8;">
@foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction)
    <div dir="{{ $direction }}" style="max-width:640px;margin:0 auto 24px;background:#fff;border-radius:8px;padding:28px 32px;box-shadow:0 2px 12px rgba(0,0,0,.08);">
        <h2 style="margin:0 0 12px;font-size:20px;">{{ __('mail.seller_agreement_signed.header', [], $locale) }}</h2>
        <p>{{ __('mail.seller_agreement_signed.intro', ['name' => $contract->signer_name], $locale) }}</p>
        <p style="background:#fffbf0;border:1px solid #f0b429;border-radius:6px;padding:12px 16px;">
            <strong>{{ $contract->contractTemplate?->name }}</strong> · v{{ $contract->template_version }}<br>
            {{ __('mail.seller_agreement_signed.signed_at', [], $locale) }}: {{ $contract->signed_at?->format('d/m/Y H:i') }}<br>
            {{ __('mail.seller_agreement_signed.reference', [], $locale) }}: <code>{{ substr($contract->rendered_content_hash, 0, 16) }}</code>
        </p>
        @if ($contract->hasSignedFile())
            <p>{{ __('mail.seller_agreement_signed.attached', [], $locale) }}</p>
        @elseif ($locale === $contract->language_signed)
            <div style="border:1px solid #eee;border-radius:6px;padding:16px;">{!! $contract->rendered_content !!}</div>
        @endif
        <p style="color:#777;font-size:13px;">{{ __('mail.seller_agreement_signed.where', [], $locale) }}</p>
    </div>
@endforeach
</body>
</html>
