<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudokaManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $organisator;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisator = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
        ]);
        $this->club = Club::factory()->create([
            'organisator_id' => $this->organisator->id,
        ]);
    }

    /** @test */
    public function organisator_can_view_judoka_list(): void
    {
        Judoka::factory()->count(5)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.judoka.index', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function organisator_can_create_judoka(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.judoka.store', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'naam' => 'Test Judoka',
                'geboortejaar' => 2015,
                'geslacht' => 'M',
                'band' => 'wit',
                'gewicht' => 25.5,
                'club_id' => $this->club->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('judokas', [
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test Judoka',
            'geboortejaar' => 2015,
        ]);
    }

    /** @test */
    public function organisator_can_update_judoka(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Original Name',
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->patch(route('toernooi.judoka.update', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
                'judoka' => $judoka,
            ]), [
                'naam' => 'Updated Name',
                'geboortejaar' => $judoka->geboortejaar,
                'geslacht' => $judoka->geslacht,
                'band' => $judoka->band,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('judokas', [
            'id' => $judoka->id,
            'naam' => 'Updated Name',
        ]);
    }

    /** @test */
    public function judoka_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.judoka.store', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                // Missing required 'naam'
                'geboortejaar' => 2015,
            ]);

        $response->assertSessionHasErrors('naam');
    }

    /** @test */
    public function judoka_weight_must_be_within_valid_range(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.judoka.store', [
                'organisator' => $this->organisator,
                'toernooi' => $this->toernooi,
            ]), [
                'naam' => 'Test',
                'geboortejaar' => 2015,
                'geslacht' => 'M',
                'band' => 'wit',
                'gewicht' => 5, // Too low (min is 10)
            ]);

        $response->assertSessionHasErrors('gewicht');
    }
}
