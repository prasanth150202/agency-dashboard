<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The real, canonical `store_modules` table. Real data only ever
 * populates six modules (module_key values below) — the old local-only
 * list also had Wishlist/Trust Badges/Sticky Add to Cart, which never
 * appear in live data and are dropped here as not actually shipped
 * features.
 */
class StoreModule extends Model
{
    public $timestamps = false;

    public const MODULES = [
        'cart_drawer' => 'Cart Drawer',
        'fbt' => 'FBT',
        'coupon' => 'Coupons',
        'upsell' => 'Upsells',
        'progress_bar' => 'Progress Bar',
        'ai_brix' => 'AI BRIX',
    ];

    protected $fillable = [
        'store_id',
        'module_name',
        'module_key',
        'is_active',
        'last_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Convenience alias — old code refers to this as `module`. */
    public function getModuleAttribute(): string
    {
        return $this->module_key;
    }

    public function getLabelAttribute(): string
    {
        return self::MODULES[$this->module_key] ?? $this->module_name;
    }

    /** Convenience alias — old code refers to this as `status`. */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }
}
