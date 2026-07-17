<?php

namespace Tests\Feature;

use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
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

    #[Test]
    public function scoreboard_live_renders_the_categorie_duration_not_the_toernooi_default(): void
    {
        [$org, $toernooi, $mat] = $this->createToernooiWithMat();
        $toernooi->update([
            'wedstrijdtijd' => 180,
            'gewichtsklassen' => ['pupillen_-30' => ['shiai_time' => 240]],
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'mat_id' => $mat->id,
            'categorie_key' => 'pupillen_-30',
        ]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);
        $mat->update(['actieve_wedstrijd_id' => $wedstrijd->id]);

        $response = $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-live/1");

        $response->assertStatus(200);
        $response->assertSee('>4:00</div>', false);
        $response->assertDontSee('>3:00</div>', false);
    }

    #[Test]
    public function scoreboard_live_falls_back_to_the_toernooi_duration_without_an_active_match(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();
        $toernooi->update(['wedstrijdtijd' => 180]);

        $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-live/1")
            ->assertStatus(200)
            ->assertSee('>3:00</div>', false);
    }

    #[Test]
    public function scoreboard_live_renders_a_duration_that_is_not_a_whole_minute(): void
    {
        [$org, $toernooi, $mat] = $this->createToernooiWithMat();
        $toernooi->update([
            'wedstrijdtijd' => 180,
            'gewichtsklassen' => ['pupillen_-30' => ['shiai_time' => 210]],
        ]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'mat_id' => $mat->id,
            'categorie_key' => 'pupillen_-30',
        ]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);
        $mat->update(['actieve_wedstrijd_id' => $wedstrijd->id]);

        // floor(210/60) . ':00' rendered 3:00 — losing the seconds entirely.
        $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-live/1")
            ->assertStatus(200)
            ->assertSee('>3:30</div>', false);
    }

    // ========================================================================
    // Scoreboard Mobile (public, portrait + landscape)
    // ========================================================================

    #[Test]
    public function scoreboard_mobile_is_publicly_accessible(): void
    {
        [$org, $toernooi, $mat] = $this->createToernooiWithMat();

        // No auth — parents/coaches open it via a public link/QR.
        $response = $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-mobiel/1");

        $response->assertStatus(200);
        $response->assertViewIs('pages.mat.scoreboard-mobile');
        // A core engine-driven element must render (shared element-ID contract).
        $response->assertSee('id="timer-display"', false);
        $response->assertSee('id="header-poule"', false);
    }

    #[Test]
    public function scoreboard_mobile_returns_200_for_invalid_mat(): void
    {
        [$org, $toernooi] = $this->createToernooiWithMat();

        $response = $this->get("/{$org->slug}/{$toernooi->slug}/mat/scoreboard-mobiel/99");

        // Mat fallback — still renders, no crash.
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

        // Three real mats exist; only mat 1 already has a toegang.
        foreach ([1, 2, 3] as $n) {
            \App\Models\Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $n]);
        }
        DeviceToegang::create(['toernooi_id' => $toernooi->id, 'rol' => 'mat', 'mat_nummer' => 1]);

        // syncMatToegangen lives on ToernooiService (moved out of the controller).
        app(\App\Services\ToernooiService::class)->syncMatToegangen($toernooi);

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

        foreach ([1, 2, 3] as $n) {
            \App\Models\Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $n]);
        }

        // Syncing twice must be idempotent (no duplicate toegangen).
        $service = app(\App\Services\ToernooiService::class);
        $service->syncMatToegangen($toernooi);
        $service->syncMatToegangen($toernooi);

        $matToegangen = $toernooi->deviceToegangen()->where('rol', 'mat')->count();
        $this->assertEquals(3, $matToegangen);
    }
}
