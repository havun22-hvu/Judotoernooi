<?php

namespace Tests\Feature;

use App\Services\DynamischeIndelingService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Tests for callPythonSolver() — runs when Python + ortools is available (CI).
 * On systems without Python, these tests are skipped and simpleFallback is tested instead.
 */
class PythonSolverCITest extends TestCase
{
    private DynamischeIndelingService $service;
    private ?string $pythonCmd = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['observability.enabled' => false]);

        $this->service = new DynamischeIndelingService();

        // Check if Python + ortools is available
        $this->pythonCmd = $this->findPython();
        if (!$this->pythonCmd) {
            $this->markTestSkipped('Python not available — solver tests run in CI only.');
        }

        // Also check ortools
        exec("{$this->pythonCmd} -c \"import ortools\" 2>&1", $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('OR-Tools not installed — solver tests run in CI only.');
        }

        // Verify solver script exists
        if (!file_exists(base_path('scripts/poule_solver.py'))) {
            $this->markTestSkipped('poule_solver.py not found.');
        }
    }

    public function test_python_solver_basic_indeling(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => 10, 'gewicht' => 30.0, 'band' => 'wit', 'club_id' => 1],
            ['id' => 2, 'leeftijd' => 10, 'gewicht' => 31.0, 'band' => 'wit', 'club_id' => 2],
            ['id' => 3, 'leeftijd' => 11, 'gewicht' => 32.0, 'band' => 'geel', 'club_id' => 1],
            ['id' => 4, 'leeftijd' => 10, 'gewicht' => 29.5, 'band' => 'wit', 'club_id' => 3],
        ]);

        $result = $this->service->berekenIndeling($judokas, 2, 5.0);

        $this->assertArrayHasKey('poules', $result);
        $this->assertArrayHasKey('totaal_ingedeeld', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertGreaterThan(0, count($result['poules']));
        // All judokas should be placed
        $this->assertEquals(4, $result['totaal_judokas']);
    }

    public function test_python_solver_with_band_constraints(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => 12, 'gewicht' => 40.0, 'band' => 'wit', 'club_id' => 1],
            ['id' => 2, 'leeftijd' => 12, 'gewicht' => 41.0, 'band' => 'geel', 'club_id' => 2],
            ['id' => 3, 'leeftijd' => 12, 'gewicht' => 42.0, 'band' => 'oranje', 'club_id' => 3],
            ['id' => 4, 'leeftijd' => 13, 'gewicht' => 43.0, 'band' => 'groen', 'club_id' => 1],
            ['id' => 5, 'leeftijd' => 13, 'gewicht' => 44.0, 'band' => 'blauw', 'club_id' => 2],
        ]);

        $result = $this->service->berekenIndeling(
            $judokas,
            maxLeeftijdVerschil: 2,
            maxKgVerschil: 10.0,
            maxBandVerschil: 2,
            bandGrens: 'oranje',
            bandVerschilBeginners: 1
        );

        $this->assertArrayHasKey('poules', $result);
        $this->assertGreaterThan(0, count($result['poules']));
    }

    public function test_python_solver_large_group(): void
    {
        $judokaData = [];
        for ($i = 1; $i <= 20; $i++) {
            $judokaData[] = [
                'id' => $i,
                'leeftijd' => rand(10, 14),
                'gewicht' => 25.0 + ($i * 1.5),
                'band' => ['wit', 'geel', 'oranje'][rand(0, 2)],
                'club_id' => rand(1, 5),
            ];
        }

        $judokas = $this->createJudokaCollection($judokaData);
        $result = $this->service->berekenIndeling($judokas, 3, 10.0);

        $this->assertGreaterThan(1, count($result['poules']));
        $this->assertEquals(20, $result['totaal_judokas']);
        $this->assertGreaterThan(0, $result['totaal_ingedeeld']);
    }

    public function test_python_solver_single_judoka(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => 10, 'gewicht' => 30.0, 'band' => 'wit', 'club_id' => 1],
        ]);

        $result = $this->service->berekenIndeling($judokas, 2, 5.0);

        $this->assertEquals(1, $result['totaal_judokas']);
        // Single judoka should still be placed in a poule
        $this->assertGreaterThanOrEqual(1, count($result['poules']));
    }

    public function test_python_solver_empty_collection(): void
    {
        $judokas = new Collection();

        $result = $this->service->berekenIndeling($judokas, 2, 5.0);

        $this->assertEquals(0, $result['totaal_judokas']);
        $this->assertEmpty($result['poules']);
    }

    public function test_python_solver_poule_structure(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => 10, 'gewicht' => 30.0, 'band' => 'wit', 'club_id' => 1],
            ['id' => 2, 'leeftijd' => 10, 'gewicht' => 31.0, 'band' => 'wit', 'club_id' => 2],
            ['id' => 3, 'leeftijd' => 11, 'gewicht' => 30.5, 'band' => 'geel', 'club_id' => 3],
        ]);

        $result = $this->service->berekenIndeling($judokas, 2, 5.0);

        foreach ($result['poules'] as $poule) {
            $this->assertArrayHasKey('judokas', $poule);
            $this->assertArrayHasKey('leeftijd_range', $poule);
            $this->assertArrayHasKey('gewicht_range', $poule);
            $this->assertArrayHasKey('band_range', $poule);
            $this->assertArrayHasKey('leeftijd_groep', $poule);
            $this->assertIsArray($poule['judokas']);
            $this->assertGreaterThan(0, count($poule['judokas']));
        }
    }

    public function test_python_solver_with_missing_data(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => null, 'gewicht' => null, 'band' => null, 'club_id' => null],
            ['id' => 2, 'leeftijd' => 10, 'gewicht' => 30.0, 'band' => 'wit', 'club_id' => 1],
            ['id' => 3, 'leeftijd' => 10, 'gewicht' => 31.0, 'band' => 'geel', 'club_id' => 2],
        ]);

        $result = $this->service->berekenIndeling($judokas, 2, 5.0);

        // Should handle gracefully (either place or mark as incomplete)
        $this->assertEquals(3, $result['totaal_judokas']);
    }

    public function test_python_solver_tight_weight_constraints(): void
    {
        $judokas = $this->createJudokaCollection([
            ['id' => 1, 'leeftijd' => 10, 'gewicht' => 25.0, 'band' => 'wit', 'club_id' => 1],
            ['id' => 2, 'leeftijd' => 10, 'gewicht' => 35.0, 'band' => 'wit', 'club_id' => 2],
            ['id' => 3, 'leeftijd' => 10, 'gewicht' => 45.0, 'band' => 'wit', 'club_id' => 3],
            ['id' => 4, 'leeftijd' => 10, 'gewicht' => 26.0, 'band' => 'wit', 'club_id' => 4],
        ]);

        // Very tight weight constraint: max 3kg difference
        $result = $this->service->berekenIndeling($judokas, 2, 3.0);

        // Should create multiple poules due to weight constraints
        $this->assertGreaterThan(0, count($result['poules']));
    }

    /**
     * Create a Collection of judoka-like objects from array data.
     */
    private function createJudokaCollection(array $data): Collection
    {
        return new Collection(array_map(function ($item) {
            return (object) [
                'id' => $item['id'],
                'leeftijd' => $item['leeftijd'],
                'gewicht' => $item['gewicht'],
                'gewicht_gewogen' => null,
                'gewichtsklasse' => null,
                'band' => $item['band'],
                'club_id' => $item['club_id'],
            ];
        }, $data));
    }

    private function findPython(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['python', 'python3', 'py'] as $cmd) {
                exec("where $cmd 2>NUL", $output, $exitCode);
                if ($exitCode === 0) return $cmd;
                $output = [];
            }
        } else {
            foreach (['python3', 'python'] as $cmd) {
                exec("which $cmd 2>/dev/null", $output, $exitCode);
                if ($exitCode === 0) return $cmd;
                $output = [];
            }
        }
        return null;
    }
}
