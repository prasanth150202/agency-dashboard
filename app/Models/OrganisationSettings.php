<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationSettings extends Model
{
    protected $table = 'organisation_settings';

    protected $fillable = [
        'organisation_id',
        'currency',
        'available_balance',
        'lifetime_earnings',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'lifetime_earnings' => 'decimal:2',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
