<x-mail::message>
@if($batch?->image_url)
<img src="{{ $batch->image_url }}" alt="{{ __('mail.gift_card_delivery.image_alt') }}" style="max-width: 100%; border-radius: 8px;">
@endif

@if($isGift)
# {{ __('mail.gift_card_delivery.heading_gift', ['buyer_name' => $buyerName]) }}
@else
# {{ __('mail.gift_card_delivery.heading_self') }}
@endif

{{ __('mail.gift_card_delivery.greeting', ['name' => $recipientName]) }}

@if($isGift)
{{ __('mail.gift_card_delivery.intro_gift', ['buyer_name' => $buyerName]) }}
@else
{{ __('mail.gift_card_delivery.intro_self') }}
@endif

@if($isGift && $giftMessage)
> {{ $giftMessage }}
@endif

**{{ __('mail.gift_card_delivery.code_label') }}**

<div style="font-family: monospace; font-size: 24px; letter-spacing: 2px; font-weight: bold; margin: 12px 0;">
{{ $card->code }}
</div>

**{{ __('mail.gift_card_delivery.pin_label') }}** {{ $plainPin }}

**{{ __('mail.gift_card_delivery.amount_label') }}** {{ $purchase->currency_code }} {{ $purchase->amount_paid }}

**{{ __('mail.gift_card_delivery.expiry_label') }}** {{ $card->expires_at?->format('d M Y') }}

**{{ __('mail.gift_card_delivery.how_to_use_title') }}**

{{ __('mail.gift_card_delivery.how_to_use_body') }}

{!! __('mail.gift_card_delivery.thanks', ['app_name' => config('app.name')]) !!}
</x-mail::message>
