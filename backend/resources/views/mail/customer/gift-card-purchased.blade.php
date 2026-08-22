<x-mail::message>
# {{ __('mail.gift_card_purchased.heading') }}

{{ __('mail.gift_card_purchased.intro', ['name' => $giftCard->recipient_name, 'amount' => number_format($giftCard->denomination / 100, 2), 'currency' => $giftCard->currency]) }}

@if($giftCard->personal_message)
> {{ $giftCard->personal_message }}
@endif

{{ __('mail.gift_card_purchased.code_intro') }}

**{{ $giftCard->code }}**

{{ __('mail.gift_card_purchased.redeem') }}

{!! __('mail.gift_card_purchased.thanks', ['app_name' => config('app.name')]) !!}
</x-mail::message>
