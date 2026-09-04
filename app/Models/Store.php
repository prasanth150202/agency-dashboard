<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'attention', 'offline'];

    /**
     * BRIX's app handle in the Shopify App Store — the segment of the
     * embedded-app URL that identifies which app to open.
     */
    public const SHOPIFY_APP_HANDLE = 'cart_app-1';

    public const INSTALLATION_STATUSES = ['NOT_INSTALLED', 'INSTALLING', 'INSTALLED', 'UNINSTALLED', 'ERROR'];

    public const AUTHORIZATION_STATUSES = ['NOT_AUTHORIZED', 'AUTHORIZING', 'AUTHORIZED', 'EXPIRED', 'REVOKED'];

    protected $fillable = [
        'organisation_id',
        'name',
        'shop_domain',
        'shopify_shop_id',
        'admin_url',
        'status',
        'installation_status',
        'authorization_status',
        'agency_relationship_status',
        'plan',
        'commission_rate',
        'installed_at',
        'uninstalled_at',
        'last_active_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'last_active_at' => 'datetime',
        'commission_rate' => 'decimal:2',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(StoreModule::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
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
            $q->where('name', 'like', "%{$term}%")
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
            $q->where('module', $module)->where('status', 'active');
        });
    }

    public function getActiveModulesCountAttribute(): int
    {
        return $this->modules->where('status', 'active')->count();
    }

    public function getTotalModulesCountAttribute(): int
    {
        return count(StoreModule::MODULES);
    }

    /**
     * The store's live, public-facing Shopify storefront.
     */
    public function getStorefrontUrlAttribute(): string
    {
        return "https://{$this->shop_domain}";
    }

    /**
     * The store's handle as Shopify uses it in admin URLs — the
     * shop_domain with the ".myshopify.com" suffix stripped.
     */
    public function getShopHandleAttribute(): string
    {
        return Str::before($this->shop_domain, '.myshopify.com');
    }

    /**
     * The BRIX app opened inside this store's Shopify admin — where
     * "Preview" and "Open module" send the agency to manage BRIX itself.
     */
    public function getBrixAppUrlAttribute(): string
    {
        return self::brixAppUrlFor($this->shop_domain, '/app');
    }

    /**
     * The embedded-app launch URL for a known shop domain, for callers
     * that only have a shop_domain string (e.g. a brix_superadmin-DB
     * BrixStore row) rather than a local Store instance.
     */
    public static function brixAppUrlFor(string $shopDomain, string $path = '/app'): string
    {
        $shopHandle = Str::before($shopDomain, '.myshopify.com');

        return "https://admin.shopify.com/store/{$shopHandle}/apps/".self::SHOPIFY_APP_HANDLE.$path;
    }

    /**
     * Where "Preview"/"Open module" links should actually go. For a store
     * that predates the agency-connection feature (agency_relationship_status
     * is null — seeded/demo data, never verified through a real Shopify
     * install), brix_app_url is a purely computed guess that may not
     * exist; admin_url is the one link guaranteed to be real for those.
     */
    public function getSafeAppUrlAttribute(): string
    {
        return $this->agency_relationship_status === null ? $this->admin_url : $this->brix_app_url;
    }

    /**
     * The commission rate actually applied to this store: its own
     * override if set, otherwise the agency's default rate.
     */
    public function getEffectiveCommissionRateAttribute(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        return (float) ($this->organisation->settings?->default_commission_rate ?? 30);
    }

    public function getCommissionSourceAttribute(): string
    {
        return $this->commission_rate !== null ? Commission::SOURCE_CUSTOM : Commission::SOURCE_AGENCY_DEFAULT;
    }

    public function getCommissionSourceLabelAttribute(): string
    {
        return $this->commission_rate !== null ? 'Custom Rate' : 'Agency Rate';
    }

    /**
     * The Stores-index badge + primary action for this store's agency
     * connection state — driven by agency_relationship_status (mirrored
     * from brix_superadmin's agency_stores.relationship_status) together
     * with installation_status. See resources/views/components/store-card.blade.php.
     */
    public function getConnectionBadgeAttribute(): array
    {
        // Predates this feature (seeded/demo stores, or anything created
        // before the agency_stores relationship existed) — fall back to
        // the original status-driven badge rather than mislabelling an
        // already-working store as needing installation.
        if ($this->agency_relationship_status === null) {
            return ['label' => ucfirst($this->status), 'status' => $this->status];
        }

        if ($this->installation_status === 'UNINSTALLED') {
            return ['label' => 'Uninstalled', 'status' => 'offline'];
        }

        return match ($this->agency_relationship_status) {
            'ACTIVE' => ['label' => 'Active', 'status' => 'active'],
            'AUTHORIZED' => ['label' => 'Authorized', 'status' => 'attention'],
            'PENDING' => ['label' => 'Pending Authorization', 'status' => 'attention'],
            'DISCONNECTED' => ['label' => 'Disconnected', 'status' => 'offline'],
            default => ['label' => 'Installation Required', 'status' => 'offline'],
        };
    }

    public function getConnectionActionAttribute(): array
    {
        if ($this->agency_relationship_status === null) {
            return ['label' => 'Open Store', 'route' => null];
        }

        if ($this->installation_status === 'UNINSTALLED' || $this->agency_relationship_status === 'DISCONNECTED') {
            return ['label' => 'Reconnect', 'route' => 'stores.reconnect'];
        }

        return match ($this->agency_relationship_status) {
            'ACTIVE' => ['label' => 'Open Store', 'route' => null],
            'AUTHORIZED' => ['label' => 'Activate', 'route' => 'stores.activate'],
            'PENDING' => ['label' => 'Continue', 'route' => 'stores.authorize'],
            default => ['label' => 'Install BRIX', 'route' => null],
        };
    }
}
