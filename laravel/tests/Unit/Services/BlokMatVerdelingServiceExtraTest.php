<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BlokMatVerdelingServiceExtraTest extends TestCase
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

    // ========================================================================
    // getCategorieVolgorde
    // ========================================================================

    #[Test]
    public function categorie_volgorde_minis_is_1(): void
    {
        $this->assertEquals(1, $this->callPrivate('getCategorieVolgorde', ["Mini's"]));
        $this->assertEquals(1, $this->callPrivate('getCategorieVolgorde', ['Minimini']));
    }

    #[Test]
    public function categorie_volgorde_jeugd_is_2(): void
    {
        $this->assertEquals(2, $this->callPrivate('getCategorieVolgorde', ['Jeugd']));
    }

    #[Test]
    public function categorie_volgorde_dames_is_3(): void
    {
        $this->assertEquals(3, $this->callPrivate('getCategorieVolgorde', ['Dames']));
    }

    #[Test]
    public function categorie_volgorde_heren_is_4(): void
    {
        $this->assertEquals(4, $this->callPrivate('getCategorieVolgorde', ['Heren']));
        $this->assertEquals(4, $this->callPrivate('getCategorieVolgorde', ['Jongens U15']));
    }

    #[Test]
    public function categorie_volgorde_null_is_99(): void
    {
        $this->assertEquals(99, $this->callPrivate('getCategorieVolgorde', [null]));
        $this->assertEquals(99, $this->callPrivate('getCategorieVolgorde', ['']));
    }

    #[Test]
    public function categorie_volgorde_unknown_is_99(): void
    {
        $this->assertEquals(99, $this->callPrivate('getCategorieVolgorde', ['Senioren']));
    }

    // ========================================================================
    // sorteerGewichten
    // ========================================================================

    #[Test]
    public function sorteer_gewichten_basis_sortering(): void
    {
        $gewichten = [
            ['leeftijd' => 'u7', 'gewicht' => '-30', 'gewicht_num' => 30, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-20', 'gewicht_num' => 20, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-25', 'gewicht_num' => 25, 'wedstrijden' => 6],
        ];

        $result = $this->callPrivate('sorteerGewichten', [$gewichten, 0]);

        $this->assertEquals(20, $result[0]['gewicht_num']);
        $this->assertEquals(25, $result[1]['gewicht_num']);
        $this->assertEquals(30, $result[2]['gewicht_num']);
    }

    #[Test]
    public function sorteer_gewichten_met_swap_strategie(): void
    {
        $gewichten = [
            ['leeftijd' => 'u7', 'gewicht' => '-20', 'gewicht_num' => 20, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-25', 'gewicht_num' => 25, 'wedstrijden' => 6],
            ['leeftijd' => 'u7', 'gewicht' => '-30', 'gewicht_num' => 30, 'wedstrijden' => 6],
        ];

        // Strategy >= 3 triggers swaps - result may differ from base sort
        $result = $this->callPrivate('sorteerGewichten', [$gewichten, 5]);
        $this->assertCount(3, $result);
    }

    // ========================================================================
    // berekenVariatieParameters
    // ========================================================================

    #[Test]
    public function bereken_variatie_parameters_returns_correct_keys(): void
    {
        $params = $this->callPrivate('berekenVariatieParameters', [0, 50]);

        $this->assertArrayHasKey('verdelingGewicht', $params);
        $this->assertArrayHasKey('aansluitingVariant', $params);
        $this->assertArrayHasKey('randomFactor', $params);
        $this->assertArrayHasKey('sorteerStrategie', $params);
        $this->assertArrayHasKey('leeftijdShuffle', $params);
    }

    #[Test]
    public function bereken_variatie_parameters_verdeling_clamped(): void
    {
        $params = $this->callPrivate('berekenVariatieParameters', [0, 50]);

        $this->assertGreaterThanOrEqual(0.1, $params['verdelingGewicht']);
        $this->assertLessThanOrEqual(0.9, $params['verdelingGewicht']);
    }

    #[Test]
    public function bereken_variatie_parameters_extremes(): void
    {
        // Very low weight
        $params = $this->callPrivate('berekenVariatieParameters', [0, 0]);
        $this->assertGreaterThanOrEqual(0.1, $params['verdelingGewicht']);

        // Very high weight
        $params = $this->callPrivate('berekenVariatieParameters', [0, 100]);
        $this->assertLessThanOrEqual(0.9, $params['verdelingGewicht']);
    }

    // ========================================================================
    // bepaalZwaarGewichtGrens
    // ========================================================================

    #[Test]
    public function bepaal_zwaar_gewicht_grens_empty_returns_9999(): void
    {
        $result = $this->callPrivate('bepaalZwaarGewichtGrens', [collect()]);
        $this->assertEquals(9999, $result);
    }

    // ========================================================================
    // isPouleVoorDames
    // ========================================================================

    #[Test]
    public function is_poule_voor_dames_by_leeftijdsklasse(): void
    {
        $toernooi = Toernooi::factory()->create();

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Dames U15',
        ]);

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertTrue($result);
    }

    #[Test]
    public function is_poule_voor_dames_by_meisjes_label(): void
    {
        $toernooi = Toernooi::factory()->create();

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Meisjes A',
        ]);

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertTrue($result);
    }

    #[Test]
    public function is_poule_niet_voor_dames_by_heren_label(): void
    {
        $toernooi = Toernooi::factory()->create();

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Heren U15',
        ]);

        $result = $this->callPrivate('isPouleVoorDames', [$poule]);
        $this->assertFalse($result);
    }

    // ========================================================================
    // vindMinsteWedstrijdenMat
    // ========================================================================

    #[Test]
    public function vind_minste_wedstrijden_mat(): void
    {
        $matIds = [1, 2, 3];
        $wedstrijdenPerMat = [1 => 10, 2 => 5, 3 => 8];

        $result = $this->callPrivate('vindMinsteWedstrijdenMat', [$matIds, $wedstrijdenPerMat]);
        $this->assertEquals(2, $result);
    }

    #[Test]
    public function vind_minste_wedstrijden_mat_equal_picks_first(): void
    {
        $matIds = [1, 2, 3];
        $wedstrijdenPerMat = [1 => 5, 2 => 5, 3 => 5];

        $result = $this->callPrivate('vindMinsteWedstrijdenMat', [$matIds, $wedstrijdenPerMat]);
        $this->assertEquals(1, $result);
    }

    // ========================================================================
    // verplaatsPoule
    // ========================================================================

    #[Test]
    public function verplaats_poule_updates_blok_id(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok1 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $blok2 = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok1->id,
        ]);

        $this->service->verplaatsPoule($poule, $blok2);

        $this->assertEquals($blok2->id, $poule->fresh()->blok_id);
    }

    // ========================================================================
    // getVerdelingsStatistieken
    // ========================================================================

    #[Test]
    public function get_verdelings_statistieken_returns_per_blok(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);

        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'aantal_wedstrijden' => 6,
        ]);

        $toernooi->load(['blokken', 'matten']);

        $stats = $this->service->getVerdelingsStatistieken($toernooi);

        $this->assertArrayHasKey(1, $stats);
        $this->assertEquals(6, $stats[1]['totaal_wedstrijden']);
        $this->assertArrayHasKey($mat->nummer, $stats[1]['matten']);
        $this->assertEquals(6, $stats[1]['matten'][$mat->nummer]['wedstrijden']);
    }

    // ========================================================================
    // isGemengdToernooi
    // ========================================================================

    #[Test]
    public function is_gemengd_toernooi_false_voor_leeg(): void
    {
        $toernooi = Toernooi::factory()->create(['gewichtsklassen' => []]);
        $this->assertFalse($this->service->isGemengdToernooi($toernooi));
    }

    // ========================================================================
    // hashToewijzingen — extra tests
    // ========================================================================

    #[Test]
    public function hash_is_string(): void
    {
        $hash = $this->callPrivate('hashToewijzingen', [['a' => 1]]);
        $this->assertIsString($hash);
        $this->assertEquals(32, strlen($hash)); // md5 hash
    }

    #[Test]
    public function hash_onafhankelijk_van_invoervolgorde(): void
    {
        $hash1 = $this->callPrivate('hashToewijzingen', [['a' => 0, 'b' => 1, 'c' => 2]]);
        $hash2 = $this->callPrivate('hashToewijzingen', [['c' => 2, 'a' => 0, 'b' => 1]]);
        $this->assertEquals($hash1, $hash2);
    }
}
