<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Organisation;
use App\Models\OrganisationSettings;
use App\Models\Payout;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AgencySeeder extends Seeder
{
    /**
     * Module adoption ratios modelled on the BRIX product brief:
     * Cart Drawer is adopted the most, Trust Badges the least.
     */
    private const ADOPTION_RATIOS = [
        'cart_drawer' => 21 / 24,
        'fbt' => 18 / 24,
        'coupon' => 16 / 24,
        'upsell' => 14 / 24,
        'progress_bar' => 11 / 24,
        'sticky_add_to_cart' => 9 / 24,
        'wishlist' => 7 / 24,
        'trust_badges' => 5 / 24,
    ];

    private array $storePool = [
        'Northwind Outdoors', 'Pixel & Thread', 'Voltage Electronics', 'Marlow Home Goods',
        'Sable & Co.', 'Crestline Coffee Co.', 'Aurora Athletics', 'Bloom & Bark',
        'Terra Botanicals', 'Silversmith Studio', 'Wanderlust Luggage', 'Nimbus Skincare',
        'Copper Kettle Kitchen', 'Fernweh Travel Co.', 'Hearth & Home', 'Ridgeline Gear',
        'Ember Candle Co.', 'Velvet Rose Beauty', 'Oakwood Furniture',
        'Coastal Table Co.', 'Juniper Lane', 'Solstice Supplements', 'Meadow & Moss',
        'Palmetto Provisions', 'Driftwood Denim', 'Halcyon Wellness', 'Cascade Cycles',
        'Amberlight Jewelry', 'Thistle & Thorn', 'Foxglove Florals', 'Larkspur Linens',
        'Boreal Outfitters', 'Ravine Roasters', 'Windmere Toys', 'Cobblestone Bakes',
        'Saltwater Supply Co.', 'Ironwood Tools', 'Petalworks', 'Meridian Menswear',
    ];

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@agency.com'],
            ['name' => 'Abilashmi', 'password' => bcrypt('password')]
        );

        $abilashmi = $this->createOrganisation('Abilashmi Agency', 'https://abilashmiagency.com', $user, 'owner');
        $growthLabs = $this->createOrganisation('Growth Labs', 'https://growthlabs.io', $user, 'admin');
        $commerceStudio = $this->createOrganisation('Commerce Studio', 'https://commercestudio.co', $user, 'member');

        $pool = $this->storePool;

        $abilashmiStores = $this->seedStores(
            organisation: $abilashmi,
            names: ['CoreEats', 'Aadhya Herbal Care', 'Hapily Earth'],
            statusOverrides: ['CoreEats' => 'attention'],
            domainOverrides: ['CoreEats' => 'coreeat.myshopify.com', 'Hapily Earth' => 'hapliearth.myshopify.com'],
        );

        $growthLabsStores = $this->seedStores(
            organisation: $growthLabs,
            names: array_slice($pool, 19, 12),
            statusOverrides: ['Thistle & Thorn' => 'attention', 'Foxglove Florals' => 'attention', 'Larkspur Linens' => 'offline'],
        );

        $commerceStudioStores = $this->seedStores(
            organisation: $commerceStudio,
            names: array_slice($pool, 31, 8),
            statusOverrides: ['Ironwood Tools' => 'attention', 'Petalworks' => 'offline'],
        );

        $this->seedOrganisationSettings($abilashmi, 24850, 184500);
        $this->seedOrganisationSettings($growthLabs, 6200, 52400);
        $this->seedOrganisationSettings($commerceStudio, 3100, 21800);

        $this->seedPayouts($abilashmi, $abilashmiStores, primary: true);
        $this->seedPayouts($growthLabs, $growthLabsStores, primary: false);
        $this->seedPayouts($commerceStudio, $commerceStudioStores, primary: false);

        $this->seedNotifications($abilashmi, $abilashmiStores);
        $this->seedNotifications($growthLabs, $growthLabsStores);
        $this->seedNotifications($commerceStudio, $commerceStudioStores);
    }

    private function createOrganisation(string $name, string $website, User $user, string $role): Organisation
    {
        $organisation = Organisation::create([
            'name' => $name,
            'website' => $website,
        ]);

        $organisation->users()->attach($user->id, ['role' => $role]);

        return $organisation;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function seedStores(Organisation $organisation, array $names, array $statusOverrides = [], array $domainOverrides = [])
    {
        $stores = collect();
        $total = count($names);

        $moduleCutoffs = collect(self::ADOPTION_RATIOS)->map(
            fn (float $ratio) => (int) round($total * $ratio)
        );

        foreach ($names as $index => $name) {
            $domain = $domainOverrides[$name] ?? Str::of($name)
                ->lower()
                ->replace('&', 'and')
                ->replaceMatches('/[^a-z0-9]+/', '-')
                ->trim('-')
                ->append('.myshopify.com')
                ->toString();

            $status = $statusOverrides[$name] ?? 'active';

            $monthsAgo = 1 + (($index * 7) % 11); // spread installs across the last year
            $installedAt = Carbon::now()->subMonths($monthsAgo)->subDays($index % 20);

            $lastActiveAt = match ($status) {
                'offline' => Carbon::now()->subDays(6 + $index % 8),
                'attention' => Carbon::now()->subHours(14 + $index % 30),
                default => Carbon::now()->subMinutes(2 + $index * 11),
            };

            $store = Store::create([
                'organisation_id' => $organisation->id,
                'name' => $name,
                'shop_domain' => $domain,
                'admin_url' => "https://{$domain}/admin",
                'status' => $status,
                'installed_at' => $installedAt,
                'last_active_at' => $lastActiveAt,
            ]);

            Store::where('id', $store->id)->update([
                'created_at' => $installedAt,
                'updated_at' => $lastActiveAt,
            ]);
            $store->refresh();

            foreach (StoreModule::MODULES as $key => $label) {
                $isActive = $index < $moduleCutoffs[$key];

                StoreModule::create([
                    'store_id' => $store->id,
                    'module' => $key,
                    'status' => $isActive ? 'active' : 'inactive',
                    'last_updated_at' => $isActive ? $installedAt->copy()->addDays(1) : null,
                ]);
            }

            $stores->push($store);
        }

        return $stores;
    }

    private function seedOrganisationSettings(Organisation $organisation, float $available, float $lifetime): void
    {
        OrganisationSettings::create([
            'organisation_id' => $organisation->id,
            'currency' => 'INR',
            'available_balance' => $available,
            'lifetime_earnings' => $lifetime,
        ]);
    }

    private function seedPayouts(Organisation $organisation, $stores, bool $primary): void
    {
        $descriptions = ['Monthly module revenue share', 'Conversion uplift bonus', 'Quarterly performance share', 'Store subscription share'];

        // Pending payouts awaiting processing.
        $pendingAmounts = $primary ? [5200, 3000] : [1800, 900];
        foreach ($pendingAmounts as $i => $amount) {
            Payout::create([
                'organisation_id' => $organisation->id,
                'store_id' => $stores[$i % $stores->count()]->id,
                'date' => Carbon::now()->subDays(3 + $i * 3)->toDateString(),
                'description' => 'Payout requested',
                'amount' => $amount,
                'status' => 'pending',
            ]);
        }

        // A couple of payouts currently processing.
        Payout::create([
            'organisation_id' => $organisation->id,
            'store_id' => $stores->first()->id,
            'date' => Carbon::now()->subDays(1)->toDateString(),
            'description' => 'Payout requested',
            'amount' => $primary ? 4100 : 1200,
            'status' => 'processing',
        ]);

        // Paid history across the last 6 months.
        for ($m = 5; $m >= 0; $m--) {
            $monthDate = Carbon::now()->subMonthsNoOverflow($m);
            $entriesThisMonth = $primary ? 4 : 2;

            for ($e = 0; $e < $entriesThisMonth; $e++) {
                $store = $stores[($m * 3 + $e) % $stores->count()];
                $day = min(27, 3 + $e * 7);
                $date = $monthDate->copy()->startOfMonth()->addDays($day);

                if ($date->greaterThan(Carbon::now())) {
                    $date = Carbon::now()->subDays($e + 1);
                }

                $amount = $primary
                    ? [12400, 15800, 9600, 10400][$e % 4]
                    : [3200, 4100][$e % 2];

                Payout::create([
                    'organisation_id' => $organisation->id,
                    'store_id' => $store->id,
                    'date' => $date->toDateString(),
                    'description' => $descriptions[$e % count($descriptions)],
                    'amount' => $amount,
                    'status' => 'paid',
                ]);
            }
        }
    }

    private function seedNotifications(Organisation $organisation, $stores): void
    {
        $first = $stores->get(0);
        $second = $stores->get(1);
        $attentionStore = $stores->firstWhere('status', 'attention') ?? $first;

        $entries = [
            [
                'store_id' => $first?->id,
                'type' => 'store_connected',
                'title' => 'New store connected',
                'message' => "{$first?->name} was connected to BRIX.",
                'created_at' => Carbon::now()->subMinutes(5),
                'read_at' => null,
            ],
            [
                'store_id' => $second?->id,
                'type' => 'module_activated',
                'title' => 'Module activated',
                'message' => 'FBT enabled for '.($second?->name ?? 'a store').'.',
                'created_at' => Carbon::now()->subMinutes(42),
                'read_at' => null,
            ],
            [
                'store_id' => null,
                'type' => 'payout_completed',
                'title' => 'Payout completed',
                'message' => '₹4,200 processed.',
                'created_at' => Carbon::now()->subHours(2),
                'read_at' => Carbon::now()->subHours(1),
            ],
            [
                'store_id' => $attentionStore?->id,
                'type' => 'module_attention',
                'title' => 'Module needs attention',
                'message' => "Coupon is misconfigured on {$attentionStore?->name} — no active codes found.",
                'created_at' => Carbon::now()->subDay(),
                'read_at' => Carbon::now()->subDay(),
            ],
            [
                'store_id' => null,
                'type' => 'info',
                'title' => 'Weekly summary ready',
                'message' => 'Your agency performance summary for last week is ready to view.',
                'created_at' => Carbon::now()->subDays(4),
                'read_at' => Carbon::now()->subDays(4),
            ],
        ];

        foreach ($entries as $entry) {
            $notification = Notification::create([
                'organisation_id' => $organisation->id,
                'store_id' => $entry['store_id'],
                'type' => $entry['type'],
                'title' => $entry['title'],
                'message' => $entry['message'],
                'read_at' => $entry['read_at'],
            ]);

            Notification::where('id', $notification->id)->update([
                'created_at' => $entry['created_at'],
                'updated_at' => $entry['created_at'],
            ]);
        }
    }
}
