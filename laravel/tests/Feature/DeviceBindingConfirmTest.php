<?php

namespace Tests\Feature;

use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A device access link is shared over WhatsApp, and messengers fetch every
 * link once for a preview. That fetch must NOT claim the binding — only a real
 * browser navigation (Sec-Fetch-Mode: navigate) binds automatically; everything
 * else lands on a confirmation page and binds after an explicit POST.
 */
class DeviceBindingConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;
    private DeviceToegang $toegang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'aantal_matten' => 1,
        ]);
        $mat = Mat::create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $this->toegang = DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
            'code' => 'ABCDEFGHJKLM',
        ]);
    }

    private function url(): string
    {
        return "/{$this->org->slug}/{$this->toernooi->slug}/toegang/{$this->toegang->code}";
    }

    #[Test]
    public function a_link_preview_fetch_does_not_bind(): void
    {
        // WhatsApp/facebook fetch: no Sec-Fetch-Mode, wants HTML for og-tags.
        $response = $this->withHeaders(['Accept' => 'text/html'])->get($this->url());

        $response->assertStatus(200);
        $response->assertViewIs('pages.toegang.bevestig');
        $this->assertFalse($this->toegang->refresh()->isGebonden());
    }

    #[Test]
    public function a_browser_navigation_binds_automatically(): void
    {
        $response = $this->withHeaders(['Sec-Fetch-Mode' => 'navigate'])->get($this->url());

        $response->assertRedirect();
        $this->assertTrue($this->toegang->refresh()->isGebonden());
    }

    #[Test]
    public function the_confirmation_post_binds_the_device(): void
    {
        $response = $this->post($this->url() . '/koppel');

        $response->assertRedirect();
        $this->assertTrue($this->toegang->refresh()->isGebonden());
    }

    #[Test]
    public function a_bound_device_reaching_the_link_again_goes_straight_to_the_interface(): void
    {
        // First bind via a browser navigation, keep the cookie.
        $bind = $this->withHeaders(['Sec-Fetch-Mode' => 'navigate'])->get($this->url());
        $token = $this->toegang->refresh()->device_token;

        // Same device (cookie present) — even a preview-style fetch is fine now:
        // the cookie proves it is the bound device, so no re-confirmation.
        $response = $this->withCookie('device_token_' . $this->toegang->id, $token)
            ->withHeaders(['Accept' => 'text/html'])
            ->get($this->url());

        $response->assertRedirect();
    }

    #[Test]
    public function a_preview_fetch_of_an_already_bound_link_shows_the_confirm_page_not_an_error(): void
    {
        // Another device already bound it.
        $this->toegang->bind(DeviceToegang::generateDeviceToken(), 'Ander apparaat');

        // A messenger preview of the link (no cookie, no navigate) must not
        // trip the "already bound" error — that only fires on a real bind attempt.
        $response = $this->withHeaders(['Accept' => 'text/html'])->get($this->url());

        $response->assertStatus(200);
        $response->assertViewIs('pages.toegang.bevestig');
    }
}
