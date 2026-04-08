<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use App\Services\ToernooiService;
use App\Services\WimpelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WimpelToernooiCoverageTest extends TestCase
{
    use RefreshDatabase;

    private WimpelService $wimpelService;
    private ToernooiService $toernooiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wimpelService = new WimpelService();
        $this->toernooiService = new ToernooiService();
    }

    // =========================================================================
    // WimpelService — verwerkToernooi (lines 23-38)
    // =========================================================================

    #[Test]
    public function verwerk_toernooi_processes_all_punten_competitie_poules(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        // Create 2 punten_competitie poules with matches
        for ($p = 0; $p < 2; $p++) {
            $poule = Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'categorie_key' => 'standaard',
            ]);

            $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
            $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

            Wedstrijd::factory()->gespeeld()->create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $judoka1->id,
                'judoka_blauw_id' => $judoka2->id,
                'winnaar_id' => $judoka1->id,
            ]);
        }

        $result = $this->wimpelService->verwerkToernooi($toernooi);

        $this->assertIsArray($result);
        // Should have created punten logs for both poules
        $this->assertEquals(2, WimpelPuntenLog::where('toernooi_id', $toernooi->id)->count());
    }

    #[Test]
    public function verwerk_toernooi_returns_milestone_warnings(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        // Create milestone at 1 point
        WimpelMilestone::create([
            'organisator_id' => $org->id,
            'punten' => 1,
            'omschrijving' => 'Eerste wimpel',
            'volgorde' => 1,
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'winnaar_id' => $judoka1->id,
        ]);

        $result = $this->wimpelService->verwerkToernooi($toernooi);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('judoka', $result[0]);
        $this->assertArrayHasKey('milestones', $result[0]);
    }

    #[Test]
    public function verwerk_toernooi_skips_non_punten_competitie_poules(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'poules'],
        ]);

        $result = $this->wimpelService->verwerkToernooi($toernooi);

        $this->assertEmpty($result);
        $this->assertEquals(0, WimpelPuntenLog::count());
    }

    // =========================================================================
    // WimpelService — verwerkPoule edge cases (lines 49, 66, 73, 84, 111-115)
    // =========================================================================

    #[Test]
    public function verwerk_poule_returns_empty_for_non_punten_competitie(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'poules'],
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $result = $this->wimpelService->verwerkPoule($poule);

        $this->assertEmpty($result);
    }

    #[Test]
    public function verwerk_poule_skips_unplayed_matches(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        // Unplayed match — no winnaar, not gespeeld
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);

        $result = $this->wimpelService->verwerkPoule($poule);

        // No wins → empty result (line 73)
        $this->assertEmpty($result);
    }

    #[Test]
    public function verwerk_poule_skips_nonexistent_judoka(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        // Create a valid played match first
        $wedstrijd = Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'winnaar_id' => $judoka1->id,
        ]);

        // Now delete the winning judoka so it can't be found in the whereIn query (line 84: continue)
        $judoka1->forceDelete();

        $result = $this->wimpelService->verwerkPoule($poule);

        // The judoka doesn't exist anymore so the loop continues past it (line 84)
        // No StamJudoka should have been created for the deleted judoka
        $this->assertEquals(0, StamJudoka::count());
    }

    #[Test]
    public function verwerk_poule_tracks_nieuwe_judokas(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $judoka1 = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Pietje Pansen',
            'geboortejaar' => 2016,
        ]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'winnaar_id' => $judoka1->id,
        ]);

        $result = $this->wimpelService->verwerkPoule($poule);

        // New stam_judoka should appear in nieuwe_judokas
        $this->assertNotEmpty($result['nieuwe_judokas']);
        $this->assertEquals('Pietje Pansen', $result['nieuwe_judokas'][0]['naam']);
    }

    // =========================================================================
    // WimpelService — matchJudoka existing with 0 punten (line 157)
    // =========================================================================

    #[Test]
    public function match_judoka_marks_existing_zero_punten_as_nieuw(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        // Existing stam_judoka with 0 punten and wimpel_is_nieuw = false
        $existing = StamJudoka::factory()->create([
            'organisator_id' => $org->id,
            'naam' => 'Klaas de Groot',
            'geboortejaar' => 2015,
            'wimpel_punten_totaal' => 0,
            'wimpel_is_nieuw' => false,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Klaas de Groot',
            'geboortejaar' => 2015,
        ]);

        $stam = $this->wimpelService->matchJudoka($org, $judoka);

        $this->assertFalse($stam->wasRecentlyCreated);
        $this->assertEquals($existing->id, $stam->id);
        $this->assertTrue($stam->fresh()->wimpel_is_nieuw);
    }

    // =========================================================================
    // WimpelService — isAlVerwerkt (lines 168-174)
    // =========================================================================

    #[Test]
    public function is_al_verwerkt_returns_true_when_no_pc_poules(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'poules'],
        ]);

        // No punten_competitie poules
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $this->assertTrue($this->wimpelService->isAlVerwerkt($toernooi));
    }

    #[Test]
    public function is_al_verwerkt_returns_false_when_unprocessed_poules(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $this->assertFalse($this->wimpelService->isAlVerwerkt($toernooi));
    }

    #[Test]
    public function is_al_verwerkt_returns_true_when_all_processed(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'winnaar_id' => $judoka1->id,
        ]);

        $this->wimpelService->verwerkPoule($poule);

        $this->assertTrue($this->wimpelService->isAlVerwerkt($toernooi));
    }

    // =========================================================================
    // WimpelService — getOnverwerkteToernooien (lines 234-247)
    // =========================================================================

    #[Test]
    public function get_onverwerkte_toernooien_returns_unprocessed_tournaments(): void
    {
        $org = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        // Tournament 1: unprocessed with played matches
        $toernooi1 = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        // Attach to organisator via pivot
        $org->toernooien()->attach($toernooi1->id, ['rol' => 'eigenaar']);

        $poule1 = Poule::factory()->create([
            'toernooi_id' => $toernooi1->id,
            'categorie_key' => 'standaard',
        ]);
        $j1 = Judoka::factory()->create(['toernooi_id' => $toernooi1->id, 'club_id' => $club->id]);
        $j2 = Judoka::factory()->create(['toernooi_id' => $toernooi1->id, 'club_id' => $club->id]);
        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule1->id,
            'judoka_wit_id' => $j1->id,
            'judoka_blauw_id' => $j2->id,
            'winnaar_id' => $j1->id,
        ]);

        // Tournament 2: already processed
        $toernooi2 = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $org->toernooien()->attach($toernooi2->id, ['rol' => 'eigenaar']);

        $poule2 = Poule::factory()->create([
            'toernooi_id' => $toernooi2->id,
            'categorie_key' => 'standaard',
        ]);
        $j3 = Judoka::factory()->create(['toernooi_id' => $toernooi2->id, 'club_id' => $club->id]);
        $j4 = Judoka::factory()->create(['toernooi_id' => $toernooi2->id, 'club_id' => $club->id]);
        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule2->id,
            'judoka_wit_id' => $j3->id,
            'judoka_blauw_id' => $j4->id,
            'winnaar_id' => $j3->id,
        ]);
        $this->wimpelService->verwerkPoule($poule2);

        $result = $this->wimpelService->getOnverwerkteToernooien($org);

        $this->assertCount(1, $result);
        $this->assertEquals($toernooi1->id, $result->first()->id);
    }

    #[Test]
    public function get_onverwerkte_toernooien_excludes_no_played_matches(): void
    {
        $org = Organisator::factory()->create();

        // Tournament with no played matches in PC poules
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'standaard',
        ]);

        $result = $this->wimpelService->getOnverwerkteToernooien($org);

        $this->assertCount(0, $result);
    }

    // =========================================================================
    // ToernooiService — syncBlokken (lines 114-129)
    // =========================================================================

    #[Test]
    public function sync_blokken_adds_missing_blocks(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_blokken' => 4]);

        // Create only 2 blocks
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $result = $this->toernooiService->syncBlokken($toernooi);

        $this->assertEquals(4, $toernooi->blokken()->count());
        $this->assertEquals(0, $result['verplaatste_poules']);
    }

    #[Test]
    public function sync_blokken_removes_excess_blocks_and_moves_poules(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_blokken' => 2]);

        // Create 4 blocks
        $blok1 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $blok2 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);
        $blok3 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 3]);
        $blok4 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 4]);

        // Put a poule in block 3 (to be removed)
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok3->id,
        ]);

        $result = $this->toernooiService->syncBlokken($toernooi);

        $this->assertEquals(2, $toernooi->blokken()->count());
        $this->assertEquals(1, $result['verplaatste_poules']);
        // Poule should be moved to sleepvak (null)
        $this->assertNull($poule->fresh()->blok_id);
    }

    #[Test]
    public function sync_blokken_no_change_when_equal(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_blokken' => 2]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $result = $this->toernooiService->syncBlokken($toernooi);

        $this->assertEquals(2, $toernooi->blokken()->count());
        $this->assertEquals(0, $result['verplaatste_poules']);
    }

    // =========================================================================
    // ToernooiService — syncMatten (lines 171-176)
    // =========================================================================

    #[Test]
    public function sync_matten_adds_missing_mats(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_matten' => 4]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $this->toernooiService->syncMatten($toernooi);

        $this->assertEquals(4, $toernooi->matten()->count());
    }

    #[Test]
    public function sync_matten_removes_excess_mats_without_poules(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_matten' => 2]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 3]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 4]);

        $this->toernooiService->syncMatten($toernooi);

        $this->assertEquals(2, $toernooi->matten()->count());
    }

    #[Test]
    public function sync_matten_keeps_mats_with_poules(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_matten' => 1]);
        Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat2 = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        // Assign a poule to mat 2 — should NOT be deleted
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'mat_id' => $mat2->id,
        ]);

        $this->toernooiService->syncMatten($toernooi);

        // Mat 2 still exists because it has poules
        $this->assertEquals(2, $toernooi->matten()->count());
    }

    // =========================================================================
    // ToernooiService — getActiefToernooi (line 230)
    // =========================================================================

    #[Test]
    public function get_actief_toernooi_returns_active_tournament(): void
    {
        $toernooi = Toernooi::factory()->create(['is_actief' => true]);

        $result = $this->toernooiService->getActiefToernooi();

        $this->assertNotNull($result);
        $this->assertEquals($toernooi->id, $result->id);
    }

    #[Test]
    public function get_actief_toernooi_returns_null_when_none(): void
    {
        Toernooi::factory()->create(['is_actief' => false]);

        $result = $this->toernooiService->getActiefToernooi();

        $this->assertNull($result);
    }

    // =========================================================================
    // ToernooiService — getStatistieken (line 268)
    // =========================================================================

    #[Test]
    public function get_statistieken_returns_complete_stats(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'aantal_blokken' => 2,
        ]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        // Create blocks
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        // Create judokas with various states
        Judoka::factory()->aanwezig()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'leeftijdsklasse' => "mini's",
        ]);
        Judoka::factory()->afwezig()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'leeftijdsklasse' => 'pupillen',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'leeftijdsklasse' => "mini's",
            'aanwezigheid' => 'onbekend',
        ]);

        $stats = $this->toernooiService->getStatistieken($toernooi);

        $this->assertEquals(3, $stats['totaal_judokas']);
        $this->assertEquals(1, $stats['aanwezig']);
        $this->assertEquals(1, $stats['afwezig']);
        $this->assertEquals(1, $stats['onbekend']);
        $this->assertArrayHasKey('per_leeftijdsklasse', $stats);
        $this->assertArrayHasKey('per_blok', $stats);
        $this->assertArrayHasKey('totaal_ontvangen', $stats);
    }

    // =========================================================================
    // ToernooiService — verwijderOudeToernooien + verwijderToernooi (lines 296-345)
    // =========================================================================

    #[Test]
    public function verwijder_oude_toernooien_removes_all_for_organisator(): void
    {
        $org = Organisator::factory()->create();
        Toernooi::factory()->count(3)->create(['organisator_id' => $org->id]);

        $count = $this->toernooiService->verwijderOudeToernooien($org->id);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, Toernooi::where('organisator_id', $org->id)->count());
    }

    #[Test]
    public function verwijder_toernooi_cascades_all_related_data(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        // Create related data
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        // Attach judokas to poule
        $poule->judokas()->attach([$judoka1->id, $judoka2->id]);

        // Create a match
        Wedstrijd::factory()->gespeeld()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'winnaar_id' => $judoka1->id,
        ]);

        $this->toernooiService->verwijderToernooi($toernooi);

        $this->assertNull(Toernooi::find($toernooi->id));
        $this->assertEquals(0, Blok::where('toernooi_id', $toernooi->id)->count());
        $this->assertEquals(0, Mat::where('toernooi_id', $toernooi->id)->count());
        $this->assertEquals(0, Judoka::where('toernooi_id', $toernooi->id)->count());
        $this->assertEquals(0, Poule::where('toernooi_id', $toernooi->id)->count());
    }

    #[Test]
    public function verwijder_oude_toernooien_returns_zero_when_none(): void
    {
        $org = Organisator::factory()->create();

        $count = $this->toernooiService->verwijderOudeToernooien($org->id);

        $this->assertEquals(0, $count);
    }

    // =========================================================================
    // ToernooiService — initialiseerToernooi template branch (lines 43-45)
    // =========================================================================

    #[Test]
    public function initialiseer_toernooi_without_template_creates_default_category(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $data = [
            'naam' => 'Test Toernooi',
            'datum' => '2026-06-01',
            'aantal_matten' => 2,
            'aantal_blokken' => 2,
        ];

        $toernooi = $this->toernooiService->initialiseerToernooi($data);

        $this->assertNotNull($toernooi);
        $this->assertEquals('Test Toernooi', $toernooi->naam);
        $this->assertNotNull($toernooi->gewichtsklassen);
        $this->assertArrayHasKey('standaard', $toernooi->gewichtsklassen);
        // Verify blocks + mats created
        $this->assertEquals(2, $toernooi->blokken()->count());
        $this->assertEquals(2, $toernooi->matten()->count());
    }
}
