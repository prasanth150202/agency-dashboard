<?php

namespace App\Models\Brix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `stores` in brix_superadmin — the real Shopify store record, owned by
 * exactly one agency (agency_id). This is distinct from \App\Models\Store,
 * which is this app's own local mirror (still used by the dashboard,
 * payouts, and module toggles — kept in sync at install/authorize/
 * activate/uninstall time, never a second source of truth for those).
 */
class Store extends Model
{
    protected $connection = 'agency';

    protected $table = 'stores';

    public $timestamps = false;

    public const INSTALLATION_STATUSES = ['NOT_INSTALLED', 'INSTALLING', 'INSTALLED', 'UNINSTALLED', 'ERROR'];

    public const AUTHORIZATION_STATUSES = ['NOT_AUTHORIZED', 'AUTHORIZING', 'AUTHORIZED', 'EXPIRED', 'REVOKED'];

    protected $fillable = [
        'agency_id',
        'shop_domain',
        'shopify_shop_id',
        'store_name',
        'status',
        'installation_status',
        'authorization_status',
        'plan',
        'commission_override_enabled',
        'commission_override_rate',
        'installed_at',
        'uninstalled_at',
        'last_active_at',
    ];

    protected $casts = [
        'commission_override_enabled' => 'boolean',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'last_active_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function agencyStores(): HasMany
    {
        return $this->hasMany(AgencyStore::class, 'store_id');
    }

    public function onboardings(): HasMany
    {
        return $this->hasMany(AgencyStoreOnboarding::class, 'created_store_id');
    }
}
