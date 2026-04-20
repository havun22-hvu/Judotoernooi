<?php

namespace Tests\Unit\Models;

use App\Models\SyncConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncConflictTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function winner_for_wedstrijden_is_local_authority(): void
    {
        $this->assertSame(SyncConflict::WINNER_LOCAL, SyncConflict::winnerFor('wedstrijden'),
            'Live wedstrijdscores op de mat zijn altijd source-of-truth.');
    }

    #[Test]
    public function winner_for_scores_is_local_authority(): void
    {
        $this->assertSame(SyncConflict::WINNER_LOCAL, SyncConflict::winnerFor('scores'));
    }

    #[Test]
    public function winner_for_other_tables_defaults_to_cloud(): void
    {
        $this->assertSame(SyncConflict::WINNER_CLOUD, SyncConflict::winnerFor('judokas'));
        $this->assertSame(SyncConflict::WINNER_CLOUD, SyncConflict::winnerFor('toernooien'));
        $this->assertSame(SyncConflict::WINNER_CLOUD, SyncConflict::winnerFor('willekeurig_andere_tabel'));
    }

    #[Test]
    public function unresolved_scope_filters_null_resolved_at(): void
    {
        SyncConflict::create([
            'table_name' => 'judokas',
            'record_id' => 1,
            'local_data' => ['x' => 1],
            'cloud_data' => ['x' => 2],
            'applied_winner' => SyncConflict::WINNER_CLOUD,
        ]);
        SyncConflict::create([
            'table_name' => 'judokas',
            'record_id' => 2,
            'local_data' => ['x' => 1],
            'cloud_data' => ['x' => 2],
            'applied_winner' => SyncConflict::WINNER_CLOUD,
            'resolved_at' => now(),
            'resolved_by' => 'admin',
        ]);

        $this->assertSame(1, SyncConflict::unresolved()->count());
    }

    #[Test]
    public function array_casts_round_trip_local_and_cloud_data(): void
    {
        $conflict = SyncConflict::create([
            'table_name' => 'judokas',
            'record_id' => 1,
            'local_data' => ['naam' => 'Jansen', 'gewicht' => 60],
            'cloud_data' => ['naam' => 'Jansen', 'gewicht' => 62],
            'applied_winner' => SyncConflict::WINNER_CLOUD,
        ]);

        $fresh = $conflict->fresh();
        $this->assertSame(['naam' => 'Jansen', 'gewicht' => 60], $fresh->local_data);
        $this->assertSame(['naam' => 'Jansen', 'gewicht' => 62], $fresh->cloud_data);
    }
}
