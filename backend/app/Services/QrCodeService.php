<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
use Throwable;

class QrCodeService
{
    /**
     * Render the given text as an SVG QR code returned as a data URI, suitable
     * for embedding directly in an <img src> inside the invoice template.
     *
     * Uses SVG (pure PHP) so it does not depend on the GD extension and stays
     * crisp at any print size. Returns null if rendering fails for any reason
     * so the PDF is never blocked by a QR problem.
     */
    public function svgDataUri(string $text): ?string
    {
        try {
            $options = new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                'eccLevel' => QRCode::ECC_M,
                'svgUseFillAttributes' => false,
                'scale' => 5,
                'addQuietzone' => true,
            ]);

            return (new QRCode($options))->render($text);
        } catch (Throwable) {
            return null;
        }
    }
}
