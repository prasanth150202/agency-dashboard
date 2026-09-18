<?php

namespace App\Models\Partners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `agency_stores` — the "authorize this store to be managed from the
 * partner dashboard" state machine: PENDING -> AUTHORIZED -> ACTIVE, or
 * -> DISCONNECTED at any point. UNIQUE(agency_id, store_id) — never
 * insert a second row for the same pair; always find-or-update.
 */
class AgencyStore extends Model
{
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
        return $this->belongsTo(Partner::class, 'agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Store::class, 'store_id');
    }
}
