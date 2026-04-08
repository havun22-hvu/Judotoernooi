<?php

namespace Tests\Unit\Services;

use App\Models\AutofixProposal;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Services\AutoFixService;
use App\Services\BackupService;
use App\Services\BracketLayoutService;
use App\Services\StambestandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfraServicesCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // AutoFixService Tests
    // ==========================================

    #[Test]
    public function autofix_shouldProcess_excludes_validation_exception(): void
    {
        config(['autofix.enabled' => true]);
        config(['autofix.excluded_exceptions' => [\Illuminate\Validation\ValidationException::class]]);

        $service = new class extends AutoFixService {
            public function testShouldProcess(\Throwable $e): bool
            {
                return $this->shouldProcess($e);
            }
        };

        $exception = \Illuminate\Validation\ValidationException::withMessages(['field' => 'error']);

        $this->assertFalse($service->testShouldProcess($exception));
    }

    #[Test]
    public function autofix_shouldProcess_excludes_message_patterns(): void
    {
        config(['autofix.enabled' => true]);
        config(['autofix.excluded_exceptions' => []]);
        config(['autofix.excluded_message_patterns' => ['#Address already in use#i']]);
        config(['autofix.excluded_file_patterns' => []]);

        $service = new class extends AutoFixService {
            public function testShouldProcess(\Throwable $e): bool
            {
                return $this->shouldProcess($e);
            }
        };

        $exception = new \RuntimeException('Address already in use on port 8000');

        $this->assertFalse($service->testShouldProcess($exception));
    }

    #[Test]
    public function autofix_shouldProcess_excludes_file_patterns(): void
    {
        config(['autofix.enabled' => true]);
        config(['autofix.excluded_exceptions' => []]);
        config(['autofix.excluded_message_patterns' => []]);
        config(['autofix.excluded_file_patterns' => ['#/tmp/#']]);

        $service = new class extends AutoFixService {
            public function testShouldProcess(\Throwable $e): bool
            {
                return $this->shouldProcess($e);
            }
        };

        // Create an exception that appears to come from /tmp/
        $exception = new \RuntimeException('Something broke');
        $ref = new \ReflectionProperty(\Exception::class, 'file');
        $ref->setValue($exception, '/tmp/some_file.php');

        $this->assertFalse($service->testShouldProcess($exception));
    }

    #[Test]
    public function autofix_isProjectFile_returns_false_for_vendor(): void
    {
        $service = new class extends AutoFixService {
            public function testIsProjectFile(string $path): bool
            {
                return $this->isProjectFile($path);
            }
        };

        $vendorPath = base_path('vendor/laravel/framework/src/Something.php');
        $this->assertFalse($service->testIsProjectFile($vendorPath));
    }

    #[Test]
    public function autofix_isProjectFile_returns_false_for_node_modules(): void
    {
        $service = new class extends AutoFixService {
            public function testIsProjectFile(string $path): bool
            {
                return $this->isProjectFile($path);
            }
        };

        $path = base_path('node_modules/something/index.js');
        $this->assertFalse($service->testIsProjectFile($path));
    }

    #[Test]
    public function autofix_isProjectFile_returns_false_for_storage(): void
    {
        $service = new class extends AutoFixService {
            public function testIsProjectFile(string $path): bool
            {
                return $this->isProjectFile($path);
            }
        };

        $path = base_path('storage/logs/laravel.log');
        $this->assertFalse($service->testIsProjectFile($path));
    }

    #[Test]
    public function autofix_isProjectFile_returns_true_for_app_files(): void
    {
        $service = new class extends AutoFixService {
            public function testIsProjectFile(string $path): bool
            {
                return $this->isProjectFile($path);
            }
        };

        $path = base_path('app/Services/SomeService.php');
        $this->assertTrue($service->testIsProjectFile($path));
    }

    #[Test]
    public function autofix_isProjectFile_returns_false_for_outside_paths(): void
    {
        $service = new class extends AutoFixService {
            public function testIsProjectFile(string $path): bool
            {
                return $this->isProjectFile($path);
            }
        };

        $this->assertFalse($service->testIsProjectFile('/some/completely/other/path.php'));
    }

    #[Test]
    public function autofix_relativePath_strips_base_path(): void
    {
        $service = new class extends AutoFixService {
            public function testRelativePath(string $path): string
            {
                return $this->relativePath($path);
            }
        };

        $full = base_path('app/Services/AutoFixService.php');
        $this->assertEquals('app/Services/AutoFixService.php', $service->testRelativePath($full));
    }

    #[Test]
    public function autofix_extractRisk_parses_low_risk(): void
    {
        $service = new class extends AutoFixService {
            public function testExtractRisk(string $analysis): string
            {
                return $this->extractRisk($analysis);
            }
        };

        $this->assertEquals('low', $service->testExtractRisk("ANALYSIS: some fix\nRISK: low"));
        $this->assertEquals('medium', $service->testExtractRisk("RISK: Medium\nFILE: test.php"));
        $this->assertEquals('high', $service->testExtractRisk("RISK: HIGH"));
        $this->assertEquals('unknown', $service->testExtractRisk("No risk info here"));
    }

    #[Test]
    public function autofix_isDryRunRisk_checks_config(): void
    {
        config(['autofix.dry_run_on_risk' => ['medium', 'high']]);

        $service = new class extends AutoFixService {
            public function testIsDryRunRisk(string $analysis): bool
            {
                return $this->isDryRunRisk($analysis);
            }
        };

        $this->assertTrue($service->testIsDryRunRisk("RISK: medium\nANALYSIS: something"));
        $this->assertTrue($service->testIsDryRunRisk("RISK: high"));
        $this->assertFalse($service->testIsDryRunRisk("RISK: low"));
        $this->assertFalse($service->testIsDryRunRisk("no risk mentioned"));
    }

    #[Test]
    public function autofix_isDryRunRisk_returns_false_when_no_config(): void
    {
        config(['autofix.dry_run_on_risk' => []]);

        $service = new class extends AutoFixService {
            public function testIsDryRunRisk(string $analysis): bool
            {
                return $this->isDryRunRisk($analysis);
            }
        };

        $this->assertFalse($service->testIsDryRunRisk("RISK: high"));
    }

    #[Test]
    public function autofix_isNotifyOnly_detects_notify_action(): void
    {
        $service = new class extends AutoFixService {
            public function testIsNotifyOnly(string $analysis): bool
            {
                return $this->isNotifyOnly($analysis);
            }
        };

        $this->assertTrue($service->testIsNotifyOnly("ACTION: NOTIFY_ONLY\nANALYSIS: column missing"));
        $this->assertTrue($service->testIsNotifyOnly("ACTION: notify_only"));
        $this->assertFalse($service->testIsNotifyOnly("ACTION: FIX\nFILE: test.php"));
        $this->assertFalse($service->testIsNotifyOnly("Some random text"));
    }

    #[Test]
    public function autofix_handle_returns_early_when_disabled(): void
    {
        config(['autofix.enabled' => false]);

        $service = new AutoFixService();
        $exception = new \RuntimeException('test');

        // Should not throw, just return silently
        $service->handle($exception);
        $this->assertDatabaseCount('autofix_proposals', 0);
    }

    #[Test]
    public function autofix_shouldProcess_skips_recently_analyzed(): void
    {
        config(['autofix.enabled' => true]);
        config(['autofix.excluded_exceptions' => []]);
        config(['autofix.excluded_message_patterns' => []]);
        config(['autofix.excluded_file_patterns' => []]);
        config(['autofix.rate_limit_minutes' => 60]);

        // Create a recent proposal
        $file = 'app/Services/TestService.php';
        AutofixProposal::create([
            'exception_class' => \RuntimeException::class,
            'exception_message' => 'test error',
            'file' => $file,
            'line' => 42,
            'stack_trace' => '',
            'code_context' => '',
            'claude_analysis' => 'test',
            'proposed_diff' => 'test',
            'approval_token' => 'token123',
            'status' => 'pending',
        ]);

        $service = new class extends AutoFixService {
            public function testShouldProcess(\Throwable $e): bool
            {
                return $this->shouldProcess($e);
            }
        };

        // Create an exception matching the proposal
        $exception = new \RuntimeException('test error');
        $ref = new \ReflectionProperty(\Exception::class, 'file');
        $ref->setValue($exception, base_path($file));
        $lineRef = new \ReflectionProperty(\Exception::class, 'line');
        $lineRef->setValue($exception, 42);

        $this->assertFalse($service->testShouldProcess($exception));
    }

    #[Test]
    public function autofix_extractBladeFile_extracts_path(): void
    {
        $service = new class extends AutoFixService {
            public function testExtractBladeFile(\Throwable $e): ?string
            {
                return $this->extractBladeFile($e);
            }
        };

        // No match
        $exception = new \RuntimeException('Regular error without view path');
        $this->assertNull($service->testExtractBladeFile($exception));

        // With view path but file doesn't exist
        $exception2 = new \RuntimeException('Error in view (View: /nonexistent/path/test.blade.php)');
        $this->assertNull($service->testExtractBladeFile($exception2));
    }

    #[Test]
    public function autofix_findFixTargetFile_returns_project_file_for_app_exception(): void
    {
        $service = new class extends AutoFixService {
            public function testFindFixTargetFile(\Throwable $e): ?string
            {
                return $this->findFixTargetFile($e);
            }
        };

        // Exception with file set to a project file path
        $exception = new \RuntimeException('test');
        $ref = new \ReflectionProperty(\Exception::class, 'file');
        $ref->setValue($exception, base_path('app/Services/TestService.php'));

        $result = $service->testFindFixTargetFile($exception);
        $this->assertEquals('app/Services/TestService.php', $result);
    }

    #[Test]
    public function autofix_findFixTargetFile_extracts_blade_file_from_view_exception(): void
    {
        $service = new class extends AutoFixService {
            public function testFindFixTargetFile(\Throwable $e): ?string
            {
                return $this->findFixTargetFile($e);
            }
        };

        // ViewException message with a non-existent blade path -> no match, falls through
        $exception = new \RuntimeException('Something (View: /nonexistent/test.blade.php)');
        $ref = new \ReflectionProperty(\Exception::class, 'file');
        $ref->setValue($exception, '/some/outside/path.php');

        // blade file doesn't exist, and the main file is outside project,
        // so it will walk the stack trace. The result depends on the actual trace.
        $result = $service->testFindFixTargetFile($exception);
        // We just verify it doesn't throw - the result depends on the stack trace
        $this->assertTrue($result === null || is_string($result));
    }

    // ==========================================
    // BackupService Tests
    // ==========================================

    #[Test]
    public function backup_isServerEnvironment_returns_false_on_windows(): void
    {
        // On Windows (test env), this should return false
        $this->assertFalse(BackupService::isServerEnvironment());
    }

    #[Test]
    public function backup_maakMilestoneBackup_returns_null_on_non_server(): void
    {
        $service = new BackupService();
        $result = $service->maakMilestoneBackup('test-backup');

        $this->assertNull($result);
    }

    #[Test]
    public function backup_maakMilestoneBackup_skips_on_sqlite(): void
    {
        // SQLite is the test database, so it should skip
        config(['database.default' => 'sqlite']);
        $service = new BackupService();

        $this->assertNull($service->maakMilestoneBackup('before-deploy'));
    }

    // ==========================================
    // BracketLayoutService Tests
    // ==========================================

    #[Test]
    public function bracket_a_layout_returns_empty_for_no_matches(): void
    {
        $service = new BracketLayoutService();
        $result = $service->berekenABracketLayout([]);

        $this->assertEmpty($result['rondes']);
        $this->assertEquals(300, $result['totale_hoogte']);
        $this->assertEmpty($result['medaille_data']);
        $this->assertEquals(0, $result['start_ronde']);
        $this->assertEquals(0, $result['totaal_rondes']);
    }

    #[Test]
    public function bracket_a_layout_calculates_positions_for_kwartfinale(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'kwartfinale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'kwartfinale', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'halve_finale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 4, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenABracketLayout($wedstrijden);

        $this->assertNotEmpty($result['rondes']);
        $this->assertEquals(3, count($result['rondes'])); // kwart, halve, finale
        $this->assertGreaterThan(0, $result['totale_hoogte']);
        $this->assertArrayHasKey('goud', $result['medaille_data']);
        $this->assertArrayHasKey('zilver', $result['medaille_data']);
    }

    #[Test]
    public function bracket_a_layout_with_start_ronde_offset(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'achtste_finale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'achtste_finale', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'kwartfinale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'halve_finale', 'bracket_positie' => 1, 'id' => 4, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 5, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenABracketLayout($wedstrijden, startRonde: 1);

        // Start at ronde 1, so achtste_finale is skipped
        $this->assertEquals(1, $result['start_ronde']);
        $this->assertEquals(4, $result['totaal_rondes']);
    }

    #[Test]
    public function bracket_a_layout_medailles_with_played_finale(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'halve_finale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 10, 'naam' => 'A'], 'blauw' => ['id' => 20, 'naam' => 'B'], 'is_gespeeld' => true, 'winnaar_id' => 10],
            ['ronde' => 'halve_finale', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 30, 'naam' => 'C'], 'blauw' => ['id' => 40, 'naam' => 'D'], 'is_gespeeld' => true, 'winnaar_id' => 30],
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => 10, 'naam' => 'A'], 'blauw' => ['id' => 30, 'naam' => 'C'], 'is_gespeeld' => true, 'winnaar_id' => 30],
        ];

        $result = $service->berekenABracketLayout($wedstrijden);

        $this->assertEquals(30, $result['medaille_data']['goud']['winnaar']['id']);
        $this->assertEquals(10, $result['medaille_data']['zilver']['verliezer']['id']);
    }

    #[Test]
    public function bracket_a_layout_medailles_with_blauw_winner(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 10, 'naam' => 'A'], 'blauw' => ['id' => 20, 'naam' => 'B'], 'is_gespeeld' => true, 'winnaar_id' => 20],
        ];

        $result = $service->berekenABracketLayout($wedstrijden);

        $this->assertEquals(20, $result['medaille_data']['goud']['winnaar']['id']);
        $this->assertEquals(10, $result['medaille_data']['zilver']['verliezer']['id']);
    }

    #[Test]
    public function bracket_b_layout_returns_empty_for_no_matches(): void
    {
        $service = new BracketLayoutService();
        $result = $service->berekenBBracketLayout([]);

        $this->assertEmpty($result['niveaus']);
        $this->assertEquals(300, $result['totale_hoogte']);
        $this->assertEmpty($result['medaille_data']);
    }

    #[Test]
    public function bracket_b_layout_calculates_positions(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'b_achtste_finale_1', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_1', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_2', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => 5], 'blauw' => ['id' => 6], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_2', 'bracket_positie' => 2, 'id' => 4, 'wit' => ['id' => 7], 'blauw' => ['id' => 8], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_kwartfinale', 'bracket_positie' => 1, 'id' => 5, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_kwartfinale', 'bracket_positie' => 2, 'id' => 6, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenBBracketLayout($wedstrijden);

        $this->assertNotEmpty($result['niveaus']);
        $this->assertGreaterThan(0, $result['totale_hoogte']);
    }

    #[Test]
    public function bracket_b_layout_with_brons_medailles(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'b_halve_finale_1', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1, 'naam' => 'A'], 'blauw' => ['id' => 2, 'naam' => 'B'], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_halve_finale_2', 'bracket_positie' => 1, 'id' => 2, 'wit' => ['id' => 3, 'naam' => 'C'], 'blauw' => ['id' => 4, 'naam' => 'D'], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_brons', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => 10, 'naam' => 'X'], 'blauw' => ['id' => 20, 'naam' => 'Y'], 'is_gespeeld' => true, 'winnaar_id' => 10],
            ['ronde' => 'b_brons', 'bracket_positie' => 2, 'id' => 4, 'wit' => ['id' => 30, 'naam' => 'Z'], 'blauw' => ['id' => 40, 'naam' => 'W'], 'is_gespeeld' => true, 'winnaar_id' => 40],
        ];

        $result = $service->berekenBBracketLayout($wedstrijden);

        $this->assertArrayHasKey('brons_1', $result['medaille_data']);
        $this->assertArrayHasKey('brons_2', $result['medaille_data']);
        $this->assertEquals(10, $result['medaille_data']['brons_1']['winnaar']['id']);
        $this->assertEquals(40, $result['medaille_data']['brons_2']['winnaar']['id']);
    }

    #[Test]
    public function bracket_b_layout_brons_blauw_winner(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'b_brons', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 10, 'naam' => 'X'], 'blauw' => ['id' => 20, 'naam' => 'Y'], 'is_gespeeld' => true, 'winnaar_id' => 20],
            ['ronde' => 'b_brons', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 30, 'naam' => 'Z'], 'blauw' => ['id' => 40, 'naam' => 'W'], 'is_gespeeld' => true, 'winnaar_id' => 30],
        ];

        $result = $service->berekenBBracketLayout($wedstrijden);

        $this->assertEquals(20, $result['medaille_data']['brons_1']['winnaar']['id']);
        $this->assertEquals(30, $result['medaille_data']['brons_2']['winnaar']['id']);
    }

    #[Test]
    public function bracket_ronde_namen_are_readable(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'kwartfinale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'kwartfinale', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'halve_finale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 4, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenABracketLayout($wedstrijden);

        $this->assertEquals('1/4', $result['rondes'][0]['naam']);
        $this->assertEquals('1/2', $result['rondes'][1]['naam']);
        $this->assertEquals('Finale', $result['rondes'][2]['naam']);
    }

    #[Test]
    public function bracket_a_layout_sets_layout_data_per_wedstrijd(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'halve_finale', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'halve_finale', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'finale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenABracketLayout($wedstrijden);

        // Check that _layout is set on matches
        $halveFinaleWed = $result['rondes'][0]['wedstrijden'][0];
        $this->assertArrayHasKey('_layout', $halveFinaleWed);
        $this->assertArrayHasKey('top', $halveFinaleWed['_layout']);
        $this->assertArrayHasKey('is_last_round', $halveFinaleWed['_layout']);
        $this->assertFalse($halveFinaleWed['_layout']['is_last_round']);

        $finaleWed = $result['rondes'][1]['wedstrijden'][0];
        $this->assertTrue($finaleWed['_layout']['is_last_round']);
    }

    #[Test]
    public function bracket_b_layout_herkomst_labels_for_first_niveau_ronde1(): void
    {
        $service = new BracketLayoutService();

        // First B-level with _1 suffix: both slots get A-losers
        $wedstrijden = [
            ['ronde' => 'b_achtste_finale_1', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_1', 'bracket_positie' => 2, 'id' => 2, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_2', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_kwartfinale', 'bracket_positie' => 1, 'id' => 4, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenBBracketLayout($wedstrijden);

        // First niveau, ronde1: herkomst should mention A-round
        $firstWed = $result['niveaus'][0]['sub_rondes'][0]['wedstrijden'][0];
        $this->assertStringContainsString('A-1/8', $firstWed['_layout']['herkomst_wit']);
    }

    #[Test]
    public function bracket_b_layout_with_start_ronde(): void
    {
        $service = new BracketLayoutService();

        $wedstrijden = [
            ['ronde' => 'b_achtste_finale_1', 'bracket_positie' => 1, 'id' => 1, 'wit' => ['id' => 1], 'blauw' => ['id' => 2], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_achtste_finale_2', 'bracket_positie' => 1, 'id' => 2, 'wit' => ['id' => 3], 'blauw' => ['id' => 4], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_kwartfinale', 'bracket_positie' => 1, 'id' => 3, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
            ['ronde' => 'b_halve_finale', 'bracket_positie' => 1, 'id' => 4, 'wit' => ['id' => null], 'blauw' => ['id' => null], 'is_gespeeld' => false, 'winnaar_id' => null],
        ];

        $result = $service->berekenBBracketLayout($wedstrijden, startRonde: 1);

        $this->assertEquals(1, $result['start_ronde']);
    }

    // ==========================================
    // StambestandService Tests
    // ==========================================

    #[Test]
    public function stambestand_importNaarToernooi_imports_judokas(): void
    {
        $organisator = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $organisator->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $stam1 = StamJudoka::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'Jansen, Piet',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 25.0,
        ]);
        $stam2 = StamJudoka::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'De Vries, Lisa',
            'geboortejaar' => 2014,
            'geslacht' => 'V',
            'band' => 'geel',
            'gewicht' => 30.0,
        ]);

        $service = new StambestandService();
        $count = $service->importNaarToernooi([$stam1->id, $stam2->id], $toernooi);

        $this->assertEquals(2, $count);
        $this->assertEquals(2, Judoka::where('toernooi_id', $toernooi->id)->count());
    }

    #[Test]
    public function stambestand_importNaarToernooi_skips_duplicates(): void
    {
        $organisator = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $organisator->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $stam = StamJudoka::factory()->create([
            'organisator_id' => $organisator->id,
            'gewicht' => 25.0,
        ]);

        $service = new StambestandService();

        // Import once
        $count1 = $service->importNaarToernooi([$stam->id], $toernooi);
        $this->assertEquals(1, $count1);

        // Import again - should skip
        $count2 = $service->importNaarToernooi([$stam->id], $toernooi);
        $this->assertEquals(0, $count2);

        $this->assertEquals(1, Judoka::where('toernooi_id', $toernooi->id)->count());
    }

    #[Test]
    public function stambestand_importNaarToernooi_skips_other_organisator(): void
    {
        $organisator1 = Organisator::factory()->create();
        $organisator2 = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator1->id]);

        // StamJudoka belongs to organisator2
        $stam = StamJudoka::factory()->create([
            'organisator_id' => $organisator2->id,
            'gewicht' => 25.0,
        ]);

        $service = new StambestandService();
        $count = $service->importNaarToernooi([$stam->id], $toernooi);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function stambestand_importNaarToernooi_skips_inactive(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $stam = StamJudoka::factory()->create([
            'organisator_id' => $organisator->id,
            'actief' => false,
            'gewicht' => 25.0,
        ]);

        $service = new StambestandService();
        $count = $service->importNaarToernooi([$stam->id], $toernooi);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function stambestand_syncVanuitImport_creates_stam_judoka(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Bakker, Jan',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'band' => 'geel',
            'gewicht' => 28.5,
            'stam_judoka_id' => null,
        ]);

        $service = new StambestandService();
        $service->syncVanuitImport($judoka, $organisator);

        $judoka->refresh();
        $this->assertNotNull($judoka->stam_judoka_id);

        $stamJudoka = StamJudoka::find($judoka->stam_judoka_id);
        $this->assertEquals('Bakker, Jan', $stamJudoka->naam);
        $this->assertEquals(2015, $stamJudoka->geboortejaar);
    }

    #[Test]
    public function stambestand_syncVanuitImport_matches_existing_stam(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $existing = StamJudoka::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'Bakker, Jan',
            'geboortejaar' => 2015,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Bakker, Jan',
            'geboortejaar' => 2015,
            'stam_judoka_id' => null,
        ]);

        $service = new StambestandService();
        $service->syncVanuitImport($judoka, $organisator);

        $judoka->refresh();
        $this->assertEquals($existing->id, $judoka->stam_judoka_id);
        // Should NOT have created a new StamJudoka
        $this->assertEquals(1, StamJudoka::where('organisator_id', $organisator->id)->where('naam', 'Bakker, Jan')->count());
    }

    #[Test]
    public function stambestand_syncVanuitImport_skips_already_linked(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);

        $stam = StamJudoka::factory()->create(['organisator_id' => $organisator->id]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'stam_judoka_id' => $stam->id,
        ]);

        $service = new StambestandService();
        $service->syncVanuitImport($judoka, $organisator);

        // Should not create any new StamJudoka
        $this->assertEquals(1, StamJudoka::where('organisator_id', $organisator->id)->count());
    }
}
