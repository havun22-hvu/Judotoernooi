<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\ChatMessage;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\WimpelMilestone;
use App\Models\WimpelUitreiking;
use App\Services\WegingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WegingWimpelChatCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->wimpelAbo()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'gewicht_tolerantie' => 0.5,
            'max_wegingen' => 2,
            'weging_verplicht' => true,
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    private function wegingUrl(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging" . ($suffix ? "/{$suffix}" : '');
    }

    private function toernooiUrl(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}" . ($suffix ? "/{$suffix}" : '');
    }

    private function orgUrl(string $suffix = ''): string
    {
        return "/{$this->org->slug}" . ($suffix ? "/{$suffix}" : '');
    }

    private function makeJudoka(array $attrs = []): Judoka
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
        ], $attrs));
    }

    // ========================================================================
    // WegingController — uncovered lines: scanQR, registreer error path,
    // getJudokasVoorLijst with data
    // ========================================================================

    #[Test]
    public function weging_registreer_service_failure_returns_error(): void
    {
        $this->actAsOrg();
        $judoka = $this->makeJudoka();

        // Mock WegingService to return failure
        $mock = $this->mock(WegingService::class);
        $mock->shouldReceive('registreerGewicht')->andReturn([
            'success' => false,
            'error' => 'Weging gesloten',
        ]);

        $url = $this->wegingUrl("{$judoka->id}/registreer");
        $response = $this->postJson($url, ['gewicht' => 29.0]);
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'Weging gesloten']);
    }

    #[Test]
    public function weging_scan_qr_returns_judoka_data(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'qr_code' => 'TESTQR123',
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
            'aanwezigheid' => 'aanwezig',
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->wegingUrl('scan-qr');
        $response = $this->postJson($url, ['qr_code' => 'TESTQR123']);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('judoka.id', $judoka->id);
        $response->assertJsonPath('judoka.naam', $judoka->naam);
    }

    #[Test]
    public function weging_scan_qr_not_found_returns_404(): void
    {
        $this->actAsOrg();

        $url = $this->wegingUrl('scan-qr');
        $response = $this->postJson($url, ['qr_code' => 'NONEXISTENT']);
        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function weging_scan_qr_wrong_toernooi_returns_404(): void
    {
        $this->actAsOrg();

        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $otherToernooi->id,
            'club_id' => $club->id,
            'qr_code' => 'WRONGTOERNOOI',
        ]);

        $url = $this->wegingUrl('scan-qr');
        $response = $this->postJson($url, ['qr_code' => 'WRONGTOERNOOI']);
        $response->assertStatus(404);
    }

    #[Test]
    public function weging_lijst_json_with_judoka_data(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1, 'weging_gesloten' => true]);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
        ]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
            'gewicht_gewogen' => 29.0,
            'aanwezigheid' => 'afwezig',
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->wegingUrl('lijst-json');
        $response = $this->getJson($url);
        $response->assertStatus(200);
    }

    #[Test]
    public function weging_index_with_blok_filter(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);

        $url = $this->wegingUrl("blok/{$blok->id}");
        $response = $this->get($url);
        $response->assertStatus(200);
    }

    // ========================================================================
    // WimpelController — full coverage (0% -> target)
    // ========================================================================

    #[Test]
    public function wimpel_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->orgUrl('wimpeltoernooi'));
        $response->assertStatus(200);
    }

    #[Test]
    public function wimpel_index_requires_auth(): void
    {
        $response = $this->get($this->orgUrl('wimpeltoernooi'));
        $response->assertRedirect();
    }

    #[Test]
    public function wimpel_show_loads_for_stam_judoka(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->metPunten(10)->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->get($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}"));
        $response->assertStatus(200);
    }

    #[Test]
    public function wimpel_show_403_for_other_org_judoka(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $otherOrg->id]);

        $response = $this->get($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}"));
        $response->assertStatus(403);
    }

    #[Test]
    public function wimpel_instellingen_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->orgUrl('wimpeltoernooi/instellingen'));
        $response->assertStatus(200);
    }

    #[Test]
    public function wimpel_store_milestone(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->orgUrl('wimpeltoernooi/milestones'), [
            'punten' => 50,
            'omschrijving' => 'Bronzen wimpel',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('wimpel_milestones', [
            'organisator_id' => $this->org->id,
            'punten' => 50,
        ]);
    }

    #[Test]
    public function wimpel_update_milestone(): void
    {
        $this->actAsOrg();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $this->org->id,
            'punten' => 50,
            'omschrijving' => 'Bronzen wimpel',
            'volgorde' => 1,
        ]);

        $response = $this->putJson($this->orgUrl("wimpeltoernooi/milestones/{$milestone->id}"), [
            'punten' => 75,
            'omschrijving' => 'Zilveren wimpel',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('wimpel_milestones', ['id' => $milestone->id, 'punten' => 75]);
    }

    #[Test]
    public function wimpel_update_milestone_403_for_other_org(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $otherOrg->id,
            'punten' => 50,
            'omschrijving' => 'Test',
            'volgorde' => 1,
        ]);

        $response = $this->putJson($this->orgUrl("wimpeltoernooi/milestones/{$milestone->id}"), [
            'punten' => 75,
            'omschrijving' => 'Hijack',
        ]);
        $response->assertStatus(403);
    }

    #[Test]
    public function wimpel_destroy_milestone(): void
    {
        $this->actAsOrg();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $this->org->id,
            'punten' => 50,
            'omschrijving' => 'Te verwijderen',
            'volgorde' => 1,
        ]);

        $response = $this->deleteJson($this->orgUrl("wimpeltoernooi/milestones/{$milestone->id}"));
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('wimpel_milestones', ['id' => $milestone->id]);
    }

    #[Test]
    public function wimpel_destroy_milestone_with_uitreikingen_blocked(): void
    {
        $this->actAsOrg();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $this->org->id,
            'punten' => 50,
            'omschrijving' => 'Heeft uitreikingen',
            'volgorde' => 1,
        ]);
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);
        WimpelUitreiking::create([
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'uitgereikt' => true,
            'uitgereikt_at' => now(),
        ]);

        $response = $this->deleteJson($this->orgUrl("wimpeltoernooi/milestones/{$milestone->id}"));
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    #[Test]
    public function wimpel_destroy_milestone_403_for_other_org(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $otherOrg->id,
            'punten' => 50,
            'omschrijving' => 'Andere org',
            'volgorde' => 1,
        ]);

        $response = $this->deleteJson($this->orgUrl("wimpeltoernooi/milestones/{$milestone->id}"));
        $response->assertStatus(403);
    }

    #[Test]
    public function wimpel_aanpassen_adds_points(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->metPunten(10)->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/aanpassen"), [
            'punten' => 5,
            'notitie' => 'Handmatige correctie',
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function wimpel_aanpassen_prevents_negative_total(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->metPunten(5)->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/aanpassen"), [
            'punten' => -10,
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    #[Test]
    public function wimpel_bevestig_judoka(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->nieuw()->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/bevestig"));
        $response->assertJson(['success' => true]);
        $this->assertFalse($stamJudoka->fresh()->wimpel_is_nieuw);
    }

    #[Test]
    public function wimpel_stuur_naar_spreker_milestone_403_for_other_org(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $otherOrg->id,
            'punten' => 50,
            'omschrijving' => 'Andere org milestone',
            'volgorde' => 1,
        ]);
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/stuur-naar-spreker"), [
            'milestone_id' => $milestone->id,
        ]);
        $response->assertStatus(403);
    }

    #[Test]
    public function wimpel_stuur_naar_spreker_no_active_toernooi(): void
    {
        $this->actAsOrg();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $this->org->id,
            'punten' => 50,
            'omschrijving' => 'Test',
            'volgorde' => 1,
        ]);
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/stuur-naar-spreker"), [
            'milestone_id' => $milestone->id,
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    #[Test]
    public function wimpel_handmatig_uitreiken(): void
    {
        $this->actAsOrg();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $this->org->id,
            'punten' => 50,
            'omschrijving' => 'Test milestone',
            'volgorde' => 1,
        ]);
        $stamJudoka = StamJudoka::factory()->metPunten(50)->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/handmatig-uitreiken"), [
            'milestone_id' => $milestone->id,
            'datum' => now()->format('Y-m-d'),
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('wimpel_uitreikingen', [
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'uitgereikt' => true,
        ]);
    }

    #[Test]
    public function wimpel_handmatig_uitreiken_403_for_other_org_milestone(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $otherOrg->id,
            'punten' => 50,
            'omschrijving' => 'Andere org',
            'volgorde' => 1,
        ]);
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->postJson($this->orgUrl("wimpeltoernooi/{$stamJudoka->id}/handmatig-uitreiken"), [
            'milestone_id' => $milestone->id,
            'datum' => now()->format('Y-m-d'),
        ]);
        $response->assertStatus(403);
    }

    #[Test]
    public function wimpel_verwerk_toernooi_already_processed(): void
    {
        $this->actAsOrg();
        // Tournament without punten competitie poules = isAlVerwerkt returns true
        $afgesloten = Toernooi::factory()->afgesloten()->create([
            'organisator_id' => $this->org->id,
        ]);

        $response = $this->postJson($this->orgUrl('wimpeltoernooi/verwerk-toernooi'), [
            'toernooi_id' => $afgesloten->id,
        ]);
        $response->assertStatus(422);
        $response->assertJson(['error' => 'Dit toernooi is al verwerkt.']);
    }

    #[Test]
    public function wimpel_verwerk_toernooi_403_for_other_org(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $otherToernooi = Toernooi::factory()->afgesloten()->create([
            'organisator_id' => $otherOrg->id,
        ]);

        $response = $this->postJson($this->orgUrl('wimpeltoernooi/verwerk-toernooi'), [
            'toernooi_id' => $otherToernooi->id,
        ]);
        $response->assertStatus(403);
    }

    // ========================================================================
    // ChatController — full coverage (0% -> target)
    // ========================================================================

    #[Test]
    public function chat_index_returns_messages(): void
    {
        $this->actAsOrg();
        ChatMessage::create([
            'toernooi_id' => $this->toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'mat',
            'naar_id' => 1,
            'bericht' => 'Test bericht',
        ]);

        $url = $this->toernooiUrl('api/chat') . '?type=hoofdjury&id=';
        $response = $this->getJson($url);
        $response->assertStatus(200);
    }

    #[Test]
    public function chat_store_creates_message(): void
    {
        $this->actAsOrg();
        $url = $this->toernooiUrl('api/chat');
        $response = $this->postJson($url, [
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'mat',
            'naar_id' => 1,
            'bericht' => 'Ga naar mat 2 aub',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('chat_messages', [
            'toernooi_id' => $this->toernooi->id,
            'bericht' => 'Ga naar mat 2 aub',
        ]);
    }

    #[Test]
    public function chat_store_validation_fails(): void
    {
        $this->actAsOrg();
        $url = $this->toernooiUrl('api/chat');
        $response = $this->postJson($url, [
            'van_type' => 'ongeldig_type',
            'naar_type' => 'mat',
            'bericht' => 'test',
        ]);
        $response->assertStatus(422);
    }

    #[Test]
    public function chat_mark_as_read(): void
    {
        $this->actAsOrg();
        $msg = ChatMessage::create([
            'toernooi_id' => $this->toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'naar_id' => null,
            'bericht' => 'Help nodig',
        ]);

        $url = $this->toernooiUrl('api/chat/read');
        $response = $this->postJson($url, [
            'type' => 'hoofdjury',
            'id' => null,
            'message_ids' => [$msg->id],
        ]);
        $response->assertJson(['success' => true]);
        $this->assertNotNull($msg->fresh()->gelezen_op);
    }

    #[Test]
    public function chat_mark_as_read_without_message_ids(): void
    {
        $this->actAsOrg();
        ChatMessage::create([
            'toernooi_id' => $this->toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'naar_id' => null,
            'bericht' => 'Iets',
        ]);

        $url = $this->toernooiUrl('api/chat/read');
        $response = $this->postJson($url, [
            'type' => 'hoofdjury',
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function chat_unread_count(): void
    {
        $this->actAsOrg();
        ChatMessage::create([
            'toernooi_id' => $this->toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'naar_id' => null,
            'bericht' => 'Ongelezen bericht',
        ]);

        $url = $this->toernooiUrl('api/chat/unread') . '?type=hoofdjury&id=';
        $response = $this->getJson($url);
        $response->assertStatus(200);
        $response->assertJsonStructure(['count']);
    }

    // ========================================================================
    // WeegkaartController — full coverage (0% -> target)
    // ========================================================================

    #[Test]
    public function weegkaart_show_by_token(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'qr_code' => 'WEEGKAART_TOKEN_123',
        ]);

        $response = $this->get("/weegkaart/WEEGKAART_TOKEN_123");
        $response->assertStatus(200);
    }

    #[Test]
    public function weegkaart_show_invalid_token_404(): void
    {
        $response = $this->get("/weegkaart/INVALID_TOKEN");
        $response->assertStatus(404);
    }

    // ========================================================================
    // StamJudokaController — full coverage (0% -> target)
    // ========================================================================

    #[Test]
    public function stambestand_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->orgUrl('judokas'));
        $response->assertStatus(200);
    }

    #[Test]
    public function stambestand_index_requires_auth(): void
    {
        $response = $this->get($this->orgUrl('judokas'));
        $response->assertRedirect();
    }

    #[Test]
    public function stambestand_store(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->orgUrl('judokas'), [
            'naam' => 'Test Judoka',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('stam_judokas', [
            'organisator_id' => $this->org->id,
            'naam' => 'Test Judoka',
        ]);
    }

    #[Test]
    public function stambestand_store_validation_fails(): void
    {
        $this->actAsOrg();
        $response = $this->postJson($this->orgUrl('judokas'), [
            'naam' => '',
            'geboortejaar' => 1800,
        ]);
        $response->assertStatus(422);
    }

    #[Test]
    public function stambestand_update(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->putJson($this->orgUrl("judokas/{$stamJudoka->id}"), [
            'naam' => 'Gewijzigde Naam',
            'geboortejaar' => 2016,
            'geslacht' => 'V',
            'band' => 'geel',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertEquals('Gewijzigde Naam', $stamJudoka->fresh()->naam);
    }

    #[Test]
    public function stambestand_update_403_for_other_org(): void
    {
        $this->actAsOrg();
        $otherOrg = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $otherOrg->id]);

        $response = $this->putJson($this->orgUrl("judokas/{$stamJudoka->id}"), [
            'naam' => 'Hijack',
            'geboortejaar' => 2016,
            'geslacht' => 'V',
            'band' => 'geel',
        ]);
        $response->assertStatus(403);
    }

    #[Test]
    public function stambestand_destroy(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->deleteJson($this->orgUrl("judokas/{$stamJudoka->id}"));
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('stam_judokas', ['id' => $stamJudoka->id]);
    }

    #[Test]
    public function stambestand_toggle_actief(): void
    {
        $this->actAsOrg();
        $stamJudoka = StamJudoka::factory()->create([
            'organisator_id' => $this->org->id,
            'actief' => true,
        ]);

        $response = $this->postJson($this->orgUrl("judokas/{$stamJudoka->id}/toggle"));
        $response->assertJson(['success' => true, 'actief' => false]);
        $this->assertFalse($stamJudoka->fresh()->actief);
    }

    // ========================================================================
    // WedstrijddagController — coverage targets
    // ========================================================================

    #[Test]
    public function wedstrijddag_poules_loads(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        $url = $this->toernooiUrl('wedstrijddag/poules');
        $response = $this->get($url);
        $response->assertStatus(200);
    }

    #[Test]
    public function wedstrijddag_toggle_heartbeat(): void
    {
        $this->actAsOrg();
        $url = $this->toernooiUrl('wedstrijddag/heartbeat-toggle');

        // First toggle: activate
        $response = $this->postJson($url);
        $response->assertJson(['active' => true]);

        // Second toggle: deactivate
        $response = $this->postJson($url);
        $response->assertJson(['active' => false]);
    }

    #[Test]
    public function wedstrijddag_nieuwe_poule(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "mini's",
        ]);

        $url = $this->toernooiUrl('wedstrijddag/nieuwe-poule');
        $response = $this->postJson($url, [
            'leeftijdsklasse' => "mini's",
            'gewichtsklasse' => '-24',
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function wedstrijddag_verwijder_uit_poule(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);
        $judoka = $this->makeJudoka();
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->toernooiUrl('wedstrijddag/verwijder-uit-poule');
        $response = $this->postJson($url, [
            'judoka_id' => $judoka->id,
            'poule_id' => $poule->id,
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function wedstrijddag_meld_judoka_af(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
        ]);
        $judoka = $this->makeJudoka(['aanwezigheid' => 'aanwezig']);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->toernooiUrl('wedstrijddag/meld-judoka-af');
        $response = $this->postJson($url, ['judoka_id' => $judoka->id]);
        $response->assertJson(['success' => true]);
        $this->assertEquals('afwezig', $judoka->fresh()->aanwezigheid);
    }

    #[Test]
    public function wedstrijddag_meld_judoka_af_not_found(): void
    {
        $this->actAsOrg();
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $this->org->toernooien()->attach($otherToernooi->id, ['rol' => 'eigenaar']);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $otherToernooi->id,
            'club_id' => $club->id,
        ]);

        $url = $this->toernooiUrl('wedstrijddag/meld-judoka-af');
        $response = $this->postJson($url, ['judoka_id' => $judoka->id]);
        $response->assertStatus(404);
    }

    #[Test]
    public function wedstrijddag_herstel_judoka_not_in_toernooi(): void
    {
        $this->actAsOrg();
        // Test the 404 path: judoka exists but belongs to different toernooi
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $this->org->toernooien()->attach($otherToernooi->id, ['rol' => 'eigenaar']);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $otherToernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'afwezig',
        ]);

        $url = $this->toernooiUrl('wedstrijddag/herstel-judoka');
        $response = $this->postJson($url, ['judoka_id' => $judoka->id]);
        $response->assertStatus(404);
    }

    #[Test]
    public function wedstrijddag_herstel_judoka_not_found(): void
    {
        $this->actAsOrg();
        $url = $this->toernooiUrl('wedstrijddag/herstel-judoka');
        $response = $this->postJson($url, ['judoka_id' => 99999]);
        $response->assertStatus(422); // Validation fails: exists check
    }

    #[Test]
    public function wedstrijddag_poules_api(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
        ]);

        $url = $this->toernooiUrl('wedstrijddag/poules-api');
        $response = $this->getJson($url);
        $response->assertStatus(200);
    }

    #[Test]
    public function wedstrijddag_mat_voortgang_api(): void
    {
        $this->actAsOrg();
        Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);

        $url = $this->toernooiUrl('wedstrijddag/mat-voortgang');
        $response = $this->getJson($url);
        $response->assertStatus(200);
    }

    #[Test]
    public function wedstrijddag_nieuwe_judoka(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "mini's",
            'gewichtsklasse' => '-24',
        ]);

        // Omit geboortejaar and gewicht to avoid bepaalGewichtsklasse type issues in SQLite
        $url = $this->toernooiUrl('wedstrijddag/nieuwe-judoka');
        $response = $this->postJson($url, [
            'naam' => 'Nieuwe Judoka',
            'band' => 'wit',
            'club_id' => $club->id,
            'poule_id' => $poule->id,
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('judokas', [
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Nieuwe Judoka',
        ]);
    }

    #[Test]
    public function wedstrijddag_wijzig_poule_type(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'type' => 'voorronde',
        ]);

        $url = $this->toernooiUrl('wedstrijddag/wijzig-poule-type');
        $response = $this->postJson($url, [
            'poule_id' => $poule->id,
            'type' => 'eliminatie',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertEquals('eliminatie', $poule->fresh()->type);
    }

    #[Test]
    public function wedstrijddag_wijzig_poule_type_same_type_error(): void
    {
        $this->actAsOrg();
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'type' => 'voorronde',
        ]);

        $url = $this->toernooiUrl('wedstrijddag/wijzig-poule-type');
        $response = $this->postJson($url, [
            'poule_id' => $poule->id,
            'type' => 'poules', // maps to voorronde, same as current
        ]);
        $response->assertStatus(400);
    }

    #[Test]
    public function wedstrijddag_wijzig_poule_type_to_kruisfinale(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'type' => 'voorronde',
            'leeftijdsklasse' => "mini's",
            'gewichtsklasse' => '-24',
        ]);

        $url = $this->toernooiUrl('wedstrijddag/wijzig-poule-type');
        $response = $this->postJson($url, [
            'poule_id' => $poule->id,
            'type' => 'poules_kruisfinale',
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function wedstrijddag_naar_zaaloverzicht(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 1,
            'weging_gesloten' => true,
        ]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $judoka = $this->makeJudoka(['gewicht_gewogen' => 28.0, 'aanwezigheid' => 'aanwezig']);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "mini's",
            'gewichtsklasse' => '-30',
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->toernooiUrl('wedstrijddag/naar-zaaloverzicht');
        $response = $this->postJson($url, [
            'category' => "mini's|-30",
        ]);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function wedstrijddag_naar_zaaloverzicht_poule(): void
    {
        $this->actAsOrg();
        $blok = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 1,
            'weging_gesloten' => true,
        ]);
        $mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $judoka = $this->makeJudoka(['gewicht_gewogen' => 28.0, 'aanwezigheid' => 'aanwezig']);
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'leeftijdsklasse' => "mini's",
            'gewichtsklasse' => '-30',
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $url = $this->toernooiUrl('wedstrijddag/naar-zaaloverzicht-poule');
        $response = $this->postJson($url, ['poule_id' => $poule->id]);
        $response->assertJson(['success' => true]);
    }
}
