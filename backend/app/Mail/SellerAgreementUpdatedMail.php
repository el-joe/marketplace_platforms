<?php

namespace App\Mail;

use App\Models\ClassifiedContractTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a vendor a new Seller Agreement version was published: either it must be accepted on the next login
 * (click_wrap) or it takes effect on a known date (notice).
 */
class SellerAgreementUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ClassifiedContractTemplate $template, public readonly string $vendorName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.seller_agreement_updated.subject', [], 'ar').' | '.__('mail.seller_agreement_updated.subject', [], 'en'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.vendor.agreement-updated', with: ['template' => $this->template, 'vendorName' => $this->vendorName]);
    }
}
