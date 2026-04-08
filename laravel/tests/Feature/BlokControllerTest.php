<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlokControllerTest extends TestCase
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
            'aantal_blokken' => 2,
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
    public function blok_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('blok'));
        $response->assertStatus(200);
    }

    #[Test]
    public function blok_index_requires_auth(): void
    {
        $response = $this->get($this->toernooiUrl('blok'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Show
    // ========================================================================

    #[Test]
    public function blok_show_loads(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $response = $this->get($this->toernooiUrl("blok/{$blok->id}"));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Zaaloverzicht
    // ========================================================================

    #[Test]
    public function zaaloverzicht_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('blok/zaaloverzicht'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Sluit weging
    // ========================================================================

    #[Test]
    public function sluit_weging_updates_blok(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => false,
        ]);

        $response = $this->post($this->toernooiUrl("blok/{$blok->id}/sluit-weging"));
        $response->assertRedirect();

        $blok->refresh();
        $this->assertTrue($blok->weging_gesloten);
    }

    // ========================================================================
    // Reset Alles
    // ========================================================================

    #[Test]
    public function reset_alles_redirects(): void
    {
        $this->actAsOrg();
        $response = $this->post($this->toernooiUrl('blok/reset-alles'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Einde Voorbereiding
    // ========================================================================

    #[Test]
    public function einde_voorbereiding_sets_timestamp(): void
    {
        $this->actAsOrg();
        $this->assertNull($this->toernooi->voorbereiding_klaar_op);

        $response = $this->post($this->toernooiUrl('blok/einde-voorbereiding'));
        $response->assertRedirect();

        $this->toernooi->refresh();
        $this->assertNotNull($this->toernooi->voorbereiding_klaar_op);
    }

    // ========================================================================
    // Heropen Voorbereiding
    // ========================================================================

    #[Test]
    public function heropen_voorbereiding_requires_password(): void
    {
        $this->actAsOrg();
        $this->toernooi->update(['voorbereiding_klaar_op' => now()]);

        // Without password should fail validation
        $response = $this->post("/{$this->org->slug}/toernooi/{$this->toernooi->slug}/heropen-voorbereiding", []);
        $response->assertSessionHasErrors('wachtwoord');
    }
}
