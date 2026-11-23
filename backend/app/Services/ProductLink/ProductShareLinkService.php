<?php

namespace App\Services\ProductLink;

use App\Models\VendorListing;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * WS12 #24: the vendor's own share link / QR code for one of its listings.
 *
 * The token is the listing id plus an HMAC (app key) over vendor + listing, so it cannot be forged or moved to
 * another vendor's product, and a normal storefront URL never qualifies for the commission discount.
 * Format: 32 hex chars (listing uuid without dashes) + 16 hex chars of signature.
 */
class ProductShareLinkService
{
    public const CHANNEL_LINK = 'link';

    public const CHANNEL_QR = 'qr';

    public function tokenFor(VendorListing $listing): string
    {
        return str_replace('-', '', (string) $listing->id).$this->signature((string) $listing->vendor_id, (string) $listing->id);
    }

    /** The listing a token belongs to, or null when the token is malformed, forged or the listing is gone. */
    public function resolve(string $token): ?VendorListing
    {
        if (! preg_match('/^[0-9a-f]{48}$/', $token)) {
            return null;
        }

        $hex = substr($token, 0, 32);
        $listingId = implode('-', [substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)]);
        $listing = VendorListing::query()->with('productVariant.product:id,slug')->find($listingId);

        if (! $listing || ! hash_equals($this->signature((string) $listing->vendor_id, (string) $listing->id), substr($token, 32))) {
            return null;
        }

        return $listing;
    }

    public function urlFor(VendorListing $listing, string $channel = self::CHANNEL_LINK): string
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/s/'.$this->tokenFor($listing);

        return $channel === self::CHANNEL_QR ? $url.'?c=qr' : $url;
    }

    /** SVG QR code (as a data URI) that opens the QR variant of the share link. */
    public function qrDataUri(VendorListing $listing): string
    {
        $qr = new QrCode(
            data: $this->urlFor($listing, self::CHANNEL_QR),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 320,
            margin: 12,
        );

        return (new SvgWriter)->write($qr)->getDataUri();
    }

    private function signature(string $vendorId, string $listingId): string
    {
        return substr(hash_hmac('sha256', 'product-link|'.$vendorId.'|'.$listingId, (string) config('app.key')), 0, 16);
    }
}
