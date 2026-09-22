<?php

namespace Tests\Feature\Portal;

use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Services\Referral\ReferralFunnel;
use App\Services\Referral\ReferralQr;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 5: QR referrals reuse the existing /ref/{code} attribution path. */
class QrReferralsTest extends TestCase
{
    use PartnerPortalTestbed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPortal();
    }

    private function link(array $t, string $name = 'Poster', array $extra = []): TrackingLink
    {
        return TrackingLink::create(array_merge(['agency_id' => $t['agency']->id, 'name' => $name, 'channel' => 'Other',
            'destination_url' => 'https://apps.shopify.com/thebrix-io'], $extra));
    }

    public function test_qr_encodes_the_existing_ref_url_and_adds_no_tables(): void
    {
        $link = $this->link($this->makeTenant('Agency A'));

        $this->assertSame(url('/ref/'.$link->code).'?src=qr', ReferralQr::url($link));
        $this->assertStringStartsWith('/ref/BRIX-', parse_url(ReferralQr::url($link), PHP_URL_PATH) ?: '');
        $this->assertStringContainsString('<svg', ReferralQr::svg($link));
        $this->assertFalse(Schema::hasTable('qr_codes'));
        $this->assertFalse(Schema::hasTable('qr_scans'));
    }

    public function test_scan_goes_through_the_normal_redirect_and_is_labelled_qr(): void
    {
        $link = $this->link($this->makeTenant('Agency A'));

        $response = $this->get('/ref/'.$link->code.'?src=qr');

        // Same signed store-domain step an ordinary click gets.
        $this->assertStringContainsString('/referral/store/', $response->headers->get('Location'));
        $click = ReferralClick::firstOrFail();
        $this->assertSame('qr', $click->source);
        $this->assertSame($link->id, $click->tracking_link_id);
    }

    public function test_ordinary_click_and_unknown_source_are_not_labelled_qr(): void
    {
        $link = $this->link($this->makeTenant('Agency A'));

        $this->get('/ref/'.$link->code);
        $this->get('/ref/'.$link->code.'?src=evil');
        $this->get('/ref/'.$link->code.'?src=QR');

        $this->assertSame(3, ReferralClick::count());
        $this->assertSame(0, ReferralClick::whereNotNull('source')->count());
    }

    public function test_inactive_link_still_404s_for_a_scan(): void
    {
        $link = $this->link($this->makeTenant('Agency A'), 'Old', ['status' => 'INACTIVE']);

        $this->get('/ref/'.$link->code.'?src=qr')->assertNotFound();
        $this->assertSame(0, ReferralClick::count());
    }

    public function test_page_lists_only_own_links_with_scan_counts(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $mine = $this->link($a, 'Alpha Poster');
        $this->link($b, 'Beta Poster');

        $this->get('/ref/'.$mine->code.'?src=qr');
        $this->get('/ref/'.$mine->code.'?src=qr');
        $this->get('/ref/'.$mine->code);

        $this->actAs($a);
        $this->get('/qr')->assertOk()->assertSee('Alpha Poster')->assertDontSee('Beta Poster')->assertSee('<svg', false)
            ->assertSeeInOrder(['Alpha Poster', '2', 'scans']);

        $rows = (new ReferralFunnel($a['agency']->id))->byLink(now()->subDay());
        $this->assertSame(3, $rows->first()['clicks']);
        $this->assertSame(2, $rows->first()['qr_scans']);
    }

    public function test_download_is_tenant_scoped(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $mine = $this->link($a);
        $theirs = $this->link($b, 'Beta Poster');

        $this->actAs($a);
        $ok = $this->get("/qr/{$mine->id}/download")->assertOk();
        $this->assertSame('image/svg+xml', $ok->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $ok->headers->get('Content-Disposition'));
        $this->get("/qr/{$theirs->id}/download")->assertForbidden();
    }

    public function test_empty_state(): void
    {
        $this->actAs($this->makeTenant('Empty'));

        $this->get('/qr')->assertOk()->assertSee('No referral links yet');
    }
}
