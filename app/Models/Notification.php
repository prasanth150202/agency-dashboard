<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `partner_notifications` — alerts shown to a partner (store connected,
 * payout paid, ...). Distinct from the real `notifications` table, which
 * is admin_user_id-scoped (the admin dashboard's own bell icon).
 */
class Notification extends Model
{
    use HasFactory;

    protected $table = 'partner_notifications';

    protected $fillable = [
        'organisation_id',
        'store_id',
        'type',
        'title',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }
}
