<?php

namespace Database\Seeders;

use App\Models\Blok;
use App\Models\Club;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Database\Seeder;

/**
 * Seeds a deterministic dataset for the Playwright authenticated e2e flows.
 *
 * Runs against the dedicated e2e SQLite database (via `--env=e2e`), never the
 * developer's dev database. The fixed email/slugs below are what the e2e
 * login seam and the specs assert on, so keep them in sync with
 * e2e/fixtures.ts and the spec files.
 */
class E2eTestSeeder extends Seeder
{
    public const ORGANISATOR_EMAIL = 'e2e@judotoernooi.test';
    public const ORGANISATOR_SLUG = 'e2e-test-organisator';
    public const TOERNOOI_SLUG = 'e2e-test-toernooi';

    /**
     * Fixed device-access codes per volunteer PWA role. Distinct 4-char
     * prefixes (the seam matches on prefix uniqueness). The specs visit
     * /{org}/{toernooi}/toegang/{code} which auto-binds and redirects to the
     * role interface. Keep in sync with e2e/fixtures.ts.
     */
    public const TOEGANG_CODES = [
        'weging' => 'WEGE00000001',
        'mat' => 'MATT00000001',
        'hoofdjury' => 'JURY00000001',
        'spreker' => 'SPRK00000001',
        'dojo' => 'DOJO00000001',
    ];

    public function run(): void
    {
        $organisator = Organisator::factory()->test()->create([
            'naam' => 'E2E Test Organisator',
            'slug' => self::ORGANISATOR_SLUG,
            'email' => self::ORGANISATOR_EMAIL,
        ]);

        // wedstrijddag() backdates the tournament to today so the mat
        // interface (which only shows on match day) renders.
        $toernooi = Toernooi::factory()->wedstrijddag()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'E2E Test Toernooi',
            'slug' => self::TOERNOOI_SLUG,
        ]);

        // The dashboard lists tournaments through the organisator_toernooi pivot
        // (belongsToMany), so the direct organisator_id alone is not enough.
        $organisator->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        $blok = Blok::factory()->ochtend()->create([
            'toernooi_id' => $toernooi->id,
        ]);

        $mat = Mat::factory()->create([
            'toernooi_id' => $toernooi->id,
            'nummer' => 1,
            'naam' => 'Mat 1',
        ]);

        Poule::factory()->metJudokas(5)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'titel' => "Pupillen -28 Poule",
        ]);

        $club = Club::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'E2E Judoclub',
        ]);

        Judoka::factory()->count(5)->aanwezig()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        // Volunteer PWA device-access rows (mat, weging, jurytafel, spreker,
        // dojo). Each is reached via /{org}/{toernooi}/toegang/{code}, which
        // auto-binds the visiting device and redirects to the role interface.
        foreach (self::TOEGANG_CODES as $rol => $code) {
            DeviceToegang::create([
                'toernooi_id' => $toernooi->id,
                'rol' => $rol,
                'code' => $code,
                'naam' => 'E2E ' . $rol,
                'mat_nummer' => $rol === 'mat' ? 1 : null,
            ]);
        }

        $this->command?->info(
            'E2E seed klaar: organisator ' . self::ORGANISATOR_EMAIL .
            ', toernooi ' . self::TOERNOOI_SLUG .
            ' (1 blok, 1 mat, 1 poule, 5 judokas, ' . count(self::TOEGANG_CODES) . ' PWA-toegangen).'
        );
    }
}
