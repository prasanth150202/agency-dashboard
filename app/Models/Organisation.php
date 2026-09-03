<?php

namespace App\Models;

use App\Models\Brix\Agency as BrixAgency;
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

    private ?BrixAgency $brixAgencyCache = null;

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

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
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
        return $this->hasMany(Payout::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function payoutAccount(): HasOne
    {
        return $this->hasOne(PayoutAccount::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AgencyLedger::class);
    }

    public function finance(): AgencyFinanceService
    {
        return new AgencyFinanceService($this);
    }

    /**
     * The real agency record in brix_superadmin that this organisation's
     * login/session is bridged to. Auto-provisions one on first use
     * (findOrCreate by brix_agency_id, falling back to creating a fresh
     * `agencies` row) — no manual setup step required. This is the only
     * place organisations.brix_agency_id is read or written.
     */
    public function brixAgency(): BrixAgency
    {
        if ($this->brixAgencyCache) {
            return $this->brixAgencyCache;
        }

        if ($this->brix_agency_id) {
            $agency = BrixAgency::find($this->brix_agency_id);

            if ($agency) {
                return $this->brixAgencyCache = $agency;
            }
        }

        $owner = $this->users()->first();

        $agency = BrixAgency::create([
            'name' => $this->name,
            'slug' => BrixAgency::uniqueSlug($this->slug ?: $this->name),
            'owner_name' => $owner->name ?? $this->name,
            'owner_email' => $owner->email ?? Str::slug($this->name).'@unknown.local',
            'status' => 'active',
        ]);

        $this->forceFill(['brix_agency_id' => $agency->id])->save();

        return $this->brixAgencyCache = $agency;
    }
}
