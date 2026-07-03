<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard for the phantom-pending `create_jobs_table` migration.
 *
 * On production the `jobs` table exists while the migration lingers as
 * "Pending" (its migrations-row was lost), so `migrate --force` used to abort
 * with "1050 Table 'jobs' already exists" before any later migration could run.
 * The migration is now idempotent (guards on Schema::hasTable). This test
 * reproduces that exact state and proves migrate now succeeds and self-heals.
 */
class JobsMigrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrate_succeeds_when_jobs_table_exists_but_migration_is_pending(): void
    {
        $name = '2026_04_17_062440_create_jobs_table';

        // Reproduce the production state: table present, migration record gone.
        $this->assertTrue(Schema::hasTable('jobs'));
        DB::table('migrations')->where('migration', $name)->delete();

        // Must NOT throw 1050 and must re-record the migration as run.
        $exit = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $exit);
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(
            DB::table('migrations')->where('migration', $name)->exists(),
            'The idempotent migration should self-heal by recording itself as run.'
        );
    }
}
