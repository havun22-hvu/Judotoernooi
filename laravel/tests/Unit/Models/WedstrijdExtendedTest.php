<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WedstrijdExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;
    private Poule $poule;
    private Judoka $wit;
    private Judoka $blauw;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $this->poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $this->wit = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);
        $this->blauw = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);
    }

    private function maakWedstrijd(array $attrs = []): Wedstrijd
    {
        return Wedstrijd::factory()->create(array_merge([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->wit->id,
            'judoka_blauw_id' => $this->blauw->id,
        ], $attrs));
    }

    // ========================================================================
    // Type checks
    // ========================================================================

    #[Test]
    public function is_eliminatie_when_ronde_set(): void
    {
        $w = $this->maakWedstrijd(['ronde' => 'kwart']);
        $this->assertTrue($w->isEliminatie());
    }

    #[Test]
    public function is_not_eliminatie_when_ronde_null(): void
    {
        $w = $this->maakWedstrijd(['ronde' => null]);
        $this->assertFalse($w->isEliminatie());
    }

    #[Test]
    public function is_hoofdboom_for_groep_a(): void
    {
        $w = $this->maakWedstrijd(['groep' => 'A']);
        $this->assertTrue($w->isHoofdboom());
    }

    #[Test]
    public function is_herkansing_for_groep_b(): void
    {
        $w = $this->maakWedstrijd(['groep' => 'B']);
        $this->assertTrue($w->isHerkansing());
    }

    // ========================================================================
    // Verliezer
    // ========================================================================

    #[Test]
    public function get_verliezer_id_returns_loser(): void
    {
        $w = $this->maakWedstrijd([
            'is_gespeeld' => true,
            'winnaar_id' => $this->wit->id,
        ]);
        $this->assertEquals($this->blauw->id, $w->getVerliezerId());
    }

    #[Test]
    public function get_verliezer_id_returns_null_when_not_played(): void
    {
        $w = $this->maakWedstrijd(['is_gespeeld' => false, 'winnaar_id' => null]);
        $this->assertNull($w->getVerliezerId());
    }

    // ========================================================================
    // registreerUitslag
    // ========================================================================

    #[Test]
    public function registreer_uitslag_sets_all_fields(): void
    {
        $w = $this->maakWedstrijd();
        $w->registreerUitslag($this->wit->id, '10', '0', 'ippon');

        $w->refresh();
        $this->assertTrue($w->is_gespeeld);
        $this->assertEquals($this->wit->id, $w->winnaar_id);
        $this->assertEquals('10', $w->score_wit);
        $this->assertEquals('0', $w->score_blauw);
        $this->assertEquals('ippon', $w->uitslag_type);
        $this->assertNotNull($w->gespeeld_op);
    }

    #[Test]
    public function registreer_uitslag_blauw_wins(): void
    {
        $w = $this->maakWedstrijd();
        $w->registreerUitslag($this->blauw->id, '10', '0', 'waza-ari');

        $w->refresh();
        $this->assertEquals($this->blauw->id, $w->winnaar_id);
        $this->assertEquals('0', $w->score_wit);
        $this->assertEquals('10', $w->score_blauw);
    }

    // ========================================================================
    // Status checks
    // ========================================================================

    #[Test]
    public function is_gelijk_when_played_without_winner(): void
    {
        $w = $this->maakWedstrijd(['is_gespeeld' => true, 'winnaar_id' => null]);
        $this->assertTrue($w->isGelijk());
    }

    #[Test]
    public function is_echt_gespeeld_needs_winner(): void
    {
        $w = $this->maakWedstrijd(['is_gespeeld' => true, 'winnaar_id' => $this->wit->id]);
        $this->assertTrue($w->isEchtGespeeld());
    }

    #[Test]
    public function is_echt_gespeeld_false_without_winner(): void
    {
        $w = $this->maakWedstrijd(['is_gespeeld' => true, 'winnaar_id' => null]);
        $this->assertFalse($w->isEchtGespeeld());
    }

    #[Test]
    public function is_nog_te_spelen(): void
    {
        $w = $this->maakWedstrijd(['is_gespeeld' => false, 'winnaar_id' => null]);
        $this->assertTrue($w->isNogTeSpelen());
    }

    // ========================================================================
    // Bracket position helpers
    // ========================================================================

    #[Test]
    public function get_winnaar_doel_locatie(): void
    {
        $w = $this->maakWedstrijd(['locatie_wit' => 1]);
        $this->assertEquals(1, $w->getWinnaarDoelLocatie());

        $w = $this->maakWedstrijd(['locatie_wit' => 3]);
        $this->assertEquals(2, $w->getWinnaarDoelLocatie());

        $w = $this->maakWedstrijd(['locatie_wit' => 5]);
        $this->assertEquals(3, $w->getWinnaarDoelLocatie());
    }

    #[Test]
    public function get_winnaar_doel_locatie_returns_null_without_locatie(): void
    {
        $w = $this->maakWedstrijd(['locatie_wit' => null]);
        $this->assertNull($w->getWinnaarDoelLocatie());
    }

    #[Test]
    public function get_winnaar_doel_slot(): void
    {
        $w = $this->maakWedstrijd(['locatie_wit' => 1]);
        $this->assertEquals('wit', $w->getWinnaarDoelSlot());

        $w = $this->maakWedstrijd(['locatie_wit' => 3]);
        $this->assertEquals('blauw', $w->getWinnaarDoelSlot());
    }

    // ========================================================================
    // Relations
    // ========================================================================

    #[Test]
    public function wedstrijd_belongs_to_poule(): void
    {
        $w = $this->maakWedstrijd();
        $this->assertNotNull($w->poule);
        $this->assertEquals($this->poule->id, $w->poule->id);
    }

    #[Test]
    public function wedstrijd_has_judoka_wit_and_blauw(): void
    {
        $w = $this->maakWedstrijd();
        $this->assertEquals($this->wit->id, $w->judokaWit->id);
        $this->assertEquals($this->blauw->id, $w->judokaBlauw->id);
    }

    #[Test]
    public function wedstrijd_has_winnaar_relation(): void
    {
        $w = $this->maakWedstrijd([
            'is_gespeeld' => true,
            'winnaar_id' => $this->wit->id,
        ]);
        $this->assertEquals($this->wit->id, $w->winnaar->id);
    }
}
