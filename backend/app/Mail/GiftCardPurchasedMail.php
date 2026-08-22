<?php

namespace App\Mail;

use App\Models\GiftCard;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GiftCardPurchasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly GiftCard $giftCard)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.gift_card_purchased.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer.gift-card-purchased',
        );
    }
}
