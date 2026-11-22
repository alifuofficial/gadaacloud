<?php

namespace Workdo\EIMS\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use Throwable;

class EimsQrCodeService
{
    /**
     * Generate an SVG vector QR Code from payload string
     */
    public function generateSvg(string $data, int $size = 200): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            return $writer->writeString($data);
        } catch (Throwable $e) {
            // Fallback lightweight SVG placeholder if generation fails
            return '<svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f1f5f9"/><text x="50%" y="50%" font-size="12" text-anchor="middle" fill="#64748b">QR Unavailable</text></svg>';
        }
    }

    /**
     * Generate a Base64 data URI (data:image/svg+xml;base64,...) for <img> tags
     */
    public function generateDataUri(string $data, int $size = 200): string
    {
        $svg = $this->generateSvg($data, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Construct the official MoR / EIMS verification QR payload string
     */
    public function buildQrPayload(
        string $sellerTin,
        string $invoiceNumber,
        string $invoiceDate,
        float $totalAmount,
        float $taxAmount,
        string $irn
    ): string {
        // MoR Directive 1142 verification format:
        // Concatenated fields or verification URL with IRN and cryptographic checksum
        return implode('|', [
            'TIN:' . $sellerTin,
            'INV:' . $invoiceNumber,
            'DATE:' . $invoiceDate,
            'TOT:' . number_format($totalAmount, 2, '.', ''),
            'VAT:' . number_format($taxAmount, 2, '.', ''),
            'IRN:' . $irn
        ]);
    }
}
