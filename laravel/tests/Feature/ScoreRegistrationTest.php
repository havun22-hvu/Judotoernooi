<?php

namespace Tests\Feature;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $organisator;
    private Toernooi $toernooi;
    private Poule $poule;
    private Wedstrijd $wedstrijd;
    private Judoka $judokaWit;
    private Judoka $judokaBlauw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisator = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
        ]);
        $this->poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);
        $this->judokaWit = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);
        $this->judokaBlauw = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);
        $this->wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $this->judokaWit->id,
            'judoka_blauw_id' => $this->judokaBlauw->id,
        ]);
    }

    /** @test */
    public function score_registration_validates_score_values(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson(route('toernooi.mat.registreer-uitslag', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'wedstrijd_id' => $this->wedstrijd->id,
                'winnaar_id' => $this->judokaWit->id,
                'score_wit' => 8, // Invalid! Must be 0, 1, or 2
                'score_blauw' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('score_wit');
    }

    /** @test */
    public function valid_scores_are_accepted(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson(route('toernooi.mat.registreer-uitslag', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'wedstrijd_id' => $this->wedstrijd->id,
                'winnaar_id' => $this->judokaWit->id,
                'score_wit' => 2,
                'score_blauw' => 1,
                'uitslag_type' => 'waza-ari',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('wedstrijden', [
            'id' => $this->wedstrijd->id,
            'winnaar_id' => $this->judokaWit->id,
            'score_wit' => '2',
            'score_blauw' => '1',
            'is_gespeeld' => true,
        ]);
    }

    /** @test */
    public function score_zero_is_valid(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson(route('toernooi.mat.registreer-uitslag', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'wedstrijd_id' => $this->wedstrijd->id,
                'winnaar_id' => $this->judokaWit->id,
                'score_wit' => 0,
                'score_blauw' => 0,
                'uitslag_type' => 'beslissing',
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function wedstrijd_id_must_exist(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson(route('toernooi.mat.registreer-uitslag', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'wedstrijd_id' => 99999, // Non-existent
                'winnaar_id' => $this->judokaWit->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('wedstrijd_id');
    }
}
