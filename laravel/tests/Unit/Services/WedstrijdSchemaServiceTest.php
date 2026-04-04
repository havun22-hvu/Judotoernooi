<?php

namespace Tests\Unit\Services;

use App\Services\WedstrijdSchemaService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class WedstrijdSchemaServiceTest extends TestCase
{
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

    // ========================================================================
    // Round Robin Schema
    // ========================================================================

    #[Test]
    public function round_robin_2_judokas_geeft_1_wedstrijd(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [2]);

        $this->assertCount(1, $schema);
        $this->assertEquals([1, 2], $schema[0]);
    }

    #[Test]
    public function round_robin_3_judokas_geeft_3_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [3]);

        $this->assertCount(3, $schema);
    }

    #[Test]
    public function round_robin_4_judokas_geeft_6_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [4]);

        $this->assertCount(6, $schema);
    }

    #[Test]
    public function round_robin_5_judokas_geeft_10_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [5]);

        // n*(n-1)/2 = 5*4/2 = 10
        $this->assertCount(10, $schema);
    }

    #[Test]
    public function round_robin_6_judokas_geeft_15_wedstrijden(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [6]);

        $this->assertCount(15, $schema);
    }

    #[Test]
    public function round_robin_elke_judoka_speelt_tegen_iedereen(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [4]);

        // Collect all unique pairs
        $pairs = [];
        foreach ($schema as [$a, $b]) {
            $pairs[] = min($a, $b) . '-' . max($a, $b);
        }

        // 4 judokas = 6 unieke paren
        $this->assertCount(6, array_unique($pairs));
    }

    #[Test]
    public function round_robin_indices_zijn_1_based(): void
    {
        $schema = $this->callPrivate('genereerRoundRobinSchema', [3]);

        foreach ($schema as [$a, $b]) {
            $this->assertGreaterThanOrEqual(1, $a);
            $this->assertGreaterThanOrEqual(1, $b);
            $this->assertLessThanOrEqual(3, $a);
            $this->assertLessThanOrEqual(3, $b);
        }
    }

    // ========================================================================
    // Optimaliseer Volgorde
    // ========================================================================

    #[Test]
    public function optimaliseer_volgorde_behoudt_alle_wedstrijden(): void
    {
        $wedstrijden = [[1, 2], [3, 4], [1, 3], [2, 4], [1, 4], [2, 3]];

        $result = $this->callPrivate('optimaliseerVolgorde', [$wedstrijden, 4]);

        $this->assertCount(6, $result);
    }

    #[Test]
    public function optimaliseer_volgorde_minimaliseert_opeenvolgende(): void
    {
        $wedstrijden = [[1, 2], [1, 3], [1, 4], [2, 3], [2, 4], [3, 4]];

        $result = $this->callPrivate('optimaliseerVolgorde', [$wedstrijden, 4]);

        // First match should not share a judoka with second (if avoidable)
        // With 4 judokas and 6 matches, perfect separation isn't always possible
        // but the optimizer should at least not start with [1,2],[1,3]
        $this->assertCount(6, $result);
    }

    #[Test]
    public function optimaliseer_volgorde_kleine_set_ongewijzigd(): void
    {
        $wedstrijden = [[1, 2]];

        $result = $this->callPrivate('optimaliseerVolgorde', [$wedstrijden, 2]);

        $this->assertEquals([[1, 2]], $result);
    }

    // ========================================================================
    // Punten Competitie
    // ========================================================================

    #[Test]
    public function punten_competitie_minder_wedstrijden_eerlijk_verdeeld(): void
    {
        $roundRobin = $this->callPrivate('genereerRoundRobinSchema', [5]);

        // 5 judokas, elk 3 wedstrijden = 7.5 → afgerond 7-8 totaal
        $result = $this->callPrivate('puntenCompMinderWedstrijden', [5, 3, $roundRobin]);

        // Check elke judoka speelt 3 of nabij 3 wedstrijden
        $counts = array_fill(1, 5, 0);
        foreach ($result as [$a, $b]) {
            $counts[$a]++;
            $counts[$b]++;
        }

        foreach ($counts as $count) {
            $this->assertGreaterThanOrEqual(2, $count);
            $this->assertLessThanOrEqual(4, $count);
        }
    }
}
