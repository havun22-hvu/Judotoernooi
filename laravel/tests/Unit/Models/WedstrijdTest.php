<?php

namespace Tests\Unit\Models;

use App\Models\Wedstrijd;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WedstrijdTest extends TestCase
{
    use RefreshDatabase;

    private function createWedstrijdWithJudokas(): array
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id]);

        $judokaWit = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);
        $judokaBlauw = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        return [$wedstrijd, $judokaWit, $judokaBlauw, $poule];
    }

    #[Test]
    public function it_determines_if_wedstrijd_is_echt_gespeeld(): void
    {
        [$wedstrijd, $judokaWit] = $this->createWedstrijdWithJudokas();

        $this->assertFalse($wedstrijd->isEchtGespeeld());

        $wedstrijd->update([
            'is_gespeeld' => true,
            'winnaar_id' => $judokaWit->id,
        ]);

        $this->assertTrue($wedstrijd->fresh()->isEchtGespeeld());
    }

    #[Test]
    public function it_determines_if_wedstrijd_is_nog_te_spelen(): void
    {
        [$wedstrijd, $judokaWit] = $this->createWedstrijdWithJudokas();

        $this->assertTrue($wedstrijd->isNogTeSpelen());

        $wedstrijd->update([
            'is_gespeeld' => true,
            'winnaar_id' => $judokaWit->id,
        ]);

        $this->assertFalse($wedstrijd->fresh()->isNogTeSpelen());
    }

    #[Test]
    public function it_belongs_to_a_poule(): void
    {
        [$wedstrijd, , , $poule] = $this->createWedstrijdWithJudokas();

        $this->assertInstanceOf(Poule::class, $wedstrijd->poule);
        $this->assertEquals($poule->id, $wedstrijd->poule->id);
    }

    #[Test]
    public function it_has_wit_and_blauw_judokas(): void
    {
        [$wedstrijd, $judokaWit, $judokaBlauw] = $this->createWedstrijdWithJudokas();

        $this->assertInstanceOf(Judoka::class, $wedstrijd->judokaWit);
        $this->assertInstanceOf(Judoka::class, $wedstrijd->judokaBlauw);
        $this->assertEquals($judokaWit->id, $wedstrijd->judokaWit->id);
        $this->assertEquals($judokaBlauw->id, $wedstrijd->judokaBlauw->id);
    }
}
