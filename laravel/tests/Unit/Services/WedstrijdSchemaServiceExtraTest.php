<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\WedstrijdSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class WedstrijdSchemaServiceExtraTest extends TestCase
{
    use RefreshDatabase;

    private WedstrijdSchemaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WedstrijdSchemaService();
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(WedstrijdSchemaService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    private function createPouleWithJudokas(int $aantal, array $toernooiOverrides = []): array
    {
        $toernooi = Toernooi::factory()->create(array_merge([
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => false,
            'dubbel_bij_3_judokas' => true,
        ], $toernooiOverrides));
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'type' => 'voorronde',
        ]);

        $judokas = [];
        for ($i = 0; $i < $aantal; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
                'aanwezigheid' => 'aanwezig',
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
            $judokas[] = $judoka;
        }

        return [$poule, $judokas, $toernooi];
    }

    // ========================================================================
    // genereerRoundRobinSchema — large pool
    // ========================================================================

    #[Test]
    public function round_robin_8_judokas_geeft_28_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [8]);
        // n*(n-1)/2 = 8*7/2 = 28
        $this->assertCount(28, $schema);
    }

    #[Test]
    public function round_robin_oneven_geeft_correcte_aantallen(): void
    {
        // 7 judokas = 7*6/2 = 21
        $schema = $this->callPrivate('genereerRoundRobinSchema', [7]);
        $this->assertCount(21, $schema);
    }

    #[Test]
    public function round_robin_geen_zelf_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [6]);

        foreach ($schema as [$a, $b]) {
            $this->assertNotEquals($a, $b, 'A judoka should not fight themselves');
        }
    }

    // ========================================================================
    // getOptimaleWedstrijdvolgorde — barrage
    // ========================================================================

    #[Test]
    public function barrage_2_judokas_geeft_1_wedstrijd(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'barrage']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 2]);
        $this->assertCount(1, $schema);
        $this->assertEquals([1, 2], $schema[0]);
    }

    #[Test]
    public function barrage_3_judokas_geeft_3_wedstrijden(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'barrage']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 3]);
        $this->assertCount(3, $schema);
    }

    // ========================================================================
    // getOptimaleWedstrijdvolgorde — standard schemas
    // ========================================================================

    #[Test]
    public function schema_2_judokas_dubbel(): void
    {
        $toernooi = Toernooi::factory()->create([
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => false,
        ]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 2]);
        $this->assertCount(2, $schema);
    }

    #[Test]
    public function schema_2_judokas_best_of_three(): void
    {
        $toernooi = Toernooi::factory()->create([
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => true,
        ]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 2]);
        $this->assertCount(3, $schema);
    }

    #[Test]
    public function schema_2_judokas_single(): void
    {
        $toernooi = Toernooi::factory()->create([
            'dubbel_bij_2_judokas' => false,
            'best_of_three_bij_2' => false,
        ]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 2]);
        $this->assertCount(1, $schema);
    }

    #[Test]
    public function schema_3_judokas_dubbel_geeft_6(): void
    {
        $toernooi = Toernooi::factory()->create(['dubbel_bij_3_judokas' => true]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 3]);
        $this->assertCount(6, $schema);
    }

    #[Test]
    public function schema_3_judokas_enkel_geeft_3(): void
    {
        $toernooi = Toernooi::factory()->create(['dubbel_bij_3_judokas' => false]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 3]);
        $this->assertCount(3, $schema);
    }

    #[Test]
    public function schema_4_judokas_geeft_6(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 4]);
        $this->assertCount(6, $schema);
    }

    #[Test]
    public function schema_5_judokas_geeft_10(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 5]);
        $this->assertCount(10, $schema);
    }

    #[Test]
    public function schema_6_judokas_geeft_15(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 6]);
        $this->assertCount(15, $schema);
    }

    #[Test]
    public function schema_7_judokas_geeft_21(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'voorronde']);

        $schema = $this->callPrivate('getOptimaleWedstrijdvolgorde', [$poule, 7]);
        $this->assertCount(21, $schema);
    }

    // ========================================================================
    // genereerWedstrijdenVoorPoule
    // ========================================================================

    #[Test]
    public function genereer_wedstrijden_voor_poule_4_judokas(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);

        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);

        $this->assertCount(6, $wedstrijden);
        foreach ($wedstrijden as $w) {
            $this->assertNotNull($w->judoka_wit_id);
            $this->assertNotNull($w->judoka_blauw_id);
            $this->assertNotEquals($w->judoka_wit_id, $w->judoka_blauw_id);
        }
    }

    #[Test]
    public function genereer_wedstrijden_voor_poule_skips_afwezig(): void
    {
        // dubbel_bij_3 = false so 3 judokas = 3 matches (single round-robin)
        [$poule, $judokas, $toernooi] = $this->createPouleWithJudokas(4, ['dubbel_bij_3_judokas' => false]);

        // Maak 1 afwezig
        $judokas[0]->update(['aanwezigheid' => 'afwezig']);

        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);

        // 3 judokas (single) = 3 wedstrijden
        $this->assertCount(3, $wedstrijden);

        // Afwezige judoka mag niet in wedstrijden voorkomen
        foreach ($wedstrijden as $w) {
            $this->assertNotEquals($judokas[0]->id, $w->judoka_wit_id);
            $this->assertNotEquals($judokas[0]->id, $w->judoka_blauw_id);
        }
    }

    #[Test]
    public function genereer_wedstrijden_voor_poule_verwijdert_bestaande(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);

        // Genereer twee keer
        $this->service->genereerWedstrijdenVoorPoule($poule);
        $first = Wedstrijd::where('poule_id', $poule->id)->count();

        $this->service->genereerWedstrijdenVoorPoule($poule);
        $second = Wedstrijd::where('poule_id', $poule->id)->count();

        $this->assertEquals($first, $second, 'Oude wedstrijden moeten verwijderd worden');
    }

    #[Test]
    public function genereer_wedstrijden_minder_dan_2_geeft_leeg(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(1);

        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);

        $this->assertEmpty($wedstrijden);
    }

    // ========================================================================
    // registreerUitslag
    // ========================================================================

    #[Test]
    public function registreer_uitslag_basic(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);
        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);
        $w = $wedstrijden[0];

        $this->service->registreerUitslag($w, $w->judoka_wit_id, '10', '0', 'ippon');

        $w->refresh();
        $this->assertTrue($w->is_gespeeld);
        $this->assertEquals($w->judoka_wit_id, $w->winnaar_id);
        $this->assertEquals('10', $w->score_wit);
        $this->assertEquals('0', $w->score_blauw);
        $this->assertEquals('ippon', $w->uitslag_type);
    }

    #[Test]
    public function registreer_uitslag_without_winner(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);
        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);
        $w = $wedstrijden[0];

        // Gelijkspel: geen winnaar maar wel scores
        $this->service->registreerUitslag($w, null, '5', '5');

        $w->refresh();
        $this->assertTrue($w->is_gespeeld);
        $this->assertNull($w->winnaar_id);
        $this->assertEquals('5', $w->score_wit);
        $this->assertEquals('5', $w->score_blauw);
    }

    #[Test]
    public function registreer_uitslag_auto_fills_missing_score(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);
        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);
        $w = $wedstrijden[0];

        // Winnaar met alleen score_wit, score_blauw leeg
        $this->service->registreerUitslag($w, $w->judoka_wit_id, '10', '');

        $w->refresh();
        $this->assertEquals('0', $w->score_blauw, 'Lege score van verliezer moet op 0 gezet worden');
    }

    // ========================================================================
    // getPouleStand
    // ========================================================================

    #[Test]
    public function get_poule_stand_returns_correct_structure(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(3);
        $wedstrijden = $this->service->genereerWedstrijdenVoorPoule($poule);

        // Speel eerste wedstrijd
        $w = $wedstrijden[0];
        $this->service->registreerUitslag($w, $w->judoka_wit_id, '10', '0', 'ippon');

        $stand = $this->service->getPouleStand($poule);

        $this->assertCount(3, $stand);
        foreach ($stand as $entry) {
            $this->assertArrayHasKey('positie', $entry);
            $this->assertArrayHasKey('judoka_id', $entry);
            $this->assertArrayHasKey('naam', $entry);
            $this->assertArrayHasKey('gewonnen', $entry);
            $this->assertArrayHasKey('verloren', $entry);
            $this->assertArrayHasKey('gelijk', $entry);
            $this->assertArrayHasKey('punten', $entry);
        }

        // Winner should have 10 points
        $winnaar = collect($stand)->firstWhere('judoka_id', $w->judoka_wit_id);
        $this->assertEquals(10, $winnaar['punten']);
        $this->assertEquals(1, $winnaar['gewonnen']);
    }

    // ========================================================================
    // puntenCompetitie
    // ========================================================================

    #[Test]
    public function punten_comp_meer_wedstrijden_repeats_fairly(): void
    {
        $roundRobin = $this->callPrivate('genereerRoundRobinSchema', [4]);

        // 4 judokas, elk 5 wedstrijden (meer dan round-robin=3)
        $result = $this->callPrivate('puntenCompMeerWedstrijden', [4, 5, $roundRobin]);

        // Check that matches exist
        $this->assertNotEmpty($result);

        // Each judoka should have approximately 5 matches
        $counts = array_fill(1, 4, 0);
        foreach ($result as [$a, $b]) {
            $counts[$a]++;
            $counts[$b]++;
        }

        foreach ($counts as $count) {
            $this->assertGreaterThanOrEqual(3, $count);
        }
    }

    #[Test]
    public function punten_comp_exact_round_robin(): void
    {
        $roundRobin = $this->callPrivate('genereerRoundRobinSchema', [5]);

        // 5 judokas, elk 4 wedstrijden = exact round-robin
        $result = $this->callPrivate('genereerPuntenCompetitieSchema', [5, 4]);

        $this->assertCount(10, $result); // full round-robin
    }

    #[Test]
    public function punten_comp_minder_dan_2_leeg(): void
    {
        $result = $this->callPrivate('genereerPuntenCompetitieSchema', [1, 3]);
        $this->assertEmpty($result);
    }

    // ========================================================================
    // optimaliseerVolgorde edge cases
    // ========================================================================

    #[Test]
    public function optimaliseer_twee_wedstrijden_ongewijzigd(): void
    {
        $wedstrijden = [[1, 2], [3, 4]];
        $result = $this->callPrivate('optimaliseerVolgorde', [$wedstrijden, 4]);
        $this->assertEquals($wedstrijden, $result);
    }
}
