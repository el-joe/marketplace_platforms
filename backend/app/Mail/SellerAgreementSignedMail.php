<?php

namespace App\Mail;

use App\Models\VendorContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Copy of the signed Seller Agreement, sent right after signing: the exact file the vendor signed,
 * or the frozen text for agreements written in the editor.
 */
class SellerAgreementSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly VendorContract $contract) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.seller_agreement_signed.subject', [], 'ar').' | '.__('mail.seller_agreement_signed.subject', [], 'en'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.vendor.agreement-signed', with: ['contract' => $this->contract]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->contract->hasSignedFile() || ! Storage::disk('private')->exists($this->contract->signed_file_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('private', $this->contract->signed_file_path)
                ->as($this->contract->signed_file_name)
                ->withMime($this->contract->signed_file_mime ?? 'application/octet-stream'),
        ];
    }
}
