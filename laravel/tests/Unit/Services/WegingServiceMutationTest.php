<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\WegingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mutation-killer tests for WegingService. The baseline Infection run left the
 * out-of-range alternative logic (too light / too heavy) and the QR-code URL
 * parsing executed-but-unasserted. These pin the exact opmerking, suggested
 * alternative pool, and QR extraction a surviving mutant would flip.
 * See docs/3-DEVELOPMENT/MUTATION-TESTING.md.
 */
class WegingServiceMutationTest extends TestCase
{
    use RefreshDatabase;

    private WegingService $service;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WegingService();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewicht_tolerantie' => 0.5,
        ]);
        $this->club = Club::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakJudoka(array $attrs = []): Judoka
    {
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ], $attrs));
    }

    // --- bepaalAlternatief: too heavy in a minus-class (kills line 82 GreaterThan + 84 step) ---

    #[Test]
    public function too_heavy_suggests_the_next_minus_class(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-36', 'gewicht' => 35.0]);

        $result = $this->service->registreerGewicht($judoka, 40.0);

        $this->assertFalse($result['binnen_klasse']);
        $this->assertSame('Te zwaar! Maximaal 36kg.', $result['opmerking']);
        $this->assertSame('-40kg', $result['alternatieve_poule']); // 36 + 4
    }

    // --- bepaalAlternatief: too light in a plus-class (kills line 76 LessThan) ---

    #[Test]
    public function too_light_in_plus_class_suggests_a_minus_class(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '+70', 'gewicht' => 72.0]);

        $result = $this->service->registreerGewicht($judoka, 60.0);

        $this->assertFalse($result['binnen_klasse']);
        $this->assertSame('Te licht! Minimaal 70kg.', $result['opmerking']);
        $this->assertSame('-70kg', $result['alternatieve_poule']);
    }

    #[Test]
    public function within_class_yields_no_alternative(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-36', 'gewicht' => 34.0]);

        $result = $this->service->registreerGewicht($judoka, 35.5);

        $this->assertTrue($result['binnen_klasse']);
        $this->assertNull($result['alternatieve_poule']);
        $this->assertNull($result['opmerking']);
    }

    // --- vindJudokaViaQR: URL extraction (kills line 161 rtrim + the strtok strip) ---

    #[Test]
    public function finds_judoka_by_raw_qr_code(): void
    {
        $judoka = $this->maakJudoka(['qr_code' => 'RAW123']);

        $this->assertTrue($this->service->vindJudokaViaQR('RAW123')->is($judoka));
    }

    #[Test]
    public function extracts_qr_code_from_url_with_trailing_slash(): void
    {
        $judoka = $this->maakJudoka(['qr_code' => 'ABC123']);

        // Trailing slash must be rtrim'd off, otherwise the lookup misses.
        $this->assertTrue($this->service->vindJudokaViaQR('/weegkaart/ABC123/')->is($judoka));
    }

    #[Test]
    public function extracts_qr_code_from_url_with_query_and_hash(): void
    {
        $judoka = $this->maakJudoka(['qr_code' => 'XYZ789']);

        $this->assertTrue($this->service->vindJudokaViaQR('/weegkaart/XYZ789?ref=1')->is($judoka));
        $this->assertTrue($this->service->vindJudokaViaQR('/weegkaart/XYZ789#top')->is($judoka));
    }

    #[Test]
    public function returns_null_when_qr_code_is_unknown(): void
    {
        $this->maakJudoka(['qr_code' => 'KNOWN']);

        $this->assertNull($this->service->vindJudokaViaQR('DOES-NOT-EXIST'));
    }
}
