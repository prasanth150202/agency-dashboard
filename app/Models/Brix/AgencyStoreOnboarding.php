<?php

namespace App\Models\Brix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `agency_store_onboarding` in brix_superadmin — one row per "Add store"
 * attempt. `state_token` stores a SHA-256 hash only; the raw token is
 * handed to the browser once (in the wait-page URL) and never persisted.
 */
class AgencyStoreOnboarding extends Model
{
    protected $connection = 'agency';

    protected $table = 'agency_store_onboarding';

    public $timestamps = false;

    public const STATUSES = [
        'STARTED', 'AUTHORIZING', 'INSTALL_REQUIRED', 'INSTALLING',
        'AUTHORIZED', 'COMPLETED', 'FAILED', 'EXPIRED',
    ];

    protected $fillable = [
        'agency_id',
        'state_token',
        'shop_domain',
        'status',
        'failure_reason',
        'created_store_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'created_store_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        if ($rawToken === '') {
            return null;
        }

        return static::where('state_token', self::hashToken($rawToken))->first();
    }
}
