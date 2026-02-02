<?php

namespace Tests\Unit\Models;

use App\Models\Wedstrijd;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WedstrijdTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;
    private Poule $poule;
    private Judoka $judokaWit;
    private Judoka $judokaBlauw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toernooi = Toernooi::factory()->create();
        $this->poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $this->judokaWit = Judoka::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $this->judokaBlauw = Judoka::factory()->create(['toernooi_id' => $this->toernooi->id]);
    }

    /** @test */
    public function it_determines_if_wedstrijd_is_echt_gespeeld(): void
    {
        $gespeeldMetWinnaar = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'is_gespeeld' => true,
            'winnaar_id' => $this->judokaWit->id,
        ]);

        $gespeeldZonderWinnaar = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'is_gespeeld' => true,
            'winnaar_id' => null, // Bijv. geannuleerd
        ]);

        $nietGespeeld = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);

        $this->assertTrue($gespeeldMetWinnaar->isEchtGespeeld());
        $this->assertFalse($gespeeldZonderWinnaar->isEchtGespeeld());
        $this->assertFalse($nietGespeeld->isEchtGespeeld());
    }

    /** @test */
    public function it_determines_if_wedstrijd_is_nog_te_spelen(): void
    {
        $gespeeld = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'is_gespeeld' => true,
            'winnaar_id' => $this->judokaWit->id,
        ]);

        $nogTeSpelen = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);

        $this->assertFalse($gespeeld->isNogTeSpelen());
        $this->assertTrue($nogTeSpelen->isNogTeSpelen());
    }

    /** @test */
    public function it_validates_scores_are_within_range(): void
    {
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
            'score_wit' => '2',
            'score_blauw' => '1',
        ]);

        // Scores should be 0, 1, or 2
        $this->assertContains((int) $wedstrijd->score_wit, [0, 1, 2]);
        $this->assertContains((int) $wedstrijd->score_blauw, [0, 1, 2]);
    }

    /** @test */
    public function it_gets_opponent_of_judoka(): void
    {
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
        ]);

        $this->assertEquals($this->judokaBlauw->id, $wedstrijd->getOpponent($this->judokaWit->id)?->id);
        $this->assertEquals($this->judokaWit->id, $wedstrijd->getOpponent($this->judokaBlauw->id)?->id);
    }

    /** @test */
    public function it_determines_color_of_judoka(): void
    {
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
        ]);

        $this->assertEquals('wit', $wedstrijd->getKleur($this->judokaWit->id));
        $this->assertEquals('blauw', $wedstrijd->getKleur($this->judokaBlauw->id));
        $this->assertNull($wedstrijd->getKleur(9999)); // Unknown judoka
    }

    /** @test */
    public function it_handles_bye_match(): void
    {
        $byeWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => null,
            'is_gespeeld' => true,
            'winnaar_id' => $this->judokaWit->id,
            'uitslag_type' => 'bye',
        ]);

        $this->assertTrue($byeWedstrijd->isBye());
        $this->assertEquals($this->judokaWit->id, $byeWedstrijd->winnaar_id);
    }
}
