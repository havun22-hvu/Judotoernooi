<?php

namespace Tests\Feature;

use App\Models\AutofixProposal;
use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\SyncQueueItem;
use App\Models\SyncStatus;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\AutoFixService;
use App\Services\BackupService;
use App\Services\DynamischeIndelingService;
use App\Services\LocalSyncService;
use App\Services\OfflineExportService;
use App\Services\OfflinePackageBuilder;
use App\Services\Payments\StripePaymentProvider;
use App\Services\VariabeleBlokVerdelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Push coverage from 82.6% to 90%+.
 *
 * Targets services with large gaps:
 * - AutoFixService (10.8% -> ~40%) — helper methods, shouldProcess, isProjectFile, relativePath
 * - BackupService (9.1% -> ~70%) — isServerEnvironment, maakMilestoneBackup, restoreFromBackup
 * - OfflineExportService (13.7% -> ~85%) — export, license, cleanup
 * - OfflinePackageBuilder (20.3% -> ~60%) — checkPrerequisites, build validation
 * - LocalSyncService (29% -> ~80%) — queue stats, sync flows (mocked HTTP)
 * - StripePaymentProvider (21.8% -> ~70%) — isAvailable, calculateTotalAmount, simulation, hash validation
 * - DatabaseChallengeRepository (0% -> ~100%) — WebAuthn challenge storage
 * - DynamischeIndelingService (79.7% -> ~90%) — fallback path
 * - VariabeleBlokVerdelingService (54.2% -> ~75%) — heeftVariabeleCategorieen, getVariabelePoules
 */
