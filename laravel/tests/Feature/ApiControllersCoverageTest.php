<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\ToernooiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable rate limiting so auth endpoint tests don't get 429
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    // ========================================================================
    // Helper: create tournament with mat + scoreboard device
    // ========================================================================

    private function createScoreboardSetup(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'aantal_matten' => 2,
            'is_actief' => true,
        ]);

        $mat = Mat::create([
            'toernooi_id' => $toernooi->id,
            'nummer' => 1,
        ]);

        $code = strtoupper('ABCDEF123456');
        $pincode = '1234';
        $apiToken = bin2hex(random_bytes(32));

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',  // 'scoreboard' not in SQLite CHECK constraint; 'mat' accepted by middleware
            'mat_nummer' => 1,
            'code' => $code,
            'pincode' => $pincode,
            'api_token' => $apiToken,
            'laatst_actief' => now(),
        ]);

        return compact('org', 'toernooi', 'mat', 'toegang', 'apiToken', 'code', 'pincode');
    }

    // ========================================================================
    // ScoreboardController — auth
    // ========================================================================

    #[Test]
    public function scoreboard_auth_succeeds_with_valid_code_and_pincode(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/auth', [
            'code' => $setup['code'],
            'pincode' => $setup['pincode'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token', 'rol', 'toernooi_id', 'toernooi_naam',
            'mat_id', 'mat_naam', 'display_code', 'reverb_config',
        ]);
        $response->assertJsonFragment(['rol' => 'mat']);
    }

    #[Test]
    public function scoreboard_auth_fails_with_invalid_code(): void
    {
        $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/auth', [
            'code' => 'INVALIDCODE!',
            'pincode' => '1234',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Ongeldige code of pincode.']);
    }

    #[Test]
    public function scoreboard_auth_fails_with_wrong_pincode(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/auth', [
            'code' => $setup['code'],
            'pincode' => '9999',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function scoreboard_auth_validates_required_fields(): void
    {
        $response = $this->postJson('/api/scoreboard/auth', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'pincode']);
    }

    // ========================================================================
    // ScoreboardController — currentMatch
    // ========================================================================

    #[Test]
    public function scoreboard_current_match_returns_null_when_no_active_match(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->getJson('/api/scoreboard/current-match', [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['match' => null]);
    }

    #[Test]
    public function scoreboard_current_match_returns_match_data_when_active(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
            'mat_id' => $setup['mat']->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        $setup['mat']->update(['actieve_wedstrijd_id' => $wedstrijd->id]);

        $response = $this->getJson('/api/scoreboard/current-match', [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'match' => ['id', 'judoka_wit', 'judoka_blauw', 'poule_naam', 'ronde', 'updated_at'],
            'updated_at',
        ]);
    }

    #[Test]
    public function scoreboard_current_match_requires_token(): void
    {
        $response = $this->getJson('/api/scoreboard/current-match');

        $response->assertStatus(401);
    }

    #[Test]
    public function scoreboard_current_match_rejects_invalid_token(): void
    {
        $response = $this->getJson('/api/scoreboard/current-match', [
            'Authorization' => 'Bearer invalidtoken123',
        ]);

        $response->assertStatus(401);
    }

    // ========================================================================
    // ScoreboardController — result
    // ========================================================================

    #[Test]
    public function scoreboard_result_registers_pool_match_result(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
            'mat_id' => $setup['mat']->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        $response = $this->postJson('/api/scoreboard/result', [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $judokaWit->id,
            'uitslag_type' => 'ippon',
            'updated_at' => null,
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        $response->assertJsonStructure(['success', 'updated_at']);
    }

    #[Test]
    public function scoreboard_result_registers_elimination_match(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
            'mat_id' => $setup['mat']->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->eliminatie()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);

        $response = $this->postJson('/api/scoreboard/result', [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $judokaBlauw->id,
            'uitslag_type' => 'wazaari',
            'updated_at' => null,
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
    }

    #[Test]
    public function scoreboard_result_rejects_invalid_winner(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $otherJudoka = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        $response = $this->postJson('/api/scoreboard/result', [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $otherJudoka->id,
            'uitslag_type' => 'ippon',
            'updated_at' => null,
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['success' => false]);
    }

    #[Test]
    public function scoreboard_result_detects_optimistic_lock_conflict(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        // Send stale updated_at to trigger conflict
        $response = $this->postJson('/api/scoreboard/result', [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $judokaWit->id,
            'uitslag_type' => 'ippon',
            'updated_at' => '2020-01-01T00:00:00.000000Z',
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['success' => false]);
    }

    #[Test]
    public function scoreboard_result_validates_required_fields(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/result', [], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['wedstrijd_id', 'winnaar_id', 'uitslag_type']);
    }

    #[Test]
    public function scoreboard_result_auto_advances_mat_slot(): void
    {
        $setup = $this->createScoreboardSetup();

        $blok = Blok::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $setup['toernooi']->id,
            'blok_id' => $blok->id,
            'mat_id' => $setup['mat']->id,
        ]);

        $judokaWit = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $setup['toernooi']->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        $nextWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        // Set mat slots: current active = wedstrijd, next = nextWedstrijd
        $setup['mat']->update([
            'actieve_wedstrijd_id' => $wedstrijd->id,
            'volgende_wedstrijd_id' => $nextWedstrijd->id,
            'gereedmaken_wedstrijd_id' => null,
        ]);

        $response = $this->postJson('/api/scoreboard/result', [
            'wedstrijd_id' => $wedstrijd->id,
            'winnaar_id' => $judokaWit->id,
            'uitslag_type' => 'ippon',
            'updated_at' => null,
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();

        // Mat should have advanced: active = nextWedstrijd
        $setup['mat']->refresh();
        $this->assertEquals($nextWedstrijd->id, $setup['mat']->actieve_wedstrijd_id);
        $this->assertNull($setup['mat']->volgende_wedstrijd_id);
    }

    // ========================================================================
    // ScoreboardController — event
    // ========================================================================

    #[Test]
    public function scoreboard_event_relays_valid_event(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/event', [
            'event' => 'timer.start',
            'remaining' => 120,
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
    }

    #[Test]
    public function scoreboard_event_rejects_invalid_event_type(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/event', [
            'event' => 'invalid.event',
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function scoreboard_event_returns_404_when_mat_not_found(): void
    {
        $setup = $this->createScoreboardSetup();

        // Change device mat_nummer to non-existent mat
        $setup['toegang']->update(['mat_nummer' => 99]);

        $response = $this->postJson('/api/scoreboard/event', [
            'event' => 'match.start',
        ], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Mat niet gevonden.']);
    }

    // ========================================================================
    // ScoreboardController — heartbeat
    // ========================================================================

    #[Test]
    public function scoreboard_heartbeat_returns_ok(): void
    {
        $setup = $this->createScoreboardSetup();

        $response = $this->postJson('/api/scoreboard/heartbeat', [], [
            'Authorization' => "Bearer {$setup['apiToken']}",
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['ok' => true]);
    }

    // ========================================================================
    // ScoreboardController — version (public route)
    // ========================================================================

    #[Test]
    public function scoreboard_version_returns_config_values(): void
    {
        $response = $this->getJson('/api/scoreboard/version');

        $response->assertOk();
        $response->assertJsonStructure([
            'version', 'versionCode', 'downloadUrl', 'forceUpdate', 'releaseNotes',
        ]);
    }

    // ========================================================================
    // SyncApiController — export (direct call, no route registered)
    // ========================================================================

    #[Test]
    public function sync_export_returns_full_tournament_data(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        $toernooi->clubs()->attach($club->id);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);
        $poule->judokas()->attach($judoka->id);

        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka->id,
            'judoka_blauw_id' => Judoka::factory()->create(['toernooi_id' => $toernooi->id])->id,
        ]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();
        $response = $controller->export($toernooi);

        $data = $response->getData(true);

        $this->assertArrayHasKey('toernooi', $data);
        $this->assertArrayHasKey('clubs', $data);
        $this->assertArrayHasKey('blokken', $data);
        $this->assertArrayHasKey('matten', $data);
        $this->assertArrayHasKey('judokas', $data);
        $this->assertArrayHasKey('poules', $data);
        $this->assertArrayHasKey('wedstrijden', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertCount(1, $data['blokken']);
        $this->assertCount(1, $data['matten']);
        $this->assertCount(1, $data['wedstrijden']);

        // Poules should include judoka_ids
        $this->assertArrayHasKey('judoka_ids', $data['poules'][0]);
    }

    // ========================================================================
    // SyncApiController — receive (direct call, no route registered)
    // ========================================================================

    #[Test]
    public function sync_receive_processes_update_items(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'update',
                    'payload' => [
                        'naam' => 'Updated Name',
                        'local_updated_at' => now()->addHour()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertContains(1, $data['synced']);
        $this->assertEquals('Updated Name', $judoka->fresh()->naam);
    }

    #[Test]
    public function sync_receive_skips_older_updates_conflict_resolution(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Original Name',
        ]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'update',
                    'payload' => [
                        'naam' => 'Stale Update',
                        'local_updated_at' => '2020-01-01T00:00:00Z',
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        // Name should NOT have changed (cloud is newer)
        $this->assertEquals('Original Name', $judoka->fresh()->naam);
    }

    #[Test]
    public function sync_receive_handles_delete_action(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'delete',
                    'payload' => ['_delete' => true],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertNull(Judoka::find($judoka->id));
    }

    #[Test]
    public function sync_receive_handles_create_action(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => 99999,
                    'action' => 'create',
                    'payload' => [
                        'toernooi_id' => $toernooi->id,
                        'club_id' => $club->id,
                        'naam' => 'New Judoka',
                        'geboortejaar' => 2018,
                        'geslacht' => 'M',
                        'band' => 'wit',
                        'gewicht' => 25.0,
                        'leeftijdsklasse' => "mini's",
                        'gewichtsklasse' => '-28',
                        'aanwezigheid' => 'onbekend',
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertNotNull(Judoka::where('naam', 'New Judoka')->first());
    }

    #[Test]
    public function sync_receive_reports_errors_for_missing_records(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => 999999,
                    'action' => 'update',
                    'payload' => ['naam' => 'Does Not Exist'],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('1', $data['errors']);
    }

    #[Test]
    public function sync_receive_handles_wedstrijden_table(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id]);
        $judokaWit = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
        ]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();

        $request = new \Illuminate\Http\Request();
        $request->replace([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'wedstrijden',
                    'record_id' => $wedstrijd->id,
                    'action' => 'update',
                    'payload' => [
                        'is_gespeeld' => true,
                        'winnaar_id' => $judokaWit->id,
                        'uitslag_type' => 'ippon',
                        'local_updated_at' => now()->addHour()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertTrue($wedstrijd->fresh()->is_gespeeld);
    }

    // ========================================================================
    // ToernooiApiController — actief (direct call, no route registered)
    // ========================================================================

    #[Test]
    public function toernooi_api_actief_returns_active_tournament(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'is_actief' => true,
        ]);

        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->actief();
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($toernooi->id, $data['toernooi']['id']);
        $this->assertEquals($toernooi->naam, $data['toernooi']['naam']);
    }

    #[Test]
    public function toernooi_api_actief_returns_404_when_no_active_tournament(): void
    {
        // No tournaments at all
        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->actief();

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
    }

    #[Test]
    public function toernooi_api_statistieken_returns_stats(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        Judoka::factory()->count(3)->create([
            'toernooi_id' => $toernooi->id,
            'aanwezigheid' => 'aanwezig',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'aanwezigheid' => 'afwezig',
        ]);

        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->statistieken($toernooi);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(4, $data['statistieken']['totaal_judokas']);
        $this->assertEquals(3, $data['statistieken']['aanwezig']);
        $this->assertEquals(1, $data['statistieken']['afwezig']);
    }

    #[Test]
    public function toernooi_api_blokken_returns_blocks(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        Blok::factory()->count(2)->create(['toernooi_id' => $toernooi->id]);

        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->blokken($toernooi);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['blokken']);
        $this->assertArrayHasKey('id', $data['blokken'][0]);
        $this->assertArrayHasKey('nummer', $data['blokken'][0]);
        $this->assertArrayHasKey('naam', $data['blokken'][0]);
    }

    #[Test]
    public function toernooi_api_matten_returns_mats(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        Mat::create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Mat::create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->matten($toernooi);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['matten']);
        $this->assertArrayHasKey('id', $data['matten'][0]);
        $this->assertArrayHasKey('nummer', $data['matten'][0]);
        $this->assertArrayHasKey('naam', $data['matten'][0]);
    }

    #[Test]
    public function toernooi_api_blokken_returns_empty_for_no_blocks(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $controller = app(\App\Http\Controllers\Api\ToernooiApiController::class);
        $response = $controller->blokken($toernooi);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['blokken']);
    }

    #[Test]
    public function sync_export_returns_empty_arrays_for_empty_tournament(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $controller = new \App\Http\Controllers\Api\SyncApiController();
        $response = $controller->export($toernooi);
        $data = $response->getData(true);

        $this->assertEmpty($data['clubs']);
        $this->assertEmpty($data['blokken']);
        $this->assertEmpty($data['matten']);
        $this->assertEmpty($data['judokas']);
        $this->assertEmpty($data['poules']);
        $this->assertEmpty($data['wedstrijden']);
    }
}
