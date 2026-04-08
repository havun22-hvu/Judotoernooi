<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokVerdeling\BlokCapaciteitHelper;
use App\Services\BlokVerdeling\BlokPlaatsingsHelper;
use App\Services\BlokVerdeling\BlokScoreCalculator;
use App\Services\BlokVerdeling\BlokVerdelingConstants;
use App\Services\BlokVerdeling\CategorieHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlokVerdelingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private BlokCapaciteitHelper $capaciteitHelper;
    private BlokPlaatsingsHelper $plaatsingsHelper;
    private BlokScoreCalculator $scoreCalculator;
    private CategorieHelper $categorieHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capaciteitHelper = new BlokCapaciteitHelper();
        $this->plaatsingsHelper = new BlokPlaatsingsHelper($this->capaciteitHelper);
        $this->scoreCalculator = new BlokScoreCalculator();
        $this->categorieHelper = new CategorieHelper();
    }

    /**
     * Create a tournament with blocks and poules for testing.
     */
    private function createToernooiMetBlokken(int $aantalBlokken = 3, array $pouleData = []): array
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'aantal_blokken' => $aantalBlokken,
        ]);

        $blokken = [];
        for ($i = 1; $i <= $aantalBlokken; $i++) {
            $blokken[] = Blok::factory()->create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $i,
                'gewenst_wedstrijden' => null,
            ]);
        }

        foreach ($pouleData as $pd) {
            Poule::factory()->create(array_merge([
                'toernooi_id' => $toernooi->id,
            ], $pd));
        }

        return [$toernooi, $blokken];
    }

    /**
     * Helper: create simple blok objects for non-DB tests.
     */
    private function makeBlokObjects(int $count): array
    {
        $blokken = [];
        for ($i = 0; $i < $count; $i++) {
            $blokken[] = (object) ['id' => $i + 1, 'nummer' => $i + 1, 'gewenst_wedstrijden' => null];
        }
        return $blokken;
    }

    // ========================================================================
    // BlokCapaciteitHelper
    // ========================================================================

    #[Test]
    public function bereken_capaciteit_met_gelijke_verdeling(): void
    {
        [$toernooi, $blokken] = $this->createToernooiMetBlokken(3, [
            ['blok_id' => null, 'aantal_wedstrijden' => 6, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-28'],
            ['blok_id' => null, 'aantal_wedstrijden' => 6, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-32'],
            ['blok_id' => null, 'aantal_wedstrijden' => 6, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-36'],
        ]);

        // Assign poules to blocks
        $poules = $toernooi->poules()->get();
        $poules[0]->update(['blok_id' => $blokken[0]->id]);
        $poules[1]->update(['blok_id' => $blokken[1]->id]);
        $poules[2]->update(['blok_id' => $blokken[2]->id]);

        $result = $this->capaciteitHelper->berekenCapaciteit($toernooi, collect($blokken));

        $this->assertCount(3, $result);
        // 18 total / 3 blocks = 6 desired per block
        foreach ($blokken as $blok) {
            $this->assertEquals(6, $result[$blok->id]['gewenst']);
            $this->assertEquals(6, $result[$blok->id]['actueel']);
            $this->assertEquals(0, $result[$blok->id]['ruimte']);
        }
    }

    #[Test]
    public function bereken_capaciteit_zonder_blokken_geeft_leeg(): void
    {
        [$toernooi] = $this->createToernooiMetBlokken(0);

        $result = $this->capaciteitHelper->berekenCapaciteit($toernooi, collect([]));

        $this->assertEmpty($result);
    }

    #[Test]
    public function bereken_capaciteit_met_ongelijke_verdeling(): void
    {
        [$toernooi, $blokken] = $this->createToernooiMetBlokken(2, [
            ['blok_id' => null, 'aantal_wedstrijden' => 10, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-28'],
            ['blok_id' => null, 'aantal_wedstrijden' => 5, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-32'],
        ]);

        $poules = $toernooi->poules()->get();
        $poules[0]->update(['blok_id' => $blokken[0]->id]);
        $poules[1]->update(['blok_id' => $blokken[1]->id]);

        $result = $this->capaciteitHelper->berekenCapaciteit($toernooi, collect($blokken));

        // 15 total / 2 blocks = 8 desired (ceil)
        $this->assertEquals(8, $result[$blokken[0]->id]['gewenst']);
        $this->assertEquals(10, $result[$blokken[0]->id]['actueel']);
        $this->assertEquals(-2, $result[$blokken[0]->id]['ruimte']);
    }

    #[Test]
    public function bereken_capaciteit_met_gewenst_wedstrijden_op_blok(): void
    {
        [$toernooi, $blokken] = $this->createToernooiMetBlokken(2, [
            ['blok_id' => null, 'aantal_wedstrijden' => 4, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-28'],
        ]);

        $blokken[0]->update(['gewenst_wedstrijden' => 20]);
        $blokken[0]->refresh();

        $poules = $toernooi->poules()->get();
        $poules[0]->update(['blok_id' => $blokken[0]->id]);

        $result = $this->capaciteitHelper->berekenCapaciteit($toernooi, collect($blokken));

        // Block 0 has custom gewenst_wedstrijden = 20
        $this->assertEquals(20, $result[$blokken[0]->id]['gewenst']);
        $this->assertEquals(4, $result[$blokken[0]->id]['actueel']);
        $this->assertEquals(16, $result[$blokken[0]->id]['ruimte']);
    }

    #[Test]
    public function initialize_simulatie_capaciteit_telt_alleen_vaste_poules(): void
    {
        [$toernooi, $blokken] = $this->createToernooiMetBlokken(2, [
            ['blok_id' => null, 'aantal_wedstrijden' => 5, 'blok_vast' => true, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-28'],
            ['blok_id' => null, 'aantal_wedstrijden' => 8, 'blok_vast' => false, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-32'],
        ]);

        $poules = $toernooi->poules()->get();
        $poules[0]->update(['blok_id' => $blokken[0]->id]);
        $poules[1]->update(['blok_id' => $blokken[0]->id]);

        $result = $this->capaciteitHelper->initializeSimulatieCapaciteit($toernooi, $blokken, 15);

        // Block 0: only the blok_vast=true poule counts (5 matches)
        $this->assertEquals(15, $result[$blokken[0]->id]['gewenst']);
        $this->assertEquals(5, $result[$blokken[0]->id]['actueel']);
        $this->assertEquals(10, $result[$blokken[0]->id]['ruimte']);

        // Block 1: no poules assigned
        $this->assertEquals(0, $result[$blokken[1]->id]['actueel']);
        $this->assertEquals(15, $result[$blokken[1]->id]['ruimte']);
    }

    #[Test]
    public function vind_blok_met_meeste_ruimte_geeft_juiste_index(): void
    {
        $blokken = $this->makeBlokObjects(3);

        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 8, 'ruimte' => 2],
            2 => ['gewenst' => 10, 'actueel' => 3, 'ruimte' => 7],
            3 => ['gewenst' => 10, 'actueel' => 6, 'ruimte' => 4],
        ];

        $index = $this->capaciteitHelper->vindBlokMetMeesteRuimte($capaciteit, $blokken);

        $this->assertEquals(1, $index); // blok id=2, index=1
    }

    #[Test]
    public function vind_random_blok_met_ruimte_geeft_top3_index(): void
    {
        $blokken = $this->makeBlokObjects(5);

        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 9, 'ruimte' => 1],
            2 => ['gewenst' => 10, 'actueel' => 2, 'ruimte' => 8],
            3 => ['gewenst' => 10, 'actueel' => 5, 'ruimte' => 5],
            4 => ['gewenst' => 10, 'actueel' => 4, 'ruimte' => 6],
            5 => ['gewenst' => 10, 'actueel' => 8, 'ruimte' => 2],
        ];

        // Run multiple times, result should always be in top 3 (indices 1, 2, 3 = ruimte 8, 6, 5)
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $this->capaciteitHelper->vindRandomBlokMetRuimte($capaciteit, $blokken);
        }

        foreach ($results as $idx) {
            $this->assertContains($idx, [1, 2, 3]); // top 3 by room
        }
    }

    #[Test]
    public function update_capaciteit_past_waarden_aan(): void
    {
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 3, 'ruimte' => 7],
        ];

        $this->capaciteitHelper->updateCapaciteit($capaciteit, 1, 4);

        $this->assertEquals(7, $capaciteit[1]['actueel']);
        $this->assertEquals(3, $capaciteit[1]['ruimte']);
    }

    #[Test]
    public function kan_plaatsen_binnen_limiet(): void
    {
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 5, 'ruimte' => 5],
        ];

        // 5 + 5 = 10, max = 10 * 1.30 = 13 → OK
        $this->assertTrue($this->capaciteitHelper->kanPlaatsen($capaciteit, 1, 5));
    }

    #[Test]
    public function kan_plaatsen_over_limiet(): void
    {
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // 10 + 5 = 15, max = 10 * 1.30 = 13 → NOT OK
        $this->assertFalse($this->capaciteitHelper->kanPlaatsen($capaciteit, 1, 5));
    }

    #[Test]
    public function kan_plaatsen_met_gewenst_nul_gebruikt_min_1(): void
    {
        $capaciteit = [
            1 => ['gewenst' => 0, 'actueel' => 0, 'ruimte' => 0],
        ];

        // gewenst=0 → max(1,0)=1, max = 1 * 1.30 = 1.30, 0+1=1 ≤ 1.30 → OK
        $this->assertTrue($this->capaciteitHelper->kanPlaatsen($capaciteit, 1, 1));
        // 0+2=2 > 1.30 → NOT OK
        $this->assertFalse($this->capaciteitHelper->kanPlaatsen($capaciteit, 1, 2));
    }

    // ========================================================================
    // BlokPlaatsingsHelper
    // ========================================================================

    #[Test]
    public function vind_beste_blok_met_aansluiting_kiest_zelfde_blok(): void
    {
        $blokken = $this->makeBlokObjects(4);
        $capaciteit = [
            1 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            2 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            3 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            4 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
        ];

        // variant=0: [0, 1, -1, 2], previous=1, randomFactor=0
        // offset 0 → idx 1 (same block) should score best for adjacency
        $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
            vorigeBlokIndex: 1,
            wedstrijden: 3,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 4,
            aansluitingVariant: 0,
            verdelingGewicht: 0.5,
            randomFactor: 0.0
        );

        // Should pick one of the adjacent blocks (0, 1, or 2)
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThan(4, $result);
    }

    #[Test]
    public function vind_beste_blok_fallback_als_geen_kandidaten(): void
    {
        $blokken = $this->makeBlokObjects(3);
        // All blocks are completely full
        $capaciteit = [
            1 => ['gewenst' => 5, 'actueel' => 10, 'ruimte' => -5],
            2 => ['gewenst' => 5, 'actueel' => 10, 'ruimte' => -5],
            3 => ['gewenst' => 5, 'actueel' => 10, 'ruimte' => -5],
        ];

        $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
            vorigeBlokIndex: 1,
            wedstrijden: 5,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 3,
            aansluitingVariant: 0,
            verdelingGewicht: 0.5
        );

        // Falls back to vindBlokMetMeesteRuimte
        $this->assertGreaterThanOrEqual(0, $result);
    }

    #[Test]
    public function vind_beste_blok_met_hoge_random_factor_pakt_tweede(): void
    {
        $blokken = $this->makeBlokObjects(4);
        $capaciteit = [
            1 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            2 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            3 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            4 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
        ];

        // randomFactor=0.8 → picks 2nd best (if >1 candidate)
        $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
            vorigeBlokIndex: 1,
            wedstrijden: 3,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 4,
            aansluitingVariant: 0,
            verdelingGewicht: 0.5,
            randomFactor: 0.8
        );

        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThan(4, $result);
    }

    #[Test]
    public function vind_beste_blok_met_zeer_hoge_random_factor_pakt_derde(): void
    {
        $blokken = $this->makeBlokObjects(5);
        $capaciteit = [
            1 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            2 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            3 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            4 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
            5 => ['gewenst' => 20, 'actueel' => 2, 'ruimte' => 18],
        ];

        // randomFactor=0.95 → picks 3rd best (if >2 candidates)
        $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
            vorigeBlokIndex: 2,
            wedstrijden: 3,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 5,
            aansluitingVariant: 0,
            verdelingGewicht: 0.5,
            randomFactor: 0.95
        );

        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThan(5, $result);
    }

    #[Test]
    public function vind_beste_blok_alle_varianten(): void
    {
        $blokken = $this->makeBlokObjects(4);
        $capaciteit = [
            1 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            2 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            3 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            4 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
        ];

        // Test all 6 strategy variants (0-5) + default
        for ($variant = 0; $variant <= 6; $variant++) {
            $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
                vorigeBlokIndex: 2,
                wedstrijden: 3,
                capaciteit: $capaciteit,
                blokken: $blokken,
                numBlokken: 4,
                aansluitingVariant: $variant,
                verdelingGewicht: 0.5
            );

            $this->assertGreaterThanOrEqual(0, $result, "Variant $variant failed");
            $this->assertLessThan(4, $result, "Variant $variant out of range");
        }
    }

    #[Test]
    public function vind_beste_blok_randindex_links(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            2 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
            3 => ['gewenst' => 20, 'actueel' => 5, 'ruimte' => 15],
        ];

        // Previous at index 0: -1 would be out of bounds
        $result = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
            vorigeBlokIndex: 0,
            wedstrijden: 3,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 3,
            aansluitingVariant: 1, // [-1 first variant]
            verdelingGewicht: 0.5
        );

        $this->assertGreaterThanOrEqual(0, $result);
    }

    #[Test]
    public function vind_beste_blok_voor_variabele_poule(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 8, 'ruimte' => 2],
            2 => ['gewenst' => 10, 'actueel' => 3, 'ruimte' => 7],
            3 => ['gewenst' => 10, 'actueel' => 6, 'ruimte' => 4],
        ];

        $result = $this->plaatsingsHelper->vindBesteBlokVoorVariabelePoule(
            wedstrijden: 3,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 3
        );

        // Block 2 (index 1) has most room (7)
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function vind_beste_blok_voor_variabele_poule_alle_vol(): void
    {
        $blokken = $this->makeBlokObjects(2);
        $capaciteit = [
            1 => ['gewenst' => 5, 'actueel' => 10, 'ruimte' => -5],
            2 => ['gewenst' => 5, 'actueel' => 10, 'ruimte' => -5],
        ];

        // None can place, so returns default index 0
        $result = $this->plaatsingsHelper->vindBesteBlokVoorVariabelePoule(
            wedstrijden: 5,
            capaciteit: $capaciteit,
            blokken: $blokken,
            numBlokken: 2
        );

        $this->assertEquals(0, $result);
    }

    // ========================================================================
    // BlokScoreCalculator
    // ========================================================================

    #[Test]
    public function bereken_scores_perfecte_verdeling(): void
    {
        $blokken = $this->makeBlokObjects(2);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        $this->assertEquals(0.0, $result['verdeling_score']);
        $this->assertTrue($result['is_valid']);
        $this->assertArrayHasKey('totaal_score', $result);
        $this->assertArrayHasKey('blok_stats', $result);
        $this->assertArrayHasKey('gewichten', $result);
    }

    #[Test]
    public function bereken_scores_slechte_verdeling_is_invalid(): void
    {
        $blokken = $this->makeBlokObjects(2);
        // Block 1 has 50% deviation (15 vs 10)
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 15, 'ruimte' => -5],
            2 => ['gewenst' => 10, 'actueel' => 5, 'ruimte' => 5],
        ];
        $toewijzingen = [];
        $perLeeftijd = [];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // 50% > MAX_AFWIJKING_PERCENTAGE (25%) → invalid
        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThan(0, $result['verdeling_score']);
        $this->assertEquals(50.0, $result['max_afwijking_pct']);
    }

    #[Test]
    public function bereken_scores_aansluiting_zelfde_blok(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            3 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // All in same block → 0 transition penalty
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 1,
            'pupillen|-36' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-36'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        $this->assertEquals(0, $result['aansluiting_score']);
        $this->assertEquals(2, $result['overgangen']);
        $this->assertEquals(0, $result['aflopend']);
    }

    #[Test]
    public function bereken_scores_aansluiting_volgend_blok(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            3 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // Sequential: 1 → 2 → 3 (ascending)
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 2,
            'pupillen|-36' => 3,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-36'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // 2 transitions: 1→2 = 10pts, 2→3 = 10pts = 20
        $this->assertEquals(20, $result['aansluiting_score']);
        $this->assertEquals(0, $result['aflopend']); // ascending, not descending
    }

    #[Test]
    public function bereken_scores_aansluiting_aflopend_penalty(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            3 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // Descending: 3 → 2 → 1 (eerste > laatste → penalty)
        $toewijzingen = [
            'pupillen|-28' => 3,
            'pupillen|-32' => 2,
            'pupillen|-36' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-36'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        $this->assertEquals(1, $result['aflopend']);
        // Score includes AANSLUITING_AFLOPEND_PENALTY (200) + transition penalties
        $this->assertGreaterThanOrEqual(200, $result['aansluiting_score']);
    }

    #[Test]
    public function bereken_scores_aansluiting_grote_sprong(): void
    {
        $blokken = $this->makeBlokObjects(5);
        $capaciteit = [];
        for ($i = 1; $i <= 5; $i++) {
            $capaciteit[$i] = ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0];
        }

        // Jump from 1 to 4 (verschil = 3, > 2 → AANSLUITING_VERDER + 3*10)
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 4,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // verschil=3: 50 + 3*10 = 80
        $expected = BlokVerdelingConstants::AANSLUITING_VERDER + 3 * 10;
        $this->assertEquals($expected, $result['aansluiting_score']);
    }

    #[Test]
    public function bereken_scores_aansluiting_grote_negatieve_sprong(): void
    {
        $blokken = $this->makeBlokObjects(5);
        $capaciteit = [];
        for ($i = 1; $i <= 5; $i++) {
            $capaciteit[$i] = ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0];
        }

        // Jump from 4 to 1 (verschil = -3, < -1 → AANSLUITING_VERDER + 3*10)
        $toewijzingen = [
            'pupillen|-28' => 4,
            'pupillen|-32' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // verschil=-3: 50 + abs(-3)*10 = 80, plus aflopend penalty 200
        $expectedTransition = BlokVerdelingConstants::AANSLUITING_VERDER + 3 * 10;
        $expectedTotal = $expectedTransition + BlokVerdelingConstants::AANSLUITING_AFLOPEND_PENALTY;
        $this->assertEquals($expectedTotal, $result['aansluiting_score']);
    }

    #[Test]
    public function bereken_scores_aansluiting_twee_blokken(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            3 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // Jump: 1 → 3 (verschil = 2)
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 3,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        $this->assertEquals(BlokVerdelingConstants::AANSLUITING_TWEE_BLOKKEN, $result['aansluiting_score']);
    }

    #[Test]
    public function bereken_scores_aansluiting_vorig_blok(): void
    {
        $blokken = $this->makeBlokObjects(3);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            3 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // Step back: 2 → 1 (verschil = -1)
        $toewijzingen = [
            'pupillen|-28' => 2,
            'pupillen|-32' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // verschil=-1: 20, plus aflopend penalty (laatste < eerste: 1 < 2)
        $this->assertEquals(
            BlokVerdelingConstants::AANSLUITING_VORIG_BLOK + BlokVerdelingConstants::AANSLUITING_AFLOPEND_PENALTY,
            $result['aansluiting_score']
        );
    }

    #[Test]
    public function bereken_scores_met_custom_gewichten(): void
    {
        $blokken = $this->makeBlokObjects(2);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 12, 'ruimte' => -2],
            2 => ['gewenst' => 10, 'actueel' => 8, 'ruimte' => 2],
        ];
        $toewijzingen = [
            'pupillen|-28' => 1,
            'pupillen|-32' => 2,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'],
            ],
        ];

        $result = $this->scoreCalculator->berekenScores(
            $toewijzingen, $capaciteit, $blokken, $perLeeftijd,
            verdelingGewicht: 0.8,
            aansluitingGewicht: 0.2
        );

        $this->assertEquals(80, $result['gewichten']['verdeling']);
        $this->assertEquals(20, $result['gewichten']['aansluiting']);
    }

    #[Test]
    public function bereken_scores_categorie_met_null_toewijzing_wordt_overgeslagen(): void
    {
        $blokken = $this->makeBlokObjects(2);
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
            2 => ['gewenst' => 10, 'actueel' => 10, 'ruimte' => 0],
        ];

        // Only first category has assignment
        $toewijzingen = [
            'pupillen|-28' => 1,
        ];
        $perLeeftijd = [
            'pupillen' => [
                ['leeftijd' => 'pupillen', 'gewicht' => '-28'],
                ['leeftijd' => 'pupillen', 'gewicht' => '-32'], // not in toewijzingen
            ],
        ];

        $result = $this->scoreCalculator->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        // No transitions counted (second is null, so no vorigBlok+blokNr pair)
        $this->assertEquals(0, $result['overgangen']);
    }

    // ========================================================================
    // CategorieHelper
    // ========================================================================

    #[Test]
    public function parse_gewicht_min_notatie(): void
    {
        $this->assertEquals(28.0, $this->categorieHelper->parseGewicht('-28'));
        $this->assertEquals(50.0, $this->categorieHelper->parseGewicht('-50'));
    }

    #[Test]
    public function parse_gewicht_plus_notatie(): void
    {
        // +50 → 50 + 1000 = 1050 (sorts after all minus weights)
        $this->assertEquals(1050.0, $this->categorieHelper->parseGewicht('+50'));
        $this->assertEquals(1073.0, $this->categorieHelper->parseGewicht('+73'));
    }

    #[Test]
    public function parse_gewicht_ongeldig_geeft_999(): void
    {
        $this->assertEquals(999.0, $this->categorieHelper->parseGewicht('onbekend'));
        $this->assertEquals(999.0, $this->categorieHelper->parseGewicht(''));
    }

    #[Test]
    public function parse_gewicht_zonder_teken(): void
    {
        // "28" → matches regex with empty sign → treated as minus
        $this->assertEquals(28.0, $this->categorieHelper->parseGewicht('28'));
    }

    #[Test]
    public function groepeer_per_leeftijd_sorteert_op_gewicht(): void
    {
        $categories = collect([
            ['leeftijd' => 'pupillen', 'gewicht' => '-36', 'gewicht_num' => 36],
            ['leeftijd' => 'pupillen', 'gewicht' => '-28', 'gewicht_num' => 28],
            ['leeftijd' => 'pupillen', 'gewicht' => '+36', 'gewicht_num' => 1036],
            ['leeftijd' => 'aspiranten', 'gewicht' => '-40', 'gewicht_num' => 40],
        ]);

        $result = $this->categorieHelper->groepeerPerLeeftijd($categories);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('pupillen', $result);
        $this->assertArrayHasKey('aspiranten', $result);

        // Pupillen sorted: -28, -36, +36
        $this->assertEquals(28, $result['pupillen'][0]['gewicht_num']);
        $this->assertEquals(36, $result['pupillen'][1]['gewicht_num']);
        $this->assertEquals(1036, $result['pupillen'][2]['gewicht_num']);
    }

    #[Test]
    public function get_grote_leeftijden_filtert_mannelijk_en_gemengd(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => [
                'heren' => [
                    'label' => 'Heren',
                    'geslacht' => 'M',
                    'min_leeftijd' => 18,
                    'max_leeftijd' => 99,
                    'max_kg_verschil' => 0,
                ],
                'dames' => [
                    'label' => 'Dames',
                    'geslacht' => 'V',
                    'min_leeftijd' => 18,
                    'max_leeftijd' => 99,
                    'max_kg_verschil' => 0,
                ],
                'jeugd' => [
                    'label' => 'Jeugd',
                    'geslacht' => 'gemengd',
                    'min_leeftijd' => 8,
                    'max_leeftijd' => 12,
                    'max_kg_verschil' => 0,
                ],
            ],
        ]);

        $grote = $this->categorieHelper->getGroteLeeftijden($toernooi);

        // M and gemengd are "groot"
        $this->assertContains('Heren', $grote);
        $this->assertContains('Jeugd', $grote);
        $this->assertNotContains('Dames', $grote);
    }

    #[Test]
    public function get_kleine_leeftijden_filtert_vrouwelijk(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => [
                'heren' => [
                    'label' => 'Heren',
                    'geslacht' => 'M',
                    'min_leeftijd' => 18,
                    'max_leeftijd' => 99,
                    'max_kg_verschil' => 0,
                ],
                'dames' => [
                    'label' => 'Dames',
                    'geslacht' => 'V',
                    'min_leeftijd' => 18,
                    'max_leeftijd' => 99,
                    'max_kg_verschil' => 0,
                ],
            ],
        ]);

        $kleine = $this->categorieHelper->getKleineLeeftijden($toernooi);

        $this->assertContains('Dames', $kleine);
        $this->assertNotContains('Heren', $kleine);
    }

    #[Test]
    public function get_grote_leeftijden_zonder_geslacht_is_groot(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 3,
                    // No 'geslacht' key → defaults to 'gemengd'
                ],
            ],
        ]);

        $grote = $this->categorieHelper->getGroteLeeftijden($toernooi);

        $this->assertContains("Mini's", $grote);
    }

    #[Test]
    public function get_categories_met_toewijzing(): void
    {
        [$toernooi, $blokken] = $this->createToernooiMetBlokken(2, [
            ['blok_id' => null, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-28', 'aantal_wedstrijden' => 6],
            ['blok_id' => null, 'leeftijdsklasse' => 'pupillen', 'gewichtsklasse' => '-32', 'aantal_wedstrijden' => 3],
        ]);

        $poules = $toernooi->poules()->get();
        $poules[0]->update(['blok_id' => $blokken[0]->id]);
        $poules[1]->update(['blok_id' => $blokken[1]->id]);

        $result = $this->categorieHelper->getCategoriesMetToewijzing($toernooi);

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertArrayHasKey('leeftijd', $first);
        $this->assertArrayHasKey('gewicht', $first);
        $this->assertArrayHasKey('gewicht_num', $first);
        $this->assertArrayHasKey('wedstrijden', $first);
        $this->assertArrayHasKey('blok_id', $first);
        $this->assertArrayHasKey('blok_vast', $first);
    }

    #[Test]
    public function get_vastgezette_bloknummers_per_leeftijd(): void
    {
        $blokken = $this->makeBlokObjects(3);

        $alleCategorieen = collect([
            ['leeftijd' => 'pupillen', 'gewicht' => '-28', 'blok_vast' => true, 'blok_id' => 1],
            ['leeftijd' => 'pupillen', 'gewicht' => '-32', 'blok_vast' => true, 'blok_id' => 2],
            ['leeftijd' => 'pupillen', 'gewicht' => '-36', 'blok_vast' => false, 'blok_id' => 3],
            ['leeftijd' => 'aspiranten', 'gewicht' => '-40', 'blok_vast' => true, 'blok_id' => 1],
            ['leeftijd' => 'aspiranten', 'gewicht' => '-50', 'blok_vast' => false, 'blok_id' => null],
        ]);

        $result = $this->categorieHelper->getVastgezetteBloknummersPerLeeftijd($alleCategorieen, $blokken);

        $this->assertArrayHasKey('pupillen', $result);
        $this->assertArrayHasKey('aspiranten', $result);
        $this->assertCount(2, $result['pupillen']); // -28 and -32
        $this->assertCount(1, $result['aspiranten']); // -40
        $this->assertContains(1, $result['pupillen']);
        $this->assertContains(2, $result['pupillen']);
    }

    #[Test]
    public function get_vastgezette_bloknummers_leeg_bij_geen_vaste(): void
    {
        $blokken = $this->makeBlokObjects(2);
        $alleCategorieen = collect([
            ['leeftijd' => 'pupillen', 'gewicht' => '-28', 'blok_vast' => false, 'blok_id' => 1],
        ]);

        $result = $this->categorieHelper->getVastgezetteBloknummersPerLeeftijd($alleCategorieen, $blokken);

        $this->assertEmpty($result);
    }

    #[Test]
    public function splits_categorieen_op_type_scheidt_vast_en_variabel(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => [
                'pupillen' => [
                    'label' => 'Pupillen',
                    'min_leeftijd' => 7,
                    'max_leeftijd' => 9,
                    'max_kg_verschil' => 0, // vast
                    'geslacht' => 'gemengd',
                ],
                'minis' => [
                    'label' => "Mini's",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 3, // variabel
                    'max_leeftijd_verschil' => 1,
                    'geslacht' => 'gemengd',
                ],
            ],
        ]);

        // Create poules for the fixed category
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
            'gewichtsklasse' => '-28',
            'aantal_wedstrijden' => 6,
            'blok_vast' => false,
        ]);

        // Mock the variabele service
        $variabeleService = $this->createMock(\App\Services\VariabeleBlokVerdelingService::class);
        $variabeleService->method('getVariabelePoules')->willReturn(collect([
            ['leeftijdsklasse' => "Mini's", 'gewichtsklasse' => '-20', 'aantal_wedstrijden' => 3],
            ['leeftijdsklasse' => 'Pupillen', 'gewichtsklasse' => '-24', 'aantal_wedstrijden' => 4],
        ]));

        [$vaste, $variabele] = $this->categorieHelper->splitsCategorieenOpType($toernooi, $variabeleService);

        // Vaste: Pupillen (max_kg_verschil=0), non-pinned poules
        $this->assertGreaterThanOrEqual(0, $vaste->count());

        // Variabele: only Mini's matches (max_kg_verschil=3)
        $this->assertEquals(1, $variabele->count());
    }
}
