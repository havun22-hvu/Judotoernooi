<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\SyncQueueItem;
use App\Models\Toernooi;
use App\Services\LocalSyncService;
use App\Services\OfflineExportService;
use App\Services\OfflinePackageBuilder;
use App\Services\VariabeleBlokVerdelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class RemainingServicesCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Helper
    // ========================================================================

    private function callPrivate(object $service, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($service, $method);
        return $ref->invoke($service, ...$args);
    }

    // ========================================================================
    // OfflineExportService — generateLicense
    // ========================================================================

    #[Test]
    public function generate_license_returns_correct_structure(): void
    {
        $organisator = Organisator::factory()->create(['naam' => 'TestOrg']);
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'Testtoernooi',
        ]);

        $service = app(OfflineExportService::class);
        $license = $service->generateLicense($toernooi, 5);

        $this->assertArrayHasKey('toernooi_id', $license);
        $this->assertArrayHasKey('toernooi_naam', $license);
        $this->assertArrayHasKey('organisator', $license);
        $this->assertArrayHasKey('generated_at', $license);
        $this->assertArrayHasKey('expires_at', $license);
        $this->assertArrayHasKey('valid_days', $license);
        $this->assertArrayHasKey('signature', $license);
        $this->assertEquals($toernooi->id, $license['toernooi_id']);
        $this->assertEquals('Testtoernooi', $license['toernooi_naam']);
        $this->assertEquals('TestOrg', $license['organisator']);
        $this->assertEquals(5, $license['valid_days']);
    }

    #[Test]
    public function generate_license_default_valid_days_is_3(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(OfflineExportService::class);
        $license = $service->generateLicense($toernooi);

        $this->assertEquals(3, $license['valid_days']);
    }

    // ========================================================================
    // OfflineExportService — verifyLicense
    // ========================================================================

    #[Test]
    public function verify_license_accepts_valid_license(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(OfflineExportService::class);
        $license = $service->generateLicense($toernooi, 5);

        $this->assertTrue(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function verify_license_rejects_expired_license(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(OfflineExportService::class);

        $license = $service->generateLicense($toernooi, 0);
        // Manually set expires_at to the past
        $license['expires_at'] = now()->subDay()->toIso8601String();
        // Recalculate signature with the past date
        $license['signature'] = hash_hmac('sha256', json_encode([
            $license['toernooi_id'],
            $license['generated_at'],
            $license['expires_at'],
        ]), config('app.key'));

        $this->assertFalse(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function verify_license_rejects_tampered_signature(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(OfflineExportService::class);
        $license = $service->generateLicense($toernooi, 5);

        $license['signature'] = 'tampered_signature_value';

        $this->assertFalse(OfflineExportService::verifyLicense($license));
    }

    #[Test]
    public function verify_license_rejects_missing_fields(): void
    {
        $this->assertFalse(OfflineExportService::verifyLicense([]));
        $this->assertFalse(OfflineExportService::verifyLicense(['toernooi_id' => 1]));
        $this->assertFalse(OfflineExportService::verifyLicense([
            'toernooi_id' => 1,
            'generated_at' => now()->toIso8601String(),
        ]));
    }

    // ========================================================================
    // OfflineExportService — jsonOrString (private)
    // ========================================================================

    #[Test]
    public function json_or_string_returns_null_for_null(): void
    {
        $service = app(OfflineExportService::class);
        $this->assertNull($this->callPrivate($service, 'jsonOrString', [null]));
    }

    #[Test]
    public function json_or_string_encodes_array(): void
    {
        $service = app(OfflineExportService::class);
        $result = $this->callPrivate($service, 'jsonOrString', [['a' => 1, 'b' => 'test']]);
        $this->assertEquals('{"a":1,"b":"test"}', $result);
    }

    #[Test]
    public function json_or_string_passes_string_through(): void
    {
        $service = app(OfflineExportService::class);
        $result = $this->callPrivate($service, 'jsonOrString', ['hello']);
        $this->assertEquals('hello', $result);
    }

    #[Test]
    public function json_or_string_casts_int_to_string(): void
    {
        $service = app(OfflineExportService::class);
        $result = $this->callPrivate($service, 'jsonOrString', [42]);
        $this->assertEquals('42', $result);
    }

    // ========================================================================
    // OfflinePackageBuilder — checkPrerequisites
    // ========================================================================

    #[Test]
    public function check_prerequisites_returns_expected_structure(): void
    {
        $exportService = app(OfflineExportService::class);
        $builder = new OfflinePackageBuilder($exportService);

        $result = $builder->checkPrerequisites();

        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertIsBool($result['ready']);
        $this->assertIsArray($result['missing']);

        // If ready, missing should be empty; if not ready, missing should have items
        if ($result['ready']) {
            $this->assertEmpty($result['missing']);
        } else {
            $this->assertNotEmpty($result['missing']);
        }
    }

    #[Test]
    public function check_prerequisites_detects_missing_launcher(): void
    {
        // Use reflection to set a non-existent launcher path
        $exportService = app(OfflineExportService::class);
        $builder = new OfflinePackageBuilder($exportService);

        $ref = new \ReflectionProperty(OfflinePackageBuilder::class, 'launcherPath');
        $ref->setValue($builder, '/non/existent/path/launcher.exe');

        $result = $builder->checkPrerequisites();

        $this->assertFalse($result['ready']);
        $this->assertNotEmpty($result['missing']);
    }

    // ========================================================================
    // LocalSyncService — createResult
    // ========================================================================

    #[Test]
    public function create_result_returns_default_structure(): void
    {
        $service = app(LocalSyncService::class);
        $result = $service->createResult();

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->records_synced);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->details);
    }

    // ========================================================================
    // LocalSyncService — getQueueStats
    // ========================================================================

    #[Test]
    public function get_queue_stats_returns_counts(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(LocalSyncService::class);

        $stats = $service->getQueueStats($toernooi->id);

        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('total_today', $stats);
        $this->assertEquals(0, $stats['pending']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['total_today']);
    }

    #[Test]
    public function get_queue_stats_counts_pending_items(): void
    {
        $toernooi = Toernooi::factory()->create();

        // Create unsynced items
        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'update',
            'payload' => ['score_wit' => 10],
        ]);
        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 2,
            'action' => 'update',
            'payload' => ['score_blauw' => 7],
        ]);

        $service = app(LocalSyncService::class);
        $stats = $service->getQueueStats($toernooi->id);

        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(2, $stats['total_today']);
    }

    // ========================================================================
    // LocalSyncService — syncLocalToCloud with empty queue
    // ========================================================================

    #[Test]
    public function sync_local_to_cloud_with_empty_queue_returns_no_changes(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(LocalSyncService::class);

        $result = $service->syncLocalToCloud($toernooi);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->records_synced);
        $this->assertContains('Geen wijzigingen om te synchroniseren', $result->details);
    }

    // ========================================================================
    // LocalSyncService — syncCloudToLocal with HTTP fake
    // ========================================================================

    #[Test]
    public function sync_cloud_to_local_handles_http_failure(): void
    {
        $toernooi = Toernooi::factory()->create();
        $service = app(LocalSyncService::class);

        Http::fake([
            '*/api/sync/export/*' => Http::response(null, 500),
        ]);

        $result = $service->syncCloudToLocal($toernooi);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — heeftVariabeleCategorieen
    // ========================================================================

    #[Test]
    public function heeft_variabele_categorieen_true_with_max_kg_verschil(): void
    {
        $toernooi = Toernooi::factory()->dynamischeKlassen()->create();
        $service = app(VariabeleBlokVerdelingService::class);

        $this->assertTrue($service->heeftVariabeleCategorieen($toernooi));
    }

    #[Test]
    public function heeft_variabele_categorieen_false_with_fixed_classes(): void
    {
        $toernooi = Toernooi::factory()->vasteKlassen()->create();
        $service = app(VariabeleBlokVerdelingService::class);

        $this->assertFalse($service->heeftVariabeleCategorieen($toernooi));
    }

    #[Test]
    public function heeft_variabele_categorieen_false_with_empty_config(): void
    {
        $toernooi = Toernooi::factory()->create(['gewichtsklassen' => null]);
        $service = app(VariabeleBlokVerdelingService::class);

        $this->assertFalse($service->heeftVariabeleCategorieen($toernooi));
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — extractLabelPrefix (private)
    // ========================================================================

    #[Test]
    public function extract_label_prefix_returns_m_from_gendered(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $this->assertEquals('M', $this->callPrivate($service, 'extractLabelPrefix', ['M 9-10j']));
        $this->assertEquals('V', $this->callPrivate($service, 'extractLabelPrefix', ['V 8-12j']));
    }

    #[Test]
    public function extract_label_prefix_returns_multi_word(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $result = $this->callPrivate($service, 'extractLabelPrefix', ['Beginners Open 9-10j']);
        $this->assertEquals('Beginners Open', $result);
    }

    #[Test]
    public function extract_label_prefix_returns_null_when_starts_with_number(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $this->assertNull($this->callPrivate($service, 'extractLabelPrefix', ['9-10j 28-32kg']));
    }

    #[Test]
    public function extract_label_prefix_returns_full_string_without_numbers(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $result = $this->callPrivate($service, 'extractLabelPrefix', ['Beginners']);
        $this->assertEquals('Beginners', $result);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — getEffectiefGewicht (private)
    // ========================================================================

    #[Test]
    public function get_effectief_gewicht_prefers_gewogen(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $judoka = (object) [
            'gewicht_gewogen' => 32.5,
            'gewicht' => 30.0,
            'gewichtsklasse' => '-36',
        ];

        $this->assertEquals(32.5, $this->callPrivate($service, 'getEffectiefGewicht', [$judoka]));
    }

    #[Test]
    public function get_effectief_gewicht_falls_back_to_gewicht(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $judoka = (object) [
            'gewicht_gewogen' => 0,
            'gewicht' => 28.0,
            'gewichtsklasse' => '-32',
        ];

        $this->assertEquals(28.0, $this->callPrivate($service, 'getEffectiefGewicht', [$judoka]));
    }

    #[Test]
    public function get_effectief_gewicht_falls_back_to_gewichtsklasse(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $judoka = (object) [
            'gewicht_gewogen' => 0,
            'gewicht' => null,
            'gewichtsklasse' => '-36',
        ];

        $this->assertEquals(36.0, $this->callPrivate($service, 'getEffectiefGewicht', [$judoka]));
    }

    #[Test]
    public function get_effectief_gewicht_returns_zero_without_data(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $judoka = (object) [
            'gewicht_gewogen' => 0,
            'gewicht' => null,
            'gewichtsklasse' => null,
        ];

        $this->assertEquals(0.0, $this->callPrivate($service, 'getEffectiefGewicht', [$judoka]));
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — sorteerPoules (private)
    // ========================================================================

    #[Test]
    public function sorteer_poules_sorts_by_age_then_weight(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $poules = collect([
            ['sort_leeftijd' => 10000, 'sort_gewicht' => 30000],
            ['sort_leeftijd' => 7000, 'sort_gewicht' => 25000],
            ['sort_leeftijd' => 7000, 'sort_gewicht' => 20000],
            ['sort_leeftijd' => 12000, 'sort_gewicht' => 40000],
        ]);

        $sorted = $this->callPrivate($service, 'sorteerPoules', [$poules]);

        $this->assertEquals(7000, $sorted[0]['sort_leeftijd']);
        $this->assertEquals(20000, $sorted[0]['sort_gewicht']);
        $this->assertEquals(7000, $sorted[1]['sort_leeftijd']);
        $this->assertEquals(25000, $sorted[1]['sort_gewicht']);
        $this->assertEquals(10000, $sorted[2]['sort_leeftijd']);
        $this->assertEquals(12000, $sorted[3]['sort_leeftijd']);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — groepeerOpLeeftijd (private)
    // ========================================================================

    #[Test]
    public function groepeer_op_leeftijd_groups_and_sorts(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $poules = collect([
            ['min_leeftijd' => 10, 'max_leeftijd' => 12, 'sort_gewicht' => 30000],
            ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'sort_gewicht' => 25000],
            ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'sort_gewicht' => 20000],
            ['min_leeftijd' => 10, 'max_leeftijd' => 12, 'sort_gewicht' => 40000],
        ]);

        $result = $this->callPrivate($service, 'groepeerOpLeeftijd', [$poules]);

        // Should have 2 groups
        $this->assertCount(2, $result);

        // First group key should be 7-9 (sorted by age)
        $keys = array_keys($result);
        $this->assertEquals('7-9', $keys[0]);
        $this->assertEquals('10-12', $keys[1]);

        // Within groups, sorted by weight
        $this->assertEquals(20000, $result['7-9'][0]['sort_gewicht']);
        $this->assertEquals(25000, $result['7-9'][1]['sort_gewicht']);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — vindOptimaleWeightSplit (private)
    // ========================================================================

    #[Test]
    public function vind_optimale_weight_split_returns_zero_for_single_item(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $groep = [['aantal_wedstrijden' => 6]];
        $result = $this->callPrivate($service, 'vindOptimaleWeightSplit', [$groep, 10, 0]);

        $this->assertEquals(0, $result['split_index']);
    }

    #[Test]
    public function vind_optimale_weight_split_finds_optimal_split(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $groep = [
            ['aantal_wedstrijden' => 5],
            ['aantal_wedstrijden' => 3],
            ['aantal_wedstrijden' => 6],
            ['aantal_wedstrijden' => 4],
        ];

        $result = $this->callPrivate($service, 'vindOptimaleWeightSplit', [$groep, 8, 0]);

        // Should split at index 2 (5+3=8, exact match)
        $this->assertEquals(2, $result['split_index']);
        $this->assertEquals(0, $result['verschil']);
    }

    #[Test]
    public function vind_optimale_weight_split_no_split_when_bad_fit(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        // All large items, splitting doesn't help much
        $groep = [
            ['aantal_wedstrijden' => 20],
            ['aantal_wedstrijden' => 20],
        ];

        $result = $this->callPrivate($service, 'vindOptimaleWeightSplit', [$groep, 3, 0]);

        // verschil 17 > 3*0.5=1.5, so no split
        $this->assertEquals(0, $result['split_index']);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — berekenScores (private)
    // ========================================================================

    #[Test]
    public function bereken_scores_perfect_distribution(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $blok1 = (object) ['id' => 1, 'nummer' => 1];
        $blok2 = (object) ['id' => 2, 'nummer' => 2];

        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 10],
            2 => ['gewenst' => 10, 'actueel' => 10],
        ];

        $result = $this->callPrivate($service, 'berekenScores', [
            $capaciteit, [$blok1, $blok2], 10, 50,
        ]);

        $this->assertEquals(0, $result['verdeling_score']);
        $this->assertEquals(0, $result['totaal_score']);
        $this->assertEquals(0, $result['max_afwijking_pct']);
        $this->assertTrue($result['is_valid']);
    }

    #[Test]
    public function bereken_scores_uneven_distribution(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $blok1 = (object) ['id' => 1, 'nummer' => 1];
        $blok2 = (object) ['id' => 2, 'nummer' => 2];

        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 15], // 50% over
            2 => ['gewenst' => 10, 'actueel' => 5],  // 50% under
        ];

        $result = $this->callPrivate($service, 'berekenScores', [
            $capaciteit, [$blok1, $blok2], 10, 100,
        ]);

        $this->assertGreaterThan(0, $result['verdeling_score']);
        $this->assertEquals(50.0, $result['max_afwijking_pct']);
        $this->assertFalse($result['is_valid']); // >30% deviation
    }

    #[Test]
    public function bereken_scores_user_weight_affects_total(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $blok1 = (object) ['id' => 1, 'nummer' => 1];
        $capaciteit = [
            1 => ['gewenst' => 10, 'actueel' => 12],
        ];

        $scores50 = $this->callPrivate($service, 'berekenScores', [
            $capaciteit, [$blok1], 10, 50,
        ]);

        $scores100 = $this->callPrivate($service, 'berekenScores', [
            $capaciteit, [$blok1], 10, 100,
        ]);

        // Higher user weight = higher total score
        $this->assertGreaterThan($scores50['totaal_score'], $scores100['totaal_score']);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — mergeAdjacentAgeGroups (private)
    // ========================================================================

    #[Test]
    public function merge_adjacent_age_groups_merges_overlapping(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $groups = [
            [
                'min_leeftijd' => 7, 'max_leeftijd' => 9,
                'min_gewicht' => 20, 'max_gewicht' => 30,
                'wedstrijden' => 5, 'poules' => ['a'],
            ],
            [
                'min_leeftijd' => 9, 'max_leeftijd' => 11,
                'min_gewicht' => 25, 'max_gewicht' => 40,
                'wedstrijden' => 3, 'poules' => ['b'],
            ],
        ];

        $result = $this->callPrivate($service, 'mergeAdjacentAgeGroups', [$groups]);

        $this->assertCount(1, $result);
        $this->assertEquals(7, $result[0]['min_leeftijd']);
        $this->assertEquals(11, $result[0]['max_leeftijd']);
        $this->assertEquals(20, $result[0]['min_gewicht']);
        $this->assertEquals(40, $result[0]['max_gewicht']);
        $this->assertEquals(8, $result[0]['wedstrijden']);
    }

    #[Test]
    public function merge_adjacent_age_groups_keeps_non_overlapping(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $groups = [
            [
                'min_leeftijd' => 7, 'max_leeftijd' => 9,
                'min_gewicht' => 20, 'max_gewicht' => 30,
                'wedstrijden' => 5, 'poules' => ['a'],
            ],
            [
                'min_leeftijd' => 12, 'max_leeftijd' => 14,
                'min_gewicht' => 35, 'max_gewicht' => 50,
                'wedstrijden' => 4, 'poules' => ['b'],
            ],
        ];

        $result = $this->callPrivate($service, 'mergeAdjacentAgeGroups', [$groups]);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function merge_adjacent_age_groups_handles_empty(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $result = $this->callPrivate($service, 'mergeAdjacentAgeGroups', [[]]);
        $this->assertEmpty($result);
    }

    #[Test]
    public function merge_adjacent_age_groups_merges_exactly_adjacent(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $groups = [
            [
                'min_leeftijd' => 7, 'max_leeftijd' => 9,
                'min_gewicht' => 20, 'max_gewicht' => 30,
                'wedstrijden' => 3, 'poules' => ['a'],
            ],
            [
                'min_leeftijd' => 10, 'max_leeftijd' => 12,
                'min_gewicht' => 30, 'max_gewicht' => 45,
                'wedstrijden' => 4, 'poules' => ['b'],
            ],
        ];

        // max_leeftijd 9 >= min_leeftijd 10 - 1 => merge
        $result = $this->callPrivate($service, 'mergeAdjacentAgeGroups', [$groups]);

        $this->assertCount(1, $result);
        $this->assertEquals(7, $result[0]['min_leeftijd']);
        $this->assertEquals(12, $result[0]['max_leeftijd']);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — genereerBlokLabels
    // ========================================================================

    #[Test]
    public function genereer_blok_labels_generates_correct_labels(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $blok1 = (object) ['id' => 1, 'nummer' => 1];
        $blok2 = (object) ['id' => 2, 'nummer' => 2];

        $blokPoules = [
            1 => [
                ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'min_gewicht' => 20, 'max_gewicht' => 30],
                ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'min_gewicht' => 25, 'max_gewicht' => 35],
            ],
            2 => [
                ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'min_gewicht' => 35, 'max_gewicht' => 50],
            ],
        ];

        $blokken = collect([$blok1, $blok2]);
        $labels = $service->genereerBlokLabels($blokPoules, $blokken);

        // First block: "t/m Xkg"
        $this->assertStringContains('7-9j', $labels[1]);
        $this->assertStringContains('t/m', $labels[1]);

        // Second block same age range: "vanaf Xkg"
        $this->assertStringContains('7-9j', $labels[2]);
        $this->assertStringContains('vanaf', $labels[2]);
    }

    #[Test]
    public function genereer_blok_labels_skips_empty_blocks(): void
    {
        $service = app(VariabeleBlokVerdelingService::class);

        $blok1 = (object) ['id' => 1, 'nummer' => 1];
        $blok2 = (object) ['id' => 2, 'nummer' => 2];

        $blokPoules = [
            1 => [
                ['min_leeftijd' => 7, 'max_leeftijd' => 9, 'min_gewicht' => 20, 'max_gewicht' => 30],
            ],
            // blok 2 has no entry
        ];

        $blokken = collect([$blok1, $blok2]);
        $labels = $service->genereerBlokLabels($blokPoules, $blokken);

        $this->assertArrayHasKey(1, $labels);
        $this->assertArrayNotHasKey(2, $labels);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — genereerVarianten throws without blokken
    // ========================================================================

    #[Test]
    public function genereer_varianten_throws_without_blokken(): void
    {
        $toernooi = Toernooi::factory()->dynamischeKlassen()->create();
        $service = app(VariabeleBlokVerdelingService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Geen blokken gevonden');

        $service->genereerVarianten($toernooi);
    }

    // ========================================================================
    // VariabeleBlokVerdelingService — verdeelOpMaxWedstrijden throws without blokken
    // ========================================================================

    #[Test]
    public function verdeel_op_max_wedstrijden_throws_without_blokken(): void
    {
        $toernooi = Toernooi::factory()->dynamischeKlassen()->create();
        $service = app(VariabeleBlokVerdelingService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Geen blokken gevonden');

        $service->verdeelOpMaxWedstrijden($toernooi, 20);
    }

    // ========================================================================
    // Helper for assertStringContains (PHPUnit 10+ compat)
    // ========================================================================

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
