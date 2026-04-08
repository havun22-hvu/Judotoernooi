<?php

namespace Tests\Feature;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiControllerExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
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
    // Store
    // ========================================================================

    #[Test]
    public function store_toernooi_creates_record(): void
    {
        $this->actAsOrg();
        $response = $this->post("/{$this->org->slug}/toernooi", [
            'naam' => 'Nieuw Toernooi',
            'datum' => '2026-06-15',
            'locatie' => 'Sporthal Test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('toernooien', ['naam' => 'Nieuw Toernooi']);
    }

    #[Test]
    public function store_toernooi_requires_naam(): void
    {
        $this->actAsOrg();
        $response = $this->post("/{$this->org->slug}/toernooi", [
            'datum' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors('naam');
    }

    // ========================================================================
    // Update
    // ========================================================================

    #[Test]
    public function update_toernooi_changes_data(): void
    {
        $this->actAsOrg();
        $response = $this->put($this->toernooiUrl(), [
            'naam' => 'Updated Toernooi',
            'datum' => '2026-07-01',
        ]);

        $response->assertRedirect();
        $this->toernooi->refresh();
        $this->assertEquals('Updated Toernooi', $this->toernooi->naam);
    }

    // ========================================================================
    // Toggle Archiveer
    // ========================================================================

    #[Test]
    public function toggle_archiveer_toernooi(): void
    {
        $this->actAsOrg();
        $this->toernooi->update(['is_gearchiveerd' => false]);
        $this->assertFalse((bool) $this->toernooi->is_gearchiveerd);

        $response = $this->post($this->toernooiUrl('archiveer'));
        $response->assertRedirect();

        $this->toernooi->refresh();
        $this->assertTrue((bool) $this->toernooi->is_gearchiveerd);
    }

    // ========================================================================
    // Destroy
    // ========================================================================

    #[Test]
    public function destroy_toernooi_deletes_record(): void
    {
        $this->actAsOrg();
        $id = $this->toernooi->id;
        $response = $this->delete($this->toernooiUrl(), [
            'bevestig_naam' => $this->toernooi->naam,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('toernooien', ['id' => $id]);
    }

    // ========================================================================
    // Update Wachtwoorden
    // ========================================================================

    #[Test]
    public function update_wachtwoorden(): void
    {
        $this->actAsOrg();
        $response = $this->put($this->toernooiUrl('wachtwoorden'), [
            'wachtwoord_admin' => 'admin123',
            'wachtwoord_jury' => 'jury123',
        ]);

        $response->assertRedirect();
        $this->toernooi->refresh();
        $this->assertTrue($this->toernooi->checkWachtwoord('admin', 'admin123'));
    }

    // ========================================================================
    // Auth checks
    // ========================================================================

    #[Test]
    public function toernooi_show_requires_auth(): void
    {
        $response = $this->get($this->toernooiUrl());
        $response->assertRedirect();
    }

    // ========================================================================
    // Create page
    // ========================================================================

    #[Test]
    public function create_toernooi_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get("/{$this->org->slug}/toernooi/nieuw");
        $response->assertStatus(200);
    }

    // ========================================================================
    // Edit page
    // ========================================================================

    #[Test]
    public function edit_toernooi_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->toernooiUrl('edit'));
        $response->assertStatus(200);
    }
}
