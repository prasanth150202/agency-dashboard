<?php

namespace App\Models;

use App\Models\Partners\AgencyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * The real, canonical `stores` table (2,502 rows) — owned by exactly one
 * agency (agency_id). Since the single-database merge there is no longer
 * a separate local mirror: this class reads/writes the same row an
 * admin dashboard would see.
 */
class Store extends Model
{
    public const STATUSES = ['active', 'attention', 'trial', 'offline'];

    /**
     * BRIX's app handle in the Shopify App Store — the segment of the
     * embedded-app URL that identifies which app to open.
     */
    public const SHOPIFY_APP_HANDLE = 'cart_app-1';

    public const INSTALLATION_STATUSES = ['NOT_INSTALLED', 'INSTALLING', 'INSTALLED', 'UNINSTALLED', 'ERROR'];

    public const AUTHORIZATION_STATUSES = ['NOT_AUTHORIZED', 'AUTHORIZING', 'AUTHORIZED', 'EXPIRED', 'REVOKED'];

    /**
     * Display labels for the BRIX Shopify app's real plan_key values
     * (Cart_ninja_combo1's app/config/plans.js — the single source of
     * truth for pricing/plans). Used to translate a mirrored plan_key
     * into this column's free-text format.
     */
    public const PLAN_LABELS = [
        'free' => 'Free',
        'starter' => 'Starter',
        'pro' => 'Pro',
    ];

    protected $fillable = [
        'agency_id',
        'store_name',
        'shop_domain',
        'shopify_shop_id',
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
        'commission_override_rate' => 'decimal:2',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'last_active_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * This app's own login/tenant record for the store's owning agency —
     * bridged via organisations.brix_agency_id, since stores belong to
     * `agencies` directly (agency_id), not to `organisations`.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'agency_id', 'brix_agency_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Partners\Partner::class, 'agency_id');
    }

    /**
     * The authorization relationship between this store and its owning
     * agency (PENDING/AUTHORIZED/ACTIVE/DISCONNECTED) — replaces the old
     * mirrored agency_relationship_status column; computed live now that
     * both tables are in the same database.
     */
    public function agencyStore(): HasOne
    {
        return $this->hasOne(AgencyStore::class, 'store_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(StoreModule::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'store_id');
    }

    public function connectionAttempts(): HasMany
    {
        return $this->hasMany(StoreConnectionAttempt::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('store_name', 'like', "%{$term}%")
                ->orWhere('shop_domain', 'like', "%{$term}%");
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || ! in_array($status, self::STATUSES, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeModule(Builder $query, ?string $module): Builder
    {
        if (blank($module) || ! array_key_exists($module, StoreModule::MODULES)) {
            return $query;
        }

        return $query->whereHas('modules', function (Builder $q) use ($module) {
            $q->where('module_key', $module)->where('is_active', true);
        });
    }

    public function getActiveModulesCountAttribute(): int
    {
        return $this->modules->where('is_active', true)->count();
    }

    public function getTotalModulesCountAttribute(): int
    {
        return count(StoreModule::MODULES);
    }

    /** Convenience alias — the real column is store_name. */
    public function getNameAttribute(): string
    {
        return $this->store_name;
    }

    /**
     * No admin_url column on the real table — this was always a computed
     * "https://{shop}/admin" guess, never a stored value.
     */
    public function getAdminUrlAttribute(): string
    {
        return "https://{$this->shop_domain}/admin";
    }

    public function getStorefrontUrlAttribute(): string
    {
        return "https://{$this->shop_domain}";
    }

    public function getShopHandleAttribute(): string
    {
        return Str::before($this->shop_domain, '.myshopify.com');
    }

    public function getBrixAppUrlAttribute(): string
    {
        return self::brixAppUrlFor($this->shop_domain, '/app');
    }

    public static function brixAppUrlFor(string $shopDomain, string $path = '/app'): string
    {
        $shopHandle = Str::before($shopDomain, '.myshopify.com');

        return "https://admin.shopify.com/store/{$shopHandle}/apps/".self::SHOPIFY_APP_HANDLE.$path;
    }

    /**
     * Where "Preview"/"Open module" links should actually go. A store
     * with no agency_stores row at all (never went through the
     * authorization flow — seeded/demo data) falls back to admin_url,
     * the one link guaranteed to make sense for those.
     */
    public function getSafeAppUrlAttribute(): string
    {
        return $this->agencyStore === null ? $this->admin_url : $this->brix_app_url;
    }

    /**
     * The commission rate actually applied to this store: its own
     * override if enabled, otherwise the owning agency's default rate.
     */
    public function getEffectiveCommissionRateAttribute(): float
    {
        if ($this->commission_override_enabled && $this->commission_override_rate !== null) {
            return (float) $this->commission_override_rate;
        }

        return (float) ($this->agency?->commission_rate ?? 30);
    }

    public function getCommissionSourceAttribute(): string
    {
        return $this->commission_override_enabled ? Commission::SOURCE_CUSTOM : Commission::SOURCE_AGENCY_DEFAULT;
    }

    public function getCommissionSourceLabelAttribute(): string
    {
        return $this->commission_override_enabled ? 'Custom Rate' : 'Agency Rate';
    }

    /**
     * The Stores-index badge + primary action for this store's agency
     * connection state — driven by the live agencyStore relationship
     * together with installation_status.
     */
    public function getConnectionBadgeAttribute(): array
    {
        $relationshipStatus = $this->agencyStore?->relationship_status;

        // No agency_stores row at all — predates this feature
        // (seeded/demo stores, or anything created before the agency
        // authorization flow existed). Fall back to the original
        // status-driven badge rather than mislabelling an already-working
        // store as needing installation.
        if ($relationshipStatus === null) {
            return ['label' => ucfirst($this->status), 'status' => $this->status];
        }

        if ($this->installation_status === 'UNINSTALLED') {
            return ['label' => 'Uninstalled', 'status' => 'offline'];
        }

        return match ($relationshipStatus) {
            'ACTIVE' => ['label' => 'Active', 'status' => 'active'],
            'AUTHORIZED' => ['label' => 'Authorized', 'status' => 'attention'],
            'PENDING' => ['label' => 'Pending Authorization', 'status' => 'attention'],
            'DISCONNECTED' => ['label' => 'Disconnected', 'status' => 'offline'],
            default => ['label' => 'Installation Required', 'status' => 'offline'],
        };
    }

    public function getConnectionActionAttribute(): array
    {
        $relationshipStatus = $this->agencyStore?->relationship_status;

        if ($relationshipStatus === null) {
            return ['label' => 'Open Store', 'route' => null];
        }

        if ($this->installation_status === 'UNINSTALLED' || $relationshipStatus === 'DISCONNECTED') {
            return ['label' => 'Reconnect', 'route' => 'stores.reconnect'];
        }

        return match ($relationshipStatus) {
            'ACTIVE' => ['label' => 'Open Store', 'route' => null],
            'AUTHORIZED' => ['label' => 'Activate', 'route' => 'stores.activate'],
            'PENDING' => ['label' => 'Continue', 'route' => 'stores.authorize'],
            default => ['label' => 'Install BRIX', 'route' => null],
        };
    }
}
