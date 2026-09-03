<?php

namespace App\Console\Commands;

use App\Models\Payout;
use Illuminate\Console\Command;

/**
 * Stands in for the BRIX Super Admin dashboard's payout review screen,
 * which lives outside this codebase. Both sides read/write the exact
 * same `payouts` row, so whatever this command does, the agency sees
 * reflected instantly — there's no separate admin copy of the data.
 */
class PayoutAdvanceCommand extends Command
{
    protected $signature = 'brix:payout
        {code : The payout code, e.g. PAY-1042}
        {action : approve|process|pay|reject}
        {--reason= : Rejection reason (required for "reject")}';

    protected $description = 'Advance a payout through its lifecycle, standing in for BRIX Super Admin review';

    public function handle(): int
    {
        $payout = Payout::where('payout_code', $this->argument('code'))->first();

        if (! $payout) {
            $this->error("No payout found with code {$this->argument('code')}.");

            return self::FAILURE;
        }

        $action = $this->argument('action');

        match ($action) {
            'approve', 'process' => $this->approve($payout),
            'pay' => $this->pay($payout),
            'reject' => $this->reject($payout),
            default => $this->error("Unknown action \"{$action}\". Use approve, pay, or reject."),
        };

        return self::SUCCESS;
    }

    private function approve(Payout $payout): void
    {
        $payout->markProcessing();
        $this->info("{$payout->payout_code} is now Processing.");
    }

    private function pay(Payout $payout): void
    {
        $payout->markPaid();
        $this->info("{$payout->payout_code} marked Paid.");
    }

    private function reject(Payout $payout): void
    {
        $reason = $this->option('reason') ?: $this->ask('Rejection reason');
        $payout->markRejected($reason);
        $this->info("{$payout->payout_code} rejected: {$reason}");
    }
}
