<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class EliminatieCoverageTest extends TestCase
{
    use RefreshDatabase;

    private EliminatieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EliminatieService();
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(EliminatieService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    private function createPouleWithJudokas(int $aantal, string $type = 'dubbel', int $aantalBrons = 2): array
    {
        $toernooi = Toernooi::factory()->create(['eliminatie_type' => $type, 'aantal_brons' => $aantalBrons]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'type' => 'eliminatie',
        ]);

        $judokaIds = [];
        for ($i = 0; $i < $aantal; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
            ]);
            $judokaIds[] = $judoka->id;
        }

        return [$poule, $judokaIds, $toernooi];
    }

    // =========================================================================
    // schrapLegeBWedstrijden (lines 1439-1465)
    // =========================================================================

    #[Test]
    public function schrap_lege_b_wedstrijden_verwijdert_lege(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Create an empty B-wedstrijd manually
        $legeWed = Wedstrijd::create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
            'volgorde' => 9999,
            'ronde' => 'b_kwartfinale_1',
            'groep' => 'B',
            'bracket_positie' => 99,
            'locatie_wit' => 197,
            'locatie_blauw' => 198,
        ]);

        $verwijderd = $this->service->schrapLegeBWedstrijden($poule->id);

        $this->assertGreaterThanOrEqual(1, $verwijderd, 'Minstens 1 lege wedstrijd moet verwijderd zijn');
        $this->assertNull(Wedstrijd::find($legeWed->id), 'Lege wedstrijd moet verwijderd zijn');
    }

    #[Test]
    public function schrap_lege_b_wedstrijden_update_verwijzingen(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Create chain: wed1 -> legeWed -> wed3
        $wed3 = Wedstrijd::create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaIds[0],
            'judoka_blauw_id' => $judokaIds[1],
            'volgorde' => 9998,
            'ronde' => 'b_halve_finale_2',
            'groep' => 'B',
            'bracket_positie' => 50,
            'locatie_wit' => 99,
            'locatie_blauw' => 100,
        ]);

        $legeWed = Wedstrijd::create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
            'volgorde' => 9997,
            'ronde' => 'b_kwartfinale_1',
            'groep' => 'B',
            'bracket_positie' => 51,
            'locatie_wit' => 101,
            'locatie_blauw' => 102,
            'volgende_wedstrijd_id' => $wed3->id,
        ]);

        $wed1 = Wedstrijd::create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaIds[2],
            'judoka_blauw_id' => $judokaIds[3],
            'volgorde' => 9996,
            'ronde' => 'b_achtste_finale_1',
            'groep' => 'B',
            'bracket_positie' => 52,
            'locatie_wit' => 103,
            'locatie_blauw' => 104,
            'volgende_wedstrijd_id' => $legeWed->id,
        ]);

        $this->service->schrapLegeBWedstrijden($poule->id);

        // wed1 should now point to wed3 (skipping deleted legeWed)
        $wed1->refresh();
        $this->assertEquals($wed3->id, $wed1->volgende_wedstrijd_id, 'Verwijzing moet doorgeschoven zijn');
    }

    #[Test]
    public function schrap_lege_b_wedstrijden_geen_lege_retourneert_nul(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Fill all B wedstrijden with at least one judoka
        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->get();

        foreach ($bWedstrijden as $idx => $wed) {
            $wed->update(['judoka_wit_id' => $judokaIds[$idx % count($judokaIds)]]);
        }

        $verwijderd = $this->service->schrapLegeBWedstrijden($poule->id);
        $this->assertEquals(0, $verwijderd);
    }

    // =========================================================================
    // herstelBKoppelingen (lines 1505-1577)
    // =========================================================================

    #[Test]
    public function herstel_b_koppelingen_herstelt_links(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Clear all B-group links
        Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->update(['volgende_wedstrijd_id' => null, 'winnaar_naar_slot' => null]);

        $hersteld = $this->service->herstelBKoppelingen($poule->id);

        $this->assertGreaterThan(0, $hersteld, 'Er moeten koppelingen hersteld zijn');

        // Verify some B-wedstrijden now have volgende_wedstrijd_id
        $gekoppeld = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->whereNotNull('volgende_wedstrijd_id')
            ->count();

        $this->assertGreaterThan(0, $gekoppeld, 'B-wedstrijden moeten weer gekoppeld zijn');
    }

    #[Test]
    public function herstel_b_koppelingen_dubbel_rondes_16_judokas(): void
    {
        // N=16 has dubbel rondes (a1=8 > a2=4)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Clear and restore
        Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->update(['volgende_wedstrijd_id' => null, 'winnaar_naar_slot' => null]);

        $hersteld = $this->service->herstelBKoppelingen($poule->id);
        $this->assertGreaterThan(0, $hersteld);

        // Check that _1 → _2 links use wit slot (1:1 mapping)
        $ronde1Weds = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'like', '%_1')
            ->whereNotNull('volgende_wedstrijd_id')
            ->get();

        foreach ($ronde1Weds as $wed) {
            $volgende = Wedstrijd::find($wed->volgende_wedstrijd_id);
            if ($volgende && str_ends_with($volgende->ronde, '_2')) {
                $this->assertEquals('wit', $wed->winnaar_naar_slot, '(1) → (2) moet altijd wit zijn');
            }
        }
    }

    // =========================================================================
    // verwijderUitB (line 1598 — blauw slot removal)
    // =========================================================================

    #[Test]
    public function verwijder_uit_b_verwijdert_blauw_slot(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $judokaId = $judokaIds[0];

        // Place judoka on BLAUW slot in a B-wedstrijd
        $bWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->first();

        $bWed->update(['judoka_blauw_id' => $judokaId]);

        $this->service->verwijderUitB($poule->id, $judokaId);

        $bWed->refresh();
        $this->assertNull($bWed->judoka_blauw_id, 'Judoka moet van blauw slot verwijderd zijn');
    }

    #[Test]
    public function verwijder_uit_b_verwijdert_wit_en_blauw(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $judokaId = $judokaIds[0];

        // Place judoka on both wit and blauw in different B-wedstrijden
        $bWeds = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->take(2)
            ->get();

        $bWeds[0]->update(['judoka_wit_id' => $judokaId]);
        $bWeds[1]->update(['judoka_blauw_id' => $judokaId]);

        $this->service->verwijderUitB($poule->id, $judokaId);

        $bWeds[0]->refresh();
        $bWeds[1]->refresh();
        $this->assertNull($bWeds[0]->judoka_wit_id, 'Judoka moet van wit slot verwijderd zijn');
        $this->assertNull($bWeds[1]->judoka_blauw_id, 'Judoka moet van blauw slot verwijderd zijn');
    }

    // =========================================================================
    // verwijderUitLatereRondes (lines 1608-1651)
    // =========================================================================

    #[Test]
    public function verwijder_uit_latere_rondes_cascade(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find an A-group wedstrijd with volgende_wedstrijd_id
        $bronWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('volgende_wedstrijd_id')
            ->whereNotNull('judoka_wit_id')
            ->first();

        $judokaId = $bronWed->judoka_wit_id;

        // Place this judoka in the next match as winner
        $volgende = Wedstrijd::find($bronWed->volgende_wedstrijd_id);
        $volgende->update([
            'judoka_wit_id' => $judokaId,
            'winnaar_id' => $judokaId,
            'is_gespeeld' => true,
        ]);

        $this->service->verwijderUitLatereRondes($poule->id, 'A', $judokaId, $bronWed->id);

        $volgende->refresh();
        $this->assertNull($volgende->judoka_wit_id, 'Judoka moet verwijderd zijn uit volgende ronde');
        $this->assertNull($volgende->winnaar_id, 'Winnaar moet gereset zijn');
        $this->assertFalse((bool) $volgende->is_gespeeld, 'is_gespeeld moet gereset zijn');
    }

    #[Test]
    public function verwijder_uit_latere_rondes_blauw_slot(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $bronWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('volgende_wedstrijd_id')
            ->whereNotNull('judoka_wit_id')
            ->first();

        $judokaId = $bronWed->judoka_wit_id;

        // Place this judoka on blauw slot in next match
        $volgende = Wedstrijd::find($bronWed->volgende_wedstrijd_id);
        $volgende->update(['judoka_blauw_id' => $judokaId]);

        $this->service->verwijderUitLatereRondes($poule->id, 'A', $judokaId, $bronWed->id);

        $volgende->refresh();
        $this->assertNull($volgende->judoka_blauw_id, 'Judoka moet van blauw slot verwijderd zijn');
    }

    #[Test]
    public function verwijder_uit_latere_rondes_stopt_bij_andere_groep(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find A wedstrijd whose next is also A
        $bronWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('volgende_wedstrijd_id')
            ->first();

        $judokaId = $judokaIds[0];
        $bronFollowUp = Wedstrijd::find($bronWed->volgende_wedstrijd_id);
        $witBefore = $bronFollowUp->judoka_wit_id;
        $blauwBefore = $bronFollowUp->judoka_blauw_id;

        // Cross-group call must not mutate the current group's bracket.
        $this->service->verwijderUitLatereRondes($poule->id, 'B', $judokaId, $bronWed->id);

        $bronFollowUp->refresh();
        $this->assertSame($witBefore, $bronFollowUp->judoka_wit_id);
        $this->assertSame($blauwBefore, $bronFollowUp->judoka_blauw_id);
    }

    // =========================================================================
    // Dubbel eliminatie met enkele rondes (N=12, a1==a2, lines 419-476)
    // =========================================================================

    #[Test]
    public function dubbel_enkele_rondes_12_judokas_met_1_brons(): void
    {
        // N=12: a1=4, a2=4 → enkele rondes. aantalBrons=1 → b_finale (lines 463-476)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12, 'dubbel', 1);
        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel', 1);

        $bFinale = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_finale')
            ->count();

        $this->assertEquals(1, $bFinale, 'Enkele rondes met 1 brons moet b_finale hebben');
    }

    #[Test]
    public function dubbel_enkele_rondes_12_judokas_met_2_brons(): void
    {
        // N=12: a1=4, a2=4 → enkele rondes, 2 brons → GEEN b_finale
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12, 'dubbel', 2);
        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel', 2);

        $bFinale = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_finale')
            ->count();

        $this->assertEquals(0, $bFinale, 'Enkele rondes met 2 brons mag geen b_finale hebben');

        // Should have b_halve_finale_2 as the end
        $bHf2 = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_halve_finale_2')
            ->count();

        $this->assertEquals(2, $bHf2, 'Moet 2 b_halve_finale_2 wedstrijden hebben');
    }

    // =========================================================================
    // Dubbel eliminatie met dubbele rondes (N=16, a1>a2) + 1 brons (line 535)
    // =========================================================================

    #[Test]
    public function dubbel_dubbele_rondes_16_judokas_met_1_brons(): void
    {
        // N=16: a1=8, a2=4 → dubbele rondes. aantalBrons=1 → b_finale (line 535)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel', 1);
        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel', 1);

        $bFinale = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_finale')
            ->count();

        $this->assertEquals(1, $bFinale, 'Dubbele rondes met 1 brons moet b_finale hebben');
    }

    // =========================================================================
    // verwerkUitslag correctie flow (lines 1076-1104)
    // =========================================================================

    #[Test]
    public function verwerkUitslag_correctie_dubbel_verwijdert_oude_winnaar_uit_latere_rondes(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find a kwartfinale with both judokas
        $kf = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->whereNotNull('volgende_wedstrijd_id')
            ->first();

        $oudeWinnaar = $kf->judoka_wit_id;
        $nieuweWinnaar = $kf->judoka_blauw_id;

        // First result
        $kf->update(['is_gespeeld' => true, 'winnaar_id' => $oudeWinnaar]);
        $this->service->verwerkUitslag($kf->fresh(), $oudeWinnaar, null, 'dubbel');

        // Correction
        $kf->update(['winnaar_id' => $nieuweWinnaar]);
        $correcties = $this->service->verwerkUitslag($kf->fresh(), $nieuweWinnaar, $oudeWinnaar, 'dubbel');

        $this->assertNotEmpty($correcties, 'Correctie moet meldingen opleveren');

        // Check that the new winner is in the next match
        $volgende = Wedstrijd::find($kf->volgende_wedstrijd_id);
        $inVolgende = ($volgende->judoka_wit_id == $nieuweWinnaar || $volgende->judoka_blauw_id == $nieuweWinnaar);
        $this->assertTrue($inVolgende, 'Nieuwe winnaar moet in volgende ronde staan');
    }

    // =========================================================================
    // plaatsVerliezerDubbel fallback path (lines 1153-1194)
    // =========================================================================

    #[Test]
    public function plaats_verliezer_dubbel_fallback_zonder_herkansing_id(): void
    {
        // N=12: enkele rondes
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find ANY A-group match with herkansing_wedstrijd_id
        $aWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->whereNotNull('herkansing_wedstrijd_id')
            ->first();

        if (!$aWed) {
            $this->markTestSkipped('No A-wedstrijd with herkansing_wedstrijd_id found');
        }

        $winnaarId = $aWed->judoka_wit_id;
        $verliezerId = $aWed->judoka_blauw_id;

        // Remove herkansing to force fallback path (lines 1153+)
        $aWed->update(['herkansing_wedstrijd_id' => null, 'verliezer_naar_slot' => null]);

        $aWed->update(['is_gespeeld' => true, 'winnaar_id' => $winnaarId]);
        $this->service->verwerkUitslag($aWed->fresh(), $winnaarId, null, 'dubbel');

        // The fallback code path was exercised (lines 1153-1194)
        // Check if verliezer ended up in B-group
        $inBGroep = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where(function ($q) use ($verliezerId) {
                $q->where('judoka_wit_id', $verliezerId)
                  ->orWhere('judoka_blauw_id', $verliezerId);
            })
            ->exists();

        $this->assertTrue($inBGroep, 'Verliezer moet via fallback in B-groep geplaatst zijn');
    }

    // =========================================================================
    // bepaalBRondeVoorVerliezer (lines 1214-1278)
    // =========================================================================

    #[Test]
    public function bepaal_b_ronde_voor_halve_finale_verliezer(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('bepaalBRondeVoorVerliezer', [$poule->id, 'halve_finale']);
        $this->assertEquals('b_halve_finale_2', $result);
    }

    #[Test]
    public function bepaal_b_ronde_voor_kwartfinale_verliezer_dubbel(): void
    {
        // N=16 has dubbel rondes
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('bepaalBRondeVoorVerliezer', [$poule->id, 'kwartfinale']);
        $this->assertEquals('b_kwartfinale_2', $result);
    }

    #[Test]
    public function bepaal_b_ronde_voor_kwartfinale_verliezer_enkel(): void
    {
        // N=12 has enkele rondes (a1==a2)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('bepaalBRondeVoorVerliezer', [$poule->id, 'kwartfinale']);
        $this->assertEquals('b_kwartfinale', $result);
    }

    #[Test]
    public function bepaal_b_ronde_voor_achtste_finale_verliezer(): void
    {
        // N=16: achtste_finale is the first round, B-achtste doesn't exist → falls to B-start
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('bepaalBRondeVoorVerliezer', [$poule->id, 'achtste_finale']);
        // B-achtste_finale doesn't exist for 16 judokas (B starts at kwartfinale), so it should fall through
        $this->assertNotNull($result, 'Moet een B-ronde teruggeven');
    }

    #[Test]
    public function bepaal_b_ronde_voor_eerste_ronde_verliezer(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // kwartfinale is first round for N=8, but test with explicit 'eerste_ronde'
        $result = $this->callPrivate('bepaalBRondeVoorVerliezer', [$poule->id, 'eerste_ronde']);
        $this->assertNotNull($result, 'Moet een B-start ronde teruggeven');
    }

    // =========================================================================
    // vindBStartRonde (lines 1284-1294)
    // =========================================================================

    #[Test]
    public function vind_b_start_ronde(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('vindBStartRonde', [$poule->id]);
        $this->assertNotNull($result);
        $this->assertStringStartsWith('b_', $result);
    }

    #[Test]
    public function vind_b_start_ronde_geen_b_groep(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(3, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // N=3: no B-group
        $result = $this->callPrivate('vindBStartRonde', [$poule->id]);
        $this->assertNull($result);
    }

    // =========================================================================
    // zoekSlotMetTegenstander (lines 1299-1314)
    // =========================================================================

    #[Test]
    public function zoek_slot_met_tegenstander(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Place one judoka in a B-wedstrijd on wit, leave blauw empty
        $bWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->first();

        $bWed->update(['judoka_wit_id' => $judokaIds[0]]);

        $result = $this->callPrivate('zoekSlotMetTegenstander', [$poule->id, $bWed->ronde]);
        $this->assertNotNull($result, 'Moet een half-gevulde wedstrijd vinden');
        $this->assertEquals($bWed->id, $result->id);
    }

    // =========================================================================
    // zoekEersteLegeBSlot (lines 1330-1399)
    // =========================================================================

    #[Test]
    public function zoek_eerste_lege_b_slot_ronde2(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find a _2 ronde
        $ronde2Wed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'like', '%_2')
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->first();

        if (!$ronde2Wed) {
            $this->markTestSkipped('No empty _2 ronde wedstrijd found');
        }

        $result = $this->callPrivate('zoekEersteLegeBSlot', [$poule->id, $ronde2Wed->ronde]);
        $this->assertNotNull($result, 'Moet een lege B-slot vinden in _2 ronde');
    }

    #[Test]
    public function zoek_eerste_lege_b_slot_odd_prioriteit(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Find a non-_2 ronde with empty wedstrijden
        $bRonde = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'not like', '%_2')
            ->whereNull('judoka_wit_id')
            ->first();

        if (!$bRonde) {
            $this->markTestSkipped('No empty non-_2 B-ronde found');
        }

        $result = $this->callPrivate('zoekEersteLegeBSlot', [$poule->id, $bRonde->ronde]);
        $this->assertNotNull($result, 'Moet een lege B-slot vinden');
    }

    // =========================================================================
    // heeftByeGehad (lines 1404-1409)
    // =========================================================================

    #[Test]
    public function heeft_bye_gehad_true(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Create a bye-uitslag for a judoka
        $bWed = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->first();

        $bWed->update([
            'uitslag_type' => 'bye',
            'winnaar_id' => $judokaIds[0],
        ]);

        $result = $this->callPrivate('heeftByeGehad', [$poule->id, $judokaIds[0]]);
        $this->assertTrue($result);
    }

    #[Test]
    public function heeft_bye_gehad_false(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $result = $this->callPrivate('heeftByeGehad', [$poule->id, $judokaIds[0]]);
        $this->assertFalse($result);
    }

    // =========================================================================
    // plaatsVerliezerIJF (lines 1416-1426)
    // =========================================================================

    #[Test]
    public function plaats_verliezer_ijf_via_herkansing(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'ijf');
        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // Find KF with herkansing_wedstrijd_id
        $kf = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->whereNotNull('herkansing_wedstrijd_id')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->first();

        $winnaarId = $kf->judoka_wit_id;
        $verliezerId = $kf->judoka_blauw_id;

        $kf->update(['is_gespeeld' => true, 'winnaar_id' => $winnaarId]);
        $this->service->verwerkUitslag($kf->fresh(), $winnaarId, null, 'ijf');

        $bWed = Wedstrijd::find($kf->herkansing_wedstrijd_id);
        $inB = ($bWed->judoka_wit_id == $verliezerId || $bWed->judoka_blauw_id == $verliezerId);
        $this->assertTrue($inB, 'IJF verliezer moet in B-wedstrijd geplaatst zijn');
    }

    // =========================================================================
    // koppelAVerliezersAanB — diverse paden (lines 644-712)
    // =========================================================================

    #[Test]
    public function dubbel_12_judokas_a_verliezers_gekoppeld_aan_b(): void
    {
        // N=12: enkele rondes (a1==a2=4), A-verliezers via samen_blauw (line 689)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Check that some A-wedstrijden have herkansing_wedstrijd_id set
        $gekoppeld = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('herkansing_wedstrijd_id')
            ->count();

        $this->assertGreaterThan(0, $gekoppeld, 'A-wedstrijden moeten aan B gekoppeld zijn');
    }

    #[Test]
    public function dubbel_16_judokas_latere_rondes_gekoppeld(): void
    {
        // N=16: dubbele rondes, kwartfinale verliezers → b_kwartfinale_2 (lines 707-709)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Check kwartfinale A-wedstrijden are linked to B
        $kwartfinales = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->whereNotNull('herkansing_wedstrijd_id')
            ->get();

        foreach ($kwartfinales as $kf) {
            $bWed = Wedstrijd::find($kf->herkansing_wedstrijd_id);
            $this->assertNotNull($bWed, 'Herkansing wedstrijd moet bestaan');
            $this->assertEquals('B', $bWed->groep, 'Herkansing moet in B-groep zijn');
            $this->assertStringContains('b_kwartfinale', $bWed->ronde);
        }
    }

    #[Test]
    public function dubbel_16_judokas_halve_finale_verliezers_naar_brons(): void
    {
        // N=16: halve finale verliezers → b_halve_finale_2 BLAUW (line 703)
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $halveFinales = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'halve_finale')
            ->whereNotNull('herkansing_wedstrijd_id')
            ->get();

        foreach ($halveFinales as $hf) {
            $bWed = Wedstrijd::find($hf->herkansing_wedstrijd_id);
            $this->assertEquals('b_halve_finale_2', $bWed->ronde, 'HF verliezer moet naar b_halve_finale_2');
            $this->assertEquals('blauw', $hf->verliezer_naar_slot, 'HF verliezer naar BLAUW slot');
        }
    }

    // =========================================================================
    // Bracket met exact 4 judokas (geen B-groep)
    // =========================================================================

    #[Test]
    public function bracket_4_judokas_geen_b_groep(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(4, 'dubbel');
        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();
        $this->assertEquals(0, $bWedstrijden, 'Bij 4 judokas mag geen B-groep zijn (n < 5)');
    }

    // =========================================================================
    // IJF met 1 brons structuur (line 896-916)
    // =========================================================================

    #[Test]
    public function ijf_met_1_brons_b_halve_finale_2_gekoppeld_aan_b_brons(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'ijf', 1);
        $this->service->genereerBracket($poule, $judokaIds, 'ijf', 1);

        $bHf2 = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_halve_finale_2')
            ->get();

        $bBrons = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_brons')
            ->first();

        $this->assertNotNull($bBrons, 'b_brons moet bestaan bij 1 brons');
        foreach ($bHf2 as $hf2) {
            $this->assertEquals($bBrons->id, $hf2->volgende_wedstrijd_id, 'B-1/2(2) moet naar b_brons linken');
        }
    }

    // =========================================================================
    // genereerBracket leest aantal_brons uit toernooi (line 85)
    // =========================================================================

    #[Test]
    public function genereer_bracket_default_aantal_brons_is_2(): void
    {
        // When aantalBrons=null and poule has no mat->blok->toernooi chain, defaults to 2
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel', null);

        $this->assertEquals(2, $result['aantal_brons'], 'Default aantal_brons moet 2 zijn');
    }

    // =========================================================================
    // Dubbel 24 judokas (large bracket with zestiende_finale)
    // =========================================================================

    #[Test]
    public function dubbel_24_judokas_correct_bracket(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(24, 'dubbel');
        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $this->assertEquals('dubbel', $result['type']);
        $this->assertEquals(23, $result['a_wedstrijden']); // N-1

        // Should have zestiende_finale in A-group
        $zestiende = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'zestiende_finale')
            ->count();

        $this->assertGreaterThan(0, $zestiende, 'N=24 moet zestiende_finale hebben');
    }

    // =========================================================================
    // Helper assertion
    // =========================================================================

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
