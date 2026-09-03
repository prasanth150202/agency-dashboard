<?php

namespace App\Models\Brix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `agency_stores` in brix_superadmin — the agency-authorization
 * relationship, distinct from Shopify install/auth on `stores`. This is
 * the "Allow this store to be managed from your agency dashboard" state
 * machine: PENDING -> AUTHORIZED -> ACTIVE, or -> DISCONNECTED at any
 * point. UNIQUE(agency_id, store_id) — never insert a second row for the
 * same pair; always find-or-update.
 */
class AgencyStore extends Model
{
    protected $connection = 'agency';

    protected $table = 'agency_stores';

    public $timestamps = true;

    public const STATUSES = ['PENDING', 'AUTHORIZED', 'ACTIVE', 'DISCONNECTED'];

    protected $fillable = [
        'agency_id',
        'store_id',
        'relationship_status',
        'authorized_at',
        'activated_at',
        'disconnected_at',
    ];

    protected $casts = [
        'authorized_at' => 'datetime',
        'activated_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
