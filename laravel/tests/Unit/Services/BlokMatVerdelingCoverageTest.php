<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BlokMatVerdelingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private BlokMatVerdelingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlokMatVerdelingService::class);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(BlokMatVerdelingService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    /**
     * Create a tournament with fixed categories, blocks, mats, and poules for testing.
     */
    private function maakToernooiMetVerdeling(int $aantalBlokken = 2, int $aantalMatten = 2): array
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 0,
                    'geslacht' => 'gemengd',
                    'gewichten' => ['-20', '-24', '-28', '+28'],
                ],
                'jeugd' => [
                    'label' => 'Jeugd',
                    'min_leeftijd' => 7,
                    'max_leeftijd' => 9,
                    'max_kg_verschil' => 0,
                    'geslacht' => 'gemengd',
                    'gewichten' => ['-30', '-36', '-42', '+42'],
                ],
            ],
        ]);

        $blokken = [];
        for ($i = 1; $i <= $aantalBlokken; $i++) {
            $blokken[] = Blok::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i,
            ]);
        }

        $matten = [];
        for ($i = 1; $i <= $aantalMatten; $i++) {
            $matten[] = Mat::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i,
                'naam' => "Mat $i",
            ]);
        }

        $nummer = 1;
        $poulesData = [
            ["Mini's", '-20', 4, 6],
            ["Mini's", '-24', 3, 3],
            ["Mini's", '-28', 4, 6],
            ["Mini's", '+28', 3, 3],
            ['Jeugd', '-30', 4, 6],
            ['Jeugd', '-36', 3, 3],
            ['Jeugd', '-42', 4, 6],
            ['Jeugd', '+42', 3, 3],
        ];

        $poules = [];
        foreach ($poulesData as [$leeftijd, $gewicht, $judokas, $wedstrijden]) {
            $poules[] = Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $nummer++,
                'leeftijdsklasse' => $leeftijd,
                'gewichtsklasse' => $gewicht,
                'type' => 'voorronde',
                'aantal_judokas' => $judokas,
                'aantal_wedstrijden' => $wedstrijden,
            ]);
        }

        $toernooi->load(['blokken', 'matten', 'poules']);

        return compact('toernooi', 'blokken', 'matten', 'poules');
    }

    // ========================================================================
    // genereerVarianten — fixed categories
    // ========================================================================

    #[Test]
    public function genereer_varianten_met_vaste_klassen(): void
    {
        $data = $this->maakToernooiMetVerdeling();

        $result = $this->service->genereerVarianten($data['toernooi']);

        $this->assertArrayHasKey('varianten', $result);
        $this->assertIsArray($result['varianten']);
    }

    #[Test]
    public function genereer_varianten_geen_blokken_gooit_exception(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-20'],
                ],
            ],
        ]);

        // Create poules but no blokken
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'aantal_wedstrijden' => 6,
        ]);

        $toernooi->load(['blokken', 'matten', 'poules']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Geen blokken gevonden');
        $this->service->genereerVarianten($toernooi);
    }

    #[Test]
    public function genereer_varianten_alle_al_verdeeld_geeft_lege_varianten(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-20'],
                ],
            ],
        ]);

        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'aantal_wedstrijden' => 6,
            'blok_vast' => true,
        ]);

        $toernooi->load(['blokken', 'matten', 'poules']);

        $result = $this->service->genereerVarianten($toernooi);

        $this->assertArrayHasKey('varianten', $result);
        $this->assertEmpty($result['varianten']);
        $this->assertArrayHasKey('message', $result);
    }

    #[Test]
    public function genereer_varianten_result_bevat_stats(): void
    {
        $data = $this->maakToernooiMetVerdeling();

        $result = $this->service->genereerVarianten($data['toernooi']);

        if (!empty($result['varianten'])) {
            $this->assertArrayHasKey('stats', $result);
            $this->assertArrayHasKey('pogingen', $result['stats']);
            $this->assertArrayHasKey('tijd_sec', $result['stats']);
        }
    }

    #[Test]
    public function genereer_varianten_variant_bevat_toewijzingen_en_scores(): void
    {
        $data = $this->maakToernooiMetVerdeling();

        $result = $this->service->genereerVarianten($data['toernooi']);

        if (!empty($result['varianten'])) {
            $variant = $result['varianten'][0];
            $this->assertArrayHasKey('toewijzingen', $variant);
            $this->assertArrayHasKey('scores', $variant);
            $this->assertArrayHasKey('totaal_score', $variant);
            $this->assertArrayHasKey('capaciteit', $variant);
        }
    }

    // ========================================================================
    // selecteerBesteVarianten
    // ========================================================================

    #[Test]
    public function selecteer_beste_varianten_geen_geldige_gebruikt_ongeldige(): void
    {
        $alleVarianten = [];
        $ongeligeVarianten = [
            [
                'toewijzingen' => ['a|b' => 1],
                'totaal_score' => 100,
                'scores' => ['is_valid' => false],
            ],
        ];

        $result = $this->callPrivate('selecteerBesteVarianten', [
            $alleVarianten,
            $ongeligeVarianten,
            ['hash1' => true],
            10,
            microtime(true) - 0.5,
        ]);

        $this->assertNotEmpty($result['varianten']);
    }

    #[Test]
    public function selecteer_beste_varianten_leeg_geeft_error(): void
    {
        $result = $this->callPrivate('selecteerBesteVarianten', [
            [],
            [],
            [],
            10,
            microtime(true) - 0.5,
        ]);

        $this->assertEmpty($result['varianten']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function selecteer_beste_varianten_sorteert_op_score(): void
    {
        $varianten = [
            ['toewijzingen' => ['a|b' => 1], 'totaal_score' => 50, 'scores' => ['is_valid' => true]],
            ['toewijzingen' => ['a|b' => 2], 'totaal_score' => 10, 'scores' => ['is_valid' => true]],
            ['toewijzingen' => ['a|b' => 3], 'totaal_score' => 30, 'scores' => ['is_valid' => true]],
        ];

        $result = $this->callPrivate('selecteerBesteVarianten', [
            $varianten, [], ['h1' => true, 'h2' => true, 'h3' => true], 100, microtime(true) - 1.0,
        ]);

        // Best variant (lowest score) should be first
        $this->assertEquals(10, $result['varianten'][0]['totaal_score']);
    }

    #[Test]
    public function selecteer_beste_varianten_max_5(): void
    {
        $varianten = [];
        for ($i = 0; $i < 10; $i++) {
            $varianten[] = [
                'toewijzingen' => ["cat|w$i" => $i],
                'totaal_score' => $i * 10,
                'scores' => ['is_valid' => true],
            ];
        }

        $result = $this->callPrivate('selecteerBesteVarianten', [
            $varianten, [], array_fill(0, 10, true), 100, microtime(true) - 1.0,
        ]);

        $this->assertLessThanOrEqual(5, count($result['varianten']));
    }

    // ========================================================================
    // berekenVariatieParameters — more seeds
    // ========================================================================

    #[Test]
    public function bereken_variatie_parameters_different_seeds(): void
    {
        $params0 = $this->callPrivate('berekenVariatieParameters', [0, 50]);
        $params5 = $this->callPrivate('berekenVariatieParameters', [5, 50]);
        $params20 = $this->callPrivate('berekenVariatieParameters', [20, 50]);

        // Different seeds produce different randomFactor (seed % 100 / 100)
        $this->assertNotEquals($params0['randomFactor'], $params5['randomFactor']);
        // All params are within expected ranges
        $this->assertGreaterThanOrEqual(0, $params20['randomFactor']);
        $this->assertLessThanOrEqual(1.0, $params20['randomFactor']);
    }

    // ========================================================================
    // sorteerGewichten — double swap strategy
    // ========================================================================

    #[Test]
    public function sorteer_gewichten_dubbele_swap_strategie(): void
    {
        $gewichten = [
            ['leeftijd' => 'u7', 'gewicht' => '-20', 'gewicht_num' => 20, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-25', 'gewicht_num' => 25, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-30', 'gewicht_num' => 30, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-35', 'gewicht_num' => 35, 'wedstrijden' => 6],
        ];

        // Strategy >= 7 triggers 2 swaps
        $result = $this->callPrivate('sorteerGewichten', [$gewichten, 7]);
        $this->assertCount(4, $result);
    }

    #[Test]
    public function sorteer_gewichten_korte_lijst_geen_swap(): void
    {
        $gewichten = [
            ['leeftijd' => 'u7', 'gewicht' => '-20', 'gewicht_num' => 20, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-25', 'gewicht_num' => 25, 'wedstrijden' => 6],
        ];

        // Strategy >= 3 but only 2 items → no swap
        $result = $this->callPrivate('sorteerGewichten', [$gewichten, 5]);
        $this->assertEquals(20, $result[0]['gewicht_num']);
        $this->assertEquals(25, $result[1]['gewicht_num']);
    }

    // ========================================================================
    // vindLaatsteBlokIndex
    // ========================================================================

    #[Test]
    public function vind_laatste_blok_index_found(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok1 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $blok2 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $blokken = [$blok1, $blok2];
        $toewijzingen = ["Mini's|-20" => 1, "Mini's|-24" => 2];
        $gewichten = [
            ['leeftijd' => "Mini's", 'gewicht' => '-20'],
            ['leeftijd' => "Mini's", 'gewicht' => '-24'],
        ];

        $result = $this->callPrivate('vindLaatsteBlokIndex', [$toewijzingen, $gewichten, $blokken]);
        $this->assertEquals(1, $result); // blok2 is at index 1
    }

    #[Test]
    public function vind_laatste_blok_index_not_found_returns_0(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok1 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);

        $blokken = [$blok1];
        $toewijzingen = [];
        $gewichten = [
            ['leeftijd' => "Mini's", 'gewicht' => '-20'],
        ];

        $result = $this->callPrivate('vindLaatsteBlokIndex', [$toewijzingen, $gewichten, $blokken]);
        $this->assertEquals(0, $result);
    }

    // ========================================================================
    // pasVariantToe
    // ========================================================================

    #[Test]
    public function pas_variant_toe_updates_poules(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];
        $blok1 = $data['blokken'][0];

        $toewijzingen = [
            "Mini's|-20" => 1,
            "Mini's|-24" => 1,
            "Jeugd|-30" => 2,
            "Jeugd|-36" => 2,
        ];

        $this->service->pasVariantToe($toernooi, $toewijzingen);

        // Check poules were updated
        $poule = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', "Mini's")
            ->where('gewichtsklasse', '-20')
            ->first();

        $this->assertEquals($blok1->id, $poule->blok_id);
        $this->assertNotNull($toernooi->fresh()->blokken_verdeeld_op);
    }

    #[Test]
    public function pas_variant_toe_fixt_kruisfinale_blokken(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];
        $blok1 = $data['blokken'][0];

        // Create a kruisfinale without blok_id
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'kruisfinale',
            'blok_id' => null,
        ]);

        $toewijzingen = ["Mini's|-20" => 1];
        $this->service->pasVariantToe($toernooi, $toewijzingen);

        // The kruisfinale should have gotten the same blok as the voorronde
        $kruisfinale = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', 'kruisfinale')
            ->where('leeftijdsklasse', "Mini's")
            ->where('gewichtsklasse', '-20')
            ->first();

        $this->assertEquals($blok1->id, $kruisfinale->blok_id);
    }

    // ========================================================================
    // genereerVerdeling (legacy)
    // ========================================================================

    #[Test]
    public function genereer_verdeling_legacy_past_eerste_variant_toe(): void
    {
        $data = $this->maakToernooiMetVerdeling();

        $stats = $this->service->genereerVerdeling($data['toernooi']);

        $this->assertIsArray($stats);
        // After genereerVerdeling, blokken_verdeeld_op should be set
        $this->assertNotNull($data['toernooi']->fresh()->blokken_verdeeld_op);
    }

    // ========================================================================
    // verdeelOverMatten
    // ========================================================================

    #[Test]
    public function verdeel_over_matten_distributes_poules(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        // First assign all poules to blok 1
        foreach ($data['poules'] as $poule) {
            $poule->update(['blok_id' => $data['blokken'][0]->id]);
        }

        $toernooi->load(['blokken', 'matten']);

        $this->service->verdeelOverMatten($toernooi);

        // Check that poules got mat_id assigned
        $poulesMetMat = Poule::where('toernooi_id', $toernooi->id)
            ->whereNotNull('mat_id')
            ->count();

        $this->assertGreaterThan(0, $poulesMetMat);
    }

    #[Test]
    public function verdeel_over_matten_zonder_matten_doet_niets(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        // No matten created
        $toernooi->load(['blokken', 'matten']);

        // Should not throw, just return
        $this->service->verdeelOverMatten($toernooi);
        $this->assertTrue(true); // No exception = pass
    }

    #[Test]
    public function verdeel_over_matten_eliminatie_krijgt_b_mat_id(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'eliminatie',
            'aantal_judokas' => 8,
            'aantal_wedstrijden' => 7,
        ]);

        $toernooi->load(['blokken', 'matten']);
        $this->service->verdeelOverMatten($toernooi);

        $poule = Poule::where('toernooi_id', $toernooi->id)->where('type', 'eliminatie')->first();
        $this->assertNotNull($poule->mat_id);
        $this->assertEquals($poule->mat_id, $poule->b_mat_id);
    }

    #[Test]
    public function verdeel_over_matten_fixt_kruisfinale_matten(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        // Voorronde with mat
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
            'aantal_judokas' => 4,
            'aantal_wedstrijden' => 6,
        ]);

        // Kruisfinale without mat
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'kruisfinale',
            'aantal_judokas' => 4,
            'aantal_wedstrijden' => 2,
            'mat_id' => null,
        ]);

        $toernooi->load(['blokken', 'matten']);
        $this->service->verdeelOverMatten($toernooi);

        $kruisfinale = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', 'kruisfinale')
            ->first();

        $this->assertEquals($mat->id, $kruisfinale->mat_id);
    }

    // ========================================================================
    // getCategorieVolgorde — extra cases
    // ========================================================================

    #[Test]
    public function categorie_volgorde_jongens_is_4(): void
    {
        $this->assertEquals(4, $this->callPrivate('getCategorieVolgorde', ['Jongens U11']));
    }

    // ========================================================================
    // extractGewichtVoorSortering — extra cases
    // ========================================================================

    #[Test]
    public function extract_gewicht_range_met_kg_suffix(): void
    {
        $this->assertEquals(24.0, $this->callPrivate('extractGewichtVoorSortering', ['24-27kg']));
    }

    #[Test]
    public function extract_gewicht_plus_met_kg(): void
    {
        $this->assertEquals(1090.0, $this->callPrivate('extractGewichtVoorSortering', ['+90kg']));
    }

    #[Test]
    public function extract_gewicht_alleen_nummer(): void
    {
        $this->assertEquals(50.0, $this->callPrivate('extractGewichtVoorSortering', ['50']));
    }

    #[Test]
    public function extract_gewicht_geen_nummer(): void
    {
        $this->assertEquals(0.0, $this->callPrivate('extractGewichtVoorSortering', ['zwaar']));
    }

    // ========================================================================
    // extractLeeftijdUitCategorieKey — extra
    // ========================================================================

    #[Test]
    public function extract_leeftijd_uppercase(): void
    {
        $this->assertEquals(15, $this->callPrivate('extractLeeftijdUitCategorieKey', ['U15_heren']));
    }

    // ========================================================================
    // isGemengdToernooi
    // ========================================================================

    #[Test]
    public function is_gemengd_toernooi_alleen_vast(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-20'],
                ],
            ],
        ]);

        $this->assertFalse($this->service->isGemengdToernooi($toernooi));
    }

    #[Test]
    public function is_gemengd_toernooi_alleen_variabel(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                ],
            ],
        ]);

        $this->assertFalse($this->service->isGemengdToernooi($toernooi));
    }

    #[Test]
    public function is_gemengd_toernooi_beide_types(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                ],
                'jeugd' => [
                    'label' => 'Jeugd',
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-30', '-36'],
                ],
            ],
        ]);

        $this->assertTrue($this->service->isGemengdToernooi($toernooi));
    }

    // ========================================================================
    // heeftVasteCategorieen
    // ========================================================================

    #[Test]
    public function heeft_vaste_categorieen_true(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'jeugd' => [
                    'label' => 'Jeugd',
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-30'],
                ],
            ],
        ]);

        $this->assertTrue($this->callPrivate('heeftVasteCategorieen', [$toernooi]));
    }

    #[Test]
    public function heeft_vaste_categorieen_false(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 3,
                ],
            ],
        ]);

        $this->assertFalse($this->callPrivate('heeftVasteCategorieen', [$toernooi]));
    }

    #[Test]
    public function heeft_vaste_categorieen_leeg(): void
    {
        $toernooi = Toernooi::factory()->create(['gewichtsklassen' => []]);
        $this->assertFalse($this->callPrivate('heeftVasteCategorieen', [$toernooi]));
    }

    // ========================================================================
    // getVerdelingsStatistieken — extra cases
    // ========================================================================

    #[Test]
    public function get_verdelings_statistieken_meerdere_blokken(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        // Assign some poules to blocks
        $data['poules'][0]->update(['blok_id' => $data['blokken'][0]->id, 'mat_id' => $data['matten'][0]->id]);
        $data['poules'][1]->update(['blok_id' => $data['blokken'][0]->id, 'mat_id' => $data['matten'][1]->id]);
        $data['poules'][4]->update(['blok_id' => $data['blokken'][1]->id, 'mat_id' => $data['matten'][0]->id]);

        $toernooi->load(['blokken', 'matten']);
        $stats = $this->service->getVerdelingsStatistieken($toernooi);

        $this->assertArrayHasKey(1, $stats);
        $this->assertArrayHasKey(2, $stats);
        $this->assertGreaterThan(0, $stats[1]['totaal_wedstrijden']);
    }

    // ========================================================================
    // getZaalOverzicht
    // ========================================================================

    #[Test]
    public function get_zaal_overzicht_basic(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        // Assign poules
        foreach ($data['poules'] as $i => $poule) {
            $blokIdx = $i < 4 ? 0 : 1;
            $matIdx = $i % 2;
            $poule->update([
                'blok_id' => $data['blokken'][$blokIdx]->id,
                'mat_id' => $data['matten'][$matIdx]->id,
            ]);
        }

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $this->assertNotEmpty($overzicht);
        $this->assertArrayHasKey('nummer', $overzicht[0]);
        $this->assertArrayHasKey('matten', $overzicht[0]);
    }

    #[Test]
    public function get_zaal_overzicht_met_kruisfinale(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'kruisfinale',
            'aantal_judokas' => 4,
            'aantal_wedstrijden' => 2,
        ]);

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $this->assertNotEmpty($overzicht);
        $poules = $overzicht[0]['matten'][1]['poules'] ?? [];
        // Kruisfinale with judokas should appear
        $found = collect($poules)->contains(fn($p) => ($p['type'] ?? '') === 'kruisfinale');
        $this->assertTrue($found);
    }

    #[Test]
    public function get_zaal_overzicht_met_eliminatie(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'b_mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'eliminatie',
            'aantal_judokas' => 8,
            'aantal_wedstrijden' => 7,
        ]);

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $this->assertNotEmpty($overzicht);
        // Eliminatie should produce A and B group entries
        $poules = $overzicht[0]['matten'][1]['poules'] ?? [];
        $groepen = collect($poules)->pluck('groep')->filter()->values()->toArray();
        $this->assertContains('A', $groepen);
        $this->assertContains('B', $groepen);
    }

    #[Test]
    public function get_zaal_overzicht_leeg_blok(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $this->assertCount(1, $overzicht);
        $this->assertEmpty($overzicht[0]['matten'][1]['poules']);
    }

    // ========================================================================
    // bepaalZwaarGewichtGrens
    // ========================================================================

    #[Test]
    public function bepaal_zwaar_gewicht_grens_met_poules(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);

        $poules = collect();
        foreach (['-20', '-30', '-40', '-50', '-60'] as $gewicht) {
            $poules->push(Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $blok->id,
                'gewichtsklasse' => $gewicht,
            ]));
        }

        $result = $this->callPrivate('bepaalZwaarGewichtGrens', [$poules]);
        // 70% threshold of [20,30,40,50,60] → index 3 = 50
        $this->assertEquals(50.0, $result);
    }

    // ========================================================================
    // isPouleVoorDames — by judoka gender
    // ========================================================================

    #[Test]
    public function is_poule_voor_dames_by_judoka_geslacht(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Senioren', // niet herkenbaar als dames
        ]);

        // Add female judokas
        $judokas = [];
        for ($i = 0; $i < 3; $i++) {
            $judokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'geslacht' => 'V',
            ]);
        }
        $poule->judokas()->attach(collect($judokas)->pluck('id'));
        $poule->load('judokas');

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertTrue($result);
    }

    #[Test]
    public function is_poule_niet_voor_dames_by_judoka_geslacht(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Senioren',
        ]);

        // Add male judokas
        $judokas = [];
        for ($i = 0; $i < 3; $i++) {
            $judokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'geslacht' => 'M',
            ]);
        }
        $poule->judokas()->attach(collect($judokas)->pluck('id'));
        $poule->load('judokas');

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertFalse($result);
    }

    #[Test]
    public function is_poule_voor_dames_lege_poule_geen_herkenbare_naam(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Senioren',
        ]);
        $poule->load('judokas');

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertFalse($result);
    }

    #[Test]
    public function is_poule_voor_dames_vrouw_label(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Vrouwen +60',
        ]);

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertTrue($result);
    }

    // ========================================================================
    // vindMinsteWedstrijdenMat — extra
    // ========================================================================

    #[Test]
    public function vind_minste_wedstrijden_mat_met_ontbrekende_keys(): void
    {
        $matIds = [1, 2, 3];
        $wedstrijdenPerMat = [1 => 10]; // 2 and 3 missing → default 0

        $result = $this->callPrivate('vindMinsteWedstrijdenMat', [$matIds, $wedstrijdenPerMat]);
        $this->assertEquals(2, $result); // 2 has 0 (missing)
    }

    // ========================================================================
    // Full integration: genereerVarianten + pasVariantToe
    // ========================================================================

    #[Test]
    public function full_flow_genereer_en_pas_toe(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        $result = $this->service->genereerVarianten($toernooi);

        if (!empty($result['varianten'])) {
            $variant = $result['varianten'][0];
            $this->service->pasVariantToe($toernooi, $variant['toewijzingen']);

            // Verify some poules got blok_id
            $metBlok = Poule::where('toernooi_id', $toernooi->id)
                ->whereNotNull('blok_id')
                ->count();
            $this->assertGreaterThan(0, $metBlok);
        }
    }

    #[Test]
    public function full_flow_genereer_verdeel_matten(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        // Assign all poules to blok 1
        foreach ($data['poules'] as $poule) {
            $poule->update(['blok_id' => $data['blokken'][0]->id]);
        }

        $toernooi->load(['blokken', 'matten']);
        $this->service->verdeelOverMatten($toernooi);

        // All poules should have mat_id
        $zonderMat = Poule::where('toernooi_id', $toernooi->id)
            ->whereNull('mat_id')
            ->count();

        $this->assertEquals(0, $zonderMat);
    }

    // ========================================================================
    // verdeelOverMatten — multiple blocks
    // ========================================================================

    #[Test]
    public function verdeel_over_matten_meerdere_blokken(): void
    {
        $data = $this->maakToernooiMetVerdeling();
        $toernooi = $data['toernooi'];

        // Assign first 4 poules to blok 1, rest to blok 2
        foreach ($data['poules'] as $i => $poule) {
            $blokIdx = $i < 4 ? 0 : 1;
            $poule->update(['blok_id' => $data['blokken'][$blokIdx]->id]);
        }

        $toernooi->load(['blokken', 'matten']);
        $this->service->verdeelOverMatten($toernooi);

        // Both mats should have poules
        $mat1Count = Poule::where('toernooi_id', $toernooi->id)
            ->where('mat_id', $data['matten'][0]->id)
            ->count();
        $mat2Count = Poule::where('toernooi_id', $toernooi->id)
            ->where('mat_id', $data['matten'][1]->id)
            ->count();

        $this->assertGreaterThan(0, $mat1Count);
        $this->assertGreaterThan(0, $mat2Count);
    }

    // ========================================================================
    // getZaalOverzicht with voorronde poules (regular flow)
    // ========================================================================

    #[Test]
    public function get_zaal_overzicht_voorronde_met_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['gewicht_tolerantie' => 0.5]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
            'aantal_judokas' => 4,
            'aantal_wedstrijden' => 6,
        ]);

        // Attach judokas to the poule
        $judokas = [];
        for ($i = 0; $i < 4; $i++) {
            $judokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'aanwezigheid' => 'aanwezig',
            ]);
        }
        $poule->judokas()->attach(collect($judokas)->pluck('id'));

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $this->assertNotEmpty($overzicht);
        $poules = $overzicht[0]['matten'][1]['poules'];
        $this->assertNotEmpty($poules);
        $this->assertEquals(4, $poules[0]['judokas']);
    }

    #[Test]
    public function get_zaal_overzicht_poule_met_1_judoka_gefilterd(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
            'aantal_judokas' => 1,
            'aantal_wedstrijden' => 0,
        ]);

        // Only 1 judoka
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'aanwezigheid' => 'aanwezig',
        ]);
        $poule->judokas()->attach([$judoka->id]);

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        // Poule with 1 judoka should be filtered out
        $poules = $overzicht[0]['matten'][1]['poules'];
        $this->assertEmpty($poules);
    }

    // ========================================================================
    // genereerGemengdeVerdeling — mixed tournament
    // ========================================================================

    #[Test]
    public function genereer_varianten_gemengd_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                    'geslacht' => 'gemengd',
                ],
                'jeugd' => [
                    'label' => 'Jeugd',
                    'min_leeftijd' => 7,
                    'max_leeftijd' => 9,
                    'max_kg_verschil' => 0,
                    'geslacht' => 'gemengd',
                    'gewichten' => ['-30', '-36', '+36'],
                ],
            ],
        ]);

        $blok1 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $blok2 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        // Fixed Jeugd poules
        foreach (['-30', '-36', '+36'] as $i => $gewicht) {
            Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i + 1,
                'leeftijdsklasse' => 'Jeugd',
                'gewichtsklasse' => $gewicht,
                'type' => 'voorronde',
                'aantal_judokas' => 4,
                'aantal_wedstrijden' => 6,
            ]);
        }

        // Variable Mini poules (these have categorie_key for variable grouping)
        foreach (['-20', '-24'] as $i => $gewicht) {
            Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => 10 + $i,
                'leeftijdsklasse' => "Mini's",
                'gewichtsklasse' => $gewicht,
                'categorie_key' => 'minis',
                'type' => 'voorronde',
                'aantal_judokas' => 4,
                'aantal_wedstrijden' => 6,
            ]);
        }

        $toernooi->load(['blokken', 'matten', 'poules']);

        $result = $this->service->genereerVarianten($toernooi);

        $this->assertArrayHasKey('varianten', $result);
        $this->assertArrayHasKey('stats', $result);
    }

    #[Test]
    public function genereer_varianten_gemengd_geen_blokken_gooit_exception(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'max_kg_verschil' => 3,
                ],
                'jeugd' => [
                    'label' => 'Jeugd',
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-30'],
                ],
            ],
        ]);

        $toernooi->load(['blokken', 'matten', 'poules']);

        $this->expectException(\RuntimeException::class);
        $this->service->genereerVarianten($toernooi);
    }

    // ========================================================================
    // getZaalOverzicht — voorronde with afwezige judokas
    // ========================================================================

    #[Test]
    public function get_zaal_overzicht_voorronde_filtert_afwezige_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['gewicht_tolerantie' => 0.5]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "Mini's",
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
            'aantal_judokas' => 4,
            'aantal_wedstrijden' => 6,
        ]);

        // 3 present, 1 absent
        for ($i = 0; $i < 3; $i++) {
            $j = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'aanwezigheid' => 'aanwezig']);
            $poule->judokas()->attach($j->id);
        }
        $absent = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'aanwezigheid' => 'afwezig']);
        $poule->judokas()->attach($absent->id);

        $toernooi->load(['blokken', 'matten']);
        $overzicht = $this->service->getZaalOverzicht($toernooi);

        $poules = $overzicht[0]['matten'][1]['poules'];
        $this->assertNotEmpty($poules);
        // Should show 3 active judokas (not 4)
        $this->assertEquals(3, $poules[0]['judokas']);
    }

    // ========================================================================
    // plaatsGewichtenVanafBlok (via full integration)
    // ========================================================================

    #[Test]
    public function genereer_varianten_met_veel_categorieen(): void
    {
        $toernooi = Toernooi::factory()->create([
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 0,
                    'geslacht' => 'gemengd',
                    'gewichten' => ['-18', '-20', '-22', '-24', '-26', '-28', '-30', '+30'],
                ],
                'dames' => [
                    'label' => 'Dames U13',
                    'min_leeftijd' => 10,
                    'max_leeftijd' => 13,
                    'max_kg_verschil' => 0,
                    'geslacht' => 'V',
                    'gewichten' => ['-30', '-36', '-40', '+40'],
                ],
            ],
        ]);

        $blokken = [];
        for ($i = 1; $i <= 3; $i++) {
            $blokken[] = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $i]);
        }

        $nummer = 1;
        foreach (["Mini's" => ['-18', '-20', '-22', '-24', '-26', '-28', '-30', '+30'], 'Dames U13' => ['-30', '-36', '-40', '+40']] as $leeftijd => $gewichten) {
            foreach ($gewichten as $gewicht) {
                Poule::factory()->create([
                    'toernooi_id' => $toernooi->id,
                    'nummer' => $nummer++,
                    'leeftijdsklasse' => $leeftijd,
                    'gewichtsklasse' => $gewicht,
                    'type' => 'voorronde',
                    'aantal_judokas' => 4,
                    'aantal_wedstrijden' => 6,
                ]);
            }
        }

        $toernooi->load(['blokken', 'matten', 'poules']);

        $result = $this->service->genereerVarianten($toernooi);

        $this->assertArrayHasKey('varianten', $result);
        // With 12 categories and 3 blocks, should find at least 1 variant
        if (!empty($result['varianten'])) {
            $variant = $result['varianten'][0];
            $this->assertArrayHasKey('toewijzingen', $variant);
            $this->assertCount(12, $variant['toewijzingen']);
        }
    }

    // ========================================================================
    // verdeelOverMatten — minis get lower target
    // ========================================================================

    #[Test]
    public function verdeel_over_matten_minis_categorie_verwerkt(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat1 = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1, 'naam' => 'Mat 1']);
        $mat2 = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2, 'naam' => 'Mat 2']);

        // Create mini poules and jeugd poules
        for ($i = 0; $i < 3; $i++) {
            Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $blok->id,
                'leeftijdsklasse' => "Mini's",
                'gewichtsklasse' => '-' . (20 + $i * 4),
                'type' => 'voorronde',
                'aantal_judokas' => 4,
                'aantal_wedstrijden' => 6,
            ]);
        }
        for ($i = 0; $i < 3; $i++) {
            Poule::factory()->create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $blok->id,
                'leeftijdsklasse' => 'Jeugd',
                'gewichtsklasse' => '-' . (30 + $i * 4),
                'type' => 'voorronde',
                'aantal_judokas' => 4,
                'aantal_wedstrijden' => 6,
            ]);
        }

        $toernooi->load(['blokken', 'matten']);
        $this->service->verdeelOverMatten($toernooi);

        // All poules should be distributed
        $zonderMat = Poule::where('toernooi_id', $toernooi->id)->whereNull('mat_id')->count();
        $this->assertEquals(0, $zonderMat);
    }
}
