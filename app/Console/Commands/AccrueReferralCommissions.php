<?php

namespace App\Console\Commands;

use App\Services\Referral\Commission\ReferralCommissionService;
use Illuminate\Console\Command;

/**
 * Records verified BRIX revenue events for referred stores and accrues
 * commission from them under each agency's own revenue-source setting.
 * A dry run unless --write is passed, and deliberately not scheduled.
 */
class AccrueReferralCommissions extends Command
{
    protected $signature = 'referrals:accrue-commissions
        {--write : Actually record revenue events and commissions (default is a dry run)}
        {--lead= : Only process this lead id}';

    protected $description = 'Record verified referral revenue events and accrue commission (dry run unless --write)';

    public function handle(ReferralCommissionService $service): int
    {
        $summary = $service->accrue((bool) $this->option('write'), $this->option('lead') ? (int) $this->option('lead') : null);
        $dry = $summary['dry_run'];

        $this->info($dry ? 'DRY RUN — nothing written.' : 'Accrual complete.');

        foreach ($summary['sources'] as $type => $state) {
            $this->line("  revenue source {$type}: {$state}");
        }

        $this->line("  leads considered: {$summary['leads']}");
        $this->line(($dry ? '  would record events: ' : '  events recorded: ').$summary['events_created']);
        $this->line(($dry ? '  would create commissions: ' : '  commissions created: ')."{$summary['commissions_created']} (total {$summary['commission_total']})");
        $this->line("  events already recorded: {$summary['events_existing']}");

        foreach ($summary['not_commissioned'] as $reason => $count) {
            $this->line("  recorded without commission ({$reason}): {$count}");
        }

        foreach ($summary['skipped'] as $reason => $count) {
            $this->line("  skipped ({$reason}): {$count}");
        }

        return self::SUCCESS;
    }
}
