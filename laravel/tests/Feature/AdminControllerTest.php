<?php

namespace Tests\Feature;

use App\Models\AutofixProposal;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Organisator
    {
        return Organisator::factory()->sitebeheerder()->create();
    }

    private function createKlant(): Organisator
    {
        return Organisator::factory()->create();
    }

    // ========================================================================
    // Klanten
    // ========================================================================

    #[Test]
    public function klanten_requires_sitebeheerder(): void
    {
        $org = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('admin.klanten'));
        $response->assertStatus(403);
    }

    #[Test]
    public function klanten_loads_for_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('admin.klanten'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Edit Klant
    // ========================================================================

    #[Test]
    public function edit_klant_loads_for_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('admin.klanten.edit', $klant));
        $response->assertStatus(200);
    }

    #[Test]
    public function edit_klant_forbidden_for_regular_user(): void
    {
        $org = $this->createKlant();
        $klant = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('admin.klanten.edit', $klant));
        $response->assertStatus(403);
    }

    // ========================================================================
    // Update Klant
    // ========================================================================

    #[Test]
    public function update_klant_updates_data(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->put(route('admin.klanten.update', $klant), [
            'naam' => 'Nieuwe Naam',
            'email' => 'nieuw@example.com',
            'telefoon' => '0612345678',
        ]);

        $response->assertRedirect(route('admin.klanten'));
        $this->assertDatabaseHas('organisators', [
            'id' => $klant->id,
            'naam' => 'Nieuwe Naam',
            'email' => 'nieuw@example.com',
        ]);
    }

    #[Test]
    public function update_klant_activates_wimpel_abo_with_defaults(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->put(route('admin.klanten.update', $klant), [
            'naam' => $klant->naam,
            'email' => $klant->email,
            'wimpel_abo_actief' => true,
        ]);

        $response->assertRedirect(route('admin.klanten'));
        $klant->refresh();
        $this->assertTrue((bool) $klant->wimpel_abo_actief);
        $this->assertNotNull($klant->wimpel_abo_start);
        $this->assertNotNull($klant->wimpel_abo_einde);
    }

    // ========================================================================
    // Facturen
    // ========================================================================

    #[Test]
    public function facturen_loads_for_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('admin.facturen'));
        $response->assertStatus(200);
    }

    #[Test]
    public function facturen_forbidden_for_regular_user(): void
    {
        $org = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('admin.facturen'));
        $response->assertStatus(403);
    }

    // ========================================================================
    // AutoFix
    // ========================================================================

    #[Test]
    public function autofix_loads_for_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('admin.autofix'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Impersonate
    // ========================================================================

    #[Test]
    public function impersonate_logs_in_as_klant(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->post(route('admin.impersonate', $klant));
        $response->assertRedirect(route('organisator.dashboard', ['organisator' => $klant->slug]));
        $this->assertAuthenticatedAs($klant, 'organisator');
    }

    #[Test]
    public function cannot_impersonate_self(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $response = $this->post(route('admin.impersonate', $admin));
        $response->assertRedirect(route('admin.klanten'));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function impersonate_stop_returns_to_admin(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        // Start impersonation
        $this->post(route('admin.impersonate', $klant));

        // Stop impersonation
        $response = $this->post(route('admin.impersonate.stop'));
        $response->assertRedirect(route('admin.klanten'));
    }

    #[Test]
    public function impersonate_stop_without_session_redirects_to_klanten(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $response = $this->post(route('admin.impersonate.stop'));
        $response->assertRedirect(route('admin.klanten'));
    }

    // ========================================================================
    // Destroy Klant
    // ========================================================================

    #[Test]
    public function destroy_klant_deletes_data(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $this->actingAs($admin, 'organisator');

        $response = $this->delete(route('admin.klanten.destroy', $klant));
        $response->assertRedirect(route('admin.klanten'));
        $this->assertDatabaseMissing('organisators', ['id' => $klant->id]);
    }

    #[Test]
    public function cannot_destroy_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $admin2 = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $response = $this->delete(route('admin.klanten.destroy', $admin2));
        $response->assertRedirect(route('admin.klanten'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('organisators', ['id' => $admin2->id]);
    }

    #[Test]
    public function destroy_klant_with_toernooien_cascades(): void
    {
        $admin = $this->createAdmin();
        $klant = $this->createKlant();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $klant->id]);
        // Link via pivot so destroyKlant can find and delete the toernooi
        $klant->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
        $this->actingAs($admin, 'organisator');

        $response = $this->delete(route('admin.klanten.destroy', $klant));
        $response->assertRedirect(route('admin.klanten'));
        $this->assertDatabaseMissing('organisators', ['id' => $klant->id]);
        $this->assertDatabaseMissing('toernooien', ['id' => $toernooi->id]);
    }
}
