<?php

namespace App\Models\Referral;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `lead_events` — append-only timeline for a lead. created_at only.
 */
class LeadEvent extends Model
{
    public const CLICKED = 'CLICKED';

    public const INSTALL_STARTED = 'INSTALL_STARTED';

    public const INSTALLED = 'INSTALLED';

    public const ACTIVATED = 'ACTIVATED';

    public const REVENUE_GENERATED = 'REVENUE_GENERATED';

    public const CHURNED = 'CHURNED';

    public const TYPES = [
        self::CLICKED,
        self::INSTALL_STARTED,
        self::INSTALLED,
        self::ACTIVATED,
        self::REVENUE_GENERATED,
        self::CHURNED,
    ];

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LeadEvent $event) {
            $event->created_at ??= now();
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