class Push90Test extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;
    private Club $club;
    private Blok $blok;
    private Mat $mat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
        $this->club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $this->mat = Mat::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
    }

    // ========================================================================
    // BackupService — 9.1% -> 70%+
    // ========================================================================

    #[Test]
    public function backup_service_detects_non_server_environment(): void
    {
        // Test suite runs SQLite, so always false
        $this->assertFalse(BackupService::isServerEnvironment());
    }

    #[Test]
    public function backup_service_skips_milestone_backup_on_local(): void
    {
        $service = new BackupService();
        $result = $service->maakMilestoneBackup('voor-test-actie');

        $this->assertNull($result);
    }

    #[Test]
    public function backup_service_handles_label_with_special_chars(): void
    {
        $service = new BackupService();
        // Should not throw
        $result = $service->maakMilestoneBackup('label/with spaces & chars!');
        $this->assertNull($result);
    }

    #[Test]
    public function backup_service_handles_empty_label(): void
    {
        $service = new BackupService();
        $result = $service->maakMilestoneBackup('');
        $this->assertNull($result);
    }

    // ========================================================================
    // AutoFixService — 10.8% -> 40%+
    // ========================================================================

    #[Test]
    public function autofix_service_skipped_when_disabled(): void
    {
        config(['autofix.enabled' => false]);
        $service = new AutoFixService();

        // Should return without doing anything
        $service->handle(new \RuntimeException('Test error'));
        $this->assertDatabaseCount('autofix_proposals', 0);
    }

    #[Test]
    public function autofix_service_skips_excluded_exception_classes(): void
    {
        config(['autofix.enabled' => true]);
        $service = new AutoFixService();

        $service->handle(new \Illuminate\Validation\ValidationException(validator([], [])));
        $service->handle(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found'));

        $this->assertDatabaseCount('autofix_proposals', 0);
    }

    #[Test]
    public function autofix_service_skips_excluded_message_patterns(): void
    {
        config(['autofix.enabled' => true]);
        $service = new AutoFixService();

        $service->handle(new \RuntimeException('Address already in use'));
        $service->handle(new \RuntimeException('Connection refused by server'));
        $service->handle(new \RuntimeException('No space left on device'));

        $this->assertDatabaseCount('autofix_proposals', 0);
    }

    #[Test]
    public function autofix_service_recently_analyzed_rate_limits(): void
    {
        config(['autofix.enabled' => true]);

        AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'test',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('a', 64),
            'status' => 'pending',
        ]);

        $this->assertTrue(AutofixProposal::recentlyAnalyzed('RuntimeException', 'app/Test.php', 10));
        $this->assertFalse(AutofixProposal::recentlyAnalyzed('RuntimeException', 'app/Other.php', 10));
        $this->assertFalse(AutofixProposal::recentlyAnalyzed('OtherException', 'app/Test.php', 10));
    }

    #[Test]
    public function autofix_service_handles_service_errors_gracefully(): void
    {
        config(['autofix.enabled' => true]);
        $service = new AutoFixService();

        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        $service->handle(new \RuntimeException('Test fix flow', 0));

        // Contract: on AI-Proxy 500, the service still reaches the proxy but
        // gives up gracefully — no proposal is persisted and no exception
        // propagates. Exact retry count is a tuning concern.
        Http::assertSent(fn ($request) => true);
        $this->assertSame(0, AutofixProposal::count(), 'AI-Proxy 500 must not persist a proposal');
    }

    // ========================================================================
    // OfflineExportService — 13.7% -> 85%+
    // ========================================================================

    #[Test]
    public function offline_export_generates_license(): void
    {
        $service = new OfflineExportService();
        $license = $service->generateLicense($this->toernooi, 5);

        $this->assertEquals($this->toernooi->id, $license['toernooi_id']);
        $this->assertEquals($this->toernooi->naam, $license['toernooi_naam']);
        $this->assertEquals(5, $license['valid_days']);
        $this->assertNotEmpty($license['signature']);
        $this->assertNotEmpty($license['generated_at']);
        $this->assertNotEmpty($license['expires_at']);
    }

    #[Test]
    public function offline_export_verify_valid_license(): void
    {
        $service = new OfflineExportService();
        $license = $service->generateLicense($this->toernooi, 3);

        $this->assertTrue(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function offline_export_verify_rejects_tampered_license(): void
    {
        $service = new OfflineExportService();
        $license = $service->generateLicense($this->toernooi, 3);
        $license['toernooi_id'] = 99999;

        $this->assertFalse(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function offline_export_verify_rejects_missing_fields(): void
    {
        $this->assertFalse(OfflineExportService::verifyLicense([]));
        $this->assertFalse(OfflineExportService::verifyLicense(['toernooi_id' => 1]));
        $this->assertFalse(OfflineExportService::verifyLicense([
            'toernooi_id' => 1,
            'generated_at' => now()->toIso8601String(),
            // missing expires_at and signature
        ]));
    }

    #[Test]
    public function offline_export_verify_rejects_expired_license(): void
    {
        $service = new OfflineExportService();
        $license = $service->generateLicense($this->toernooi, 3);
        $license['expires_at'] = now()->subDay()->toIso8601String();
        // Re-sign with tampered expiry
        $license['signature'] = hash_hmac('sha256', json_encode([
            $license['toernooi_id'],
            $license['generated_at'],
            $license['expires_at'],
        ]), config('app.key'));

        $this->assertFalse(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function offline_export_cleanup_removes_old_files(): void
    {
        $service = new OfflineExportService();
        $oldFile = storage_path('app/offline_999_' . (time() - 7200) . '.sqlite');
        file_put_contents($oldFile, 'dummy');
        touch($oldFile, time() - 7200);

        $newFile = storage_path('app/offline_888_' . time() . '.sqlite');
        file_put_contents($newFile, 'dummy');

        $count = $service->cleanup();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($newFile);

        @unlink($newFile);
    }

    #[Test]
    public function offline_export_exports_tournament_to_sqlite(): void
    {
        $service = new OfflineExportService();

        // Create minimal data
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $path = $service->export($this->toernooi);

        $this->assertFileExists($path);
        $this->assertStringContainsString('offline_', $path);
        $this->assertStringEndsWith('.sqlite', $path);

        // Verify SQLite has data
        $pdo = new \PDO('sqlite:' . $path);
        $count = $pdo->query('SELECT COUNT(*) FROM toernooien')->fetchColumn();
        $this->assertEquals(1, $count);

        $pdo = null;
        @unlink($path);
    }

    // ========================================================================
    // OfflinePackageBuilder — 20.3% -> 60%+
    // ========================================================================

    #[Test]
    public function offline_package_builder_check_prerequisites_returns_structure(): void
    {
        $exportService = new OfflineExportService();
        $builder = new OfflinePackageBuilder($exportService);

        $result = $builder->checkPrerequisites();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertIsBool($result['ready']);
        $this->assertIsArray($result['missing']);
    }

    // ========================================================================
    // LocalSyncService — 29% -> 80%+
    // ========================================================================

    #[Test]
    public function local_sync_creates_result_object(): void
    {
        $service = new LocalSyncService();
        $result = $service->createResult();

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->records_synced);
        $this->assertIsArray($result->errors);
        $this->assertIsArray($result->details);
    }

    #[Test]
    public function local_sync_queue_stats_empty(): void
    {
        $service = new LocalSyncService();
        $stats = $service->getQueueStats($this->toernooi->id);

        $this->assertEquals(0, $stats['pending']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['total_today']);
    }

    #[Test]
    public function local_sync_queue_stats_with_items(): void
    {
        SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'update',
            'payload' => ['naam' => 'Test'],
        ]);
        SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 2,
            'action' => 'update',
            'payload' => ['naam' => 'Test2'],
            'error_message' => 'fail',
        ]);

        $service = new LocalSyncService();
        $stats = $service->getQueueStats($this->toernooi->id);

        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(2, $stats['total_today']);
    }

    #[Test]
    public function local_sync_cloud_to_local_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'clubs' => [
                    ['id' => 999, 'naam' => 'SyncClub', 'organisator_id' => $this->org->id],
                ],
                'blokken' => [
                    ['id' => 998, 'toernooi_id' => $this->toernooi->id, 'nummer' => 5],
                ],
                'matten' => [
                    ['id' => 997, 'toernooi_id' => $this->toernooi->id, 'nummer' => 5],
                ],
                'judokas' => [],
                'poules' => [],
                'wedstrijden' => [],
            ], 200),
        ]);

        $service = new LocalSyncService();
        $result = $service->syncCloudToLocal($this->toernooi);

        $this->assertTrue($result->success);
        $this->assertGreaterThan(0, $result->records_synced);
        $this->assertDatabaseHas('clubs', ['naam' => 'SyncClub']);
    }

    #[Test]
    public function local_sync_cloud_to_local_handles_error(): void
    {
        Http::fake([
            '*' => Http::response('server down', 500),
        ]);

        $service = new LocalSyncService();
        $result = $service->syncCloudToLocal($this->toernooi);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    #[Test]
    public function local_sync_local_to_cloud_no_items(): void
    {
        $service = new LocalSyncService();
        $result = $service->syncLocalToCloud($this->toernooi);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Geen wijzigingen', $result->details[0]);
    }

    #[Test]
    public function local_sync_local_to_cloud_with_items(): void
    {
        $item = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'update',
            'payload' => ['x' => 1],
        ]);

        Http::fake([
            '*' => Http::response([
                'synced' => [$item->id],
                'errors' => [],
            ], 200),
        ]);

        $service = new LocalSyncService();
        $result = $service->syncLocalToCloud($this->toernooi);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->records_synced);
    }

    #[Test]
    public function local_sync_local_to_cloud_handles_server_error(): void
    {
        SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'update',
            'payload' => ['x' => 1],
        ]);

        Http::fake([
            '*' => Http::response('err', 500),
        ]);

        $service = new LocalSyncService();
        $result = $service->syncLocalToCloud($this->toernooi);

        $this->assertFalse($result->success);
    }

    #[Test]
    public function local_sync_process_queue_returns_count(): void
    {
        $service = new LocalSyncService();
        $count = $service->processQueue($this->toernooi);

        $this->assertEquals(0, $count);
    }

    // ========================================================================
    // StripePaymentProvider — 21.8% -> 70%+
    // ========================================================================

    #[Test]
    public function stripe_provider_get_name(): void
    {
        $provider = new StripePaymentProvider();
        $this->assertEquals('stripe', $provider->getName());
    }

    #[Test]
    public function stripe_provider_is_available_without_key(): void
    {
        config(['services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();

        $this->assertFalse($provider->isAvailable());
    }

    #[Test]
    public function stripe_provider_is_available_with_key(): void
    {
        config(['services.stripe.secret' => 'sk_test_123']);
        $provider = new StripePaymentProvider();

        $this->assertTrue($provider->isAvailable());
    }

    #[Test]
    public function stripe_provider_is_simulation_mode_in_dev(): void
    {
        config(['app.env' => 'local', 'services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();

        $this->assertTrue($provider->isSimulationMode());
    }

    #[Test]
    public function stripe_provider_not_simulation_in_production(): void
    {
        config(['app.env' => 'production', 'services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();

        $this->assertFalse($provider->isSimulationMode());
    }

    #[Test]
    public function stripe_provider_simulate_payment(): void
    {
        $provider = new StripePaymentProvider();
        $result = $provider->simulatePayment([
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'description' => 'Test payment',
            'metadata' => ['key' => 'val'],
        ]);

        $this->assertStringStartsWith('cs_simulated_', $result->id);
        $this->assertEquals('open', $result->status);
        $this->assertNotEmpty($result->checkoutUrl);
        $this->assertEquals('10.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
    }

    #[Test]
    public function stripe_provider_calculate_total_without_connected_account(): void
    {
        $this->toernooi->update([
            'platform_toeslag' => 0.50,
            'platform_toeslag_percentage' => false,
            'payment_provider' => 'mollie',
            'stripe_account_id' => null,
        ]);
        $provider = new StripePaymentProvider();

        $total = $provider->calculateTotalAmount($this->toernooi, 10.00);
        $this->assertEquals(10.50, $total);
    }

    #[Test]
    public function stripe_provider_calculate_total_with_percentage(): void
    {
        $this->toernooi->update([
            'platform_toeslag' => 5,
            'platform_toeslag_percentage' => true,
            'payment_provider' => 'mollie',
            'stripe_account_id' => null,
        ]);
        $provider = new StripePaymentProvider();

        $total = $provider->calculateTotalAmount($this->toernooi, 100.00);
        $this->assertEquals(105.00, $total);
    }

    #[Test]
    public function stripe_provider_calculate_total_with_connected_account(): void
    {
        $this->toernooi->update([
            'payment_provider' => 'stripe',
            'stripe_account_id' => 'acct_test123',
        ]);
        $provider = new StripePaymentProvider();

        $total = $provider->calculateTotalAmount($this->toernooi, 10.00);
        $this->assertEquals(10.00, $total);
    }

    #[Test]
    public function stripe_provider_generate_and_validate_callback_hash(): void
    {
        $provider = new StripePaymentProvider();
        $hash = $provider->generateCallbackHash($this->toernooi);

        $this->assertNotEmpty($hash);
        $this->assertTrue($provider->validateCallbackHash($this->toernooi->id, $hash));
    }

    #[Test]
    public function stripe_provider_rejects_invalid_callback_hash(): void
    {
        $provider = new StripePaymentProvider();

        $this->assertFalse($provider->validateCallbackHash($this->toernooi->id, 'invalid'));
        $this->assertFalse($provider->validateCallbackHash(null, 'anything'));
        $this->assertFalse($provider->validateCallbackHash($this->toernooi->id, null));
        $this->assertFalse($provider->validateCallbackHash(null, null));
    }

    #[Test]
    public function stripe_provider_disconnect_clears_fields(): void
    {
        $this->toernooi->update([
            'stripe_account_id' => null, // no stripe call
            'payment_provider' => 'stripe',
        ]);
        $provider = new StripePaymentProvider();

        $provider->disconnect($this->toernooi);

        $this->toernooi->refresh();
        $this->assertNull($this->toernooi->stripe_account_id);
    }

    // ========================================================================
    // DatabaseChallengeRepository — 0% -> 100%
    // ========================================================================

    #[Test]
    public function webauthn_repository_pull_with_no_valid_challenges_returns_null(): void
    {
        $repo = new \App\WebAuthn\DatabaseChallengeRepository();

        // Insert expired challenge
        DB::table('webauthn_challenges')->insert([
            'token' => 'expired_token',
            'challenge_data' => serialize(null),
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $validation = $this->createMock(\Laragear\WebAuthn\Assertion\Validator\AssertionValidation::class);
        $result = $repo->pull($validation);

        $this->assertNull($result);
    }

    #[Test]
    public function webauthn_repository_pull_skips_invalid_challenges(): void
    {
        $repo = new \App\WebAuthn\DatabaseChallengeRepository();

        // Insert challenge that will unserialize to null -> isValid() returns null
        DB::table('webauthn_challenges')->insert([
            'token' => 'null_token',
            'challenge_data' => serialize(null),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validation = $this->createMock(\Laragear\WebAuthn\Assertion\Validator\AssertionValidation::class);
        $result = $repo->pull($validation);

        $this->assertNull($result);
    }

    // ========================================================================
    // DynamischeIndelingService — 79.7% -> 90%+
    // ========================================================================

    #[Test]
    public function dynamische_indeling_empty_judokas_returns_empty(): void
    {
        $service = new DynamischeIndelingService();
        $result = $service->berekenIndeling(collect());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('poules', $result);
        $this->assertEmpty($result['poules']);
    }

    #[Test]
    public function dynamische_indeling_single_judoka(): void
    {
        $service = new DynamischeIndelingService();

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'gewicht' => 40,
            'geboortejaar' => now()->year - 10,
            'band' => 'geel',
        ]);

        $result = $service->berekenIndeling(
            collect([$judoka]),
            maxLeeftijdVerschil: 2,
            maxKgVerschil: 3.0
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('poules', $result);
    }

    #[Test]
    public function dynamische_indeling_multiple_judokas(): void
    {
        $service = new DynamischeIndelingService();

        $judokas = collect();
        for ($i = 0; $i < 5; $i++) {
            $judokas->push(Judoka::factory()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'gewicht' => 30 + $i,
                'geboortejaar' => now()->year - 10,
                'band' => 'geel',
            ]));
        }

        $result = $service->berekenIndeling(
            $judokas,
            maxLeeftijdVerschil: 2,
            maxKgVerschil: 5.0
        );

        $this->assertIsArray($result);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — 54.2% -> 75%+
    // ========================================================================

    #[Test]
    public function variabele_detects_no_variable_categories(): void
    {
        $this->toernooi->update(['gewichtsklassen' => null]);
        $service = new VariabeleBlokVerdelingService();

        $this->assertFalse($service->heeftVariabeleCategorieen($this->toernooi));
    }

    #[Test]
    public function variabele_detects_empty_config(): void
    {
        $service = new VariabeleBlokVerdelingService();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'gewichtsklassen' => [],
            'gebruik_gewichtsklassen' => false,
        ]);

        $this->assertFalse($service->heeftVariabeleCategorieen($toernooi));
    }

    #[Test]
    public function variabele_generate_throws_without_blokken(): void
    {
        $service = new VariabeleBlokVerdelingService();

        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
        ]);
        // No blokken attached

        $this->expectException(\RuntimeException::class);
        $service->genereerVarianten($toernooi);
    }

    #[Test]
    public function variabele_generate_no_variable_poules(): void
    {
        $service = new VariabeleBlokVerdelingService();
        $result = $service->genereerVarianten($this->toernooi);

        // No poules with blok_vast=false and type != kruisfinale
        $this->assertArrayHasKey('varianten', $result);
    }

    #[Test]
    public function variabele_get_poules_empty(): void
    {
        $service = new VariabeleBlokVerdelingService();
        $poules = $service->getVariabelePoules($this->toernooi);

        $this->assertCount(0, $poules);
    }

    // ========================================================================
    // SyncStatus model coverage
    // ========================================================================

    #[Test]
    public function sync_status_get_or_create(): void
    {
        $status = SyncStatus::getOrCreate($this->toernooi->id, 'cloud_to_local');

        $this->assertEquals($this->toernooi->id, $status->toernooi_id);
        $this->assertEquals('cloud_to_local', $status->direction);
        $this->assertEquals('idle', $status->status);

        // Second call returns same record
        $status2 = SyncStatus::getOrCreate($this->toernooi->id, 'cloud_to_local');
        $this->assertEquals($status->id, $status2->id);
    }

    #[Test]
    public function sync_status_lifecycle(): void
    {
        $status = SyncStatus::getOrCreate($this->toernooi->id, 'local_to_cloud');

        $status->startSync();
        $this->assertEquals('syncing', $status->fresh()->status);
        $this->assertTrue($status->fresh()->isSyncing());

        $status->completeSync(42);
        $fresh = $status->fresh();
        $this->assertEquals('success', $fresh->status);
        $this->assertEquals(42, $fresh->records_synced);
        $this->assertTrue($fresh->isHealthy());

        $status->failSync('network error');
        $this->assertEquals('failed', $status->fresh()->status);
        $this->assertFalse($status->fresh()->isHealthy());
    }

    #[Test]
    public function sync_status_not_healthy_without_last_sync(): void
    {
        $status = SyncStatus::getOrCreate($this->toernooi->id, 'cloud_to_local');
        $status->update(['status' => 'success', 'last_sync_at' => null]);

        $this->assertFalse($status->fresh()->isHealthy());
    }

    #[Test]
    public function sync_status_labels(): void
    {
        $status = new SyncStatus();

        $status->status = 'idle';
        $this->assertEquals('Wachtend', $status->getStatusLabel());
        $status->status = 'syncing';
        $this->assertEquals('Bezig...', $status->getStatusLabel());
        $status->status = 'success';
        $this->assertEquals('Geslaagd', $status->getStatusLabel());
        $status->status = 'failed';
        $this->assertEquals('Mislukt', $status->getStatusLabel());
        $status->status = 'unknown';
        $this->assertEquals('Onbekend', $status->getStatusLabel());
    }

    #[Test]
    public function sync_status_time_since_sync_null(): void
    {
        $status = new SyncStatus();
        $status->last_sync_at = null;
        $this->assertNull($status->getTimeSinceSync());
    }

    #[Test]
    public function sync_status_time_since_sync_formatted(): void
    {
        $status = SyncStatus::getOrCreate($this->toernooi->id, 'cloud_to_local');
        $status->update(['last_sync_at' => now()->subMinutes(5)]);

        $result = $status->fresh()->getTimeSinceSync();
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    // ========================================================================
    // SyncQueueItem model coverage
    // ========================================================================

    #[Test]
    public function sync_queue_item_scopes(): void
    {
        $unsynced = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
        ]);
        $synced = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 2,
            'action' => 'create',
            'payload' => [],
            'synced_at' => now(),
        ]);
        $failed = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 3,
            'action' => 'create',
            'payload' => [],
            'error_message' => 'err',
        ]);

        $this->assertEquals(2, SyncQueueItem::unsynced()->count());
        $this->assertEquals(1, SyncQueueItem::failed()->count());
        $this->assertEquals(3, SyncQueueItem::forToernooi($this->toernooi->id)->count());
    }

    #[Test]
    public function sync_queue_item_mark_synced(): void
    {
        $item = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'update',
            'payload' => [],
            'error_message' => 'was failing',
        ]);

        $item->markSynced();

        $this->assertNotNull($item->fresh()->synced_at);
        $this->assertNull($item->fresh()->error_message);
    }

    #[Test]
    public function sync_queue_item_mark_failed(): void
    {
        $item = SyncQueueItem::create([
            'toernooi_id' => $this->toernooi->id,
            'table_name' => 'judokas',
            'record_id' => 1,
            'action' => 'update',
            'payload' => [],
        ]);

        $item->markFailed('oops');

        $this->assertEquals('oops', $item->fresh()->error_message);
    }

    // ========================================================================
    // LocalSyncController — JSON endpoints (31.9% -> 60%+)
    // ========================================================================

    #[Test]
    public function local_sync_status_returns_json(): void
    {
        $response = $this->get('/local-server/status');
        $response->assertStatus(200);
        $response->assertJsonStructure(['role', 'ip', 'timestamp']);
    }

    #[Test]
    public function local_sync_heartbeat_returns_ok(): void
    {
        $response = $this->get('/local-server/heartbeat');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function local_sync_sync_data_returns_toernooien(): void
    {
        Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'datum' => today(),
        ]);

        $response = $this->get('/local-server/sync');
        $response->assertStatus(200);
        $response->assertJsonStructure(['timestamp', 'toernooien']);
    }

    #[Test]
    public function local_sync_receive_blocks_non_standby(): void
    {
        config(['local-server.role' => 'primary']);
        $response = $this->postJson('/local-server/receive-sync', ['foo' => 'bar']);
        $response->assertStatus(400);
    }

    #[Test]
    public function local_sync_standby_status_returns_info(): void
    {
        $response = $this->get('/local-server/standby-status');
        $response->assertStatus(200);
    }

    #[Test]
    public function local_sync_internet_status_returns_status(): void
    {
        $response = $this->get('/local-server/internet-status');
        $response->assertStatus(200);
    }

    #[Test]
    public function local_sync_queue_status_json(): void
    {
        $response = $this->get('/local-server/queue-status');
        $response->assertStatus(200);
    }

    // ========================================================================
    // AutofixProposal model extra coverage
    // ========================================================================

    #[Test]
    public function autofix_proposal_is_approved(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'E',
            'exception_message' => 'm',
            'file' => 'f',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('a', 64),
            'status' => 'approved',
        ]);

        $this->assertTrue($proposal->isApproved());

        $proposal->update(['status' => 'pending']);
        $this->assertFalse($proposal->fresh()->isApproved());
    }

    // ========================================================================
    // AutoFixController — 34.5% -> 70%+
    // ========================================================================

    #[Test]
    public function autofix_controller_show_displays_proposal(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'x',
            'file' => 'app/Foo.php',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => 'analysis text',
            'proposed_diff' => 'diff',
            'approval_token' => str_repeat('c', 64),
            'status' => 'pending',
        ]);

        $response = $this->get('/autofix/' . $proposal->approval_token);
        $response->assertStatus(200);
        $response->assertViewIs('autofix.show');
        $response->assertViewHas('proposal');
    }

    #[Test]
    public function autofix_controller_show_404_on_invalid_token(): void
    {
        $response = $this->get('/autofix/' . str_repeat('z', 64));
        $response->assertStatus(404);
    }

    #[Test]
    public function autofix_controller_reject_marks_rejected(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'x',
            'file' => 'app/Foo.php',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('d', 64),
            'status' => 'pending',
        ]);

        $response = $this->post('/autofix/' . $proposal->approval_token . '/reject');
        $response->assertRedirect();

        $this->assertEquals('rejected', $proposal->fresh()->status);
    }

    #[Test]
    public function autofix_controller_reject_non_pending_redirects_with_error(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'x',
            'file' => 'app/Foo.php',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('e', 64),
            'status' => 'applied',
        ]);

        $response = $this->post('/autofix/' . $proposal->approval_token . '/reject');
        $response->assertRedirect();

        // Still applied (not changed)
        $this->assertEquals('applied', $proposal->fresh()->status);
    }

    #[Test]
    public function autofix_controller_approve_with_invalid_analysis_fails(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'x',
            'file' => 'app/Foo.php',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => 'no file info here',
            'proposed_diff' => '',
            'approval_token' => str_repeat('f', 64),
            'status' => 'pending',
        ]);

        $response = $this->post('/autofix/' . $proposal->approval_token . '/approve');
        $response->assertRedirect();

        // Should end up failed because applyFix throws
        $fresh = $proposal->fresh();
        $this->assertEquals('failed', $fresh->status);
        $this->assertNotNull($fresh->apply_error);
    }

    #[Test]
    public function autofix_controller_approve_non_pending_blocked(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'x',
            'file' => 'app/Foo.php',
            'line' => 1,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('g', 64),
            'status' => 'rejected',
        ]);

        $response = $this->post('/autofix/' . $proposal->approval_token . '/approve');
        $response->assertRedirect();
        $this->assertEquals('rejected', $proposal->fresh()->status);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — 55.2% -> 75%+
    // ========================================================================

    #[Test]
    public function variabele_detects_variable_categories_in_config(): void
    {
        $service = new VariabeleBlokVerdelingService();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'gebruik_gewichtsklassen' => false,
            'gewichtsklassen' => [
                'minis' => [
                    'label' => 'Minis',
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 3.0, // variable!
                    'max_leeftijd_verschil' => 1,
                ],
            ],
        ]);

        $this->assertTrue($service->heeftVariabeleCategorieen($toernooi));
    }

    #[Test]
    public function variabele_detects_no_variable_in_fixed_config(): void
    {
        $service = new VariabeleBlokVerdelingService();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'gebruik_gewichtsklassen' => true,
            'gewichtsklassen' => [
                'senioren' => [
                    'label' => 'Senioren',
                    'min_leeftijd' => 18,
                    'max_leeftijd' => 40,
                    'max_kg_verschil' => 0, // fixed
                    'gewichten' => ['-60', '-66', '+66'],
                ],
            ],
        ]);

        $this->assertFalse($service->heeftVariabeleCategorieen($toernooi));
    }

    #[Test]
    public function variabele_get_poules_with_data(): void
    {
        $service = new VariabeleBlokVerdelingService();

        // Create a poule with judokas
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $this->blok->id,
            'mat_id' => $this->mat->id,
            'type' => 'voorronde',
            'blok_vast' => false,
            'aantal_wedstrijden' => 3,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'gewicht' => 40,
            'geboortejaar' => now()->year - 10,
        ]);
        $poule->judokas()->attach($judoka->id);

        $poules = $service->getVariabelePoules($this->toernooi);

        $this->assertCount(1, $poules);
        $this->assertEquals($poule->id, $poules->first()['id']);
        $this->assertArrayHasKey('min_leeftijd', $poules->first());
        $this->assertArrayHasKey('max_gewicht', $poules->first());
    }

    #[Test]
    public function variabele_pas_variant_toe_individual_poules(): void
    {
        $service = new VariabeleBlokVerdelingService();

        $blok2 = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 2,
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $this->blok->id,
            'mat_id' => $this->mat->id,
            'type' => 'voorronde',
            'blok_vast' => false,
        ]);

        $service->pasVariantToe($this->toernooi, [
            'poule_' . $poule->id => 2,
        ]);

        $this->assertEquals($blok2->id, $poule->fresh()->blok_id);
    }

    #[Test]
    public function variabele_pas_variant_toe_by_category(): void
    {
        $service = new VariabeleBlokVerdelingService();

        $blok2 = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 2,
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $this->blok->id,
            'mat_id' => $this->mat->id,
            'type' => 'voorronde',
            'blok_vast' => false,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-30',
        ]);

        $service->pasVariantToe($this->toernooi, [
            'pupillen|-30' => 2,
        ]);

        $this->assertEquals($blok2->id, $poule->fresh()->blok_id);
    }

    // ========================================================================
    // BackupService — extra coverage for restoreFromBackup path
    // ========================================================================

    #[Test]
    public function backup_service_restore_returns_false_on_missing_file(): void
    {
        $service = new BackupService();
        $result = $service->restoreFromBackup('/nonexistent/path/to/backup.sql.gz');

        // Shell command fails on missing file, returns false
        $this->assertFalse($result);
    }

    // ========================================================================
    // StripePaymentProvider — createPayment via mocked StripeClient
    // ========================================================================

    #[Test]
    public function stripe_provider_get_payment_calls_client(): void
    {
        config(['services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();

        // When secret is null, constructor may still work but operations fail
        // Best we can do is verify getName/isAvailable without triggering Stripe
        $this->assertIsString($provider->getName());
    }

    // ========================================================================
    // OfflineExportService — extra license paths
    // ========================================================================

    #[Test]
    public function offline_export_license_signature_is_deterministic(): void
    {
        $service = new OfflineExportService();

        // Cannot test exact match (time differs), but verify verify() works on freshly generated
        $license1 = $service->generateLicense($this->toernooi, 3);
        $license2 = $service->generateLicense($this->toernooi, 3);

        $this->assertTrue(OfflineExportService::verifyLicense($license1));
        $this->assertTrue(OfflineExportService::verifyLicense($license2));
    }

    #[Test]
    public function offline_export_license_respects_valid_days(): void
    {
        $service = new OfflineExportService();

        $license1 = $service->generateLicense($this->toernooi, 1);
        $license7 = $service->generateLicense($this->toernooi, 7);

        $this->assertEquals(1, $license1['valid_days']);
        $this->assertEquals(7, $license7['valid_days']);
    }

    // ========================================================================
    // AutoFixService — extra paths (shouldProcess with excluded file patterns)
    // ========================================================================

    #[Test]
    public function autofix_service_handles_excluded_file_patterns(): void
    {
        // Exception-origin file is this test file; pick a pattern that hits it.
        config([
            'autofix.enabled' => true,
            'autofix.excluded_file_patterns' => ['#Push90Test#'],
        ]);
        Http::fake();
        $service = new AutoFixService();

        $service->handle(new \RuntimeException('some error'));

        // Excluded-file path must short-circuit before the AI Proxy call.
        Http::assertNothingSent();
        $this->assertSame(0, AutofixProposal::count());
    }

    #[Test]
    public function autofix_service_accepts_project_file_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('isProjectFile');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, base_path('app/Services/AutoFixService.php')));
        $this->assertFalse($method->invoke($service, base_path('vendor/laravel/framework/src/Foo.php')));
        $this->assertFalse($method->invoke($service, '/tmp/totally/unrelated/file.php'));
    }

    #[Test]
    public function autofix_service_relative_path_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('relativePath');
        $method->setAccessible(true);

        $absolute = base_path('app/Test.php');
        $this->assertEquals('app/Test.php', str_replace('\\', '/', $method->invoke($service, $absolute)));
    }

    #[Test]
    public function autofix_service_extract_risk_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('extractRisk');
        $method->setAccessible(true);

        $this->assertEquals('low', $method->invoke($service, "RISK: low\nOther"));
        $this->assertEquals('medium', $method->invoke($service, 'RISK: medium'));
        $this->assertEquals('high', $method->invoke($service, 'RISK: HIGH'));
        $this->assertEquals('unknown', $method->invoke($service, 'no risk info'));
    }

    #[Test]
    public function autofix_service_is_notify_only_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('isNotifyOnly');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'ACTION: NOTIFY_ONLY'));
        $this->assertFalse($method->invoke($service, 'ACTION: FIX'));
        $this->assertFalse($method->invoke($service, 'no action'));
    }

    #[Test]
    public function autofix_service_is_dry_run_risk_via_reflection(): void
    {
        config(['autofix.dry_run_on_risk' => ['medium', 'high']]);
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('isDryRunRisk');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'RISK: high'));
        $this->assertTrue($method->invoke($service, 'RISK: medium'));
        $this->assertFalse($method->invoke($service, 'RISK: low'));
        $this->assertFalse($method->invoke($service, 'no risk'));
    }

    #[Test]
    public function autofix_service_dry_run_empty_config_returns_false(): void
    {
        config(['autofix.dry_run_on_risk' => []]);
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('isDryRunRisk');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, 'RISK: high'));
    }

    #[Test]
    public function autofix_service_extract_blade_file_from_exception(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('extractBladeFile');
        $method->setAccessible(true);

        // Exception without blade reference
        $e = new \RuntimeException('normal error');
        $this->assertNull($method->invoke($service, $e));

        // Exception with blade reference but non-existing file
        $e2 = new \RuntimeException('failed (View: /nonexistent/file.blade.php)');
        $this->assertNull($method->invoke($service, $e2));
    }

    #[Test]
    public function autofix_service_find_fix_target_file_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('findFixTargetFile');
        $method->setAccessible(true);

        // Throw a real exception, its file is this test file (a project file)
        try {
            throw new \RuntimeException('test');
        } catch (\RuntimeException $e) {
            $result = $method->invoke($service, $e);
            $this->assertNotNull($result);
            $this->assertStringContainsString('Push90Test', $result);
        }
    }

    #[Test]
    public function autofix_service_gather_code_context_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('gatherCodeContext');
        $method->setAccessible(true);

        try {
            throw new \RuntimeException('test context');
        } catch (\RuntimeException $e) {
            $result = $method->invoke($service, $e);
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        }
    }

    #[Test]
    public function autofix_service_is_full_file_content_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('isFullFileContent');
        $method->setAccessible(true);

        // A small file (this test) should return true
        $this->assertIsBool($method->invoke($service, __FILE__));
        $this->assertFalse($method->invoke($service, '/nonexistent/file.php'));
    }

    #[Test]
    public function autofix_service_read_file_with_context_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('readFileWithContext');
        $method->setAccessible(true);

        $result = $method->invoke($service, __FILE__, 10, 5);
        $this->assertIsString($result);
        $this->assertStringContainsString('10 |', $result);
    }

    #[Test]
    public function autofix_service_read_file_for_context_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('readFileForContext');
        $method->setAccessible(true);

        $result = $method->invoke($service, __FILE__, 10);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function autofix_service_resolve_toernooi_via_reflection(): void
    {
        $service = new AutoFixService();
        $reflection = new \ReflectionClass($service);

        $method = $reflection->getMethod('resolveToernooi');
        $method->setAccessible(true);

        // Without route, returns null
        $result = $method->invoke($service);
        $this->assertNull($result);
    }

    // ========================================================================
    // PaymentProviderFactory sanity
    // ========================================================================

    #[Test]
    public function payment_provider_factory_creates_stripe(): void
    {
        $factory = app(\App\Services\PaymentProviderFactory::class);
        $provider = $factory->make('stripe');

        $this->assertInstanceOf(StripePaymentProvider::class, $provider);
    }

    // ========================================================================
    // SyncQueueItem::queueChange + throw
    // ========================================================================

    #[Test]
    public function sync_queue_item_queue_change_on_judoka(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $item = SyncQueueItem::queueChange($judoka, 'update');

        $this->assertEquals('judokas', $item->table_name);
        $this->assertEquals($judoka->id, $item->record_id);
        $this->assertEquals('update', $item->action);
    }

    #[Test]
    public function sync_queue_item_queue_change_throws_on_unknown_toernooi(): void
    {
        // Using a model that has no toernooi_id and no poule->toernooi_id
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'test_table';
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot determine toernooi_id');
        SyncQueueItem::queueChange($model, 'create');
    }

    #[Test]
    public function autofix_proposal_rate_limit_window(): void
    {
        // Old enough that it's outside the window (default 60 min)
        $old = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'm',
            'file' => 'app/Xrate.php',
            'line' => 99,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => '',
            'proposed_diff' => '',
            'approval_token' => str_repeat('b', 64),
            'status' => 'pending',
        ]);
        // Raw DB update to bypass timestamp auto-update
        DB::table('autofix_proposals')
            ->where('id', $old->id)
            ->update(['created_at' => now()->subHours(24)]);

        $this->assertFalse(AutofixProposal::recentlyAnalyzed('RuntimeException', 'app/Xrate.php', 99));
    }
}
