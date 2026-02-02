<?php

namespace Tests\Unit\Models;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudokaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_formats_naam_correctly(): void
    {
        $judoka = Judoka::factory()->make(['naam' => 'de jong, piet']);

        // Trigger the creating event
        $formatted = Judoka::formatNaam('de jong, piet');

        $this->assertEquals('De Jong, Piet', $formatted);
    }

    /** @test */
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

    /** @test */
    public function it_returns_effectief_gewicht_preferring_gewogen(): void
    {
        $judoka = Judoka::factory()->make([
            'gewicht' => 25.5,
            'gewicht_gewogen' => 26.0,
        ]);

        $this->assertEquals(26.0, $judoka->getEffectiefGewicht());
    }

    /** @test */
    public function it_returns_ingeschreven_gewicht_when_not_weighed(): void
    {
        $judoka = Judoka::factory()->make([
            'gewicht' => 25.5,
            'gewicht_gewogen' => null,
        ]);

        $this->assertEquals(25.5, $judoka->getEffectiefGewicht());
    }

    /** @test */
    public function it_calculates_leeftijd_correctly(): void
    {
        $judoka = Judoka::factory()->make([
            'geboortejaar' => date('Y') - 8,
        ]);

        $this->assertEquals(8, $judoka->getLeeftijd());
    }

    /** @test */
    public function it_determines_aanwezig_status(): void
    {
        $aanwezig = Judoka::factory()->make(['aanwezigheid' => 'aanwezig']);
        $afwezig = Judoka::factory()->make(['aanwezigheid' => 'afwezig']);
        $onbekend = Judoka::factory()->make(['aanwezigheid' => 'onbekend']);

        $this->assertTrue($aanwezig->isAanwezig());
        $this->assertFalse($afwezig->isAanwezig());
        $this->assertFalse($onbekend->isAanwezig());
    }

    /** @test */
    public function it_checks_if_judoka_is_actief(): void
    {
        // Aanwezig en gewogen
        $actief = Judoka::factory()->make([
            'aanwezigheid' => 'aanwezig',
            'gewicht_gewogen' => 25.0,
        ]);

        // Afwezig
        $afwezig = Judoka::factory()->make([
            'aanwezigheid' => 'afwezig',
            'gewicht_gewogen' => 25.0,
        ]);

        // Aanwezig maar niet gewogen (weging vereist)
        $nietGewogen = Judoka::factory()->make([
            'aanwezigheid' => 'aanwezig',
            'gewicht_gewogen' => null,
        ]);

        $this->assertTrue($actief->isActief(wegingGesloten: true));
        $this->assertFalse($afwezig->isActief(wegingGesloten: true));
        $this->assertFalse($nietGewogen->isActief(wegingGesloten: true));

        // Als weging niet vereist is
        $this->assertTrue($nietGewogen->isActief(wegingGesloten: false));
    }

    /** @test */
    public function it_checks_complete_data(): void
    {
        $complete = Judoka::factory()->make([
            'naam' => 'Test',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'gewicht' => 25.0,
        ]);

        $incomplete = Judoka::factory()->make([
            'naam' => 'Test',
            'geboortejaar' => null,
            'geslacht' => null,
        ]);

        $this->assertTrue($complete->isCompleet());
        $this->assertFalse($incomplete->isCompleet());
    }
}
