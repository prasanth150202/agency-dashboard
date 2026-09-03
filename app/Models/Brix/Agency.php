<?php

namespace App\Models\Brix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * `agencies` in brix_superadmin — the real, canonical agency record.
 * Bridged from this app's own Organisation via
 * Organisation::brixAgency(). See config/database.php's 'agency'
 * connection.
 */
class Agency extends Model
{
    protected $connection = 'agency';

    protected $table = 'agencies';

    public $timestamps = false;

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
        return $this->hasMany(Store::class, 'agency_id');
    }

    public function storeOnboardings(): HasMany
    {
        return $this->hasMany(AgencyStoreOnboarding::class, 'agency_id');
    }

    public function agencyStores(): HasMany
    {
        return $this->hasMany(AgencyStore::class, 'agency_id');
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
