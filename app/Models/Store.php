<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'attention', 'offline'];

    protected $fillable = [
        'organisation_id',
        'name',
        'shop_domain',
        'admin_url',
        'status',
        'installed_at',
        'last_active_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'last_active_at' => 'datetime',
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
}
