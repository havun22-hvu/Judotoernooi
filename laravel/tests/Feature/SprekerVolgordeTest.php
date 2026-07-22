<?php

namespace Tests\Feature;

use App\Http\Controllers\BlokSprekerController;
use App\Models\Blok;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De spreker toont afgeronde poules op **afrondtijd** (spreker_klaar, oudste bovenaan =
 * langst wachtend), NIET op poule-nummer.
 *
 * Valkuil: `Toernooi::poules()` heeft een default `orderBy('nummer')`. Een gewone
 * `->orderBy('spreker_klaar')` erbovenop degradeert de tijd tot tiebreak → de spreker
 * sorteert dan op nummer en een later-afgeronde poule met een lager nummer "dringt voor".
 * De fix is `->reorder('spreker_klaar', 'asc')`, die de nummer-sortering wist.
 *
 * DO NOT REMOVE — dit brak de spreker-volgorde op staging (22-07-2026).
 */
class SprekerVolgordeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spreker_orders_finished_poules_by_completion_time_not_number(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);

        // Hóger nummer, maar EERDER afgerond → hoort bovenaan (langst wachtend).
        $vroeg = Poule::factory()->create([
            'toernooi_id' => $toernooi->id, 'blok_id' => $blok->id, 'mat_id' => $mat->id,
            'nummer' => 9, 'spreker_klaar' => now()->subHour(), 'afgeroepen_at' => null,
        ]);
        // Láger nummer, maar LATER afgerond → hoort eronder. Zonder reorder() dringt deze voor.
        $laat = Poule::factory()->create([
            'toernooi_id' => $toernooi->id, 'blok_id' => $blok->id, 'mat_id' => $mat->id,
            'nummer' => 3, 'spreker_klaar' => now(), 'afgeroepen_at' => null,
        ]);

        $view = app(BlokSprekerController::class)->sprekerInterface($org, $toernooi);
        $volgorde = $view->getData()['klarePoules']->pluck('id')->all();

        $this->assertSame(
            [$vroeg->id, $laat->id],
            $volgorde,
            'Spreker moet eerst-afgeronde poule (langst wachtend) bovenaan tonen, niet op poule-nummer.'
        );
    }
}
