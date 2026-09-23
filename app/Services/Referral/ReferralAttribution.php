<?php

namespace App\Services\Referral;

use App\Models\Partners\ActivityLog;
use App\Models\Partners\AgencyStore;
use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Brix\StoreInstallationSync;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3 referral attribution. Turns a referral click into a Lead only
 * when BRIX's own install callback proves the shop is genuinely new.
 *
 * The install callback carries nothing but shop_domain, so the link
 * between click and install is a shop_domain the merchant typed on the
 * signed /ref store step (bindShop) — stored on the referral_clicks row,
 * never in a cookie or a browser-supplied agency/store id.
 *
 * Every public entry point is fail-safe: a referral bug must never break
 * the Shopify install/authorize/activate/uninstall flows it hooks into.
 */
class ReferralAttribution
{
    public const CREATED = 'lead_created';

    public const NO_REFERRAL = 'no_referral';

    public const INVALID_SHOP = 'invalid_shop';

    public const INACTIVE_REFERRAL = 'inactive_referral';

    public const EXISTING_LEAD = 'existing_lead';

    public const EXISTING_ATTRIBUTION = 'existing_attribution';

    public const EXISTING_CUSTOMER = 'existing_customer';

    public const CONFLICTING_ONBOARDING = 'conflicting_onboarding';

    public const UNDETERMINED = 'undetermined';

    private const SHOP_REGEX = '/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/';

    private const EARLY_STAGES = [
        Lead::STAGE_NEW,
        Lead::STAGE_CONTACTED,
        Lead::STAGE_INTERESTED,
        Lead::STAGE_INSTALL_STARTED,
    ];

    public static function normalizeShopDomain(?string $domain): ?string
    {
        $domain = Str::of((string) $domain)
            ->trim()
            ->lower()
            ->replaceMatches('/^[a-z]+:\/\//', '')
            ->trim('/')
            ->toString();

        return preg_match(self::SHOP_REGEX, $domain) === 1 ? $domain : null;
    }

    public static function attributionWindowStart(): \Carbon\CarbonInterface
    {
        return now()->subDays((int) config('services.referrals.attribution_days', 7));
    }

    /**
     * A click is a usable temporary attribution only while it is inside
     * the attribution window and its tracking link is still ACTIVE.
     */
    public static function isClickUsable(ReferralClick $click): bool
    {
        return $click->created_at->greaterThanOrEqualTo(self::attributionWindowStart())
            && $click->trackingLink?->status === TrackingLink::STATUS_ACTIVE;
    }

    /**
     * Attach the merchant-supplied shop domain to a click. A click binds
     * to one shop only; re-submitting the same shop is idempotent.
     */
    public static function bindShop(ReferralClick $click, ?string $shopDomain): bool
    {
        $shop = self::normalizeShopDomain($shopDomain);

        if ($shop === null || ! self::isClickUsable($click)) {
            return false;
        }

        if ($click->shop_domain !== null) {
            return $click->shop_domain === $shop;
        }

        $click->update(['shop_domain' => $shop]);

        return true;
    }

    /**
     * Called from the existing store-installed callback once the actual
     * shop_domain is known. Returns an outcome constant; never throws.
     *
     * @param  int|null  $onboardingAgencyId  agency whose pending "Add store"
     *                                        attempt this install belongs to, if any
     */
    public static function handleInstall(string $shopDomain, ?int $onboardingAgencyId = null, ?string $shopifyShopId = null): string
    {
        try {
            return self::resolveInstall($shopDomain, $onboardingAgencyId, $shopifyShopId);
        } catch (\Throwable $e) {
            report($e);

            return self::UNDETERMINED;
        }
    }

