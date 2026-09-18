<?php

namespace App\Models;

use App\Models\Partners\Partner;
use App\Services\AgencyFinanceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Organisation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'website',
    ];

    private ?Partner $partnerCache = null;

    protected static function booted(): void
    {
        static::creating(function (Organisation $organisation) {
            if (empty($organisation->slug)) {
                $organisation->slug = static::uniqueSlug($organisation->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Every relation below is keyed through brix_agency_id -> agencies.id
     * (agency_id on the related table), not this row's own `id` — the
     * real schema's stores/payouts/transactions/agency_ledger all belong
     * to an `agencies` row directly, never to `organisations`.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'agency_id', 'brix_agency_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(OrganisationSettings::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'agency_id', 'brix_agency_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'agency_id', 'brix_agency_id');
    }

    public function payoutAccount(): HasOne
    {
        return $this->hasOne(PayoutAccount::class, 'agency_id', 'brix_agency_id')->where('is_default', true);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AgencyLedger::class, 'agency_id', 'brix_agency_id');
    }

    public function finance(): AgencyFinanceService
    {
        return new AgencyFinanceService($this);
    }

    /**
     * This organisation's financial currency (organisation_settings.currency),
     * falling back to INR only when settings haven't been created yet.
     * The one place Commission/Payout display code should read currency
     * from — never hardcode a symbol.
     */
    public function getCurrencyAttribute(): string
    {
        return $this->settings?->currency ?? 'INR';
    }

    /**
     * The real partner record (agencies) that this organisation's
     * login/session is bridged to. Auto-provisions one on first use
     * (find by brix_agency_id, falling back to creating a fresh
     * `agencies` row) — no manual setup step required. This is the only
     * place organisations.brix_agency_id is read or written.
     */
    public function brixAgency(): Partner
    {
        return $this->partner();
    }

    public function partner(): Partner
    {
        if ($this->partnerCache) {
            return $this->partnerCache;
        }

        if ($this->brix_agency_id) {
            $partner = Partner::find($this->brix_agency_id);

            if ($partner) {
                return $this->partnerCache = $partner;
            }
        }

        $owner = $this->users()->first();

        $partner = Partner::create([
            'name' => $this->name,
            'slug' => Partner::uniqueSlug($this->slug ?: $this->name),
            'owner_name' => $owner->name ?? $this->name,
            'owner_email' => $owner->email ?? Str::slug($this->name).'@unknown.local',
            'status' => 'active',
        ]);

        $this->forceFill(['brix_agency_id' => $partner->id])->save();

        return $this->partnerCache = $partner;
    }
}
