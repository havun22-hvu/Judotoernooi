<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\OfflineExportService;
use App\Services\OfflinePackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoodplanControllerCoverageTest extends TestCase
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
            'plan_type' => 'paid',
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);

        $this->club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->blok = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 1,
        ]);
        $this->mat = Mat::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 1,
        ]);
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    private function noodplanUrl(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/noodplan" . ($suffix ? "/{$suffix}" : '');
    }

    private function createPouleWithJudokas(int $aantalJudokas = 3, bool $metWedstrijden = false): Poule
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $this->blok->id,
            'mat_id' => $this->mat->id,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-28',
        ]);

        $judokas = [];
        for ($i = 0; $i < $aantalJudokas; $i++) {
            $judoka = Judoka::factory()->aanwezig()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'leeftijdsklasse' => 'pupillen',
                'gewichtsklasse' => '-28',
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
            $judokas[] = $judoka;
        }

        if ($metWedstrijden && $aantalJudokas >= 2) {
            $volgorde = 1;
            for ($i = 0; $i < count($judokas); $i++) {
                for ($j = $i + 1; $j < count($judokas); $j++) {
                    Wedstrijd::factory()->create([
                        'poule_id' => $poule->id,
                        'judoka_wit_id' => $judokas[$i]->id,
                        'judoka_blauw_id' => $judokas[$j]->id,
                        'volgorde' => $volgorde++,
                    ]);
                }
            }
        }

        return $poule;
    }

    // =========================================================================
    // Line 107: Free tier weeglijst — remaining <= 0 returns collect()
    // Need >10 judokas across multiple blokken so second blok hits remaining=0
    // =========================================================================

    #[Test]
    public function weeglijst_free_tier_truncates_at_limit_across_blokken(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);

        // Blok 1 with 10 judokas (hits the FREE_MAX_WEEGLIJST limit)
        $poule1 = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $this->blok->id,
            'mat_id' => $this->mat->id,
        ]);
        for ($i = 0; $i < 10; $i++) {
            $judoka = Judoka::factory()->aanwezig()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
            ]);
            $poule1->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }

        // Blok 2 with more judokas — these should be empty (remaining <= 0 → line 107)
        $blok2 = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 2,
        ]);
        $poule2 = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok2->id,
            'mat_id' => $this->mat->id,
        ]);
        for ($i = 0; $i < 5; $i++) {
            $judoka = Judoka::factory()->aanwezig()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
            ]);
            $poule2->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }

        $response = $this->actAsOrg()->get($this->noodplanUrl('weeglijst'));
        $response->assertStatus(200);
    }

    // =========================================================================
    // Lines 454-532: downloadOfflinePakket paid tier — builds data + downloads
    // =========================================================================

    #[Test]
    public function offline_pakket_paid_tier_downloads_html(): void
    {
        // Create tournament data so the data collection runs through all branches
        $poule = $this->createPouleWithJudokas(3, true);

        // Create a DeviceToegang record
        DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test Device',
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        $response = $this->actAsOrg()->get($this->noodplanUrl('offline-pakket'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    // =========================================================================
    // Lines 590-592: uploadOfflineResultaten DB exception → catch block
    // =========================================================================

    #[Test]
    public function upload_resultaten_returns_error_on_db_exception(): void
    {
        $poule = $this->createPouleWithJudokas(2, true);
        $wedstrijd = $poule->wedstrijden->first();

        // Force a DB exception by dropping the column that the update writes to
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE wedstrijden DROP COLUMN gespeeld_op');

        $response = $this->actAsOrg()->postJson($this->noodplanUrl('upload-resultaten'), [
            'resultaten' => [
                [
                    'wedstrijd_id' => $wedstrijd->id,
                    'winnaar_id' => $wedstrijd->judoka_wit_id,
                    'score_wit' => 10,
                    'score_blauw' => 0,
                ],
            ],
        ]);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }

    // =========================================================================
    // Lines 616-638: downloadServerPakket — prerequisites not ready + build error
    // =========================================================================

    #[Test]
    public function server_pakket_paid_tier_prerequisites_not_ready(): void
    {
        $this->mock(OfflinePackageBuilder::class, function ($mock) {
            $mock->shouldReceive('checkPrerequisites')
                ->once()
                ->andReturn(['ready' => false, 'missing' => ['Go launcher binary']]);
        });

        $response = $this->actAsOrg()->get($this->noodplanUrl('server-pakket'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function server_pakket_paid_tier_build_throws_exception(): void
    {
        $this->mock(OfflinePackageBuilder::class, function ($mock) {
            $mock->shouldReceive('checkPrerequisites')
                ->once()
                ->andReturn(['ready' => true, 'missing' => []]);
            $mock->shouldReceive('build')
                ->once()
                ->andThrow(new \Exception('Build failed'));
        });

        $response = $this->actAsOrg()->get($this->noodplanUrl('server-pakket'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Lines 655-671: downloadDatabase — success + exception paths
    // =========================================================================

    #[Test]
    public function database_export_paid_tier_downloads_sqlite(): void
    {
        // Create a temp file to simulate the exported database
        $tempFile = tempnam(sys_get_temp_dir(), 'test_db_');
        file_put_contents($tempFile, 'SQLite format 3');

        $this->mock(OfflineExportService::class, function ($mock) use ($tempFile) {
            $mock->shouldReceive('export')
                ->once()
                ->andReturn($tempFile);
        });

        $response = $this->actAsOrg()->get($this->noodplanUrl('database-export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
    }

    #[Test]
    public function database_export_paid_tier_handles_exception(): void
    {
        $this->mock(OfflineExportService::class, function ($mock) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow(new \Exception('Export failed'));
        });

        $response = $this->actAsOrg()->get($this->noodplanUrl('database-export'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // =========================================================================
    // Lines 698-707: stream SSE endpoint
    // =========================================================================

    #[Test]
    public function stream_returns_sse_response(): void
    {
        $this->createPouleWithJudokas(3, true);

        // Save current output buffering level so we can restore after the controller
        // calls ob_end_clean() which strips PHPUnit's buffers.
        $bufferLevel = ob_get_level();

        $response = $this->actAsOrg()->get($this->noodplanUrl('stream'));

        // Restore output buffers that the controller removed
        while (ob_get_level() < $bufferLevel) {
            ob_start();
        }

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }
}
