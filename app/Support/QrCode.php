<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QrCodeBuilder;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * Renders QR codes locally as inline SVG data URIs so signed URLs never leave
 * the server (no third-party QR service). SVG output needs no GD/Imagick.
 */
class QrCode
{
    public static function dataUri(string $data): string
    {
        try {
            $options = new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                'eccLevel' => EccLevel::M,
                'scale' => 5,
                'addQuietzone' => true,
                'quietzoneSize' => 4,
                'svgAddXmlHeader' => false,
                'drawLightModules' => false,
            ]);

            return (new QrCodeBuilder($options))->render($data);
        } catch (Throwable) {
            return '';
        }
    }
}
