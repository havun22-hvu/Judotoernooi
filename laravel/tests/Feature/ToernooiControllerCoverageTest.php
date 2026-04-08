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
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function createOrgWithToernooi(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        return [$org, $toernooi];
    }

    // ========================================================================
    // index (admin overview) — sitebeheerder only
    // ========================================================================

    #[Test]
    public function index_returns_403_for_non_sitebeheerder(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('admin.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function index_loads_for_sitebeheerder(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('admin.index'));

        $response->assertStatus(200);
    }

    // ========================================================================
    // store — create a new toernooi
    // ========================================================================

    #[Test]
    public function store_creates_toernooi_and_redirects(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi", [
            'naam' => 'Test Toernooi 2026',
            'datum' => '2026-06-15',
            'locatie' => 'Sporthal Test',
            'aantal_matten' => 3,
            'aantal_blokken' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('toernooien', ['naam' => 'Test Toernooi 2026']);
    }

    #[Test]
    public function store_validation_fails_without_required_fields(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi", []);

        $response->assertSessionHasErrors(['naam', 'datum']);
    }

    // ========================================================================
    // show — mobile redirect for smartphones
    // ========================================================================

    #[Test]
    public function show_redirects_smartphones_to_mobile_view(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        ])->get("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertRedirect();
    }

    #[Test]
    public function show_does_not_redirect_desktop_browsers(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->get("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function show_does_not_redirect_when_desktop_param_present(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) Mobile',
        ])->get("/{$org->slug}/toernooi/{$toernooi->slug}?desktop=1");

        $response->assertStatus(200);
    }

    #[Test]
    public function show_does_not_redirect_tablets(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Mobile',
        ])->get("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertStatus(200);
    }

    // ========================================================================
    // toggleArchiveer
    // ========================================================================

    #[Test]
    public function toggle_archiveer_archives_toernooi_for_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/archiveer");

        $response->assertRedirect();
        $this->assertTrue($toernooi->fresh()->is_gearchiveerd);
    }

    #[Test]
    public function toggle_archiveer_unarchives_archived_toernooi(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['is_gearchiveerd' => true]);
        $this->actingAs($org, 'organisator');

        $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/archiveer");

        $this->assertFalse($toernooi->fresh()->is_gearchiveerd);
    }

    #[Test]
    public function toggle_archiveer_forbidden_for_non_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/archiveer");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function toggle_archiveer_allowed_for_sitebeheerder(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $admin = Organisator::factory()->sitebeheerder()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/archiveer");

        $response->assertRedirect();
        $this->assertTrue($toernooi->fresh()->is_gearchiveerd);
    }

    // ========================================================================
    // destroy
    // ========================================================================

    #[Test]
    public function destroy_deletes_toernooi_for_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->delete("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('toernooien', ['id' => $toernooi->id]);
    }

    #[Test]
    public function destroy_forbidden_for_non_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->delete("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('toernooien', ['id' => $toernooi->id]);
    }

    #[Test]
    public function destroy_with_bewaar_presets_keeps_presets(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->delete("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'bewaar_presets' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('presets bewaard', session('success'));
    }

    #[Test]
    public function destroy_deletes_related_data(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $this->actingAs($org, 'organisator');

        $this->delete("/{$org->slug}/toernooi/{$toernooi->slug}");

        $this->assertDatabaseMissing('blokken', ['id' => $blok->id]);
        $this->assertDatabaseMissing('judokas', ['id' => $judoka->id]);
    }

    #[Test]
    public function destroy_redirects_sitebeheerder_to_admin_index(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $admin = Organisator::factory()->sitebeheerder()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->delete("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertRedirect(route('admin.index'));
    }

    // ========================================================================
    // reset
    // ========================================================================

    #[Test]
    public function reset_removes_judokas_and_poules(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'weging_gesloten' => true]);
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/reset");

        $response->assertRedirect();
        $this->assertEquals(0, $toernooi->judokas()->count());
    }

    // ========================================================================
    // organisatorDashboard
    // ========================================================================

    #[Test]
    public function organisator_dashboard_shows_toernooien(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/dashboard");

        $response->assertStatus(200);
        $response->assertViewHas('toernooien');
    }

    #[Test]
    public function organisator_dashboard_shows_archived_separately(): void
    {
        $org = Organisator::factory()->create();
        $active = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $archived = Toernooi::factory()->create(['organisator_id' => $org->id, 'is_gearchiveerd' => true]);
        $org->toernooien()->attach($active->id, ['rol' => 'eigenaar']);
        $org->toernooien()->attach($archived->id, ['rol' => 'eigenaar']);
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/dashboard");

        $response->assertStatus(200);
        $response->assertViewHas('gearchiveerd');
    }

    // ========================================================================
    // redirectToOrganisatorDashboard
    // ========================================================================

    #[Test]
    public function redirect_to_organisator_dashboard_works(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get('/dashboard');

        $response->assertRedirect("/{$org->slug}/dashboard");
    }

    // ========================================================================
    // updateWachtwoorden
    // ========================================================================

    #[Test]
    public function update_wachtwoorden_updates_role_passwords(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/wachtwoorden", [
            'wachtwoord_admin' => 'nieuw123',
            'wachtwoord_jury' => 'jury456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function update_wachtwoorden_with_no_changes_shows_info(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/wachtwoorden", []);

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    // ========================================================================
    // updateBloktijden
    // ========================================================================

    #[Test]
    public function update_bloktijden_updates_block_times(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/bloktijden", [
            'blokken' => [
                $blok->id => [
                    'weging_start' => '08:00',
                    'weging_einde' => '09:00',
                    'starttijd' => '09:30',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('08:00', (string) $blok->fresh()->weging_start);
    }

    #[Test]
    public function update_bloktijden_handles_empty_times(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'weging_start' => '08:00']);
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/bloktijden", [
            'blokken' => [
                $blok->id => [
                    'weging_start' => '',
                    'weging_einde' => '',
                    'starttijd' => '',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertNull($blok->fresh()->weging_start);
    }

    // ========================================================================
    // updateBetalingInstellingen
    // ========================================================================

    #[Test]
    public function update_betaling_instellingen_saves_payment_settings(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/betalingen", [
            'betaling_actief' => true,
            'inschrijfgeld' => 7.50,
            'payment_provider' => 'stripe',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $toernooi->fresh();
        $this->assertTrue((bool) $fresh->betaling_actief);
        $this->assertEquals(7.50, $fresh->inschrijfgeld);
        $this->assertEquals('stripe', $fresh->payment_provider);
    }

    #[Test]
    public function update_betaling_instellingen_validates_input(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/betalingen", [
            'inschrijfgeld' => 9999,
            'payment_provider' => 'invalid',
        ]);

        $response->assertSessionHasErrors();
    }

    // ========================================================================
    // updatePortaalInstellingen
    // ========================================================================

    #[Test]
    public function update_portaal_instellingen_saves_settings(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/portaal", [
            'portaal_modus' => 'volledig',
            'weegkaarten_publiek' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('volledig', $toernooi->fresh()->portaal_modus);
    }

    #[Test]
    public function update_portaal_instellingen_validates_modus(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/portaal", [
            'portaal_modus' => 'invalid',
        ]);

        $response->assertSessionHasErrors('portaal_modus');
    }

    // ========================================================================
    // updateLocalServerIps
    // ========================================================================

    #[Test]
    public function update_local_server_ips_saves_network_settings(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/local-server-ips", [
            'local_server_primary_ip' => '192.168.1.100',
            'heeft_eigen_router' => true,
            'eigen_router_ssid' => 'JudoNet',
        ]);

        $response->assertRedirect();
        $fresh = $toernooi->fresh();
        $this->assertEquals('192.168.1.100', $fresh->local_server_primary_ip);
        $this->assertTrue((bool) $fresh->heeft_eigen_router);
    }

    #[Test]
    public function update_local_server_ips_validates_ip_format(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}/local-server-ips", [
            'local_server_primary_ip' => 'not-an-ip',
        ]);

        $response->assertSessionHasErrors('local_server_primary_ip');
    }

    // ========================================================================
    // detectMyIp
    // ========================================================================

    #[Test]
    public function detect_my_ip_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->getJson("/{$org->slug}/toernooi/{$toernooi->slug}/detect-my-ip");

        $response->assertOk();
        $response->assertJsonStructure(['ip', 'hostname', 'saved_as']);
    }

    #[Test]
    public function detect_my_ip_can_save_as_primary(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->getJson("/{$org->slug}/toernooi/{$toernooi->slug}/detect-my-ip?save=primary");

        $response->assertOk();
        $response->assertJson(['saved_as' => 'primary']);
    }

    // ========================================================================
    // heropenVoorbereiding
    // ========================================================================

    #[Test]
    public function heropen_voorbereiding_with_correct_password(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['weegkaarten_gemaakt_op' => now()]);
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/heropen-voorbereiding", [
            'wachtwoord' => 'password', // factory default password
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull($toernooi->fresh()->weegkaarten_gemaakt_op);
    }

    #[Test]
    public function heropen_voorbereiding_with_wrong_password(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['weegkaarten_gemaakt_op' => now()]);
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/heropen-voorbereiding", [
            'wachtwoord' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNotNull($toernooi->fresh()->weegkaarten_gemaakt_op);
    }

    #[Test]
    public function heropen_voorbereiding_requires_password(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/heropen-voorbereiding", []);

        $response->assertSessionHasErrors('wachtwoord');
    }

    // ========================================================================
    // afsluiten (show page)
    // ========================================================================

    #[Test]
    public function afsluiten_page_loads_with_statistics(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/afsluiten");

        $response->assertStatus(200);
        $response->assertViewHas('statistieken');
        $response->assertViewHas('clubRanking');
    }

    // ========================================================================
    // bevestigAfsluiten
    // ========================================================================

    #[Test]
    public function bevestig_afsluiten_closes_tournament_for_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/afsluiten");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNotNull($toernooi->fresh()->afgesloten_at);
    }

    #[Test]
    public function bevestig_afsluiten_forbidden_for_non_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/afsluiten");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($toernooi->fresh()->afgesloten_at);
    }

    #[Test]
    public function bevestig_afsluiten_fails_for_already_closed_tournament(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['afgesloten_at' => now()]);
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/afsluiten");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function bevestig_afsluiten_allowed_for_sitebeheerder(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $admin = Organisator::factory()->sitebeheerder()->create();
        // Sitebeheerder needs to be in the toernooien relation for the contains check
        $admin->toernooien()->attach($toernooi->id, ['rol' => 'beheerder']);
        $this->actingAs($admin, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/afsluiten");

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    // ========================================================================
    // heropenen
    // ========================================================================

    #[Test]
    public function heropenen_reopens_closed_tournament(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['afgesloten_at' => now()]);
        $this->actingAs($org, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/heropenen");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull($toernooi->fresh()->afgesloten_at);
    }

    #[Test]
    public function heropenen_forbidden_for_non_owner(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $toernooi->update(['afgesloten_at' => now()]);
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->post("/{$org->slug}/toernooi/{$toernooi->slug}/heropenen");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNotNull($toernooi->fresh()->afgesloten_at);
    }

    // ========================================================================
    // update — AJAX and form submission
    // ========================================================================

    #[Test]
    public function update_toernooi_via_form_submission(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => 'Bijgewerkt Toernooi',
            'datum' => '2026-08-01',
            'aantal_matten' => 4,
            'aantal_blokken' => 3,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Bijgewerkt Toernooi', $toernooi->fresh()->naam);
    }

    #[Test]
    public function update_toernooi_via_ajax_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->putJson("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => 'AJAX Update',
            'datum' => '2026-08-01',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function update_processes_gewichtsklassen_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $gewichtsklassenJson = json_encode([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 6,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'gewichten' => ['-20', '-24'],
                'wedstrijd_systeem' => 'poules',
            ],
        ]);

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'gewichtsklassen_json' => $gewichtsklassenJson,
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function update_detects_missing_commas_in_gewichtsklassen(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $gewichtsklassenJson = json_encode([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 6,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'gewichten' => ['-20 -23'], // Missing comma
            ],
        ]);

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'gewichtsklassen_json' => $gewichtsklassenJson,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('gewichtsklassen');
    }

    #[Test]
    public function update_missing_commas_returns_json_for_ajax(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $gewichtsklassenJson = json_encode([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 6,
                'geslacht' => 'gemengd',
                'gewichten' => ['-20 -23'],
            ],
        ]);

        $response = $this->putJson("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'gewichtsklassen_json' => $gewichtsklassenJson,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['gewichtsklassen']]);
    }

    #[Test]
    public function update_handles_checkbox_fields(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'coach_incheck_actief' => true,
            'danpunten_actief' => true,
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => true,
            'dubbel_bij_3_judokas' => true,
            'dubbel_bij_4_judokas' => true,
        ]);

        $response->assertRedirect();
        $fresh = $toernooi->fresh();
        $this->assertTrue((bool) $fresh->coach_incheck_actief);
        $this->assertTrue((bool) $fresh->danpunten_actief);
    }

    #[Test]
    public function update_handles_mat_voorkeuren(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->put("/{$org->slug}/toernooi/{$toernooi->slug}", [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'mat_voorkeuren' => ['blauw_rechts' => true],
        ]);

        $response->assertRedirect();
    }
}
