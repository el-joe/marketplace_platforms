<?php

namespace App\Services;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class BrandedQrService
{
    private string $logoPath;

    private int $qrSize = 400;

    public function __construct()
    {
        $this->logoPath = public_path('images/nawi-logo.png');
    }

    /** Generate a branded QR code PNG and return it as a binary string. */
    public function generate(string $url, string $label = ''): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $this->qrSize,
            margin: 20,
            foregroundColor: new Color(10, 100, 100),  // #0a6464 Nawi teal
            backgroundColor: new Color(255, 255, 255),
        );

        $logo = file_exists($this->logoPath)
            ? new Logo(path: $this->logoPath, resizeToWidth: 80)
            : null;

        $writer = new PngWriter;
        $result = $writer->write($qrCode, $logo);

        return $result->getString();
    }

    /**
     * Generate, persist to public storage, and return the storage-relative path.
     * Overwrites any previously stored QR for the same entity.
     *
     * @param  string  $storagePath  e.g. "qr/products/some-slug.png"
     */
    public function generateAndStore(string $url, string $label, string $storagePath): string
    {
        $png = $this->generate($url, $label);

        Storage::disk('public')->put($storagePath, $png);

        return $storagePath;
    }
}
