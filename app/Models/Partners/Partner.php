<?php

namespace App\Models\Partners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * `agencies` — the real, canonical partner record (162 rows of real
 * data). Bridged from this app's own login/tenant model via
 * Organisation::partner() (organisations.brix_agency_id -> agencies.id,
 * a real foreign key now that both tables live in the same database).
 *
 * Named Partner rather than Agency going forward — this is the model new
 * code (admin dashboard, etc.) should use. The table name itself is left
 * alone: 162 real rows, live foreign keys, no benefit to renaming it.
 */
class Partner extends Model
{
    protected $table = 'agencies';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'owner_name',
        'owner_email',
        'phone',
        'status',
        'commission_enabled',
        'commission_type',
        'commission_rate',
        'country',
    ];

    protected $casts = [
        'commission_enabled' => 'boolean',
        'commission_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function stores(): HasMany
    {
        return $this->hasMany(\App\Models\Store::class, 'agency_id');
    }

    public function storeOnboardings(): HasMany
    {
        return $this->hasMany(AgencyStoreOnboarding::class, 'agency_id');
    }

    public function agencyStores(): HasMany
    {
        return $this->hasMany(AgencyStore::class, 'agency_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(\App\Models\Payout::class, 'agency_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(\App\Models\AgencyLedger::class, 'agency_id');
    }

    public function trackingLinks(): HasMany
    {
        return $this->hasMany(\App\Models\Referral\TrackingLink::class, 'agency_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(\App\Models\Referral\Lead::class, 'agency_id');
    }

    public static function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'agency';
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }
}
