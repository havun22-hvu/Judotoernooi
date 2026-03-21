<?php

namespace Tests\Unit\Models;

use App\Models\Judoka;
use App\Models\Toernooi;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JudokaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_qr_code_on_create(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'qr_code' => null,
        ]);

        $this->assertNotNull($judoka->qr_code);
        $this->assertIsString($judoka->qr_code);
    }

    #[Test]
    public function it_returns_effectief_gewicht_preferring_gewogen(): void
    {
        $judoka = Judoka::factory()->make([
            'gewicht' => 25.5,
            'gewicht_gewogen' => 26.0,
        ]);

        $this->assertEquals(26.0, $judoka->getEffectiefGewicht());
    }

    #[Test]
    public function it_returns_ingeschreven_gewicht_when_not_weighed(): void
    {
        $judoka = Judoka::factory()->make([
            'gewicht' => 25.5,
            'gewicht_gewogen' => null,
        ]);

        $this->assertEquals(25.5, $judoka->getEffectiefGewicht());
    }

    #[Test]
    public function it_calculates_leeftijd_via_attribute(): void
    {
        $judoka = Judoka::factory()->make([
            'geboortejaar' => date('Y') - 8,
        ]);

        $this->assertEquals(8, $judoka->leeftijd);
    }

    #[Test]
    public function it_determines_aanwezig_status(): void
    {
        $aanwezig = Judoka::factory()->make(['aanwezigheid' => 'aanwezig']);
        $afwezig = Judoka::factory()->make(['aanwezigheid' => 'afwezig']);
        $onbekend = Judoka::factory()->make(['aanwezigheid' => 'onbekend']);

        $this->assertTrue($aanwezig->isAanwezig());
        $this->assertFalse($afwezig->isAanwezig());
        $this->assertFalse($onbekend->isAanwezig());
    }

    #[Test]
    public function it_checks_if_judoka_is_actief(): void
    {
        $actief = Judoka::factory()->make([
            'aanwezigheid' => 'aanwezig',
            'gewicht_gewogen' => 25.0,
        ]);

        $afwezig = Judoka::factory()->make([
            'aanwezigheid' => 'afwezig',
            'gewicht_gewogen' => 25.0,
        ]);

        $this->assertTrue($actief->isActief(wegingGesloten: true));
        $this->assertFalse($afwezig->isActief(wegingGesloten: true));
    }

    #[Test]
    public function it_checks_volledig_data(): void
    {
        $volledig = Judoka::factory()->make([
            'naam' => 'Test',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'gewicht' => 25.0,
        ]);

        $this->assertTrue($volledig->isVolledig());
    }
}
