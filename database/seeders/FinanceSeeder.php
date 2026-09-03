<?php

namespace Database\Seeders;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\OrganisationSettings;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PlatformSetting;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        // Platform-wide finance rules (would live in a Super Admin settings
        // screen — this app only has the agency side, so they're seeded).
        PlatformSetting::updateOrCreate(['id' => 1], [
            'minimum_payout_amount' => 1000,
            'commission_holding_period_days' => 7,
        ]);

        foreach (Organisation::all() as $organisation) {
            $this->seedForOrganisation($organisation);
        }
    }

    private function seedForOrganisation(Organisation $organisation): void
    {
        OrganisationSettings::updateOrCreate(
            ['organisation_id' => $organisation->id],
            ['default_commission_rate' => 30]
        );

        $stores = Store::where('organisation_id', $organisation->id)->get();

        if ($stores->isEmpty()) {
            return;
        }

        // Give each demo agency a different payout-account state, so
        // switching organisations shows every UI state out of the box:
        // verified, pending verification, and not configured at all.
        match ($organisation->name) {
            'Abilashmi Agency' => PayoutAccount::updateOrCreate(
                ['organisation_id' => $organisation->id],
                [
                    'method' => PayoutAccount::METHOD_BANK_TRANSFER,
                    'account_holder_name' => 'Abilashmi Digital Agency',
                    'account_number' => '50100234564521',
                    'account_last4' => '4521',
                    'ifsc_code' => 'HDFC0001234',
                    'verification_status' => PayoutAccount::STATUS_VERIFIED,
                ]
            ),
            'Growth Labs' => PayoutAccount::updateOrCreate(
                ['organisation_id' => $organisation->id],
                [
                    'method' => PayoutAccount::METHOD_UPI,
                    'account_holder_name' => 'Growth Labs',
                    'upi_id' => 'growthlabs@upi',
                    'verification_status' => PayoutAccount::STATUS_PENDING_VERIFICATION,
                ]
            ),
            default => null, // Commerce Studio: no payout account configured yet.
        };

        if ($organisation->name === 'Abilashmi Agency') {
            $stores->firstWhere('name', 'Hapily Earth')?->update([
                'plan' => 'Pro',
                'commission_rate' => 20.00,
            ]);
            $stores->firstWhere('name', 'Aadhya Herbal Care')?->update(['plan' => 'Pro']);
            $stores->firstWhere('name', 'CoreEats')?->update(['plan' => 'Starter']);
        }

        $grossByPlan = ['Starter' => 499, 'Pro' => 999, 'Growth' => 1499];
        $holdingDays = PlatformSetting::current()->commission_holding_period_days;

        foreach ($stores as $index => $store) {
            $store->refresh();
            $gross = $grossByPlan[$store->plan] ?? 499;
            $rate = $store->effective_commission_rate;
            $source = $store->commission_source;

            // 6 months of recurring subscription-share commission: realized
            // (available) for every past month, still pending this month.
            // Whether it's actually been *withdrawn* is tracked entirely by
            // Payout.status — a commission never needs its own "paid" flag,
            // which would otherwise double-subtract against payout history.
            for ($monthsAgo = 6; $monthsAgo >= 0; $monthsAgo--) {
                $transactionDate = $monthsAgo === 0
                    ? Carbon::now()->subDays(2 + $index) // still within the holding window
                    : Carbon::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(5 + $index);

                $availableAt = $transactionDate->copy()->addDays($holdingDays);

                $status = $monthsAgo === 0 ? Commission::STATUS_PENDING : Commission::STATUS_AVAILABLE;

                $commissionAmount = round($gross * $rate / 100, 2);

                Commission::create([
                    'organisation_id' => $organisation->id,
                    'store_id' => $store->id,
                    'gross_amount' => $gross,
                    'commission_rate' => $rate,
                    'commission_source' => $source,
                    'commission_amount' => $commissionAmount,
                    'brix_amount' => round($gross - $commissionAmount, 2),
                    'status' => $status,
                    'transaction_date' => $transactionDate->toDateString(),
                    'available_at' => $availableAt,
                ]);
            }
        }

        // A little payout history, sized well within the available balance
        // so "Request Payout" is still demonstrable fresh. Only agencies
        // with a payout account configured have any payout history —
        // Commerce Studio deliberately has none, to show that empty state.
        $account = PayoutAccount::where('organisation_id', $organisation->id)->first();

        if (! $account?->is_verified) {
            return;
        }

        $lifetime = $organisation->finance()->lifetimeEarnings();
        $slice = max(50, round($lifetime * 0.12, -1));

        Payout::create([
            'organisation_id' => $organisation->id,
            'date' => Carbon::now()->subMonthsNoOverflow(3)->toDateString(),
            'description' => 'Payout',
            'amount' => $slice,
            'currency' => 'INR',
            'status' => Payout::STATUS_PAID,
            'payment_method' => $account->method,
            'payout_account_id' => $account->id,
            'provider' => 'manual',
            'requested_at' => Carbon::now()->subMonthsNoOverflow(3),
            'processing_at' => Carbon::now()->subMonthsNoOverflow(3)->addDay(),
            'paid_at' => Carbon::now()->subMonthsNoOverflow(3)->addDays(2),
        ]);

        if ($organisation->name === 'Abilashmi Agency') {
            Payout::create([
                'organisation_id' => $organisation->id,
                'date' => Carbon::now()->subMonthsNoOverflow(1)->toDateString(),
                'description' => 'Payout',
                'amount' => 540.00,
                'currency' => 'INR',
                'status' => Payout::STATUS_PAID,
                'payment_method' => $account->method,
                'payout_account_id' => $account->id,
                'provider' => 'manual',
                'requested_at' => Carbon::now()->subMonthsNoOverflow(1),
                'processing_at' => Carbon::now()->subMonthsNoOverflow(1)->addDay(),
                'paid_at' => Carbon::now()->subMonthsNoOverflow(1)->addDays(2),
            ]);

            Payout::create([
                'organisation_id' => $organisation->id,
                'date' => Carbon::now()->subDays(10)->toDateString(),
                'description' => 'Payout',
                'amount' => 250.00,
                'currency' => 'INR',
                'status' => Payout::STATUS_REJECTED,
                'payment_method' => $account->method,
                'payout_account_id' => $account->id,
                'provider' => 'manual',
                'requested_at' => Carbon::now()->subDays(10),
                'rejected_at' => Carbon::now()->subDays(9),
                'rejection_reason' => 'Insufficient payout verification',
            ]);
        }
    }
}
