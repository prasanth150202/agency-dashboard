<?php

namespace App\Models\Referral;

use App\Models\Partners\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `referral_clicks` — one row per click on a tracking link, recorded
 * before BRIX installation is known to have happened. created_at only —
 * a click is never updated after the fact.
 */
class ReferralClick extends Model
{
    public $timestamps = false;

    /** Set on a click that arrived by scanning the link's QR code; NULL is an ordinary link click. */
    public const SOURCE_QR = 'qr';

    protected $fillable = [
        'agency_id',
        'tracking_link_id',
        'referral_code',
        'session_id',
        'shop_domain',
        'referrer',
        'source',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReferralClick $click) {
            $click->created_at ??= now();
        });
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'agency_id');
    }
}
