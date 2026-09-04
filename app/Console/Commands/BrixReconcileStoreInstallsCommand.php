<?php

namespace App\Console\Commands;

use App\Models\Brix\AgencyStoreOnboarding;
use App\Models\Brix\Store as BrixStore;
use App\Services\Brix\BrixInstallCheck;
use App\Services\Brix\StoreInstallationSync;
use Illuminate\Console\Command;

/**
 * Backfills brix_superadmin.stores.installation_status for shops that are
 * really installed on Shopify (per the live BRIX backend) but were never
 * mirrored — because the install webhook fired before an
 * agency_store_onboarding row existed, or never fired at all. Standing in
 * for what the install webhook would have done.
 *
 * Attribution: a shop is only mirrored if there is an onboarding row that
 * names its owning agency (or --agency is passed with --shop). Orphan
 * installs with no onboarding are skipped — stores.agency_id must be real.
 */
class BrixReconcileStoreInstallsCommand extends Command
{
    protected $signature = 'brix:reconcile-store-installs
        {--shop= : Only reconcile this shop domain}
        {--agency= : Agency id to attribute a --shop that has no onboarding row}
        {--all : Also revisit onboarding rows already COMPLETED/FAILED}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Mirror real Shopify installs into brix_superadmin that the install webhook missed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shopOption = $this->option('shop');

        $targets = $shopOption
            ? $this->targetsForShop((string) $shopOption)
            : $this->targetsFromOnboardings();

        if ($targets->isEmpty()) {
            $this->info('Nothing to reconcile.');

            return self::SUCCESS;
        }

        $mirrored = 0;
        $skipped = 0;

        foreach ($targets as $shopDomain => $agencyId) {
            $existing = BrixStore::where('shop_domain', $shopDomain)->first();

            if ($existing?->installation_status === 'INSTALLED') {
                $this->line("  <fg=gray>skip</>  {$shopDomain} — already INSTALLED");
                $skipped++;

                continue;
            }

            $installed = BrixInstallCheck::isInstalled($shopDomain);

            if ($installed === null) {
                $this->line("  <fg=yellow>?</>     {$shopDomain} — backend unreachable / unknown");
                $skipped++;

                continue;
            }

            if ($installed === false) {
                $this->line("  <fg=gray>skip</>  {$shopDomain} — not installed on Shopify");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=cyan>would mirror</> {$shopDomain} (agency {$agencyId})");
                $mirrored++;

                continue;
            }

            $result = StoreInstallationSync::mirror($shopDomain, (int) $agencyId, null, 'backfill_command');

            if ($result === null) {
                $this->line("  <fg=red>fail</>  {$shopDomain} — belongs to another agency");
                $skipped++;

                continue;
            }

            AgencyStoreOnboarding::where('agency_id', $agencyId)
                ->where('shop_domain', $shopDomain)
                ->whereIn('status', ['STARTED', 'INSTALL_REQUIRED', 'INSTALLING'])
                ->update(['status' => 'AUTHORIZING', 'created_store_id' => $result['store']->id]);

            $this->line("  <fg=green>ok</>    {$shopDomain} — mirrored INSTALLED (relationship {$result['relationship_status']})");
            $mirrored++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Mirrored: {$mirrored}   Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function targetsForShop(string $shop): \Illuminate\Support\Collection
    {
        $shop = strtolower(trim($shop));

        $onboarding = AgencyStoreOnboarding::where('shop_domain', $shop)->latest('id')->first();
        $agencyId = $this->option('agency') ?: $onboarding?->agency_id;

        if (! $agencyId) {
            $this->error("No onboarding row for {$shop} and no --agency given — cannot attribute the install.");

            return collect();
        }

        return collect([$shop => (int) $agencyId]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function targetsFromOnboardings(): \Illuminate\Support\Collection
    {
        $query = AgencyStoreOnboarding::query();

        if (! $this->option('all')) {
            $query->whereNotIn('status', ['COMPLETED', 'FAILED']);
        }

        // Latest onboarding row per shop wins the agency attribution.
        return $query->orderByDesc('id')
            ->get(['shop_domain', 'agency_id'])
            ->reduce(function (\Illuminate\Support\Collection $carry, AgencyStoreOnboarding $row) {
                if (! $carry->has($row->shop_domain)) {
                    $carry->put($row->shop_domain, (int) $row->agency_id);
                }

                return $carry;
            }, collect());
    }
}
