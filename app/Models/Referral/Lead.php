<?php

namespace App\Models\Referral;

use App\Models\Partners\Partner;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `leads` — an agency's funnel record for one prospective/converted
 * merchant. `lead_stage` is this app's own agency-facing pipeline state;
 * `brix_status` mirrors Store's real installation/authorization state
 * once matched, and is deliberately kept separate — see the module's
 * Scenario A/B business rule: a lead must never be reused to pay
 * commission for a merchant who already had BRIX before this referral.
 */
class Lead extends Model
{
    public const STAGE_NEW = 'NEW';

    public const STAGE_CONTACTED = 'CONTACTED';

    public const STAGE_INTERESTED = 'INTERESTED';

    public const STAGE_INSTALL_STARTED = 'INSTALL_STARTED';

    public const STAGE_INSTALLED = 'INSTALLED';

    public const STAGE_ACTIVE = 'ACTIVE';

    public const STAGE_NOT_INTERESTED = 'NOT_INTERESTED';

    public const STAGE_LOST = 'LOST';

    public const STAGES = [
        self::STAGE_NEW,
        self::STAGE_CONTACTED,
        self::STAGE_INTERESTED,
        self::STAGE_INSTALL_STARTED,
        self::STAGE_INSTALLED,
        self::STAGE_ACTIVE,
        self::STAGE_NOT_INTERESTED,
        self::STAGE_LOST,
    ];

    /** Pipeline stages an agency may set by hand — its own outreach, never BRIX install truth. */
    public const MANUAL_STAGES = [
        self::STAGE_CONTACTED,
        self::STAGE_INTERESTED,
        self::STAGE_NOT_INTERESTED,
        self::STAGE_LOST,
    ];

    /** Stages from which a manual change is still allowed (before BRIX has a real install to report). */
    private const MANUALLY_EDITABLE_FROM = [
        self::STAGE_NEW,
        self::STAGE_CONTACTED,
        self::STAGE_INTERESTED,
        self::STAGE_NOT_INTERESTED,
        self::STAGE_LOST,
    ];

    public const SOURCE_MANUAL = 'MANUAL';

    public const SOURCE_REFERRAL = 'REFERRAL';

    protected $fillable = [
        'agency_id',
        'tracking_link_id',
        'store_id',
        'shop_domain',
        'company_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'website',
        'notes',
        'source',
        'created_by',
        'lead_stage',
        'brix_status',
        'brix_plan',
        'first_clicked_at',
        'contacted_at',
        'installed_at',
        'activated_at',
    ];

    protected $casts = [
        'first_clicked_at' => 'datetime',
        'contacted_at' => 'datetime',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->lead_stage ??= self::STAGE_NEW;
        });
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }

    /**
     * The stage is the agency's own until BRIX has matched a real store
     * or install to this lead; after that it follows BRIX, not the agency.
     */
    public function canChangeStageManually(): bool
    {
        return $this->store_id === null
            && $this->installed_at === null
            && $this->brix_status === null
            && in_array($this->lead_stage, self::MANUALLY_EDITABLE_FROM, true);
    }

    /**
     * Named groups of the real stages above, for a "view" tab UI (All / In
     * Review / Install Started / Installed / Active / Lost). Churned isn't a
     * lead_stage at all — Phase 3 never rewrites lead_stage on uninstall, it
     * only fires a CHURNED LeadEvent and sets brix_status — so "Churned"
     * filters on brix_status instead (see scopeView). There is no "Approved"
     * state in the tested Phase 1-3 schema; deliberately not invented here.
     */
    public const VIEW_GROUPS = [
        'in_review' => [self::STAGE_NEW, self::STAGE_CONTACTED, self::STAGE_INTERESTED],
        'install_started' => [self::STAGE_INSTALL_STARTED],
        'installed' => [self::STAGE_INSTALLED],
        'active' => [self::STAGE_ACTIVE],
        'lost' => [self::STAGE_NOT_INTERESTED, self::STAGE_LOST],
    ];

    public function scopeStage(Builder $query, ?string $stage): Builder
    {
        return in_array($stage, self::STAGES, true) ? $query->where('lead_stage', $stage) : $query;
    }

    public function scopeView(Builder $query, ?string $view): Builder
    {
        if ($view === 'churned') {
            return $query->where('brix_status', 'UNINSTALLED');
        }

        return isset(self::VIEW_GROUPS[$view]) ? $query->whereIn('lead_stage', self::VIEW_GROUPS[$view]) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('shop_domain', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('contact_email', 'like', "%{$term}%")
                ->orWhereHas('store', fn (Builder $s) => $s->where('store_name', 'like', "%{$term}%"));
        });
    }

    public function scopeForAgency(Builder $query, int $agencyId): Builder
    {
        return $query->where('agency_id', $agencyId);
    }
}