    /**
     * Mirror the real store/agency-store state onto the shop's lead
     * (brix_status, stage, timestamps, events). Never creates a lead and
     * never deletes one.
     */
    public static function syncStore(Store $store): void
    {
        try {
            self::applyStoreState($store);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function resolveInstall(string $shopDomain, ?int $onboardingAgencyId, ?string $shopifyShopId): string
    {
        $shop = self::normalizeShopDomain($shopDomain);

        if ($shop === null) {
            return self::INVALID_SHOP;
        }

        // Rule 3: one lead per shop, ever. An existing lead is preserved
        // as-is; a later referral never creates or overwrites one.
        if ($existing = Lead::where('shop_domain', $shop)->first()) {
            if ($outcome = self::completePendingManualLead($existing, $shop, $onboardingAgencyId, $shopifyShopId)) {
                return $outcome;
            }

            $store = Store::where('shop_domain', $shop)->first();
            if ($store) {
                self::syncStore($store);
            }

            return self::EXISTING_LEAD;
        }

        $click = ReferralClick::with('trackingLink')
            ->where('shop_domain', $shop)
            ->where('created_at', '>=', self::attributionWindowStart())
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $click) {
            return self::NO_REFERRAL;
        }

        $link = $click->trackingLink;

        // Rule 4: invalid/inactive referral — no attribution, no lead.
        if (! $link || $link->status !== TrackingLink::STATUS_ACTIVE) {
            return self::skip(self::INACTIVE_REFERRAL, $click, $shop);
        }

        // An agency that explicitly started connecting this shop wins over
        // a referral from a different agency.
        if ($onboardingAgencyId !== null && $onboardingAgencyId !== (int) $link->agency_id) {
            return self::skip(self::CONFLICTING_ONBOARDING, $click, $shop);
        }

        // Rule 2/3: a store row owned by another agency is existing
        // attribution; one installed before this click is an existing
        // BRIX customer.
        $store = Store::where('shop_domain', $shop)->first();

        if ($store) {
            if ((int) $store->agency_id !== (int) $link->agency_id) {
                return self::skip(self::EXISTING_ATTRIBUTION, $click, $shop);
            }

            if ($store->installed_at !== null && $store->installed_at->lessThan($click->created_at)) {
                return self::skip(self::EXISTING_CUSTOMER, $click, $shop);
            }
        }

        $firstSeen = self::firstSeenAt($shop);

        if ($firstSeen === null) {
            return self::skip(self::UNDETERMINED, $click, $shop);
        }

        // BRIX's own shops row is upserted before this callback fires, so
        // "row exists" proves nothing — only a row first created after the
        // click (and not in the future, which would mean the two clocks
        // disagree) proves the shop was genuinely new.
        if ($firstSeen->lessThan($click->created_at) || $firstSeen->greaterThan(now()->addMinutes(5))) {
            return self::skip(self::EXISTING_CUSTOMER, $click, $shop);
        }

        $lead = self::createLead($link, $click, $shop, $store);

        if (! $lead) {
            return self::EXISTING_LEAD;
        }

        // Existing architecture: every store belongs to an agency. For a
        // genuinely new referred install with no store row yet, record the
        // real install through the existing mirror (idempotent, guarded
        // against cross-agency reinstall) so the normal authorize/activate
        // flow can take over. Skipped when an agency's own onboarding path
        // is already creating the store.
        if (! $store && $onboardingAgencyId === null) {
            $mirrored = StoreInstallationSync::mirror($shop, (int) $link->agency_id, $shopifyShopId, 'referral_install');
            $store = $mirrored['store'] ?? null;
        }

        if ($store) {
            self::syncStore($store);
        }

        ActivityLog::record('REFERRAL_LEAD_CREATED', (int) $link->agency_id, $store?->id, [
            'shop_domain' => $shop,
            'lead_id' => $lead->id,
            'tracking_link_id' => $link->id,
        ]);

        return self::CREATED;
    }

    /**
     * A manually-added lead already carries the shop_domain (extracted from
     * the agency's submitted website, confirmed not-installed at the time)
     * and its own dedicated link. When the merchant installs after clicking
     * THAT link, credit the install to this lead instead of dropping it as
     * EXISTING_LEAD. Returns null when this isn't that case.
     */
    private static function completePendingManualLead(Lead $lead, string $shop, ?int $onboardingAgencyId, ?string $shopifyShopId): ?string
    {
        if ($lead->source !== Lead::SOURCE_MANUAL || $lead->tracking_link_id === null || $lead->installed_at !== null) {
            return null;
        }

        $click = ReferralClick::with('trackingLink')
            ->where('tracking_link_id', $lead->tracking_link_id)
            ->where('shop_domain', $shop)
            ->where('created_at', '>=', self::attributionWindowStart())
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $click) {
            return null;
        }

        $link = $click->trackingLink;

        if (! $link || $link->status !== TrackingLink::STATUS_ACTIVE) {
            return self::skip(self::INACTIVE_REFERRAL, $click, $shop);
        }

        if ($onboardingAgencyId !== null && $onboardingAgencyId !== (int) $lead->agency_id) {
            return self::skip(self::CONFLICTING_ONBOARDING, $click, $shop);
        }

        $store = Store::where('shop_domain', $shop)->first();

        if ($store && (int) $store->agency_id !== (int) $lead->agency_id) {
            return self::skip(self::EXISTING_ATTRIBUTION, $click, $shop);
        }

        $installedAt = now();

        DB::transaction(function () use ($lead, $click, $shop, $installedAt) {
            $lead->update([
                'lead_stage' => Lead::STAGE_INSTALLED,
                'brix_status' => 'INSTALLED',
                'first_clicked_at' => $lead->first_clicked_at ?? $click->created_at,
                'installed_at' => $installedAt,
            ]);

            $lead->events()->create([
                'event_type' => LeadEvent::CLICKED,
                'metadata' => ['referral_click_id' => $click->id, 'referral_code' => $click->referral_code],
                'created_at' => $click->created_at,
            ]);

            $lead->events()->create([
                'event_type' => LeadEvent::INSTALLED,
                'metadata' => ['shop_domain' => $shop, 'source' => 'install_callback'],
                'created_at' => $installedAt,
            ]);
        });

        if (! $store && $onboardingAgencyId === null) {
            $store = StoreInstallationSync::mirror($shop, (int) $lead->agency_id, $shopifyShopId, 'referral_install')['store'] ?? null;
        }

        if ($store) {
            self::syncStore($store);
        }

        ActivityLog::record('REFERRAL_MANUAL_LEAD_INSTALLED', (int) $lead->agency_id, $store?->id, [
            'shop_domain' => $shop,
            'lead_id' => $lead->id,
            'tracking_link_id' => $link->id,
        ]);

        return self::CREATED;
    }

