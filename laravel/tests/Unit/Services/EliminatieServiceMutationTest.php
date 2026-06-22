<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\EliminatieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mutation-killer tests for EliminatieService. The baseline left the bracket-size
 * contract returned by genereerBracket() executed but not precisely asserted —
 * a hotspot of surviving mutants (the a_wedstrijden = n-1 and b_wedstrijden
 * formula on lines 100/104/105). A wrong bracket size = a broken tournament, so
 * we pin the exact returned counts across systems and bronze counts.
 * See docs/3-DEVELOPMENT/MUTATION-TESTING.md.
 */
class EliminatieServiceMutationTest extends TestCase
{
    use RefreshDatabase;

    private EliminatieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EliminatieService();
    }

    /** @return array{0: Poule, 1: array<int>} */
    private function pouleMet(int $aantal): array
    {
        $toernooi = Toernooi::factory()->create(['eliminatie_type' => 'ijf', 'aantal_brons' => 2]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'eliminatie']);

        $ids = [];
        for ($i = 0; $i < $aantal; $i++) {
            $ids[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
            ])->id;
        }

        return [$poule, $ids];
    }

    /** @return array<string, array{int, string, int, int, int}> n, type, brons, verwachtA, verwachtB */
    public static function bracketSizes(): array
    {
        return [
            // dubbel: a = n-1, b = max(0, n-4)
            'dubbel 8'  => [8, 'dubbel', 2, 7, 4],
            'dubbel 6'  => [6, 'dubbel', 2, 5, 2],
            'dubbel 4'  => [4, 'dubbel', 2, 3, 0],   // n<5 → no B-group
            // ijf: a = n-1, b = (brons===1 ? 5 : 4)
            'ijf 8 brons2'  => [8, 'ijf', 2, 7, 4],
            'ijf 8 brons1'  => [8, 'ijf', 1, 7, 5],
            'ijf 16 brons2' => [16, 'ijf', 2, 15, 4],
        ];
    }

    #[Test]
    #[DataProvider('bracketSizes')]
    public function genereer_bracket_returns_the_exact_size_contract(
        int $n,
        string $type,
        int $brons,
        int $verwachtA,
        int $verwachtB,
    ): void {
        [$poule, $ids] = $this->pouleMet($n);

        $result = $this->service->genereerBracket($poule, $ids, $type, $brons);

        $this->assertSame($verwachtA, $result['a_wedstrijden'], 'a_wedstrijden = n - 1');
        $this->assertSame($verwachtB, $result['b_wedstrijden'], 'b_wedstrijden formula');
        $this->assertSame($type, $result['type']);
        $this->assertSame($brons, $result['aantal_brons']);
    }
}
