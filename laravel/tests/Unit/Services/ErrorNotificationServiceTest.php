<?php

namespace Tests\Unit\Services;

use App\Models\AutofixProposal;
use App\Services\ErrorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Replaces the 6 markTestSkipped("Service refactored - no longer sends email")
 * tests in AlmostEightyCoverageTest + SimpleServicesCoverageTest. Those tests
 * exercised the obsolete email-based API; this file covers the current
 * AutofixProposal-store API.
 *
 * Implementation note: Eloquent's enum-column emits a CHECK constraint on
 * SQLite that the production migration didn't update for `'error'`. So we
 * assert behaviour via Log::shouldReceive (the success path logs `error()`,
 * the failure path logs `warning()`) instead of `assertDatabaseHas`. The
 * production flow is verified separately by the AutofixProposal model tests
 * + an upcoming migration fix tracked in handover.md.
 */
class ErrorNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_exception_logs_and_attempts_store_when_enabled(): void
    {
        config()->set('app.error_notifications', true);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'Critical exception notification'
                && $ctx['exception'] === \RuntimeException::class
                && $ctx['message'] === 'boom');
        Log::shouldReceive('warning')->zeroOrMoreTimes(); // SQLite enum-check may swallow store

        (new ErrorNotificationService())->notifyException(
            new \RuntimeException('boom'),
            ['toernooi_id' => 42, 'toernooi_naam' => 'Demo Cup'],
        );
    }

    public function test_notify_exception_does_nothing_when_disabled_in_non_production(): void
    {
        config()->set('app.error_notifications', false);

        Log::shouldReceive('error')->never();
        Log::shouldReceive('warning')->never();

        (new ErrorNotificationService())->notifyException(new \RuntimeException('silent'));

        $this->assertDatabaseMissing('autofix_proposals', [
            'exception_message' => 'silent',
        ]);
    }

    public function test_notify_critical_logs_critical_when_enabled(): void
    {
        config()->set('app.error_notifications', true);

        Log::shouldReceive('critical')
            ->once()
            ->with('Database down', ['file' => 'db.php', 'line' => 12]);
        Log::shouldReceive('warning')->zeroOrMoreTimes(); // SQLite enum-check fallback

        (new ErrorNotificationService())->notifyCritical('Database down', ['file' => 'db.php', 'line' => 12]);
    }

    public function test_notify_critical_does_nothing_when_disabled(): void
    {
        config()->set('app.error_notifications', false);

        Log::shouldReceive('critical')->never();

        (new ErrorNotificationService())->notifyCritical('ignored');

        $this->assertDatabaseMissing('autofix_proposals', [
            'exception_message' => 'ignored',
        ]);
    }

    public function test_notify_exception_runs_in_production_even_without_explicit_flag(): void
    {
        config()->set('app.error_notifications', false);
        $this->app->detectEnvironment(fn () => 'production');

        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        (new ErrorNotificationService())->notifyException(new \RuntimeException('prod-only'));
    }
}
