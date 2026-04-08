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
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class EliminatieServiceExtraTest extends TestCase
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

    private function createPouleWithJudokas(int $aantal, string $type = 'ijf', int $aantalBrons = 2): array
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
    // berekenLocaties (private)
    // =========================================================================

    #[Test]
    public function bereken_locaties_positie_1(): void
    {
        $result = $this->callPrivate('berekenLocaties', [1]);
        $this->assertEquals(1, $result['locatie_wit']);
        $this->assertEquals(2, $result['locatie_blauw']);
    }

    #[Test]
    public function bereken_locaties_positie_4(): void
    {
        $result = $this->callPrivate('berekenLocaties', [4]);
        $this->assertEquals(7, $result['locatie_wit']);
        $this->assertEquals(8, $result['locatie_blauw']);
    }

    #[Test]
    public function bereken_locaties_positie_8(): void
    {
        $result = $this->callPrivate('berekenLocaties', [8]);
        $this->assertEquals(15, $result['locatie_wit']);
        $this->assertEquals(16, $result['locatie_blauw']);
    }

    // =========================================================================
    // berekenDoel (private)
    // =========================================================================

    #[Test]
    public function bereken_doel_machten_van_2(): void
    {
        $this->assertEquals(8, $this->callPrivate('berekenDoel', [8]));
        $this->assertEquals(16, $this->callPrivate('berekenDoel', [16]));
        $this->assertEquals(32, $this->callPrivate('berekenDoel', [32]));
    }

    #[Test]
    public function bereken_doel_niet_machten_van_2(): void
    {
        $this->assertEquals(8, $this->callPrivate('berekenDoel', [12]));
        $this->assertEquals(16, $this->callPrivate('berekenDoel', [20]));
        $this->assertEquals(16, $this->callPrivate('berekenDoel', [24]));
    }

    #[Test]
    public function bereken_doel_edge_cases(): void
    {
        $this->assertEquals(0, $this->callPrivate('berekenDoel', [0]));
        $this->assertEquals(1, $this->callPrivate('berekenDoel', [1]));
        $this->assertEquals(2, $this->callPrivate('berekenDoel', [2]));
        $this->assertEquals(2, $this->callPrivate('berekenDoel', [3]));
        $this->assertEquals(4, $this->callPrivate('berekenDoel', [5]));
    }

    // =========================================================================
    // berekenMinimaleBWedstrijden (private)
    // =========================================================================

    #[Test]
    public function bereken_minimale_b_wedstrijden(): void
    {
        $this->assertEquals(2, $this->callPrivate('berekenMinimaleBWedstrijden', [3]));
        $this->assertEquals(2, $this->callPrivate('berekenMinimaleBWedstrijden', [4]));
        $this->assertEquals(4, $this->callPrivate('berekenMinimaleBWedstrijden', [5]));
        $this->assertEquals(4, $this->callPrivate('berekenMinimaleBWedstrijden', [8]));
        $this->assertEquals(8, $this->callPrivate('berekenMinimaleBWedstrijden', [9]));
        $this->assertEquals(8, $this->callPrivate('berekenMinimaleBWedstrijden', [16]));
        $this->assertEquals(16, $this->callPrivate('berekenMinimaleBWedstrijden', [17]));
        $this->assertEquals(16, $this->callPrivate('berekenMinimaleBWedstrijden', [32]));
        $this->assertEquals(32, $this->callPrivate('berekenMinimaleBWedstrijden', [33]));
    }

    // =========================================================================
    // berekenBracketParams (private)
    // =========================================================================

    #[Test]
    public function bereken_bracket_params_exact_power_of_2(): void
    {
        $params = $this->callPrivate('berekenBracketParams', [8]);

        $this->assertEquals(8, $params['d']);
        $this->assertEquals(0, $params['v1']);
        $this->assertEquals(4, $params['a1Verliezers']);
        $this->assertEquals(2, $params['a2Verliezers']);
        $this->assertTrue($params['dubbelRondes']); // a1(4) > a2(2)
    }

    #[Test]
    public function bereken_bracket_params_n16(): void
    {
        $params = $this->callPrivate('berekenBracketParams', [16]);

        $this->assertEquals(16, $params['d']);
        $this->assertEquals(0, $params['v1']);
        $this->assertEquals(8, $params['a1Verliezers']);
        $this->assertEquals(4, $params['a2Verliezers']);
        $this->assertTrue($params['dubbelRondes']); // 8 > 4
    }

    #[Test]
    public function bereken_bracket_params_non_power_of_2(): void
    {
        $params = $this->callPrivate('berekenBracketParams', [12]);

        $this->assertEquals(8, $params['d']);
        $this->assertEquals(4, $params['v1']); // 12 - 8 = 4 echte wedstrijden eerste ronde
        $this->assertEquals(4, $params['a1Verliezers']);
        $this->assertEquals(4, $params['a2Verliezers']);
        $this->assertFalse($params['dubbelRondes']); // 4 == 4
    }

    // =========================================================================
    // getRondeNaam (private)
    // =========================================================================

    #[Test]
    public function get_ronde_naam_totaal_judokas(): void
    {
        $this->assertEquals('halve_finale', $this->callPrivate('getRondeNaam', [3, false]));
        $this->assertEquals('kwartfinale', $this->callPrivate('getRondeNaam', [5, false]));
        $this->assertEquals('achtste_finale', $this->callPrivate('getRondeNaam', [9, false]));
        $this->assertEquals('zestiende_finale', $this->callPrivate('getRondeNaam', [17, false]));
        $this->assertEquals('tweeendertigste_finale', $this->callPrivate('getRondeNaam', [33, false]));
    }

    #[Test]
    public function get_ronde_naam_voor_aantal(): void
    {
        $this->assertEquals('finale', $this->callPrivate('getRondeNaam', [2, true]));
        $this->assertEquals('halve_finale', $this->callPrivate('getRondeNaam', [4, true]));
        $this->assertEquals('kwartfinale', $this->callPrivate('getRondeNaam', [8, true]));
        $this->assertEquals('achtste_finale', $this->callPrivate('getRondeNaam', [16, true]));
        $this->assertEquals('zestiende_finale', $this->callPrivate('getRondeNaam', [32, true]));
    }

    // =========================================================================
    // getBRondeNaam (private)
    // =========================================================================

    #[Test]
    public function get_b_ronde_naam(): void
    {
        $this->assertEquals('b_halve_finale', $this->callPrivate('getBRondeNaam', [2]));
        $this->assertEquals('b_kwartfinale', $this->callPrivate('getBRondeNaam', [4]));
        $this->assertEquals('b_achtste_finale', $this->callPrivate('getBRondeNaam', [8]));
        $this->assertEquals('b_zestiende_finale', $this->callPrivate('getBRondeNaam', [16]));
        $this->assertEquals('b_kwartfinale', $this->callPrivate('getBRondeNaam', [99])); // default
    }

    // =========================================================================
    // DUBBEL ELIMINATIE BRACKET
    // =========================================================================

    #[Test]
    public function dubbel_genereert_correct_voor_8_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $this->assertEquals('dubbel', $result['type']);
        $this->assertEquals(2, $result['aantal_brons']);

        // Dubbel: totaal = 2N - 5 = 11
        $totaal = Wedstrijd::where('poule_id', $poule->id)->count();
        $this->assertEquals(11, $totaal);
    }

    #[Test]
    public function dubbel_genereert_correct_voor_16_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16, 'dubbel');

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Dubbel: totaal = 2*16 - 5 = 27
        $totaal = Wedstrijd::where('poule_id', $poule->id)->count();
        $this->assertEquals(27, $totaal);
    }

    #[Test]
    public function dubbel_met_1_brons(): void
    {
        [$poule, $judokaIds, $toernooi] = $this->createPouleWithJudokas(8, 'dubbel', 1);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel', 1);

        // 1 brons: moet b_finale hebben
        $bFinale = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_finale')
            ->count();

        $this->assertEquals(1, $bFinale, 'Bij 1 brons moet er een B-finale zijn');
    }

    #[Test]
    public function bracket_met_minder_dan_2_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(1);

        $result = $this->service->genereerBracket($poule, [$judokaIds[0]], 'dubbel');

        $this->assertEquals(0, $result['totaal_wedstrijden']);
    }

    #[Test]
    public function bracket_met_precies_2_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(2);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // 2 judokas: 1 finale, geen B-groep (n < 5)
        $aWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'A')->count();
        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();

        $this->assertEquals(1, $aWedstrijden);
        $this->assertEquals(0, $bWedstrijden);
    }

    #[Test]
    public function bracket_met_5_judokas_heeft_b_groep(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(5);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();
        $this->assertGreaterThan(0, $bWedstrijden, 'Bij 5 judokas moet er een B-groep zijn');
    }

    // =========================================================================
    // berekenStatistieken — extra sizes
    // =========================================================================

    #[Test]
    public function bereken_statistieken_5_judokas_dubbel(): void
    {
        $stats = $this->service->berekenStatistieken(5, 'dubbel');

        $this->assertEquals(5, $stats['judokas']);
        $this->assertEquals('dubbel', $stats['type']);
        $this->assertEquals(4, $stats['a_wedstrijden']);
        $this->assertEquals(5, $stats['totaal_wedstrijden']); // 2*5-5 = 5
    }

    #[Test]
    public function bereken_statistieken_returns_all_keys(): void
    {
        $stats = $this->service->berekenStatistieken(8, 'dubbel');

        $this->assertArrayHasKey('judokas', $stats);
        $this->assertArrayHasKey('type', $stats);
        $this->assertArrayHasKey('doel', $stats);
        $this->assertArrayHasKey('v1', $stats);
        $this->assertArrayHasKey('a1_verliezers', $stats);
        $this->assertArrayHasKey('a2_verliezers', $stats);
        $this->assertArrayHasKey('eerste_golf', $stats);
        $this->assertArrayHasKey('b_start_wedstrijden', $stats);
        $this->assertArrayHasKey('a_wedstrijden', $stats);
        $this->assertArrayHasKey('b_wedstrijden', $stats);
        $this->assertArrayHasKey('totaal_wedstrijden', $stats);
        $this->assertArrayHasKey('eerste_ronde', $stats);
        $this->assertArrayHasKey('a_byes', $stats);
        $this->assertArrayHasKey('b_byes', $stats);
        $this->assertArrayHasKey('dubbel_rondes', $stats);
    }

    #[Test]
    public function bereken_statistieken_eerste_ronde_naam(): void
    {
        $this->assertEquals('kwartfinale', $this->service->berekenStatistieken(8, 'dubbel')['eerste_ronde']);
        $this->assertEquals('achtste_finale', $this->service->berekenStatistieken(16, 'ijf')['eerste_ronde']);
        $this->assertEquals('achtste_finale', $this->service->berekenStatistieken(12, 'dubbel')['eerste_ronde']);
    }

    // =========================================================================
    // UITSLAG VERWERKING — DUBBEL
    // =========================================================================

    #[Test]
    public function dubbel_verliezer_wordt_in_b_groep_geplaatst(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'dubbel');
        $this->service->genereerBracket($poule, $judokaIds, 'dubbel');

        // Speel een eerste-ronde wedstrijd
        $eersteWedstrijd = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->first();

        $winnaarId = $eersteWedstrijd->judoka_wit_id;
        $verliezerId = $eersteWedstrijd->judoka_blauw_id;

        $eersteWedstrijd->update(['is_gespeeld' => true, 'winnaar_id' => $winnaarId]);
        $this->service->verwerkUitslag($eersteWedstrijd, $winnaarId, null, 'dubbel');

        // Verliezer moet ergens in B-groep staan
        $inBGroep = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where(function ($q) use ($verliezerId) {
                $q->where('judoka_wit_id', $verliezerId)
                  ->orWhere('judoka_blauw_id', $verliezerId);
            })
            ->exists();

        $this->assertTrue($inBGroep, 'Verliezer moet in B-groep geplaatst zijn');
    }

    #[Test]
    public function verwerkUitslag_returns_empty_correcties_for_new_result(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'ijf');
        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $kf = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->first();

        $winnaarId = $kf->judoka_wit_id;
        $kf->update(['is_gespeeld' => true, 'winnaar_id' => $winnaarId]);
        $correcties = $this->service->verwerkUitslag($kf, $winnaarId, null, 'ijf');

        // First-time result has no corrections
        $this->assertEmpty($correcties, 'Eerste uitslag mag geen correcties opleveren');
    }

    // =========================================================================
    // IJF met 1 brons
    // =========================================================================

    #[Test]
    public function ijf_met_1_brons_heeft_extra_wedstrijd(): void
    {
        [$poule, $judokaIds, $toernooi] = $this->createPouleWithJudokas(8, 'ijf', 1);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'ijf', 1);

        // Met 1 brons: b_brons wedstrijd moet bestaan
        $bBrons = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'b_brons')
            ->count();

        $this->assertEquals(1, $bBrons, 'IJF met 1 brons moet b_brons wedstrijd hebben');
    }

    // =========================================================================
    // genereerBracket verwijdert oude wedstrijden
    // =========================================================================

    #[Test]
    public function genereer_bracket_verwijdert_bestaande_wedstrijden(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        // Genereer eerste keer
        $this->service->genereerBracket($poule, $judokaIds, 'ijf');
        $eerste = Wedstrijd::where('poule_id', $poule->id)->count();

        // Genereer opnieuw — moet oude verwijderen
        $this->service->genereerBracket($poule, $judokaIds, 'ijf');
        $tweede = Wedstrijd::where('poule_id', $poule->id)->count();

        $this->assertEquals($eerste, $tweede, 'Hergenerate moet zelfde aantal opleveren');
    }

    // =========================================================================
    // verwerkUitslag met correctie (oude winnaar != nieuwe winnaar)
    // =========================================================================

    #[Test]
    public function verwerkUitslag_correctie_verwijdert_oude_winnaar(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8, 'ijf');
        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $kf = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->whereNotNull('judoka_wit_id')
            ->whereNotNull('judoka_blauw_id')
            ->first();

        $oudeWinnaar = $kf->judoka_wit_id;
        $nieuweWinnaar = $kf->judoka_blauw_id;

        // Eerst: oude winnaar
        $kf->update(['is_gespeeld' => true, 'winnaar_id' => $oudeWinnaar]);
        $this->service->verwerkUitslag($kf, $oudeWinnaar, null, 'ijf');

        // Correctie: nieuwe winnaar
        $kf->update(['winnaar_id' => $nieuweWinnaar]);
        $correcties = $this->service->verwerkUitslag($kf, $nieuweWinnaar, $oudeWinnaar, 'ijf');

        $this->assertNotEmpty($correcties, 'Correctie moet meldingen opleveren');
    }

    // =========================================================================
    // A-groep byes bij niet-machten van 2
    // =========================================================================

    #[Test]
    public function bracket_12_judokas_heeft_correcte_byes(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // N=12, D=8: eerste ronde heeft 4 echte wedstrijden + 4 byes
        $eersteRonde = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'achtste_finale')
            ->get();

        $echte = $eersteRonde->filter(fn($w) => $w->judoka_wit_id && $w->judoka_blauw_id)->count();
        $byes = $eersteRonde->filter(fn($w) => $w->judoka_wit_id && !$w->judoka_blauw_id)->count();

        $this->assertEquals(4, $echte, 'N=12: 4 echte wedstrijden in eerste ronde');
        $this->assertEquals(4, $byes, 'N=12: 4 byes in eerste ronde');
    }
}