    private static function createLead(TrackingLink $link, ReferralClick $click, string $shop, ?Store $store): ?Lead
    {
        try {
            return DB::transaction(function () use ($link, $click, $shop, $store) {
                $installedAt = now();

                $lead = Lead::create([
                    'agency_id' => $link->agency_id,
                    'tracking_link_id' => $link->id,
                    'store_id' => $store?->id,
                    'shop_domain' => $shop,
                    'lead_stage' => Lead::STAGE_INSTALLED,
                    'brix_status' => 'INSTALLED',
                    'first_clicked_at' => $click->created_at,
                    'installed_at' => $installedAt,
                ]);

                $lead->events()->create([
                    'event_type' => LeadEvent::CLICKED,
                    'metadata' => ['referral_click_id' => $click->id, 'referral_code' => $click->referral_code],
                    'created_at' => $click->created_at,
                ]);

                $lead->events()->create([
                    'event_type' => LeadEvent::INSTALLED,
                    'metadata' => ['shop_domain' => $shop, 'source' => 'install_callback'],
                    'created_at' => $installedAt,
                ]);

                return $lead;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent callback created this shop's lead first.
            return null;
        }
    }

    private static function applyStoreState(Store $store): void
    {
        $lead = Lead::where('shop_domain', $store->shop_domain)->first();

        // Only the lead's own agency may see/track this store's state.
        if (! $lead || (int) $lead->agency_id !== (int) $store->agency_id) {
            return;
        }

        $relationship = AgencyStore::where('agency_id', $store->agency_id)->where('store_id', $store->id)->first();
        $status = self::brixStatus($store, $relationship?->relationship_status);
        $previous = $lead->brix_status;

        DB::transaction(function () use ($lead, $store, $relationship, $status, $previous) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->first();
            $updates = [];
            $events = [];

            if ($lead->store_id === null) {
                $updates['store_id'] = $store->id;
            }

            if ($status !== $lead->brix_status) {
                $updates['brix_status'] = $status;
            }

            if (in_array($status, ['INSTALLED', 'AUTHORIZED'], true) && in_array($lead->lead_stage, self::EARLY_STAGES, true)) {
                $updates['lead_stage'] = Lead::STAGE_INSTALLED;
            }

            if ($status === 'ACTIVE' && $previous !== 'ACTIVE') {
                $updates['lead_stage'] = Lead::STAGE_ACTIVE;
                $updates['activated_at'] = $lead->activated_at ?? $relationship?->activated_at ?? now();
                $events[] = LeadEvent::ACTIVATED;
            }

            if ($status === 'UNINSTALLED' && $previous !== 'UNINSTALLED') {
                $events[] = LeadEvent::CHURNED;
            }

            if (in_array($status, ['INSTALLED', 'AUTHORIZED'], true) && $previous === 'UNINSTALLED') {
                $events[] = LeadEvent::INSTALLED;
            }

            if ($updates) {
                $lead->update($updates);
            }

            foreach ($events as $type) {
                $lead->events()->create([
                    'event_type' => $type,
                    'metadata' => ['shop_domain' => $store->shop_domain, 'brix_status' => $status, 'previous_brix_status' => $previous],
                ]);
            }
        });
    }

    /**
     * The lead's brix_status is always derived from the real
     * stores.installation_status / agency_stores.relationship_status —
     * never set from anything the referral itself claims.
     */
    private static function brixStatus(Store $store, ?string $relationshipStatus): string
    {
        if ($store->installation_status === 'UNINSTALLED') {
            return 'UNINSTALLED';
        }

        return match ($relationshipStatus) {
            'ACTIVE' => 'ACTIVE',
            'AUTHORIZED' => 'AUTHORIZED',
            'DISCONNECTED' => 'DISCONNECTED',
            default => $store->installation_status ?: 'UNKNOWN',
        };
    }

    /** First time BRIX's own shops table saw this shop (read-only connection). */
    private static function firstSeenAt(string $shop): ?Carbon
    {
        try {
            $value = DB::connection('cartninja')->table('shops')->where('shop_domain', $shop)->value('created_at');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }

    private static function skip(string $reason, ReferralClick $click, string $shop): string
    {
        ActivityLog::record('REFERRAL_ATTRIBUTION_SKIPPED', (int) $click->agency_id, null, [
            'shop_domain' => $shop,
            'reason' => $reason,
            'tracking_link_id' => $click->tracking_link_id,
        ]);

        return $reason;
    }
}
