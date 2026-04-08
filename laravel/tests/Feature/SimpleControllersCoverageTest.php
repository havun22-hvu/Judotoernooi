<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuthDevice;
use App\Models\GewichtsklassenPreset;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiTemplate;
use App\Models\TvKoppeling;
use App\Models\Vrijwilliger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimpleControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: create organisator with toernooi and pivot link.
     */
    private function createOrgWithToernooi(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $toernooi->organisatoren()->attach($org->id, ['rol' => 'eigenaar']);

        return [$org, $toernooi];
    }

    // ========================================================================
    // LegalController
    // ========================================================================

    #[Test]
    public function legal_terms_page_loads(): void
    {
        $this->get('/algemene-voorwaarden')->assertStatus(200);
    }

    #[Test]
    public function legal_privacy_page_loads(): void
    {
        $this->get('/privacyverklaring')->assertStatus(200);
    }

    #[Test]
    public function legal_cookies_page_loads(): void
    {
        $this->get('/cookiebeleid')->assertStatus(200);
    }

    #[Test]
    public function legal_disclaimer_page_loads(): void
    {
        $this->get('/disclaimer')->assertStatus(200);
    }

    // ========================================================================
    // SitemapController
    // ========================================================================

    #[Test]
    public function sitemap_returns_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertSee('<urlset', false);
        $response->assertSee('/algemene-voorwaarden', false);
    }

    #[Test]
    public function sitemap_includes_active_tournaments(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();

        // Ensure tournament is in the future and not closed
        $toernooi->update(['datum' => now()->addWeek(), 'afgesloten_at' => null]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertSee($org->slug . '/' . $toernooi->slug);
    }

    #[Test]
    public function sitemap_excludes_closed_tournaments(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->afgesloten()->create([
            'organisator_id' => $org->id,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertDontSee($toernooi->slug);
    }

    // ========================================================================
    // ReverbController (local environment returns special response)
    // ========================================================================

    #[Test]
    public function reverb_status_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->getJson("/{$org->slug}/toernooi/{$toernooi->slug}/reverb/status");

        $response->assertStatus(200);
        $response->assertJsonStructure(['running']);
    }

    #[Test]
    public function reverb_start_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/toernooi/{$toernooi->slug}/reverb/start");

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message']);
    }

    #[Test]
    public function reverb_stop_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/toernooi/{$toernooi->slug}/reverb/stop");

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message']);
    }

    #[Test]
    public function reverb_restart_returns_json(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/toernooi/{$toernooi->slug}/reverb/restart");

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message']);
    }

    // ========================================================================
    // AccountController
    // ========================================================================

    #[Test]
    public function account_page_requires_authentication(): void
    {
        $response = $this->get('/auth/account');

        // auth:organisator returns 401 or redirect depending on config
        $this->assertTrue(in_array($response->getStatusCode(), [401, 302]));
    }

    #[Test]
    public function account_page_loads_for_authenticated_user(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get('/auth/account');

        $response->assertStatus(200);
    }

    #[Test]
    public function account_update_saves_profile_data(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->put('/auth/account', [
            'naam' => 'Nieuwe Naam',
            'email' => $org->email,
            'telefoon' => '0612345678',
            'locale' => 'nl',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organisators', [
            'id' => $org->id,
            'naam' => 'Nieuwe Naam',
        ]);
    }

    #[Test]
    public function account_update_validates_required_fields(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->put('/auth/account', []);

        $response->assertSessionHasErrors(['naam', 'email', 'locale']);
    }

    #[Test]
    public function account_password_update_works_with_correct_current_password(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->put('/auth/account/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('password_success');
    }

    #[Test]
    public function account_password_update_fails_with_wrong_current_password(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->put('/auth/account/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    #[Test]
    public function account_remove_device_deactivates_device(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $device = AuthDevice::create([
            'organisator_id' => $org->id,
            'token' => 'test-token-123',
            'device_name' => 'Test Device',
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        $response = $this->delete("/auth/account/device/{$device->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('auth_devices', [
            'id' => $device->id,
            'is_active' => false,
        ]);
    }

    #[Test]
    public function account_remove_device_fails_for_nonexistent_device(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->delete('/auth/account/device/99999');

        $response->assertSessionHasErrors('device');
    }

    // ========================================================================
    // ActivityLogController
    // ========================================================================

    #[Test]
    public function activity_log_page_loads_for_organisator(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/activiteiten");

        $response->assertStatus(200);
    }

    #[Test]
    public function activity_log_filters_by_actie(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'aangemaakt',
            'beschrijving' => 'Test log entry',
        ]);

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/activiteiten?actie=aangemaakt");

        $response->assertStatus(200);
    }

    #[Test]
    public function activity_log_filters_by_model_type(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'aangemaakt',
            'model_type' => 'Judoka',
            'beschrijving' => 'Judoka aangemaakt',
        ]);

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/activiteiten?model_type=Judoka");

        $response->assertStatus(200);
    }

    #[Test]
    public function activity_log_searches_in_description(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'aangemaakt',
            'beschrijving' => 'Specifieke zoekterm hier',
        ]);

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/activiteiten?zoek=Specifieke");

        $response->assertStatus(200);
    }

    // ========================================================================
    // GewichtsklassenPresetController
    // ========================================================================

    #[Test]
    public function preset_index_returns_presets_for_organisator(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        GewichtsklassenPreset::create([
            'organisator_id' => $org->id,
            'naam' => 'Test Preset',
            'configuratie' => ['cat1' => ['max_leeftijd' => 8, 'gewichten' => ['-25', '-30']]],
        ]);

        $response = $this->getJson("/{$org->slug}/presets");

        $response->assertStatus(200);
        $response->assertJsonFragment(['naam' => 'Test Preset']);
    }

    #[Test]
    public function preset_store_creates_new_preset(): void
    {
        $org = Organisator::factory()->premium()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/presets", [
            'naam' => 'Mijn Preset',
            'configuratie' => [
                'cat1' => ['max_leeftijd' => 10, 'gewichten' => ['-30', '-35']],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertDatabaseHas('gewichtsklassen_presets', [
            'organisator_id' => $org->id,
            'naam' => 'Mijn Preset',
        ]);
    }

    #[Test]
    public function preset_store_respects_free_tier_limit(): void
    {
        $org = Organisator::factory()->create(); // not premium
        $this->actingAs($org, 'organisator');

        // Create 2 presets (free tier limit)
        GewichtsklassenPreset::create([
            'organisator_id' => $org->id,
            'naam' => 'Preset 1',
            'configuratie' => ['cat' => ['max_leeftijd' => 8]],
        ]);
        GewichtsklassenPreset::create([
            'organisator_id' => $org->id,
            'naam' => 'Preset 2',
            'configuratie' => ['cat' => ['max_leeftijd' => 10]],
        ]);

        // Try to create a 3rd
        $response = $this->postJson("/{$org->slug}/presets", [
            'naam' => 'Preset 3',
            'configuratie' => ['cat' => ['max_leeftijd' => 12]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    #[Test]
    public function preset_store_updates_existing_by_name(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        GewichtsklassenPreset::create([
            'organisator_id' => $org->id,
            'naam' => 'Bestaand',
            'configuratie' => ['cat' => ['max_leeftijd' => 8]],
        ]);

        $response = $this->postJson("/{$org->slug}/presets", [
            'naam' => 'Bestaand',
            'configuratie' => ['cat' => ['max_leeftijd' => 12]],
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertEquals(1, GewichtsklassenPreset::where('organisator_id', $org->id)->count());
    }

    #[Test]
    public function preset_destroy_deletes_own_preset(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $preset = GewichtsklassenPreset::create([
            'organisator_id' => $org->id,
            'naam' => 'Te verwijderen',
            'configuratie' => ['cat' => ['max_leeftijd' => 8]],
        ]);

        $response = $this->deleteJson(route('organisator.presets.destroy', [
            'organisator' => $org->slug,
            'preset' => $preset->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertDatabaseMissing('gewichtsklassen_presets', ['id' => $preset->id]);
    }

    #[Test]
    public function preset_destroy_rejects_other_organisator_preset(): void
    {
        $org = Organisator::factory()->create();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $preset = GewichtsklassenPreset::create([
            'organisator_id' => $otherOrg->id,
            'naam' => 'Andermans preset',
            'configuratie' => ['cat' => ['max_leeftijd' => 8]],
        ]);

        $response = $this->deleteJson(route('organisator.presets.destroy', [
            'organisator' => $org->slug,
            'preset' => $preset->id,
        ]));

        $response->assertStatus(403);
    }

    // ========================================================================
    // VrijwilligerController
    // ========================================================================

    #[Test]
    public function vrijwilliger_index_returns_volunteers(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        Vrijwilliger::create([
            'organisator_id' => $org->id,
            'voornaam' => 'Jan',
            'functie' => 'mat',
        ]);

        $response = $this->getJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers");

        $response->assertStatus(200);
        $response->assertJsonFragment(['voornaam' => 'Jan']);
    }

    #[Test]
    public function vrijwilliger_store_creates_volunteer(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers", [
            'voornaam' => 'Piet',
            'telefoonnummer' => '06-12345678',
            'email' => 'piet@example.com',
            'functie' => 'weging',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['voornaam' => 'Piet']);
        $this->assertDatabaseHas('vrijwilligers', ['voornaam' => 'Piet']);
    }

    #[Test]
    public function vrijwilliger_store_validates_input(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['voornaam', 'functie']);
    }

    #[Test]
    public function vrijwilliger_update_modifies_volunteer(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $org->id,
            'voornaam' => 'Kees',
            'functie' => 'mat',
        ]);

        $response = $this->putJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers/{$vrijwilliger->id}", [
            'voornaam' => 'Kees Updated',
            'functie' => 'weging',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['voornaam' => 'Kees Updated']);
    }

    #[Test]
    public function vrijwilliger_update_rejects_other_organisator(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $otherOrg->id,
            'voornaam' => 'Andermans',
            'functie' => 'mat',
        ]);

        $response = $this->putJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers/{$vrijwilliger->id}", [
            'voornaam' => 'Gehackt',
            'functie' => 'mat',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function vrijwilliger_destroy_deletes_volunteer(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $org->id,
            'voornaam' => 'Te verwijderen',
            'functie' => 'dojo',
        ]);

        $response = $this->deleteJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers/{$vrijwilliger->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertDatabaseMissing('vrijwilligers', ['id' => $vrijwilliger->id]);
    }

    #[Test]
    public function vrijwilliger_destroy_rejects_other_organisator(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $otherOrg->id,
            'voornaam' => 'Andermans',
            'functie' => 'spreker',
        ]);

        $response = $this->deleteJson("/{$org->slug}/toernooi/{$toernooi->slug}/api/vrijwilligers/{$vrijwilliger->id}");

        $response->assertStatus(403);
    }

    // ========================================================================
    // TvController
    // ========================================================================

    #[Test]
    public function tv_koppel_page_loads(): void
    {
        $response = $this->get('/tv');

        $response->assertStatus(200);
    }

    #[Test]
    public function tv_link_requires_authentication(): void
    {
        $response = $this->postJson('/tv/link', [
            'code' => 'ABCD',
            'toernooi_id' => 1,
            'mat_nummer' => 1,
        ]);

        // auth middleware (web guard) returns 401 for JSON requests
        $response->assertStatus(401);
    }

    #[Test]
    public function tv_link_validates_input(): void
    {
        $org = Organisator::factory()->create();
        // tv/link uses default 'auth' middleware (web guard)
        $this->actingAs($org);

        $response = $this->postJson('/tv/link', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'toernooi_id', 'mat_nummer']);
    }

    #[Test]
    public function tv_link_rejects_invalid_code(): void
    {
        $org = Organisator::factory()->create(['is_sitebeheerder' => true]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        // tv/link uses default 'auth' middleware (web guard)
        $this->actingAs($org);

        $response = $this->postJson('/tv/link', [
            'code' => 'ZZZZ',
            'toernooi_id' => $toernooi->id,
            'mat_nummer' => 1,
        ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // ToernooiTemplateController
    // ========================================================================

    #[Test]
    public function template_index_returns_templates(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        ToernooiTemplate::create([
            'organisator_id' => $org->id,
            'naam' => 'Mijn Template',
            'instellingen' => ['max_per_poule' => 4],
        ]);

        $response = $this->getJson("/{$org->slug}/templates");

        $response->assertStatus(200);
        $response->assertJsonFragment(['naam' => 'Mijn Template']);
    }

    #[Test]
    public function template_show_returns_own_template(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $template = ToernooiTemplate::create([
            'organisator_id' => $org->id,
            'naam' => 'Show Template',
            'instellingen' => ['max_per_poule' => 4],
        ]);

        $response = $this->getJson(route('organisator.templates.show', [
            'organisator' => $org->slug,
            'template' => $template->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['naam' => 'Show Template']);
    }

    #[Test]
    public function template_show_rejects_other_organisator(): void
    {
        $org = Organisator::factory()->create();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $template = ToernooiTemplate::create([
            'organisator_id' => $otherOrg->id,
            'naam' => 'Andermans Template',
            'instellingen' => ['max_per_poule' => 4],
        ]);

        $response = $this->getJson(route('organisator.templates.show', [
            'organisator' => $org->slug,
            'template' => $template->id,
        ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function template_store_creates_from_toernooi(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        $response = $this->postJson(route('toernooi.template.store', [
            'organisator' => $org->slug,
            'toernooi' => $toernooi->slug,
        ]), [
            'naam' => 'Nieuw Template',
            'beschrijving' => 'Test beschrijving',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertDatabaseHas('toernooi_templates', [
            'organisator_id' => $org->id,
            'naam' => 'Nieuw Template',
        ]);
    }

    #[Test]
    public function template_store_rejects_duplicate_name(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $this->actingAs($org, 'organisator');

        ToernooiTemplate::create([
            'organisator_id' => $org->id,
            'naam' => 'Duplicate',
            'instellingen' => [],
        ]);

        $response = $this->postJson(route('toernooi.template.store', [
            'organisator' => $org->slug,
            'toernooi' => $toernooi->slug,
        ]), [
            'naam' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function template_update_requires_authentication(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $template = ToernooiTemplate::create([
            'organisator_id' => $org->id,
            'naam' => 'Update Me',
            'instellingen' => ['max_per_poule' => 3],
        ]);

        $response = $this->putJson(route('toernooi.template.update', [
            'organisator' => $org->slug,
            'toernooi' => $toernooi->slug,
            'template' => $template->id,
        ]), [
            'naam' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function template_update_rejects_other_organisator(): void
    {
        [$org, $toernooi] = $this->createOrgWithToernooi();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $template = ToernooiTemplate::create([
            'organisator_id' => $otherOrg->id,
            'naam' => 'Not Yours',
            'instellingen' => [],
        ]);

        $response = $this->putJson(route('toernooi.template.update', [
            'organisator' => $org->slug,
            'toernooi' => $toernooi->slug,
            'template' => $template->id,
        ]), [
            'naam' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function template_destroy_deletes_own_template(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $template = ToernooiTemplate::create([
            'organisator_id' => $org->id,
            'naam' => 'Te verwijderen',
            'instellingen' => [],
        ]);

        $response = $this->deleteJson(route('organisator.templates.destroy', [
            'organisator' => $org->slug,
            'template' => $template->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertDatabaseMissing('toernooi_templates', ['id' => $template->id]);
    }

    #[Test]
    public function template_destroy_rejects_other_organisator(): void
    {
        $org = Organisator::factory()->create();
        $otherOrg = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $template = ToernooiTemplate::create([
            'organisator_id' => $otherOrg->id,
            'naam' => 'Andermans',
            'instellingen' => [],
        ]);

        $response = $this->deleteJson(route('organisator.templates.destroy', [
            'organisator' => $org->slug,
            'template' => $template->id,
        ]));

        $response->assertStatus(403);
    }
}
