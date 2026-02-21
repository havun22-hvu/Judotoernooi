<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use App\Models\WimpelUitreiking;
use App\Services\WimpelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WimpelServiceTest extends TestCase
{
    use RefreshDatabase;

    private WimpelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WimpelService();
    }

    private function createPuntenCompetitieSetup(int $judokaCount = 4, int $winsPerJudoka = 2): array
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

        $judokas = [];
        for ($i = 0; $i < $judokaCount; $i++) {
            $judokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
            ]);
        }

        // Create played matches with winners
        for ($i = 0; $i < $judokaCount && $i < $winsPerJudoka; $i++) {
            for ($w = 0; $w < $winsPerJudoka && $w < $judokaCount - 1; $w++) {
                $opponent = ($i + $w + 1) % $judokaCount;
                Wedstrijd::factory()->gespeeld()->create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $judokas[$i]->id,
                    'judoka_blauw_id' => $judokas[$opponent]->id,
                    'winnaar_id' => $judokas[$i]->id,
                ]);
            }
        }

        return compact('org', 'toernooi', 'poule', 'judokas', 'club');
    }

    // =========================================================================
    // POULE VERWERKING
    // =========================================================================

    #[Test]
    public function verwerk_poule_creates_punten_log(): void
    {
        $setup = $this->createPuntenCompetitieSetup(3, 1);

        $result = $this->service->verwerkPoule($setup['poule']);

        $this->assertGreaterThan(0, WimpelPuntenLog::count());
    }

    #[Test]
    public function verwerk_poule_creates_stam_judokas(): void
    {
        $setup = $this->createPuntenCompetitieSetup(3, 1);

        $this->service->verwerkPoule($setup['poule']);

        $this->assertGreaterThan(0, StamJudoka::where('organisator_id', $setup['org']->id)->count());
    }

    #[Test]
    public function verwerk_poule_skips_already_processed(): void
    {
        $setup = $this->createPuntenCompetitieSetup(3, 1);

        $this->service->verwerkPoule($setup['poule']);
        $countAfterFirst = WimpelPuntenLog::count();

        $this->service->verwerkPoule($setup['poule']);
        $countAfterSecond = WimpelPuntenLog::count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    #[Test]
    public function is_poule_al_verwerkt_returns_correct_state(): void
    {
        $setup = $this->createPuntenCompetitieSetup(3, 1);

        $this->assertFalse($this->service->isPouleAlVerwerkt($setup['poule']));

        $this->service->verwerkPoule($setup['poule']);

        $this->assertTrue($this->service->isPouleAlVerwerkt($setup['poule']));
    }

    // =========================================================================
    // MILESTONE CHECKS
    // =========================================================================

    #[Test]
    public function check_milestones_creates_uitreikingen(): void
    {
        $org = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->metPunten(12)->create([
            'organisator_id' => $org->id,
        ]);

        WimpelMilestone::create([
            'organisator_id' => $org->id,
            'punten' => 10,
            'omschrijving' => 'Gele wimpel',
            'volgorde' => 1,
        ]);

        $bereikt = $this->service->checkMilestones($stamJudoka, 8);

        $this->assertNotEmpty($bereikt);
        $this->assertEquals(1, WimpelUitreiking::where('stam_judoka_id', $stamJudoka->id)->count());
    }

    #[Test]
    public function check_milestones_skips_already_passed(): void
    {
        $org = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->metPunten(25)->create([
            'organisator_id' => $org->id,
        ]);

        WimpelMilestone::create([
            'organisator_id' => $org->id,
            'punten' => 10,
            'omschrijving' => 'Gele wimpel',
            'volgorde' => 1,
        ]);
        WimpelMilestone::create([
            'organisator_id' => $org->id,
            'punten' => 20,
            'omschrijving' => 'Groene wimpel',
            'volgorde' => 2,
        ]);

        // Old punten was 18, so only 20-milestone should be triggered
        $bereikt = $this->service->checkMilestones($stamJudoka, 18);

        $this->assertCount(1, $bereikt);
        $this->assertEquals(20, $bereikt[0]['punten']);
    }

    // =========================================================================
    // HANDMATIGE AANPASSING
    // =========================================================================

    #[Test]
    public function handmatig_aanpassen_updates_punten(): void
    {
        $org = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->metPunten(5)->create([
            'organisator_id' => $org->id,
        ]);

        $this->service->handmatigAanpassen($stamJudoka, 3, 'Correctie');

        $stamJudoka->refresh();
        $this->assertEquals(8, $stamJudoka->wimpel_punten_totaal);

        $log = WimpelPuntenLog::where('stam_judoka_id', $stamJudoka->id)->first();
        $this->assertEquals('handmatig', $log->type);
        $this->assertEquals(3, $log->punten);
        $this->assertEquals('Correctie', $log->notitie);
    }

    // =========================================================================
    // MATCH JUDOKA
    // =========================================================================

    #[Test]
    public function match_judoka_creates_new_stam_judoka(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Jan Jansen',
            'geboortejaar' => 2015,
        ]);

        $stam = $this->service->matchJudoka($org, $judoka);

        $this->assertTrue($stam->wasRecentlyCreated);
        $this->assertTrue($stam->wimpel_is_nieuw);
        $this->assertEquals('Jan Jansen', $stam->naam);
        $this->assertEquals(2015, $stam->geboortejaar);
    }

    #[Test]
    public function match_judoka_finds_existing_stam_judoka(): void
    {
        $org = Organisator::factory()->create();
        $existing = StamJudoka::factory()->metPunten(10)->create([
            'organisator_id' => $org->id,
            'naam' => 'Jan Jansen',
            'geboortejaar' => 2015,
        ]);

        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Jan Jansen',
            'geboortejaar' => 2015,
        ]);

        $stam = $this->service->matchJudoka($org, $judoka);

        $this->assertFalse($stam->wasRecentlyCreated);
        $this->assertEquals($existing->id, $stam->id);
    }
}
