<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\WegingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WegingServiceTest extends TestCase
{
    use RefreshDatabase;

    private WegingService $service;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WegingService();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewicht_tolerantie' => 0.5,
        ]);
        $this->club = Club::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakJudoka(array $attrs = []): Judoka
    {
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ], $attrs));
    }

    // ========================================================================
    // registreerGewicht
    // ========================================================================

    #[Test]
    public function registreer_gewicht_within_class(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30', 'gewicht' => 28.0]);

        $result = $this->service->registreerGewicht($judoka, 29.5);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['binnen_klasse']);
        $this->assertNull($result['alternatieve_poule']);
        $judoka->refresh();
        $this->assertEquals(29.5, (float) $judoka->gewicht_gewogen);
        $this->assertEquals('aanwezig', $judoka->aanwezigheid);
    }

    #[Test]
    public function registreer_gewicht_outside_class_suggests_alternative(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30', 'gewicht' => 28.0]);

        $result = $this->service->registreerGewicht($judoka, 32.0);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['binnen_klasse']);
        $this->assertNotNull($result['alternatieve_poule']);
        $this->assertStringContainsString('34', $result['alternatieve_poule']);
    }

    #[Test]
    public function registreer_gewicht_plus_class_too_light(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '+70', 'gewicht' => 72.0]);

        $result = $this->service->registreerGewicht($judoka, 65.0);

        $this->assertFalse($result['binnen_klasse']);
        $this->assertNotNull($result['opmerking']);
        $this->assertStringContainsString('70', $result['opmerking']);
    }

    #[Test]
    public function registreer_gewicht_creates_weging_record(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30', 'gewicht' => 28.0]);

        $this->service->registreerGewicht($judoka, 29.0, 'Test User');

        $this->assertDatabaseHas('wegingen', [
            'judoka_id' => $judoka->id,
            'gewicht' => 29.0,
            'geregistreerd_door' => 'Test User',
        ]);
    }

    // ========================================================================
    // getWeeglijst
    // ========================================================================

    #[Test]
    public function get_weeglijst_returns_all_judokas(): void
    {
        $this->maakJudoka();
        $this->maakJudoka();

        $result = $this->service->getWeeglijst($this->toernooi);

        $this->assertCount(2, $result);
    }

    // ========================================================================
    // markeerAanwezig / markeerAfwezig
    // ========================================================================

    #[Test]
    public function markeer_aanwezig_sets_status(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'onbekend']);

        $this->service->markeerAanwezig($judoka);

        $judoka->refresh();
        $this->assertEquals('aanwezig', $judoka->aanwezigheid);
    }

    #[Test]
    public function markeer_afwezig_sets_status(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'aanwezig']);

        $this->service->markeerAfwezig($judoka);

        $judoka->refresh();
        $this->assertEquals('afwezig', $judoka->aanwezigheid);
    }

    // ========================================================================
    // vindJudokaViaQR
    // ========================================================================

    #[Test]
    public function vind_judoka_via_qr_code(): void
    {
        $judoka = $this->maakJudoka();

        $found = $this->service->vindJudokaViaQR($judoka->qr_code);

        $this->assertNotNull($found);
        $this->assertEquals($judoka->id, $found->id);
    }

    #[Test]
    public function vind_judoka_via_qr_url(): void
    {
        $judoka = $this->maakJudoka();

        $found = $this->service->vindJudokaViaQR("/weegkaart/{$judoka->qr_code}");

        $this->assertNotNull($found);
        $this->assertEquals($judoka->id, $found->id);
    }

    #[Test]
    public function vind_judoka_via_qr_url_with_query_params(): void
    {
        $judoka = $this->maakJudoka();

        $found = $this->service->vindJudokaViaQR("/weegkaart/{$judoka->qr_code}?foo=bar");

        $this->assertNotNull($found);
        $this->assertEquals($judoka->id, $found->id);
    }

    #[Test]
    public function vind_judoka_via_qr_returns_null_for_unknown(): void
    {
        $found = $this->service->vindJudokaViaQR('non-existent-qr');

        $this->assertNull($found);
    }

    // ========================================================================
    // zoekJudokaOpNaam
    // ========================================================================

    #[Test]
    public function zoek_judoka_op_naam_finds_match(): void
    {
        $judoka = $this->maakJudoka(['naam' => 'Jansen, Pieter']);

        $result = $this->service->zoekJudokaOpNaam($this->toernooi, 'Jansen');

        $this->assertCount(1, $result);
        $this->assertEquals($judoka->id, $result->first()->id);
    }

    #[Test]
    public function zoek_judoka_op_naam_returns_empty_for_no_match(): void
    {
        $this->maakJudoka(['naam' => 'Jansen, Pieter']);

        $result = $this->service->zoekJudokaOpNaam($this->toernooi, 'Kansen');

        $this->assertCount(0, $result);
    }
}
