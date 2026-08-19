<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreModule extends Model
{
    public const MODULES = [
        'cart_drawer' => 'Cart Drawer',
        'fbt' => 'Frequently Bought Together',
        'coupon' => 'Coupon',
        'upsell' => 'Upsell',
        'progress_bar' => 'Progress Bar',
        'sticky_add_to_cart' => 'Sticky Add to Cart',
        'wishlist' => 'Wishlist',
        'trust_badges' => 'Trust Badges',
    ];

    protected $fillable = [
        'store_id',
        'module',
        'status',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getLabelAttribute(): string
    {
        return self::MODULES[$this->module] ?? $this->module;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
