<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\CoachKaart;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachKaartTest extends TestCase
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
    // Creating - auto-generated fields
    // ========================================================================

    #[Test]
    public function qr_code_generated_on_create(): void
    {
        $kaart = $this->maakKaart();
        $this->assertNotEmpty($kaart->qr_code);
        $this->assertStringStartsWith('CK', $kaart->qr_code);
    }

    #[Test]
    public function pincode_generated_on_create(): void
    {
        $kaart = $this->maakKaart();
        $this->assertNotEmpty($kaart->pincode);
        $this->assertEquals(4, strlen($kaart->pincode));
    }

    #[Test]
    public function qr_codes_are_unique(): void
    {
        $kaart1 = $this->maakKaart();
        $kaart2 = $this->maakKaart();
        $this->assertNotEquals($kaart1->qr_code, $kaart2->qr_code);
    }

    // ========================================================================
    // Static generators
    // ========================================================================

    #[Test]
    public function generate_pincode_is_4_digits(): void
    {
        $pin = CoachKaart::generatePincode();
        $this->assertEquals(4, strlen($pin));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
    }

    #[Test]
    public function generate_qr_code_starts_with_ck(): void
    {
        $code = CoachKaart::generateQrCode();
        $this->assertStringStartsWith('CK', $code);
        $this->assertEquals(12, strlen($code)); // CK + 10 chars
    }

    #[Test]
    public function generate_activatie_token_is_64_chars(): void
    {
        $token = CoachKaart::generateActivatieToken();
        $this->assertEquals(64, strlen($token));
    }

    #[Test]
    public function generate_device_token_is_64_chars(): void
    {
        $token = CoachKaart::generateDeviceToken();
        $this->assertEquals(64, strlen($token));
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function belongs_to_toernooi(): void
    {
        $kaart = $this->maakKaart();
        $this->assertNotNull($kaart->toernooi);
        $this->assertEquals($this->toernooi->id, $kaart->toernooi->id);
    }

    #[Test]
    public function belongs_to_club(): void
    {
        $kaart = $this->maakKaart();
        $this->assertNotNull($kaart->club);
        $this->assertEquals($this->club->id, $kaart->club->id);
    }

    // ========================================================================
    // Status methods
    // ========================================================================

    #[Test]
    public function is_geldig_requires_activated_and_foto(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => true, 'foto' => 'coaches/test.jpg']);
        $this->assertTrue($kaart->isGeldig());
    }

    #[Test]
    public function is_geldig_false_without_foto(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => true, 'foto' => null]);
        $this->assertFalse($kaart->isGeldig());
    }

    #[Test]
    public function is_geldig_false_without_activation(): void
    {
        $kaart = $this->maakKaart(['is_geactiveerd' => false, 'foto' => 'test.jpg']);
        $this->assertFalse($kaart->isGeldig());
    }

    #[Test]
    public function is_device_gebonden(): void
    {
        $kaart = $this->maakKaart(['device_token' => 'abc123']);
        $this->assertTrue($kaart->isDeviceGebonden());
    }

    #[Test]
    public function is_not_device_gebonden(): void
    {
        $kaart = $this->maakKaart(['device_token' => null]);
        $this->assertFalse($kaart->isDeviceGebonden());
    }

    #[Test]
    public function is_ingecheckt(): void
    {
        $kaart = $this->maakKaart(['ingecheckt_op' => now()]);
        $this->assertTrue($kaart->isIngecheckt());
    }

    #[Test]
    public function is_not_ingecheckt(): void
    {
        $kaart = $this->maakKaart(['ingecheckt_op' => null]);
        $this->assertFalse($kaart->isIngecheckt());
    }

    // ========================================================================
    // Device binding
    // ========================================================================

    #[Test]
    public function bind_device_stores_info(): void
    {
        $kaart = $this->maakKaart();
        $kaart->bindDevice('token123', 'Chrome on Windows');
        $kaart->refresh();

        $this->assertEquals('token123', $kaart->device_token);
        $this->assertEquals('Chrome on Windows', $kaart->device_info);
        $this->assertNotNull($kaart->gebonden_op);
    }

    #[Test]
    public function reset_device_clears_info(): void
    {
        $kaart = $this->maakKaart([
            'device_token' => 'old_token',
            'device_info' => 'Old Device',
            'gebonden_op' => now(),
        ]);

        $kaart->resetDevice();
        $kaart->refresh();

        $this->assertNull($kaart->device_token);
        $this->assertNull($kaart->device_info);
        $this->assertNull($kaart->gebonden_op);
    }

    // ========================================================================
    // Markeer gescand
    // ========================================================================

    #[Test]
    public function markeer_gescand_updates_fields(): void
    {
        $kaart = $this->maakKaart();
        $kaart->markeerGescand();
        $kaart->refresh();

        $this->assertTrue($kaart->is_gescand);
        $this->assertNotNull($kaart->gescand_op);
    }

    // ========================================================================
    // kanQrTonen
    // ========================================================================

    #[Test]
    public function kan_qr_tonen_with_correct_device(): void
    {
        $kaart = $this->maakKaart([
            'device_token' => 'my_token',
            'foto' => 'test.jpg',
        ]);
        $this->assertTrue($kaart->kanQrTonen('my_token'));
    }

    #[Test]
    public function kan_qr_tonen_false_wrong_device(): void
    {
        $kaart = $this->maakKaart([
            'device_token' => 'my_token',
            'foto' => 'test.jpg',
        ]);
        $this->assertFalse($kaart->kanQrTonen('other_token'));
    }

    #[Test]
    public function kan_qr_tonen_false_no_foto(): void
    {
        $kaart = $this->maakKaart([
            'device_token' => 'my_token',
            'foto' => null,
        ]);
        $this->assertFalse($kaart->kanQrTonen('my_token'));
    }

    // ========================================================================
    // kanOverdragen
    // ========================================================================

    #[Test]
    public function kan_overdragen_when_incheck_not_active(): void
    {
        $this->toernooi->update(['coach_incheck_actief' => false]);
        $kaart = $this->maakKaart(['ingecheckt_op' => now()]);
        $this->assertTrue($kaart->kanOverdragen());
    }

    #[Test]
    public function kan_overdragen_when_not_ingecheckt(): void
    {
        $this->toernooi->update(['coach_incheck_actief' => true]);
        $kaart = $this->maakKaart(['ingecheckt_op' => null]);
        $this->assertTrue($kaart->kanOverdragen());
    }

    #[Test]
    public function cannot_overdragen_when_ingecheckt(): void
    {
        $this->toernooi->update(['coach_incheck_actief' => true]);
        $kaart = $this->maakKaart(['ingecheckt_op' => now()]);
        $this->assertFalse($kaart->kanOverdragen());
    }

    // ========================================================================
    // Timed scan URL / signature
    // ========================================================================

    #[Test]
    public function validate_scan_token_valid(): void
    {
        $kaart = $this->maakKaart();
        $timestamp = now()->timestamp;
        $sig = $kaart->generateScanSignature($timestamp);

        $this->assertTrue($kaart->validateScanToken($timestamp, $sig, 5));
    }

    #[Test]
    public function validate_scan_token_expired(): void
    {
        $kaart = $this->maakKaart();
        $timestamp = now()->subMinutes(10)->timestamp;
        $sig = $kaart->generateScanSignature($timestamp);

        $this->assertFalse($kaart->validateScanToken($timestamp, $sig, 5));
    }

    #[Test]
    public function validate_scan_token_wrong_signature(): void
    {
        $kaart = $this->maakKaart();
        $timestamp = now()->timestamp;

        $this->assertFalse($kaart->validateScanToken($timestamp, 'wrong_signature', 5));
    }

    // ========================================================================
    // Foto URL
    // ========================================================================

    #[Test]
    public function foto_url_returns_null_without_foto(): void
    {
        $kaart = $this->maakKaart(['foto' => null]);
        $this->assertNull($kaart->getFotoUrl());
    }

    #[Test]
    public function foto_url_returns_asset_path(): void
    {
        $kaart = $this->maakKaart(['foto' => 'coaches/photo.jpg']);
        $url = $kaart->getFotoUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('coaches/photo.jpg', $url);
    }
}
