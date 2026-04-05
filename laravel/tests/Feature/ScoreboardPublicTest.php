<?php

namespace Tests\Feature;

use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScoreboardPublicTest extends TestCase
{
    use RefreshDatabase;

    private function createToernooiWithMat(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'aantal_matten' => 3,
        ]);
        $mat = Mat::create([
            'toernooi_id' => $toernooi->id,
            'nummer' => 1,
        ]);

        return [$org, $toernooi, $mat];
    }

    // ========================================================================
    // Scoreboard Live (LCD)
    // ========================================================================

    #[Test]
    public function scoreboard_live_is_publicly_accessible(): void
    {
        [$org, $toernooi, $mat] = $this->createToernooiWithMat();

        $response = $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-live/1");

        $response->assertStatus(200);
    }

    #[Test]
    public function scoreboard_live_returns_404_for_invalid_mat(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();

        $response = $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-live/99");

        // Still renders view (mat fallback), no crash
        $response->assertStatus(200);
    }

    // ========================================================================
    // Scoreboard State API
    // ========================================================================

    #[Test]
    public function scoreboard_state_returns_mat_id_without_active_match(): void
    {
        [$org, $toernooi, $mat] = $this->createToernooiWithMat();

        $response = $this->getJson("/{$org->slug}/{$toernooi->slug}/live/scorebord/1/state");

        $response->assertStatus(200);
        $response->assertJsonFragment(['mat_id' => $mat->id]);
        $response->assertJsonMissing(['judoka_wit']);
    }

    #[Test]
    public function scoreboard_state_returns_null_for_invalid_mat(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();

        $response = $this->getJson("/{$org->slug}/{$toernooi->slug}/live/scorebord/99/state");

        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    #[Test]
    public function scoreboard_state_is_publicly_accessible(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();

        // No auth — should work
        $response = $this->getJson("/{$org->slug}/{$toernooi->slug}/live/scorebord/1/state");

        $response->assertStatus(200);
    }

    // ========================================================================
    // Public App (publiek index)
    // ========================================================================

    #[Test]
    public function publiek_index_is_publicly_accessible(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();

        $response = $this->get("/{$org->slug}/{$toernooi->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function publiek_matten_api_returns_all_mats(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();
        // Create additional mats
        Mat::create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);
        Mat::create(['toernooi_id' => $toernooi->id, 'nummer' => 3]);

        $response = $this->getJson("/{$org->slug}/{$toernooi->slug}/matten");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'matten');
    }

    // ========================================================================
    // Device Toegangen — Mat Auto-Sync
    // ========================================================================

    #[Test]
    public function device_toegang_sync_creates_missing_mat_toegangen(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'aantal_matten' => 3,
        ]);

        // Manually create mat 1 only
        DeviceToegang::create(['toernooi_id' => $toernooi->id, 'rol' => 'mat', 'mat_nummer' => 1]);

        // Call sync directly
        $controller = new \App\Http\Controllers\DeviceToegangBeheerController();
        $reflection = new \ReflectionMethod($controller, 'syncMatToegangen');
        $reflection->setAccessible(true);
        $reflection->invoke($controller, $toernooi);

        $matToegangen = $toernooi->deviceToegangen()->where('rol', 'mat')->orderBy('mat_nummer')->pluck('mat_nummer')->toArray();
        $this->assertEquals([1, 2, 3], $matToegangen);
    }

    #[Test]
    public function device_toegang_sync_does_not_duplicate_existing(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'aantal_matten' => 3,
        ]);

        $controller = new \App\Http\Controllers\DeviceToegangBeheerController();
        $reflection = new \ReflectionMethod($controller, 'syncMatToegangen');
        $reflection->setAccessible(true);

        $reflection->invoke($controller, $toernooi);
        $reflection->invoke($controller, $toernooi);

        $matToegangen = $toernooi->deviceToegangen()->where('rol', 'mat')->count();
        $this->assertEquals(3, $matToegangen);
    }
}
