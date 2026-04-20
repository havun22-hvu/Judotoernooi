<?php

namespace Tests\Unit\Services;

use App\Models\ActivityLog;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor de actor-detectie + truncation + model-binding logica
 * van ActivityLogger. Toegevoegd 2026-04-20 om de gap richting 80 %
 * Unit-coverage te dichten.
 */
class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_falls_back_to_system_actor_when_no_auth(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'test_action', 'Did a thing');

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertSame('systeem', $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertSame('Systeem', $log->actor_naam);
    }

    public function test_log_attributes_to_organisator_when_authenticated(): void
    {
        $toernooi = Toernooi::factory()->create();
        $org = Organisator::factory()->create(['naam' => 'Henk']);
        $this->actingAs($org, 'organisator');

        $log = ActivityLogger::log($toernooi, 'test', 'Henk did it');

        $this->assertSame('organisator', $log->actor_type);
        $this->assertSame($org->id, $log->actor_id);
        $this->assertStringContainsString('Henk', $log->actor_naam);
        $this->assertSame('dashboard', $log->interface);
    }

    public function test_log_truncates_long_descriptions_to_255_chars(): void
    {
        $toernooi = Toernooi::factory()->create();
        $long = str_repeat('a', 400);

        $log = ActivityLogger::log($toernooi, 'test', $long);

        $this->assertSame(255, mb_strlen($log->beschrijving));
    }

    public function test_log_records_model_class_basename_and_id_when_passed(): void
    {
        $toernooi = Toernooi::factory()->create();
        $org = Organisator::factory()->create();

        $log = ActivityLogger::log($toernooi, 'edit', 'Edited', ['model' => $org]);

        $this->assertSame('Organisator', $log->model_type);
        $this->assertSame((string) $org->id, (string) $log->model_id);
    }

    public function test_log_supports_explicit_model_type_when_no_model_instance(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'edit', 'Edited',
            ['model_type' => 'Judoka', 'model_id' => 42]);

        $this->assertSame('Judoka', $log->model_type);
        $this->assertSame('42', (string) $log->model_id);
    }

    public function test_log_persists_arbitrary_properties(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'edit', 'Edited',
            ['properties' => ['old' => 'A', 'new' => 'B']]);

        $this->assertEquals(['old' => 'A', 'new' => 'B'], $log->properties);
    }

    public function test_log_returns_null_and_does_not_throw_on_storage_error(): void
    {
        // Toernooi without id won't satisfy the FK; logger must swallow
        // the exception and return null instead of breaking the caller.
        $bogus = new Toernooi(['id' => 0]);
        $bogus->id = 999_999_999; // FK target that doesn't exist

        $log = ActivityLogger::log($bogus, 'test', 'should fail silently');

        $this->assertNull($log);
    }
}
