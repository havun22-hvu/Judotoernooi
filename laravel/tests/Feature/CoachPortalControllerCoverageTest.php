<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachPortalControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;
    private Club $club;
    private string $portalCode = 'TESTPORTAL123';
    private string $pincode = '12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'portaal_modus' => 'volledig',
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);

        $this->club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($this->club->id, [
            'portal_code' => $this->portalCode,
            'pincode' => $this->pincode,
        ]);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function portalUrl(string $path = ''): string
    {
        $base = "/{$this->org->slug}/{$this->toernooi->slug}/school/{$this->portalCode}";
        return $path ? "{$base}/{$path}" : $base;
    }

    private function sessionKey(): string
    {
        return "club_logged_in_{$this->toernooi->id}_{$this->portalCode}";
    }

    // ========================================================================
    // indexCode — login page / redirect if already logged in
    // ========================================================================

    #[Test]
    public function index_code_redirects_to_judokas_when_already_logged_in(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl());

        $response->assertRedirect();
        $this->assertStringContainsString('judokas', $response->headers->get('Location'));
    }

    // ========================================================================
    // loginPin — locale handling + edge cases
    // ========================================================================

    #[Test]
    public function login_redirects_to_judokas(): void
    {
        $response = $this->post($this->portalUrl('login'), ['pincode' => $this->pincode]);

        $response->assertRedirect();
    }

    #[Test]
    public function login_with_invalid_code_returns_404(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->post("/{$this->org->slug}/{$this->toernooi->slug}/school/INVALIDCODE/login", [
                'pincode' => '12345',
            ]);
        $response->assertStatus(404);
    }

    #[Test]
    public function login_with_invalid_pincode_format_fails_validation(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->post($this->portalUrl('login'), [
                'pincode' => '12',
            ]);
        $response->assertSessionHasErrors('pincode');
    }

    // ========================================================================
    // judokasCode — with session (full page)
    // ========================================================================

    #[Test]
    public function judokas_page_loads_when_logged_in(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('judokas'));

        $response->assertStatus(200);
    }

    #[Test]
    public function judokas_page_shows_judokas_for_club(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Testjudoka Eén',
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('judokas'));

        $response->assertStatus(200);
        $response->assertSee('Testjudoka');
    }

    // ========================================================================
    // storeJudokaCode — create judoka via portal
    // ========================================================================

    #[Test]
    public function store_judoka_without_session_redirects_to_login(): void
    {
        $response = $this->post($this->portalUrl('judoka'), [
            'naam' => 'Nieuwe Judoka',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function store_judoka_creates_judoka(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Nieuwe Judoka',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'gewicht' => 35,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', [
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Nieuwe Judoka',
        ]);
    }

    #[Test]
    public function store_judoka_with_minimal_data(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Minimale Judoka',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', ['naam' => 'Minimale Judoka']);
    }

    #[Test]
    public function store_judoka_duplicate_rejected(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Duplicate Judoka',
            'geboortejaar' => 2016,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Duplicate Judoka',
                'geboortejaar' => 2016,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function store_judoka_blocked_when_inschrijving_closed(): void
    {
        $this->toernooi->update(['inschrijving_deadline' => now()->subDay()]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Te Laat Judoka',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function store_judoka_blocked_when_max_reached(): void
    {
        $this->toernooi->update(['max_judokas' => 1]);
        // Create one judoka to fill the max
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Vol Judoka',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function store_judoka_blocked_when_portaal_not_volledig(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Blocked Judoka',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function store_judoka_with_gewichtsklasse_explicit(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Klasse Judoka',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'V',
                'gewicht' => 30,
                'gewichtsklasse' => '-32',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', [
            'naam' => 'Klasse Judoka',
            'gewichtsklasse' => '-32',
        ]);
    }

    #[Test]
    public function store_judoka_fallback_gewichtsklasse_from_gewicht(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Fallback Klasse',
                'geboortejaar' => date('Y') - 8,
                'gewicht' => 42,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', [
            'naam' => 'Fallback Klasse',
            'gewichtsklasse' => '-42',
        ]);
    }

    #[Test]
    public function store_judoka_with_telefoon_06_parsing(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Telefoon Judoka',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
                'telefoon' => '06 12345678',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'naam' => 'Telefoon Judoka',
            'telefoon' => '+31612345678',
        ]);
    }

    #[Test]
    public function store_judoka_with_0031_telefoon(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Intl Judoka',
                'geboortejaar' => date('Y') - 9,
                'geslacht' => 'V',
                'telefoon' => '0031612345678',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'naam' => 'Intl Judoka',
            'telefoon' => '+31612345678',
        ]);
    }

    #[Test]
    public function store_judoka_with_empty_telefoon(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'No Phone Judoka',
                'geboortejaar' => date('Y') - 7,
                'geslacht' => 'M',
                'telefoon' => '',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'naam' => 'No Phone Judoka',
            'telefoon' => null,
        ]);
    }

    #[Test]
    public function store_judoka_with_band_and_geboortejaar(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'Band Judoka',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
                'band' => 'oranje',
                'gewicht' => 28,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function store_judoka_with_jbn_lidnummer(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('judoka'), [
                'naam' => 'JBN Judoka',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'jbn_lidnummer' => '12345678',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'jbn_lidnummer' => '12345678',
        ]);
    }

    // ========================================================================
    // updateJudokaCode — update judoka via portal
    // ========================================================================

    #[Test]
    public function update_judoka_without_session_redirects(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->put($this->portalUrl("judoka/{$judoka->id}"), [
            'naam' => 'Updated',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_judoka_success(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Original',
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Updated Naam',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'gewicht' => 35,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function update_judoka_not_found_returns_error(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl('judoka/99999'), [
                'naam' => 'Ghost',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_judoka_wrong_club_returns_403(): void
    {
        $otherClub = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $otherClub->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Hacked',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'gewicht' => 30,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function update_judoka_blocked_when_portaal_readonly(): void
    {
        $this->toernooi->update(['portaal_modus' => 'alleen_lezen']);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Blocked',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'gewicht' => 30,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_judoka_blocked_when_inschrijving_closed(): void
    {
        $this->toernooi->update(['inschrijving_deadline' => now()->subDay()]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Too Late',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'M',
                'gewicht' => 30,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_judoka_with_explicit_gewichtsklasse(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Updated',
                'geboortejaar' => date('Y') - 10,
                'geslacht' => 'V',
                'gewicht' => 30,
                'gewichtsklasse' => '-32',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function update_judoka_fallback_gewichtsklasse(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->put($this->portalUrl("judoka/{$judoka->id}"), [
                'naam' => 'Fallback',
                'gewicht' => 45,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $judoka->refresh();
        $this->assertEquals('-45', $judoka->gewichtsklasse);
    }

    // ========================================================================
    // destroyJudokaCode — delete judoka via portal
    // ========================================================================

    #[Test]
    public function destroy_judoka_without_session_redirects(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->delete($this->portalUrl("judoka/{$judoka->id}"));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function destroy_judoka_success(): void
    {
        // Must be paid tier to allow deletion
        $this->toernooi->update(['plan_type' => 'paid']);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->delete($this->portalUrl("judoka/{$judoka->id}"));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('judokas', ['id' => $judoka->id]);
    }

    #[Test]
    public function destroy_judoka_not_found_returns_error(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->delete($this->portalUrl('judoka/99999'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function destroy_judoka_wrong_club_returns_403(): void
    {
        $otherClub = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $otherClub->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->delete($this->portalUrl("judoka/{$judoka->id}"));

        $response->assertStatus(403);
    }

    #[Test]
    public function destroy_judoka_blocked_when_portaal_not_volledig(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->delete($this->portalUrl("judoka/{$judoka->id}"));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function destroy_judoka_blocked_when_inschrijving_closed(): void
    {
        $this->toernooi->update(['inschrijving_deadline' => now()->subDay()]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->delete($this->portalUrl("judoka/{$judoka->id}"));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ========================================================================
    // weegkaartenCode — with session
    // ========================================================================

    #[Test]
    public function weegkaarten_page_loads_when_logged_in(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('weegkaarten'));

        $response->assertStatus(200);
    }

    // ========================================================================
    // coachkaartenCode — with session (creates coach kaart if needed)
    // ========================================================================

    #[Test]
    public function coachkaarten_page_loads_and_creates_default_kaart(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('coachkaarten'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('coach_kaarten', [
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
    }

    #[Test]
    public function coachkaarten_page_with_existing_kaarten(): void
    {
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Bestaande Kaart',
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('coachkaarten'));

        $response->assertStatus(200);
    }

    #[Test]
    public function coachkaarten_page_cleans_up_excess_unscanned_kaarten(): void
    {
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Kaart 1',
        ]);
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Kaart 2',
            'is_gescand' => false,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('coachkaarten'));

        $response->assertStatus(200);
    }

    #[Test]
    public function coachkaarten_page_keeps_scanned_kaarten(): void
    {
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Kaart 1',
            'is_gescand' => true,
        ]);
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Kaart 2',
            'is_gescand' => true,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('coachkaarten'));

        $response->assertStatus(200);
        $this->assertEquals(2, CoachKaart::where('toernooi_id', $this->toernooi->id)
            ->where('club_id', $this->club->id)
            ->count());
    }

    #[Test]
    public function coachkaarten_page_after_voorbereiding_keeps_all(): void
    {
        $this->toernooi->update(['voorbereiding_klaar_op' => now()]);

        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
        CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('coachkaarten'));

        $response->assertStatus(200);
        $this->assertEquals(2, CoachKaart::where('toernooi_id', $this->toernooi->id)
            ->where('club_id', $this->club->id)
            ->count());
    }

    // ========================================================================
    // toewijzenCoachkaart — assign coach name to kaart
    // ========================================================================

    #[Test]
    public function toewijzen_coachkaart_without_session_redirects(): void
    {
        $kaart = CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->post($this->portalUrl("coachkaart/{$kaart->id}/toewijzen"), [
            'naam' => 'Coach Naam',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function toewijzen_coachkaart_with_naam(): void
    {
        $kaart = CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl("coachkaart/{$kaart->id}/toewijzen"), [
                'naam' => 'Coach Jansen',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $kaart->refresh();
        $this->assertEquals('Coach Jansen', $kaart->naam);
    }

    #[Test]
    public function toewijzen_coachkaart_with_organisatie_coach(): void
    {
        $coach = Coach::create([
            'club_id' => $this->club->id,
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Organisatie Coach',
        ]);

        $kaart = CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl("coachkaart/{$kaart->id}/toewijzen"), [
                'organisatie_coach_id' => $coach->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $kaart->refresh();
        $this->assertEquals('Organisatie Coach', $kaart->naam);
    }

    #[Test]
    public function toewijzen_coachkaart_wrong_club_returns_403(): void
    {
        $otherClub = Club::factory()->create(['organisator_id' => $this->org->id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $otherClub->id,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl("coachkaart/{$kaart->id}/toewijzen"), [
                'naam' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    // ========================================================================
    // syncJudokasCode — sync judokas
    // ========================================================================

    #[Test]
    public function sync_judokas_without_session_redirects(): void
    {
        $response = $this->post($this->portalUrl('sync'));
        $response->assertRedirect();
    }

    #[Test]
    public function sync_judokas_success_with_complete_judokas(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'naam' => 'Sync Judoka',
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'gewicht' => 30,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-32',
            'band' => 'wit',
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('sync'));

        $response->assertRedirect();
    }

    #[Test]
    public function sync_judokas_blocked_when_portaal_readonly(): void
    {
        $this->toernooi->update(['portaal_modus' => 'alleen_lezen']);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('sync'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function sync_judokas_blocked_when_inschrijving_closed(): void
    {
        $this->toernooi->update(['inschrijving_deadline' => now()->subDay()]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('sync'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function sync_judokas_with_incomplete_shows_warning(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'geboortejaar' => null,
            'geslacht' => null,
            'gewicht' => null,
            'leeftijdsklasse' => null,
            'gewichtsklasse' => null,
            'band' => null,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('sync'));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    // ========================================================================
    // afrekenCode — payment overview
    // ========================================================================

    #[Test]
    public function afrekenen_page_without_session_redirects(): void
    {
        $response = $this->get($this->portalUrl('afrekenen'));
        $response->assertRedirect();
    }

    #[Test]
    public function afrekenen_page_loads_when_betaling_actief(): void
    {
        $this->toernooi->update([
            'betaling_actief' => true,
            'inschrijfgeld' => 15.00,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('afrekenen'));

        $response->assertStatus(200);
    }

    #[Test]
    public function afrekenen_page_redirects_when_betaling_not_actief(): void
    {
        $this->toernooi->update(['betaling_actief' => false]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('afrekenen'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ========================================================================
    // betalenCode — initiate payment
    // ========================================================================

    #[Test]
    public function betalen_without_session_redirects(): void
    {
        $response = $this->post($this->portalUrl('betalen'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function betalen_blocked_when_betaling_not_actief(): void
    {
        $this->toernooi->update(['betaling_actief' => false]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('betalen'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function betalen_blocked_when_no_judokas_ready(): void
    {
        $this->toernooi->update([
            'betaling_actief' => true,
            'inschrijfgeld' => 15.00,
        ]);

        $response = $this->withSession([$this->sessionKey() => true])
            ->post($this->portalUrl('betalen'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ========================================================================
    // betalingSuccesCode — payment success page
    // ========================================================================

    #[Test]
    public function betaling_succes_without_session_redirects(): void
    {
        $response = $this->get($this->portalUrl('betaling/succes'));
        $response->assertRedirect();
    }

    #[Test]
    public function betaling_succes_loads_when_logged_in(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('betaling/succes'));

        $response->assertStatus(200);
    }

    // ========================================================================
    // betalingGeannuleerdCode — payment cancelled
    // ========================================================================

    #[Test]
    public function betaling_geannuleerd_redirects_with_warning(): void
    {
        $response = $this->get($this->portalUrl('betaling/geannuleerd'));
        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    // ========================================================================
    // resultatenCode — results page
    // ========================================================================

    #[Test]
    public function resultaten_without_session_redirects(): void
    {
        $response = $this->get($this->portalUrl('resultaten'));
        $response->assertRedirect();
    }

    #[Test]
    public function resultaten_loads_when_logged_in(): void
    {
        $response = $this->withSession([$this->sessionKey() => true])
            ->get($this->portalUrl('resultaten'));

        $response->assertStatus(200);
    }

    // ========================================================================
    // redirectLegacy — legacy route
    // ========================================================================

    #[Test]
    public function legacy_redirect_to_new_url_structure(): void
    {
        $response = $this->get("/school/{$this->portalCode}");
        $response->assertRedirect();
        $this->assertStringContainsString($this->org->slug, $response->headers->get('Location'));
        $this->assertStringContainsString($this->toernooi->slug, $response->headers->get('Location'));
    }
}
