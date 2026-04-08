<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatControllerTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'aantal_matten' => 2,
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    private function toernooiUrl(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}" . ($suffix ? "/{$suffix}" : '');
    }

    // ========================================================================
    // Index
    // ========================================================================

    #[Test]
    public function mat_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('mat'));
        $response->assertStatus(200);
    }

    #[Test]
    public function mat_index_requires_auth(): void
    {
        $response = $this->get($this->toernooiUrl('mat'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Show
    // ========================================================================

    #[Test]
    public function mat_show_loads(): void
    {
        $this->actAsOrg();
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $response = $this->get($this->toernooiUrl("mat/{$mat->id}"));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Interface
    // ========================================================================

    #[Test]
    public function mat_interface_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('mat/interface'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Get Wedstrijden API
    // ========================================================================

    #[Test]
    public function get_wedstrijden_requires_mat_id(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->toernooiUrl('mat/wedstrijden'), []);
        // Should return 400 or 422 without mat_id
        $this->assertContains($response->status(), [400, 422]);
    }

    // ========================================================================
    // Registreer uitslag
    // ========================================================================

    #[Test]
    public function registreer_uitslag_requires_wedstrijd_id(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->toernooiUrl('mat/uitslag'), []);
        $response->assertStatus(422);
    }

    #[Test]
    public function registreer_uitslag_succeeds(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $judokaWit = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);
        $judokaBlauw = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
            'is_gespeeld' => false,
        ]);

        $response = $this->postJson($this->toernooiUrl('mat/uitslag'), [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $judokaWit->id,
            'score_wit' => '10',
            'score_blauw' => '0',
        ]);

        $response->assertStatus(200);
    }

    // ========================================================================
    // Set huidige wedstrijd
    // ========================================================================

    #[Test]
    public function set_huidige_wedstrijd(): void
    {
        $this->actAsOrg();
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'mat_id' => $mat->id,
        ]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);

        $response = $this->postJson($this->toernooiUrl('mat/huidige-wedstrijd'), [
            'wedstrijd_id' => $wedstrijd->id,
            'mat_id' => $mat->id,
        ]);

        $response->assertStatus(200);
    }
}
