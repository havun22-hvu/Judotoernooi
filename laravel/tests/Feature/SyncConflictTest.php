<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SyncApiController;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\SyncConflict;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sync conflict detection + resolution.
 *
 * Goal: STOP losing tournament data when both sides have edited the
 * same record between syncs. Live mat data (wedstrijden) must keep
 * its local edit, config data (judokas) must keep its cloud edit,
 * and every conflict gets logged for admin review.
 */
class SyncConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function makeRequest(array $payload): Request
    {
        $request = new Request();
        $request->replace($payload);

        return $request;
    }

    #[Test]
    public function detects_conflict_when_both_sides_changed_since_last_sync(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id]);
        $judokaWit = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $judokaBlauw = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);

        // Cloud changed AFTER last sync
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
            'score_wit' => 0,
            'score_blauw' => 5,
        ]);

        $lastSyncedAt = now()->subMinutes(10)->toIso8601String();
        // Cloud record was just updated -> after last sync
        $wedstrijd->touch();

        $controller = new SyncApiController();
        $request = $this->makeRequest([
            'toernooi_id' => $toernooi->id,
            'last_synced_at' => $lastSyncedAt,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'wedstrijden',
                    'record_id' => $wedstrijd->id,
                    'action' => 'update',
                    'payload' => [
                        'score_wit' => 10,
                        'score_blauw' => 0,
                        'local_updated_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertContains(1, $data['conflicts']);
        $this->assertDatabaseHas('sync_conflicts', [
            'table_name' => 'wedstrijden',
            'record_id' => $wedstrijd->id,
            'applied_winner' => SyncConflict::WINNER_LOCAL,
        ]);
    }

    #[Test]
    public function wedstrijd_conflict_keeps_local_score(): void
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
            'score_wit' => 0,
            'score_blauw' => 7,
        ]);

        $lastSyncedAt = now()->subMinutes(10)->toIso8601String();
        $wedstrijd->touch(); // cloud changed since last sync

        $controller = new SyncApiController();
        $request = $this->makeRequest([
            'toernooi_id' => $toernooi->id,
            'last_synced_at' => $lastSyncedAt,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'wedstrijden',
                    'record_id' => $wedstrijd->id,
                    'action' => 'update',
                    'payload' => [
                        'score_wit' => 10,
                        'score_blauw' => 0,
                        'local_updated_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $controller->receive($request);

        $fresh = $wedstrijd->fresh();
        $this->assertEquals(10, $fresh->score_wit, 'Local mat score must win');
        $this->assertEquals(0, $fresh->score_blauw);
    }

    #[Test]
    public function judoka_conflict_keeps_cloud_value(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Cloud Updated Name',
        ]);

        $lastSyncedAt = now()->subMinutes(10)->toIso8601String();
        $judoka->touch(); // cloud changed after last sync

        $controller = new SyncApiController();
        $request = $this->makeRequest([
            'toernooi_id' => $toernooi->id,
            'last_synced_at' => $lastSyncedAt,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'update',
                    'payload' => [
                        'naam' => 'Stale Local Name',
                        'local_updated_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $controller->receive($request);

        $this->assertEquals('Cloud Updated Name', $judoka->fresh()->naam, 'Cloud config must win for judokas');
        $this->assertDatabaseHas('sync_conflicts', [
            'table_name' => 'judokas',
            'record_id' => $judoka->id,
            'applied_winner' => SyncConflict::WINNER_CLOUD,
        ]);
    }

    #[Test]
    public function no_conflict_when_only_local_changed(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Original',
        ]);

        // Last sync is AFTER the cloud record was updated => only local changed
        $lastSyncedAt = now()->addMinute()->toIso8601String();

        $controller = new SyncApiController();
        $request = $this->makeRequest([
            'toernooi_id' => $toernooi->id,
            'last_synced_at' => $lastSyncedAt,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'update',
                    'payload' => [
                        'naam' => 'Updated From Local',
                        'local_updated_at' => now()->addMinutes(2)->toIso8601String(),
                        'updated_at' => now()->addMinutes(2)->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertEmpty($data['conflicts']);
        $this->assertEquals('Updated From Local', $judoka->fresh()->naam);
        $this->assertDatabaseCount('sync_conflicts', 0);
    }

    #[Test]
    public function legacy_clients_without_last_synced_at_still_work(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Original',
        ]);

        $controller = new SyncApiController();
        $request = $this->makeRequest([
            'toernooi_id' => $toernooi->id,
            'items' => [
                [
                    'id' => 1,
                    'table' => 'judokas',
                    'record_id' => $judoka->id,
                    'action' => 'update',
                    'payload' => [
                        'naam' => 'Updated',
                        'local_updated_at' => now()->addMinute()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $response = $controller->receive($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEmpty($data['conflicts']);
        $this->assertDatabaseCount('sync_conflicts', 0);
    }

    #[Test]
    public function sync_conflict_winner_for_helper(): void
    {
        $this->assertEquals(SyncConflict::WINNER_LOCAL, SyncConflict::winnerFor('wedstrijden'));
        $this->assertEquals(SyncConflict::WINNER_LOCAL, SyncConflict::winnerFor('scores'));
        $this->assertEquals(SyncConflict::WINNER_CLOUD, SyncConflict::winnerFor('judokas'));
        $this->assertEquals(SyncConflict::WINNER_CLOUD, SyncConflict::winnerFor('poules'));
    }
}
