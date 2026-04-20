<?php

namespace Tests\Unit\Services;

use App\Services\BackupService;
use Tests\TestCase;

/**
 * Coverage voor BackupService environment-detectie en milestone-skip
 * gedrag in lokale dev. De daadwerkelijke mysqldump-pad is niet
 * test-baar zonder MySQL + shell access; de skip-en-return-null pad
 * dekt de defensieve code die gebruikers tegen kapotte backups
 * beschermt op niet-server environments.
 */
class BackupServiceTest extends TestCase
{
    public function test_is_server_environment_returns_false_on_sqlite(): void
    {
        config()->set('database.default', 'sqlite');

        $this->assertFalse(BackupService::isServerEnvironment());
    }

    public function test_is_server_environment_returns_false_on_windows_even_with_mysql(): void
    {
        config()->set('database.default', 'mysql');

        // PHP_OS_FAMILY is a constant — we can't override it. On Windows the
        // assertion below is exercised; on Linux/Mac CI it's vacuously true
        // because the sqlite check above already covered the false branch.
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFalse(BackupService::isServerEnvironment());
        } else {
            // On non-Windows the mysql config alone makes it return true.
            $this->assertTrue(BackupService::isServerEnvironment());
        }
    }

    public function test_maak_milestone_backup_returns_null_on_local_dev(): void
    {
        config()->set('database.default', 'sqlite');

        $this->assertNull((new BackupService())->maakMilestoneBackup('test-label'));
    }

    public function test_maak_milestone_backup_skips_silently_when_not_server_env(): void
    {
        // Ensure no exception is raised when called on local dev — the
        // service must never break the calling workflow.
        config()->set('database.default', 'sqlite');

        try {
            $result = (new BackupService())->maakMilestoneBackup('any-label');
        } catch (\Throwable $e) {
            $this->fail('BackupService threw on local dev: ' . $e->getMessage());
        }

        $this->assertNull($result);
    }
}
