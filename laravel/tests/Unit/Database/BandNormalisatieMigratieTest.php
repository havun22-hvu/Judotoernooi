<?php

namespace Tests\Unit\Database;

use App\Enums\Band;
use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BandNormalisatieMigratieTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIE = __DIR__ . '/../../../database/migrations/2026_07_17_090000_normalize_band_numbers_to_colour_names.php';

    private function draaiMigratie(): void
    {
        (require self::MIGRATIE)->up();
    }

    private function maakJudokaMetRuweBand(string $band): int
    {
        $judoka = Judoka::factory()->create(['toernooi_id' => Toernooi::factory()->create()->id]);
        DB::table('judokas')->where('id', $judoka->id)->update(['band' => $band]);

        return $judoka->id;
    }

    private function bandVan(int $id): ?string
    {
        return DB::table('judokas')->where('id', $id)->value('band');
    }

    #[Test]
    public function converts_every_legacy_number_to_its_colour_name(): void
    {
        $verwacht = [
            '0' => 'zwart',
            '1' => 'bruin',
            '2' => 'blauw',
            '3' => 'groen',
            '4' => 'oranje',
            '5' => 'geel',
            '6' => 'wit',
        ];

        $ids = [];
        foreach ($verwacht as $nummer => $kleur) {
            $ids[$nummer] = $this->maakJudokaMetRuweBand($nummer);
        }

        $this->draaiMigratie();

        foreach ($verwacht as $nummer => $kleur) {
            $this->assertSame($kleur, $this->bandVan($ids[$nummer]), "band {$nummer} moest {$kleur} worden");
        }
    }

    #[Test]
    public function a_black_belt_is_no_longer_reported_as_missing(): void
    {
        // The exact production symptom: 21 judokas stored as "0" were listed as "band ontbreekt".
        $id = $this->maakJudokaMetRuweBand('0');

        $this->draaiMigratie();

        $judoka = Judoka::find($id);
        $this->assertSame('zwart', $judoka->band);
        $this->assertNotContains('band', $judoka->getOntbrekendeVelden());
        $this->assertSame('Zwart', Band::toKleur($judoka->band));
    }

    #[Test]
    public function leaves_colour_names_untouched_and_is_repeatable(): void
    {
        $kleur = $this->maakJudokaMetRuweBand('geel');
        $zwart = $this->maakJudokaMetRuweBand('0');

        $this->draaiMigratie();
        $this->draaiMigratie(); // idempotent: a second run must not change anything

        $this->assertSame('geel', $this->bandVan($kleur));
        $this->assertSame('zwart', $this->bandVan($zwart));
    }

    #[Test]
    public function does_not_touch_values_that_merely_contain_a_digit(): void
    {
        $id = $this->maakJudokaMetRuweBand('geel (5e kyu)');

        $this->draaiMigratie();

        $this->assertSame('geel (5e kyu)', $this->bandVan($id));
    }
}
