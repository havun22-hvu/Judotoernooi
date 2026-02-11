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
use Tests\TestCase;

class EliminatieServiceTest extends TestCase
{
    use RefreshDatabase;

    private EliminatieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EliminatieService();
    }

    private function createPouleWithJudokas(int $aantal): array
    {
        $toernooi = Toernooi::factory()->create(['eliminatie_type' => 'ijf', 'aantal_brons' => 2]);
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
    // IJF REPECHAGE - BRACKET GENERATIE
    // =========================================================================

    #[Test]
    public function ijf_genereert_correct_aantal_wedstrijden_voor_8_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // N=8: A=7 wedstrijden, B=4 (2 repechage + 2 brons) = 11 totaal
        $aWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'A')->count();
        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();

        $this->assertEquals(7, $aWedstrijden, 'A-groep moet N-1=7 wedstrijden hebben');
        $this->assertEquals(4, $bWedstrijden, 'B-groep moet 4 wedstrijden hebben (2 repechage + 2 brons)');
    }

    #[Test]
    public function ijf_genereert_correct_aantal_wedstrijden_voor_16_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $aWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'A')->count();
        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();

        // N=16: A=15, B=4 = 19 totaal
        $this->assertEquals(15, $aWedstrijden, 'A-groep moet N-1=15 wedstrijden hebben');
        $this->assertEquals(4, $bWedstrijden, 'B-groep moet altijd 4 wedstrijden hebben bij IJF');
    }

    #[Test]
    public function ijf_genereert_correct_aantal_wedstrijden_voor_12_judokas(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(12);

        $result = $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $aWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'A')->count();
        $bWedstrijden = Wedstrijd::where('poule_id', $poule->id)->where('groep', 'B')->count();

        // N=12: B=4 altijd bij IJF, A bevat ook bye-entries (D=8 eerste ronde entries + 4 KF + 2 HF + 1 F)
        $this->assertEquals(4, $bWedstrijden, 'B-groep moet 4 wedstrijden hebben bij IJF');
        $this->assertGreaterThanOrEqual(11, $aWedstrijden, 'A-groep moet minstens N-1 entries hebben');
    }

    #[Test]
    public function ijf_b_groep_heeft_juiste_rondes(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $bRondes = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->pluck('ronde')
            ->sort()
            ->values()
            ->toArray();

        $this->assertContains('b_repechage_1', $bRondes);
        $this->assertContains('b_repechage_2', $bRondes);
        $this->assertContains('b_brons_1', $bRondes);
        $this->assertContains('b_brons_2', $bRondes);
        $this->assertCount(4, $bRondes, 'IJF B-groep moet exact 4 wedstrijden hebben');
    }

    #[Test]
    public function ijf_repechage_winnaars_gaan_naar_brons(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // Repechage wedstrijden moeten gekoppeld zijn aan brons wedstrijden
        $repechages = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', 'like', 'b_repechage_%')
            ->get();

        foreach ($repechages as $repechage) {
            $this->assertNotNull($repechage->volgende_wedstrijd_id, "Repechage {$repechage->ronde} moet gekoppeld zijn aan brons");
            $this->assertEquals('wit', $repechage->winnaar_naar_slot, 'Repechage winnaar moet op WIT slot komen');

            $bronsWedstrijd = Wedstrijd::find($repechage->volgende_wedstrijd_id);
            $this->assertNotNull($bronsWedstrijd);
            $this->assertStringStartsWith('b_brons_', $bronsWedstrijd->ronde);
        }
    }

    #[Test]
    public function ijf_kwartfinale_verliezers_gekoppeld_aan_repechage(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // Kwartfinale wedstrijden moeten herkansing_wedstrijd_id hebben
        $kwartfinales = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->orderBy('bracket_positie')
            ->get();

        $this->assertCount(4, $kwartfinales, 'Er moeten 4 kwartfinales zijn');

        // Pos 1+3 → repechage 1, Pos 2+4 → repechage 2
        $this->assertNotNull($kwartfinales[0]->herkansing_wedstrijd_id, 'KF 1 moet aan repechage gekoppeld zijn');
        $this->assertNotNull($kwartfinales[1]->herkansing_wedstrijd_id, 'KF 2 moet aan repechage gekoppeld zijn');
        $this->assertNotNull($kwartfinales[2]->herkansing_wedstrijd_id, 'KF 3 moet aan repechage gekoppeld zijn');
        $this->assertNotNull($kwartfinales[3]->herkansing_wedstrijd_id, 'KF 4 moet aan repechage gekoppeld zijn');

        // KF 1 en KF 3 moeten naar dezelfde repechage gaan
        $this->assertEquals(
            $kwartfinales[0]->herkansing_wedstrijd_id,
            $kwartfinales[2]->herkansing_wedstrijd_id,
            'KF 1 en KF 3 moeten naar dezelfde repechage gaan'
        );

        // KF 2 en KF 4 moeten naar dezelfde repechage gaan
        $this->assertEquals(
            $kwartfinales[1]->herkansing_wedstrijd_id,
            $kwartfinales[3]->herkansing_wedstrijd_id,
            'KF 2 en KF 4 moeten naar dezelfde repechage gaan'
        );
    }

    #[Test]
    public function ijf_halve_finale_verliezers_gekoppeld_aan_brons(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $halveFinales = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'halve_finale')
            ->orderBy('bracket_positie')
            ->get();

        $this->assertCount(2, $halveFinales, 'Er moeten 2 halve finales zijn');

        foreach ($halveFinales as $hf) {
            $this->assertNotNull($hf->herkansing_wedstrijd_id, "HF {$hf->bracket_positie} moet aan brons gekoppeld zijn");
            $this->assertEquals('blauw', $hf->verliezer_naar_slot, 'HF verliezer moet op BLAUW slot (repechage winnaar op wit)');

            $bronsWedstrijd = Wedstrijd::find($hf->herkansing_wedstrijd_id);
            $this->assertStringStartsWith('b_brons_', $bronsWedstrijd->ronde);
        }
    }

    // =========================================================================
    // IJF vs DUBBEL VERGELIJKING
    // =========================================================================

    #[Test]
    public function ijf_heeft_minder_wedstrijden_dan_dubbel(): void
    {
        // N=16 zodat verschil duidelijk is (IJF=19, Dubbel=27)
        [$pouleIjf, $judokaIdsIjf] = $this->createPouleWithJudokas(16);
        $this->service->genereerBracket($pouleIjf, $judokaIdsIjf, 'ijf');
        $ijfTotaal = Wedstrijd::where('poule_id', $pouleIjf->id)->count();

        [$pouleDubbel, $judokaIdsDubbel] = $this->createPouleWithJudokas(16);
        $this->service->genereerBracket($pouleDubbel, $judokaIdsDubbel, 'dubbel');
        $dubbelTotaal = Wedstrijd::where('poule_id', $pouleDubbel->id)->count();

        $this->assertLessThan($dubbelTotaal, $ijfTotaal, 'IJF moet minder wedstrijden genereren dan dubbel eliminatie');
    }

    // =========================================================================
    // STATISTIEKEN
    // =========================================================================

    #[Test]
    public function berekenStatistieken_ijf_formule_klopt(): void
    {
        // IJF: totaal = N - 1 + 4 = N + 3
        foreach ([8, 12, 16, 32] as $n) {
            $stats = $this->service->berekenStatistieken($n, 'ijf');
            $this->assertEquals($n + 3, $stats['totaal_wedstrijden'], "IJF met N={$n}: totaal moet N+3=" . ($n + 3) . " zijn");
            $this->assertEquals(4, $stats['b_wedstrijden'], "IJF B-wedstrijden moet altijd 4 zijn (N={$n})");
            $this->assertEquals($n - 1, $stats['a_wedstrijden'], "IJF A-wedstrijden moet N-1 zijn (N={$n})");
        }
    }

    #[Test]
    public function berekenStatistieken_dubbel_formule_klopt(): void
    {
        // Dubbel: totaal = 2N - 5 (met 2 brons)
        foreach ([8, 12, 16, 32] as $n) {
            $stats = $this->service->berekenStatistieken($n, 'dubbel');
            $this->assertEquals(2 * $n - 5, $stats['totaal_wedstrijden'], "Dubbel met N={$n}: totaal moet 2N-5=" . (2 * $n - 5) . " zijn");
        }
    }

    // =========================================================================
    // UITSLAG VERWERKING IJF
    // =========================================================================

    #[Test]
    public function ijf_verliezer_kwartfinale_wordt_in_repechage_geplaatst(): void
    {
        [$poule, $judokaIds] = $this->createPouleWithJudokas(8);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        // Speel een kwartfinale
        $kf = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'kwartfinale')
            ->orderBy('bracket_positie')
            ->first();

        $this->assertNotNull($kf->judoka_wit_id, 'KF moet judoka op wit hebben');
        $this->assertNotNull($kf->judoka_blauw_id, 'KF moet judoka op blauw hebben');

        $winnaarId = $kf->judoka_wit_id;
        $verliezerId = $kf->judoka_blauw_id;

        // Verwerk uitslag
        $kf->update(['is_gespeeld' => true, 'winnaar_id' => $winnaarId]);
        $this->service->verwerkUitslag($kf, $winnaarId, null, 'ijf');

        // Verliezer moet in repechage staan
        $repechage = Wedstrijd::find($kf->herkansing_wedstrijd_id);
        $this->assertNotNull($repechage, 'Repechage wedstrijd moet bestaan');

        $verliezerInRepechage = ($repechage->judoka_wit_id === $verliezerId || $repechage->judoka_blauw_id === $verliezerId);
        $this->assertTrue($verliezerInRepechage, 'Verliezer van KF moet in repechage geplaatst zijn');
    }

    #[Test]
    public function ijf_verliezer_eerste_ronde_krijgt_geen_herkansing(): void
    {
        // Bij N=16 zijn er 1/8 finales - verliezers daarvan krijgen GEEN repechage
        [$poule, $judokaIds] = $this->createPouleWithJudokas(16);

        $this->service->genereerBracket($poule, $judokaIds, 'ijf');

        $eersteRonde = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->where('ronde', 'achtste_finale')
            ->get();

        foreach ($eersteRonde as $wedstrijd) {
            // Bij IJF: alleen kwartfinale verliezers krijgen repechage
            // Eerste ronde verliezers zouden GEEN herkansing_wedstrijd_id moeten hebben
            // (tenzij het systeem dit anders implementeert)
            $this->assertNull(
                $wedstrijd->herkansing_wedstrijd_id,
                "1/8 finale verliezers mogen GEEN herkansing hebben bij IJF"
            );
        }
    }
}
