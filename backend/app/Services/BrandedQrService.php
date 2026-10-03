<?php

namespace App\Services;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class BrandedQrService
{
    private string $logoPath;

    private string $fontPath;

    private int $qrSize = 400;

    public function __construct()
    {
        $this->logoPath = public_path('images/nawi-logo.png');
        $this->fontPath = '/usr/share/fonts/truetype/noto/NotoSansArabic-CondensedBold.ttf';
    }

    /**
     * Generate a branded QR code PNG and return it as a binary string.
     *
     * @param  string  $url  The URL the QR code encodes (scan redirect URL)
     * @param  string  $label  Text rendered below the QR (product number, vendor name, etc.)
     */
    public function generate(string $url, string $label): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $this->qrSize,
            margin: 20,
            foregroundColor: new Color(16, 22, 40),   // #101628 brand dark
            backgroundColor: new Color(255, 255, 255),
        );

        $logo = file_exists($this->logoPath)
            ? new Logo(path: $this->logoPath, resizeToWidth: 80)
            : null;

        $writer = new PngWriter;
        $result = $writer->write($qrCode, $logo);

        return $this->compositeLabel($result->getString(), $label);
    }

    /**
     * Overlay a text label below the QR image and return the final PNG bytes.
     */
    private function compositeLabel(string $qrPng, string $label): string
    {
        $qr = imagecreatefromstring($qrPng);
        $qrW = imagesx($qr);
        $qrH = imagesy($qr);

        $paddingBottom = 60;
        $canvas = imagecreatetruecolor($qrW, $qrH + $paddingBottom);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $dark = imagecolorallocate($canvas, 16, 22, 40);
        $brand = imagecolorallocate($canvas, 255, 107, 0); // accent orange

        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $qr, 0, 0, 0, 0, $qrW, $qrH);
        imagedestroy($qr);

        // Separator line
        imageline($canvas, 20, $qrH + 10, $qrW - 20, $qrH + 10, $brand);

        // Render label text (supports Arabic via FreeType)
        $fontSize = 18;
        if (file_exists($this->fontPath) && function_exists('imagettftext')) {
            // Reverse RTL label for correct GD rendering
            $displayLabel = $this->prepareText($label);
            $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $displayLabel);
            $textW = abs($bbox[2] - $bbox[0]);
            $x = max(10, ($qrW - $textW) / 2);
            $y = $qrH + 40;
            imagettftext($canvas, $fontSize, 0, (int) $x, (int) $y, $dark, $this->fontPath, $displayLabel);
        } else {
            // Fallback: built-in font (no Arabic support but guaranteed)
            $textW = strlen($label) * 9;
            $x = max(10, ($qrW - $textW) / 2);
            imagestring($canvas, 4, (int) $x, $qrH + 22, $label, $dark);
        }

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);

        return $png;
    }

    /**
     * For Arabic text, GD FreeType needs the string as-is (UTF-8).
     * This helper also handles very long labels by truncating.
     */
    private function prepareText(string $text): string
    {
        if (mb_strlen($text) > 50) {
            $text = mb_substr($text, 0, 47).'...';
        }

        return $text;
    }
}
