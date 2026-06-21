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
use App\Models\Wedstrijd;
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
        // interface (which only shows on match day) renders. dynamischeKlassen()
        // gives it real age/weight categories (mini's 4-6j, pupillen 7-9j) so the
        // seeded judokas actually categorise — without it every judoka is
        // "uncategorised" and poule generation produces nothing.
        $toernooi = Toernooi::factory()->wedstrijddag()->dynamischeKlassen()->create([
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
        // Second mat — target for the poule-move (kleurbeurt) flow test.
        $mat2 = Mat::factory()->create([
            'toernooi_id' => $toernooi->id,
            'nummer' => 2,
            'naam' => 'Mat 2',
        ]);

        $club = Club::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'E2E Judoclub',
        ]);

        // Five deterministic pupillen (8j), same age + sex and clustered weights
        // so they form a single valid poule. Weighed and present, so they count
        // toward standings.
        $jaar = (int) date('Y');
        $judokas = [];
        foreach ([26.0, 27.0, 27.5, 28.0, 28.5] as $i => $gewicht) {
            $judokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
                'naam' => sprintf('Pupil %d, E2E', $i + 1),
                'geboortejaar' => $jaar - 8,
                'geslacht' => 'M',
                'band' => 'geel',
                'gewicht' => $gewicht,
                'gewicht_gewogen' => $gewicht,
                'leeftijdsklasse' => 'pupillen',
                'aanwezigheid' => 'aanwezig',
            ]);
        }

        // A real poule with the five judokas linked and a full round-robin of
        // (unplayed) matches, so the uitslag→standings flow has matches to score.
        // NOTE: the poule-generation flow test deletes+regenerates poules, so the
        // uitslag test must run before it (it does — earlier in the spec file).
        $aantal = count($judokas);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'titel' => 'Pupillen -28 Poule',
            'aantal_judokas' => $aantal,
            'aantal_wedstrijden' => $aantal * ($aantal - 1) / 2,
        ]);
        foreach ($judokas as $pos => $judoka) {
            $poule->judokas()->attach($judoka->id, ['positie' => $pos + 1]);
        }
        $volgorde = 1;
        $rrWedstrijden = [];
        for ($a = 0; $a < $aantal; $a++) {
            for ($b = $a + 1; $b < $aantal; $b++) {
                $rrWedstrijden[] = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $judokas[$a]->id,
                    'judoka_blauw_id' => $judokas[$b]->id,
                    'volgorde' => $volgorde++,
                    'is_gespeeld' => false,
                ]);
            }
        }
        $eersteWedstrijd = $rrWedstrijden[0];

        // Kleurbeurt on mat 1: groen/geel/blauw point at three of this poule's
        // matches. Use unplayed ones ([3..5]) so the uitslag test (which plays
        // [0]) can't disturb this. The poule-move flow test asserts that moving
        // the poule to mat 2 keeps groen (actieve_wedstrijd_id) on mat 1 and
        // clears geel/blauw.
        $mat->update([
            'actieve_wedstrijd_id' => $rrWedstrijden[3]->id,     // groen
            'volgende_wedstrijd_id' => $rrWedstrijden[4]->id,    // geel
            'gereedmaken_wedstrijd_id' => $rrWedstrijden[5]->id, // blauw
        ]);

        // --- Eliminatie bracket (4 judokas) for the winner-advancement flow ---
        // Built via the real EliminatieService so the bracket linkage
        // (volgende_wedstrijd_id + winnaar_naar_slot) is exactly what production
        // produces. 4 judokas → 2 A-group halve-finales + a finale, no byes.
        $elimJudokas = [];
        foreach (range(1, 4) as $n) {
            $elimJudokas[] = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
                'naam' => sprintf('Elim %d, E2E', $n),
                'geboortejaar' => $jaar - 8,
                'geslacht' => 'M',
                'band' => 'geel',
                'gewicht' => 30.0,
                'gewicht_gewogen' => 30.0,
                'leeftijdsklasse' => 'pupillen',
                'aanwezigheid' => 'aanwezig',
            ]);
        }
        $elimPoule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'titel' => 'Pupillen Eliminatie',
            'type' => 'eliminatie',
        ]);
        foreach ($elimJudokas as $pos => $judoka) {
            $elimPoule->judokas()->attach($judoka->id, ['positie' => $pos + 1]);
        }
        app(\App\Services\EliminatieService::class)->genereerBracket(
            $elimPoule,
            array_map(fn ($j) => $j->id, $elimJudokas),
            'dubbel',
        );
        $halveFinales = Wedstrijd::where('poule_id', $elimPoule->id)
            ->where('groep', 'A')->where('ronde', 'halve_finale')
            ->orderBy('bracket_positie')->get();
        $elimFinale = Wedstrijd::where('poule_id', $elimPoule->id)
            ->where('groep', 'A')->where('ronde', 'finale')->first();

        // Expose the dynamic IDs the flow specs need (auto-increment ids aren't
        // known up front). Read by e2e/flows.auth.spec.ts.
        file_put_contents(database_path('e2e-ids.json'), json_encode([
            'pouleId' => $poule->id,
            'wedstrijdId' => $eersteWedstrijd->id,
            'judokaWitId' => $eersteWedstrijd->judoka_wit_id,
            'judokaBlauwId' => $eersteWedstrijd->judoka_blauw_id,
            'blokId' => $blok->id,
            'mat1Id' => $mat->id,
            'mat2Id' => $mat2->id,
            'groenWedstrijdId' => $rrWedstrijden[3]->id,
            'eliminatie' => [
                'pouleId' => $elimPoule->id,
                'finaleId' => $elimFinale?->id,
                'halveFinales' => $halveFinales->map(fn ($w) => [
                    'id' => $w->id,
                    'witId' => $w->judoka_wit_id,
                ])->values()->all(),
            ],
        ]));

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
