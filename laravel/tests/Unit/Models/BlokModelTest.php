<?php

namespace Tests\Unit\Models;

use App\Models\Blok;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlokModelTest extends TestCase
{
    use RefreshDatabase;

    private function maakToernooiMetBlok(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        return [$toernooi, $blok];
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function it_belongs_to_toernooi(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        $this->assertInstanceOf(Toernooi::class, $blok->toernooi);
        $this->assertEquals($toernooi->id, $blok->toernooi->id);
    }

    #[Test]
    public function it_has_many_poules(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        Poule::factory()->count(3)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
        ]);

        $this->assertCount(3, $blok->poules);
    }

    // ========================================================================
    // Casts
    // ========================================================================

    #[Test]
    public function weging_gesloten_is_cast_to_boolean(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        $blok->update(['weging_gesloten' => 1]);

        $this->assertTrue($blok->fresh()->weging_gesloten);
    }

    // ========================================================================
    // Attributes
    // ========================================================================

    #[Test]
    public function naam_attribute_returns_display_name(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        $naam = $blok->naam;

        $this->assertNotEmpty($naam);
        $this->assertIsString($naam);
    }

    #[Test]
    public function totaal_wedstrijden_attribute_defaults_to_zero(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        $this->assertEquals(0, $blok->totaal_wedstrijden);
    }

    // ========================================================================
    // Factory States
    // ========================================================================

    #[Test]
    public function ochtend_factory_sets_morning_times(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->ochtend()->create(['toernooi_id' => $toernooi->id]);

        $this->assertNotNull($blok->weging_start);
        $this->assertNotNull($blok->weging_einde);
    }

    #[Test]
    public function middag_factory_sets_afternoon_times(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->middag()->create(['toernooi_id' => $toernooi->id]);

        $this->assertNotNull($blok->weging_start);
    }

    // ========================================================================
    // Weging
    // ========================================================================

    #[Test]
    public function sluit_weging_sets_gesloten_flag(): void
    {
        [$toernooi, $blok] = $this->maakToernooiMetBlok();

        $this->assertFalse($blok->weging_gesloten);

        $blok->sluitWeging();

        $this->assertTrue($blok->fresh()->weging_gesloten);
        $this->assertNotNull($blok->fresh()->weging_gesloten_op);
    }
}
