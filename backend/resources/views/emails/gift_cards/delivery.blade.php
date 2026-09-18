<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isGift ? __('mail.gift_card_delivery.heading_gift', ['buyer_name' => $buyerName]) : __('mail.gift_card_delivery.heading_self') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: #f0b429;
            padding: 32px 40px;
            text-align: center;
        }

        .header img {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .header h1 {
            color: #1a1a1a;
            font-size: 22px;
            margin: 0;
        }

        .body {
            padding: 36px 40px;
            color: #333;
            font-size: 15px;
            line-height: 1.8;
        }

        .gift-message {
            background: #f9f9f9;
            border-right: 4px solid #f0b429;
            padding: 12px 16px;
            border-radius: 6px;
            font-style: italic;
            color: #555;
            margin: 20px 0;
        }

        .code-box {
            background: #fffbf0;
            border: 1px solid #f0b429;
            border-radius: 6px;
            padding: 20px 24px;
            margin: 20px 0;
        }

        .code-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .code-value {
            font-family: monospace;
            font-size: 24px;
            letter-spacing: 2px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            font-size: 14px;
        }

        .detail-label {
            color: #6b7280;
        }

        .detail-value {
            font-weight: 700;
            color: #1a1a1a;
        }

        .how-to-use {
            background: #f9f9f9;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
            font-size: 14px;
        }

        .footer {
            background: #f9f9f9;
            padding: 24px 40px;
            text-align: center;
            font-size: 13px;
            color: #999;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="header">
            @if($batch?->image_url)
                <img src="{{ $batch->image_url }}" alt="{{ __('mail.gift_card_delivery.image_alt') }}">
            @endif
            @if($isGift)
                <h1>{{ __('mail.gift_card_delivery.heading_gift', ['buyer_name' => $buyerName]) }}</h1>
            @else
                <h1>{{ __('mail.gift_card_delivery.heading_self') }}</h1>
            @endif
        </div>

        <div class="body">
            <p>{{ __('mail.gift_card_delivery.greeting', ['name' => $recipientName]) }}</p>

            @if($isGift)
                <p>{{ __('mail.gift_card_delivery.intro_gift', ['buyer_name' => $buyerName]) }}</p>
            @else
                <p>{{ __('mail.gift_card_delivery.intro_self') }}</p>
            @endif

            @if($isGift && $giftMessage)
                <div class="gift-message">{{ $giftMessage }}</div>
            @endif

            <div class="code-box">
                <div class="code-label">{{ __('mail.gift_card_delivery.code_label') }}</div>
                <div class="code-value">{{ $card->code }}</div>

                <div class="detail-row">
                    <span class="detail-label">{{ __('mail.gift_card_delivery.pin_label') }}</span>
                    <span class="detail-value">{{ $plainPin }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">{{ __('mail.gift_card_delivery.amount_label') }}</span>
                    <span class="detail-value">{{ $purchase->currency_code }} {{ $purchase->amount_paid }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">{{ __('mail.gift_card_delivery.expiry_label') }}</span>
                    <span class="detail-value">{{ $card->expires_at?->format('d M Y') }}</span>
                </div>
            </div>

            <div class="how-to-use">
                <strong>{{ __('mail.gift_card_delivery.how_to_use_title') }}</strong><br>
                {{ __('mail.gift_card_delivery.how_to_use_body') }}
            </div>

            <p style="margin-top:24px;">{!! __('mail.gift_card_delivery.thanks', ['app_name' => config('app.name')]) !!}</p>
        </div>

        <div class="footer">
            {!! __('mail.gift_card_delivery.footer_copyright', ['year' => date('Y')]) !!}
        </div>
    </div>
</body>

</html>
