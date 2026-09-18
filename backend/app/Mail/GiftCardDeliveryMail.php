<?php

namespace App\Mail;

use App\Models\GiftCardPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// The plain PIN is minted fresh by GiftCardPurchaseService::deliverCard() on
// every delivery (including resends) and never persisted anywhere — only its
// hash is stored on the GiftCard. Once this email is sent, the plaintext is
// gone; a resend mints and emails a brand-new PIN rather than recovering one.
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
