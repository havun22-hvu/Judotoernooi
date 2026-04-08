<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleControllerExtendedTest extends TestCase
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
            'gebruik_gewichtsklassen' => true,
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
    public function poule_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('poule'));
        $response->assertStatus(200);
    }

    #[Test]
    public function poule_index_requires_auth(): void
    {
        $response = $this->get($this->toernooiUrl('poule'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Store (API)
    // ========================================================================

    #[Test]
    public function store_poule_creates_record(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->toernooiUrl('poule'), [
            'naam' => 'Test Poule',
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-30',
            'geslacht' => 'M',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('poules', [
            'toernooi_id' => $this->toernooi->id,
        ]);
    }

    // ========================================================================
    // Destroy (API)
    // ========================================================================

    #[Test]
    public function destroy_poule_removes_record(): void
    {
        $this->actAsOrg();
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        $response = $this->deleteJson($this->toernooiUrl("poule/{$poule->id}"));
        $response->assertStatus(200);
        $this->assertDatabaseMissing('poules', ['id' => $poule->id]);
    }

    // ========================================================================
    // Verifieer
    // ========================================================================

    #[Test]
    public function verifieer_returns_json(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->toernooiUrl('poule/verifieer'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Genereer
    // ========================================================================

    #[Test]
    public function genereer_poules_with_judokas(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create judokas in a consistent category
        Judoka::factory()->count(8)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-30',
            'geslacht' => 'M',
            'band' => 'wit',
            'aanwezigheid' => 'aanwezig',
        ]);

        $response = $this->post($this->toernooiUrl('poule/genereer'));
        $response->assertRedirect();
    }
}
