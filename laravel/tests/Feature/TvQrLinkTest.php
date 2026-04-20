<?php

namespace Tests\Feature;

use App\Models\DeviceToegang;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\TvKoppeling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TvQrLinkTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->kycCompleet()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'aantal_matten' => 3,
            'is_actief' => true,
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    #[Test]
    public function tv_koppel_page_renders_qr_and_code(): void
    {
        $response = $this->get(route('tv.koppel'));

        $response->assertOk();
        $response->assertViewHas('qrSvg');
        $response->assertViewHas('qrUrl');
        $response->assertSee('<svg', false);
    }

    #[Test]
    public function qr_scan_requires_auth(): void
    {
        $koppeling = TvKoppeling::create([
            'code' => TvKoppeling::generateCode(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get('/tv/qr/' . $koppeling->code)
            ->assertRedirect('/organisator/login');
    }

    #[Test]
    public function qr_scan_shows_ready_state_for_authed_organisator(): void
    {
        $this->actingAs($this->org);
        $koppeling = TvKoppeling::create([
            'code' => TvKoppeling::generateCode(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->get('/tv/qr/' . $koppeling->code);

        $response->assertOk();
        $response->assertViewHas('status', 'ready');
        $response->assertViewHas('toernooien');
        $response->assertSee($this->toernooi->naam);
    }

    #[Test]
    public function qr_scan_shows_expired_state_for_old_code(): void
    {
        $this->actingAs($this->org);
        $koppeling = TvKoppeling::create([
            'code' => '9999',
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->get('/tv/qr/' . $koppeling->code);

        $response->assertOk();
        $response->assertViewHas('status', 'expired');
    }

    #[Test]
    public function api_tv_link_couples_code_using_token_mat(): void
    {
        $koppeling = TvKoppeling::create([
            'code' => TvKoppeling::generateCode(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $toegang = DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'rol' => 'scoreboard',
            'mat_nummer' => 2,
            'code' => 'TESTCODE2222',
            'api_token' => str_repeat('a', 64),
            'gebonden_op' => now(),
        ]);

        // Also need a mat-rol toegang for the redirect URL
        DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 2,
            'code' => 'MATCODE22222',
            'gebonden_op' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $toegang->api_token,
        ])->postJson('/api/scoreboard/tv-link', [
            'code' => $koppeling->code,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('mat_nummer', 2);

        $koppeling->refresh();
        $this->assertNotNull($koppeling->linked_at);
        $this->assertEquals(2, $koppeling->mat_nummer);
        $this->assertEquals($this->toernooi->id, $koppeling->toernooi_id);
    }

    #[Test]
    public function api_tv_link_rejects_invalid_code(): void
    {
        $toegang = DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'rol' => 'scoreboard',
            'mat_nummer' => 1,
            'code' => 'TESTCODE1111',
            'api_token' => str_repeat('b', 64),
            'gebonden_op' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $toegang->api_token,
        ])->postJson('/api/scoreboard/tv-link', [
            'code' => '0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    #[Test]
    public function api_tv_link_requires_bearer_token(): void
    {
        $this->postJson('/api/scoreboard/tv-link', ['code' => '1234'])
            ->assertStatus(401);
    }
}
