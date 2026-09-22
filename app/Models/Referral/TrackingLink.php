<?php

namespace App\Models\Referral;

use App\Models\Partners\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * `tracking_links` — an agency-created referral link (e.g. an Instagram
 * campaign link) that drives merchants toward installing BRIX. agency_id
 * points at `agencies.id` directly, the same convention Store/AgencyStore
 * use — see the migration for why no hard FK constraint is declared.
 */
class TrackingLink extends Model
{
    public const CHANNELS = ['Instagram', 'Website', 'WhatsApp', 'Facebook', 'Email', 'Other'];

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'agency_id',
        'name',
        'code',
        'channel',
        'campaign_name',
        'destination_url',
        'notes',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TrackingLink $link) {
            if (empty($link->code)) {
                $link->code = static::generateUniqueCode();
            }

            $link->status ??= self::STATUS_ACTIVE;
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'BRIX-'.Str::upper(Str::random(4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'agency_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('campaign_name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || ! in_array($status, self::STATUSES, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeChannel(Builder $query, ?string $channel): Builder
    {
        if (blank($channel) || ! in_array($channel, self::CHANNELS, true)) {
            return $query;
        }

        return $query->where('channel', $channel);
    }

    public function getReferralUrlAttribute(): string
    {
        return url('/ref/'.$this->code);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
