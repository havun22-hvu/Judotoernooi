<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\CoachKaart;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachKaartExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $this->club = Club::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakKaart(array $attrs = []): CoachKaart
    {
        return CoachKaart::create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Coach Test',
        ], $attrs));
    }

    // ========================================================================
    // Wisselingen relationship
    // ========================================================================

    #[Test]
    public function wisselingen_relationship_returns_empty(): void
    {
        $kaart = $this->maakKaart();
        $this->assertCount(0, $kaart->wisselingen);
    }

    // ========================================================================
    // Checkins relationship
    // ========================================================================

    #[Test]
    public function checkins_relationship_returns_empty(): void
    {
        $kaart = $this->maakKaart();
        $this->assertCount(0, $kaart->checkins);
    }

    // ========================================================================
    // huidigeWisseling
    // ========================================================================

    #[Test]
    public function huidige_wisseling_returns_null_when_no_wisselingen(): void
    {
        $kaart = $this->maakKaart();
        $this->assertNull($kaart->huidigeWisseling());
    }

    // ========================================================================
    // activeer
    // ========================================================================

    #[Test]
    public function activeer_sets_all_fields(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => false]);

        $kaart->activeer('Nieuwe Coach', 'foto.jpg', 'device_token_123', 'Chrome');
        $kaart->refresh();

        $this->assertEquals('Nieuwe Coach', $kaart->naam);
        $this->assertEquals('foto.jpg', $kaart->foto);
        $this->assertTrue($kaart->is_geactiveerd);
        $this->assertNotNull($kaart->geactiveerd_op);
        $this->assertEquals('device_token_123', $kaart->device_token);
        $this->assertEquals('Chrome', $kaart->device_info);
        $this->assertNotNull($kaart->gebonden_op);
    }

    #[Test]
    public function activeer_creates_wisseling_record(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => false]);

        $kaart->activeer('Coach A', 'foto.jpg', 'token', 'Safari');

        $this->assertCount(1, $kaart->wisselingen);
        $wisseling = $kaart->wisselingen->first();
        $this->assertEquals('Coach A', $wisseling->naam);
        $this->assertNull($wisseling->overgedragen_op);
    }

    // ========================================================================
    // overdragen
    // ========================================================================

    #[Test]
    public function overdragen_creates_new_wisseling_and_updates_card(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => false]);
        $kaart->activeer('Coach A', 'foto_a.jpg', 'token_a', 'Chrome');

        $kaart->overdragen('Coach B', 'foto_b.jpg', 'token_b', 'Safari');
        $kaart->refresh();

        // Card should show new coach
        $this->assertEquals('Coach B', $kaart->naam);
        $this->assertEquals('foto_b.jpg', $kaart->foto);
        $this->assertEquals('token_b', $kaart->device_token);

        // Should have 2 wisselingen
        $this->assertCount(2, $kaart->wisselingen);
    }

    // ========================================================================
    // checkin / checkout / forceCheckout
    // ========================================================================

    #[Test]
    public function checkin_sets_timestamp_and_creates_log(): void
    {
        $kaart = $this->maakKaart([
            'is_geactiveerd' => true,
            'foto' => 'test.jpg',
        ]);

        $kaart->checkin();
        $kaart->refresh();

        $this->assertNotNull($kaart->ingecheckt_op);
        $this->assertCount(1, $kaart->checkins);
        $this->assertEquals('in', $kaart->checkins->first()->actie);
    }

    #[Test]
    public function checkout_clears_timestamp_and_creates_log(): void
    {
        $kaart = $this->maakKaart([
            'is_geactiveerd' => true,
            'foto' => 'test.jpg',
            'ingecheckt_op' => now(),
        ]);

        $kaart->checkout();
        $kaart->refresh();

        $this->assertNull($kaart->ingecheckt_op);
        $this->assertCount(1, $kaart->checkins);
        $this->assertEquals('uit', $kaart->checkins->first()->actie);
    }

    #[Test]
    public function force_checkout_clears_and_logs_forced(): void
    {
        $kaart = $this->maakKaart([
            'is_geactiveerd' => true,
            'foto' => 'test.jpg',
            'ingecheckt_op' => now(),
        ]);

        $kaart->forceCheckout();
        $kaart->refresh();

        $this->assertNull($kaart->ingecheckt_op);
        $this->assertCount(1, $kaart->checkins);
        $this->assertEquals('uit_geforceerd', $kaart->checkins->first()->actie);
    }

    // ========================================================================
    // getTimedScanUrl
    // ========================================================================

    #[Test]
    public function get_timed_scan_url_contains_timestamp_and_signature(): void
    {
        $kaart = $this->maakKaart();
        $url = $kaart->getTimedScanUrl(5);

        $this->assertStringContainsString($kaart->qr_code, $url);
        // URL should have t= and s= parameters
        $this->assertStringContainsString('t=', $url);
        $this->assertStringContainsString('s=', $url);
    }

    // ========================================================================
    // generateScanSignature
    // ========================================================================

    #[Test]
    public function scan_signature_is_deterministic(): void
    {
        $kaart = $this->maakKaart();
        $timestamp = 1234567890;

        $sig1 = $kaart->generateScanSignature($timestamp);
        $sig2 = $kaart->generateScanSignature($timestamp);

        $this->assertEquals($sig1, $sig2);
        $this->assertEquals(16, strlen($sig1));
    }

    #[Test]
    public function scan_signature_differs_for_different_timestamps(): void
    {
        $kaart = $this->maakKaart();

        $sig1 = $kaart->generateScanSignature(1000);
        $sig2 = $kaart->generateScanSignature(2000);

        $this->assertNotEquals($sig1, $sig2);
    }

    // ========================================================================
    // validateScanToken — future timestamp
    // ========================================================================

    #[Test]
    public function validate_scan_token_rejects_future_timestamp(): void
    {
        $kaart = $this->maakKaart();
        $futureTimestamp = now()->addMinutes(10)->timestamp;
        $sig = $kaart->generateScanSignature($futureTimestamp);

        // Future timestamps should fail (age would be negative)
        $this->assertFalse($kaart->validateScanToken($futureTimestamp, $sig, 5));
    }
}
