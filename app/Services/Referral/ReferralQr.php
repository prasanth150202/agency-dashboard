<?php

namespace App\Services\Referral;

use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR code for an existing referral link. The code encodes the link's own
 * /ref/{code} URL — the same attribution path an ordinary click takes — plus
 * `?src=qr`, which only labels the resulting click as a scan. There is no
 * second attribution system; QR adds no tables of its own.
 */
class ReferralQr
{
    public static function url(TrackingLink $link): string
    {
        return $link->referral_url.'?src='.ReferralClick::SOURCE_QR;
    }

    /** SVG markup (vector: crisp at any print size, and needs no GD/Imagick). */
    public static function svg(TrackingLink $link, int $size = 320): string
    {
        return (new Builder(writer: new SvgWriter(), data: self::url($link), size: $size, margin: 8))
            ->build()
            ->getString();
    }
}
