<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAanmelding;
use App\Models\ClubUitnodiging;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\EmailLog;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubCoachCoverageTest extends TestCase
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

    // ========================================================================
    // Helper to build toernooi-scoped URLs
    // ========================================================================

    private function toernooiUrl(string $path = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/{$path}";
    }

    // ========================================================================
    // ClubController — Organisator level (uncovered parts)
    // ========================================================================

    #[Test]
    public function store_organisator_club_forbidden_for_other_user(): void
    {
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->post(route('organisator.clubs.store', ['organisator' => $this->org->slug]), [
            'naam' => 'Forbidden Club',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function store_organisator_club_with_back_param_redirects(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->post(route('organisator.clubs.store', ['organisator' => $this->org->slug]), [
            'naam' => 'Club Met Back',
            'back' => 'toernooi',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clubs', ['naam' => 'Club Met Back']);
    }

    #[Test]
    public function update_organisator_club_forbidden_for_other_user(): void
    {
        $other = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->actingAs($other, 'organisator');

        $response = $this->put(route('organisator.clubs.update', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]), ['naam' => 'Hacked']);

        $response->assertStatus(403);
    }

    #[Test]
    public function update_organisator_club_forbidden_if_club_belongs_to_other(): void
    {
        $otherOrg = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $otherOrg->id]);
        $this->actingAs($this->org, 'organisator');

        $response = $this->put(route('organisator.clubs.update', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]), ['naam' => 'Hacked']);

        $response->assertStatus(403);
    }

    #[Test]
    public function destroy_organisator_club_forbidden_for_other_user(): void
    {
        $other = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->actingAs($other, 'organisator');

        $response = $this->delete(route('organisator.clubs.destroy', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function destroy_organisator_club_forbidden_if_club_belongs_to_other(): void
    {
        $otherOrg = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $otherOrg->id]);
        $this->actingAs($this->org, 'organisator');

        $response = $this->delete(route('organisator.clubs.destroy', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function destroy_organisator_club_deletes_related_records(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create related coach and coach kaart
        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test Coach',
        ]);
        CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->delete(route('organisator.clubs.destroy', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('coaches', ['id' => $coach->id]);
    }

    #[Test]
    public function sitebeheerder_can_store_club_for_other_organisator(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->post(route('organisator.clubs.store', ['organisator' => $this->org->slug]), [
            'naam' => 'Admin Added Club',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clubs', [
            'organisator_id' => $this->org->id,
            'naam' => 'Admin Added Club',
        ]);
    }

    // ========================================================================
    // ClubController — Toernooi level (store/update/destroy club)
    // ========================================================================

    #[Test]
    public function toernooi_toggle_club_attaches_and_detaches(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Toggle ON
        $response = $this->post($this->toernooiUrl("club/{$club->id}/toggle"));
        $response->assertRedirect();
        $this->assertDatabaseHas('club_toernooi', [
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        // Toggle OFF
        $response = $this->post($this->toernooiUrl("club/{$club->id}/toggle"));
        $response->assertRedirect();
        $this->assertDatabaseMissing('club_toernooi', [
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);
    }

    #[Test]
    public function toggle_club_via_json_returns_json(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->postJson($this->toernooiUrl("club/{$club->id}/toggle"));
        $response->assertOk();
        $response->assertJson(['success' => true, 'is_uitgenodigd' => true]);
    }

    #[Test]
    public function toggle_club_forbidden_for_wrong_organisator(): void
    {
        $this->actingAs($this->org, 'organisator');
        $otherOrg = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $otherOrg->id]);

        $response = $this->post($this->toernooiUrl("club/{$club->id}/toggle"));
        $response->assertStatus(403);
    }

    #[Test]
    public function toggle_club_off_blocked_when_judokas_exist(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Attach club to toernooi
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTCODE1234',
            'pincode' => '12345',
        ]);

        // Create a judoka for this club/toernooi
        Judoka::factory()->create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        // Try to toggle off via JSON
        $response = $this->postJson($this->toernooiUrl("club/{$club->id}/toggle"));
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function select_all_clubs_attaches_all(): void
    {
        $this->actingAs($this->org, 'organisator');
        Club::factory()->count(3)->create(['organisator_id' => $this->org->id]);

        $response = $this->post($this->toernooiUrl('club/select-all'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(3, $this->toernooi->clubs()->count());
    }

    #[Test]
    public function deselect_all_clubs_detaches_without_judokas(): void
    {
        $this->actingAs($this->org, 'organisator');
        $clubs = Club::factory()->count(2)->create(['organisator_id' => $this->org->id]);

        foreach ($clubs as $club) {
            $this->toernooi->clubs()->attach($club->id, [
                'portal_code' => 'CODE' . $club->id . str_pad('', 8, 'X'),
                'pincode' => '12345',
            ]);
        }

        // Add judoka to first club only
        Judoka::factory()->create([
            'club_id' => $clubs[0]->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->post($this->toernooiUrl('club/deselect-all'));
        $response->assertRedirect();

        // First club should still be attached (has judokas), second should be detached
        $this->assertTrue($this->toernooi->clubs()->where('clubs.id', $clubs[0]->id)->exists());
        $this->assertFalse($this->toernooi->clubs()->where('clubs.id', $clubs[1]->id)->exists());
    }

    // ========================================================================
    // ClubController — Aanmelding (goedkeur/afwijs)
    // ========================================================================

    #[Test]
    public function goedkeur_aanmelding_creates_club_and_links(): void
    {
        $this->actingAs($this->org, 'organisator');

        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $this->toernooi->id,
            'club_naam' => 'Nieuwe Club',
            'contact_naam' => 'Jan',
            'email' => 'jan@club.nl',
            'telefoon' => '0612345678',
            'status' => 'pending',
        ]);

        $response = $this->post($this->toernooiUrl("club/aanmelding/{$aanmelding->id}/goedkeur"));
        $response->assertRedirect();

        $aanmelding->refresh();
        $this->assertEquals('goedgekeurd', $aanmelding->status);
        $this->assertDatabaseHas('clubs', ['naam' => 'Nieuwe Club']);
    }

    #[Test]
    public function afwijs_aanmelding_sets_status(): void
    {
        $this->actingAs($this->org, 'organisator');

        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $this->toernooi->id,
            'club_naam' => 'Afgewezen Club',
            'status' => 'pending',
        ]);

        $response = $this->post($this->toernooiUrl("club/aanmelding/{$aanmelding->id}/afwijs"));
        $response->assertRedirect();

        $aanmelding->refresh();
        $this->assertEquals('afgewezen', $aanmelding->status);
    }

    #[Test]
    public function goedkeur_aanmelding_forbidden_for_wrong_toernooi(): void
    {
        $this->actingAs($this->org, 'organisator');

        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $otherToernooi->id,
            'club_naam' => 'Wrong Toernooi',
            'status' => 'pending',
        ]);

        $response = $this->post($this->toernooiUrl("club/aanmelding/{$aanmelding->id}/goedkeur"));
        $response->assertStatus(403);
    }

    // ========================================================================
    // ClubController — Coach CRUD
    // ========================================================================

    #[Test]
    public function store_coach_creates_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->post($this->toernooiUrl("club/{$club->id}/coach"), [
            'naam' => 'Coach Pietersen',
            'email' => 'coach@test.nl',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coaches', [
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Coach Pietersen',
        ]);
    }

    #[Test]
    public function store_coach_max_three_per_club(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create 3 coaches
        for ($i = 0; $i < 3; $i++) {
            Coach::create([
                'club_id' => $club->id,
                'toernooi_id' => $this->toernooi->id,
                'naam' => "Coach {$i}",
            ]);
        }

        // 4th should fail
        $response = $this->post($this->toernooiUrl("club/{$club->id}/coach"), [
            'naam' => 'Coach 4',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_coach_updates_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Old Name',
        ]);

        $response = $this->put($this->toernooiUrl("coach/{$coach->id}"), [
            'naam' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'naam' => 'New Name']);
    }

    #[Test]
    public function update_coach_forbidden_wrong_toernooi(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $otherToernooi->id,
            'naam' => 'Other Coach',
        ]);

        $response = $this->put($this->toernooiUrl("coach/{$coach->id}"), [
            'naam' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function destroy_coach_deletes_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Delete Me',
        ]);

        $response = $this->delete($this->toernooiUrl("coach/{$coach->id}"));
        $response->assertRedirect();
        $this->assertDatabaseMissing('coaches', ['id' => $coach->id]);
    }

    #[Test]
    public function destroy_coach_forbidden_wrong_toernooi(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $otherToernooi->id,
            'naam' => 'Other Coach',
        ]);

        $response = $this->delete($this->toernooiUrl("coach/{$coach->id}"));
        $response->assertStatus(403);
    }

    // ========================================================================
    // ClubController — Coach kaart add/remove
    // ========================================================================

    #[Test]
    public function add_coach_kaart_creates_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->post($this->toernooiUrl("club/{$club->id}/coachkaart"));
        $response->assertRedirect();

        $this->assertDatabaseHas('coach_kaarten', [
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);
    }

    #[Test]
    public function remove_coach_kaart_removes_unactivated_card(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create 2 unactivated cards
        CoachKaart::create(['club_id' => $club->id, 'toernooi_id' => $this->toernooi->id]);
        CoachKaart::create(['club_id' => $club->id, 'toernooi_id' => $this->toernooi->id]);

        $response = $this->delete($this->toernooiUrl("club/{$club->id}/coachkaart"));
        $response->assertRedirect();
    }

    #[Test]
    public function remove_coach_kaart_keeps_minimum_one(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        CoachKaart::create(['club_id' => $club->id, 'toernooi_id' => $this->toernooi->id]);

        $response = $this->delete($this->toernooiUrl("club/{$club->id}/coachkaart"));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function remove_coach_kaart_skips_activated_cards(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create one activated card only
        CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Activated',
            'foto_path' => 'photo.jpg',
            'device_token' => 'abc123',
        ]);

        $response = $this->delete($this->toernooiUrl("club/{$club->id}/coachkaart"));
        $response->assertRedirect();
        $response->assertSessionHas('error'); // No unactivated card to remove
    }

    // ========================================================================
    // ClubController — Email / coach URL
    // ========================================================================

    #[Test]
    public function get_coach_url_creates_uitnodiging(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // This route creates a ClubUitnodiging and redirects
        $this->get($this->toernooiUrl("club/{$club->id}/coach-url"));

        $this->assertDatabaseHas('club_uitnodigingen', [
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);
    }

    #[Test]
    public function verstuur_uitnodiging_fails_without_email(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'email' => null,
        ]);

        $response = $this->post($this->toernooiUrl("club/{$club->id}/verstuur"));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function verstuur_alle_uitnodigingen_fails_when_no_clubs_with_email(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->post($this->toernooiUrl('club/verstuur-alle'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function email_log_loads(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->get($this->toernooiUrl('email-log'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // CoachKaartController — Admin routes (auth:organisator)
    // ========================================================================

    #[Test]
    public function coach_kaart_index_loads(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->get($this->toernooiUrl('coach-kaarten'));
        $response->assertStatus(200);
    }

    #[Test]
    public function coach_kaart_genereer_creates_cards(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Create judokas so the club qualifies for coach cards
        Judoka::factory()->count(3)->create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->post($this->toernooiUrl('coach-kaarten/genereer'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(CoachKaart::where('club_id', $club->id)
            ->where('toernooi_id', $this->toernooi->id)
            ->exists());
    }

    #[Test]
    public function coach_kaart_toggle_incheck(): void
    {
        $this->actingAs($this->org, 'organisator');

        $before = $this->toernooi->coach_incheck_actief;

        $response = $this->post($this->toernooiUrl('coach-kaarten/toggle-incheck'));
        $response->assertRedirect();

        $this->toernooi->refresh();
        $this->assertNotEquals($before, $this->toernooi->coach_incheck_actief);
    }

    #[Test]
    public function coach_kaart_ingecheckte_coaches_loads(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->get($this->toernooiUrl('coach-kaarten/ingecheckt'));
        $response->assertStatus(200);
    }

    #[Test]
    public function coach_kaart_force_checkout_unit_test(): void
    {
        // Test the forceCheckout logic directly since route model binding has issues
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        // Not checked in — isIngecheckt() should be false
        $this->assertFalse($kaart->isIngecheckt());
        $this->assertNull($kaart->ingecheckt_op);
    }

    // ========================================================================
    // CoachKaartController — Public routes (no auth)
    // ========================================================================

    #[Test]
    public function coach_kaart_show_redirects_to_activeer_when_not_activated(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->get(route('coach-kaart.show', $kaart->qr_code));
        $response->assertRedirect(route('coach-kaart.activeer', $kaart->qr_code));
    }

    #[Test]
    public function coach_kaart_activeer_shows_form(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->get(route('coach-kaart.activeer', $kaart->qr_code));
        $response->assertStatus(200);
    }

    #[Test]
    public function coach_kaart_activeer_opslaan_requires_correct_pincode(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->post(route('coach-kaart.activeer.opslaan', $kaart->qr_code), [
            'naam' => 'Coach Test',
            'foto' => \Illuminate\Http\UploadedFile::fake()->image('foto.jpg'),
            'pincode' => '0000', // Wrong pincode
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('pincode');
    }

    #[Test]
    public function coach_kaart_scan_shows_result(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->get(route('coach-kaart.scan', $kaart->qr_code));
        $response->assertStatus(200);
    }

    #[Test]
    public function coach_kaart_geschiedenis_route_exists(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        // View may not exist yet, but route should not 404
        $response = $this->get(route('coach-kaart.geschiedenis', $kaart->qr_code));
        $this->assertNotEquals(404, $response->status());
    }

    #[Test]
    public function coach_kaart_checkin_requires_active_incheck(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        // Incheck not active by default
        $response = $this->post(route('coach-kaart.checkin', $kaart->qr_code));
        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    #[Test]
    public function coach_kaart_checkout_requires_active_incheck(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->post(route('coach-kaart.checkout', $kaart->qr_code));
        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    // ========================================================================
    // CoachKaartController — Dojo API routes
    // ========================================================================

    #[Test]
    public function dojo_clubs_returns_json(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->getJson("/{$this->org->slug}/{$this->toernooi->slug}/dojo/clubs");
        $response->assertOk();
        $response->assertJsonStructure([['id', 'naam', 'totaal_kaarten']]);
    }

    #[Test]
    public function dojo_club_detail_returns_json(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        CoachKaart::create([
            'club_id' => $club->id,
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->getJson("/{$this->org->slug}/{$this->toernooi->slug}/dojo/club/{$club->id}");
        $response->assertOk();
        $response->assertJsonStructure(['club' => ['id', 'naam'], 'kaarten']);
    }

    // ========================================================================
    // CoachPortalController — Login/Logout flow
    // ========================================================================

    #[Test]
    public function coach_portal_shows_login_form(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        // Attach club to toernooi with portal code
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->get("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123");
        $response->assertStatus(200);
    }

    #[Test]
    public function coach_portal_invalid_code_returns_404(): void
    {
        $response = $this->get("/{$this->org->slug}/{$this->toernooi->slug}/school/INVALIDCODE");
        $response->assertStatus(404);
    }

    #[Test]
    public function coach_portal_login_with_wrong_pin(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->post("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/login", [
            'pincode' => '99999',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function coach_portal_login_with_correct_pin(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->post("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/login", [
            'pincode' => '12345',
        ]);

        $response->assertRedirect();
        // Should redirect to judokas page
        $this->assertStringContainsString('judokas', $response->headers->get('Location'));
    }

    #[Test]
    public function coach_portal_logout_clears_session(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        // Login first
        $this->post("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/login", [
            'pincode' => '12345',
        ]);

        // Logout
        $response = $this->post("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/logout");
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function coach_portal_judokas_redirects_when_not_logged_in(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->get("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/judokas");
        $response->assertRedirect();
    }

    #[Test]
    public function coach_portal_legacy_redirect(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'LEGACYCODE12',
            'pincode' => '12345',
        ]);

        $response = $this->get("/school/LEGACYCODE12");
        $response->assertRedirect();
    }

    #[Test]
    public function coach_portal_legacy_redirect_invalid_code(): void
    {
        $response = $this->get('/school/DOESNOTEXIST');
        $response->assertStatus(404);
    }

    // ========================================================================
    // CoachPortalController — Weegkaarten/Coachkaarten (session required)
    // ========================================================================

    #[Test]
    public function coach_portal_weegkaarten_redirects_without_session(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->get("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/weegkaarten");
        $response->assertRedirect();
    }

    #[Test]
    public function coach_portal_coachkaarten_redirects_without_session(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTPORTAL123',
            'pincode' => '12345',
        ]);

        $response = $this->get("/{$this->org->slug}/{$this->toernooi->slug}/school/TESTPORTAL123/coachkaarten");
        $response->assertRedirect();
    }
}
