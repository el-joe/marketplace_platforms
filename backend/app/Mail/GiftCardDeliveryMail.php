<?php

namespace App\Mail;

use App\Models\GiftCardPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// The PIN is only passed to this Mailable from a short-lived Redis cache.
// It was generated fresh at purchase time (not the original batch PIN).
// Redis key: "gift_card_pin:{gift_card_id}" with TTL 24 hours.
// After this email is sent, the plain PIN is inaccessible forever.
// If customer requests resend within 24h: retrieve from Redis.
// If resend after 24h: generate a NEW PIN, re-hash into gift_cards.pin_hash,
//   cache the new plain PIN, then send.
class GiftCardDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GiftCardPurchase $purchase, public string $plainPin)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->purchase->recipient_email],
            subject: "Your Noon Gift Card — {$this->purchase->currency_code} {$this->purchase->amount_paid}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gift_cards.delivery',
            with: [
                'purchase' => $this->purchase,
                'card' => $this->purchase->giftCard,
                'batch' => $this->purchase->batch,
                'plainPin' => $this->plainPin,
                'isGift' => $this->purchase->is_gift,
                'buyerName' => $this->purchase->buyer->name,
                'recipientName' => $this->purchase->recipient_name ?? $this->purchase->buyer->name,
                'giftMessage' => $this->purchase->gift_message,
            ],
        );
    }
}
