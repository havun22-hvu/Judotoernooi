<?php

namespace Tests\Feature;

use App\Models\DeviceToegang;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ToernooiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MattenToegangenSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_mat_toegangen_voegt_toe_en_verwijdert_wezen(): void
    {
        $toernooi = Toernooi::factory()->create(['aantal_matten' => 3]);
        foreach ([1, 2, 3] as $n) {
            Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $n]);
        }
        // Bestaand: toegang voor mat 1 en 2 + een wees (mat 5 bestaat niet).
        foreach ([1, 2, 5] as $n) {
            DeviceToegang::create(['toernooi_id' => $toernooi->id, 'rol' => 'mat', 'mat_nummer' => $n]);
        }

        app(ToernooiService::class)->syncMatToegangen($toernooi);

        $nummers = $toernooi->deviceToegangen()
            ->where('rol', 'mat')->pluck('mat_nummer')->sort()->values()->all();
        $this->assertEquals([1, 2, 3], $nummers, 'mat-toegang 3 erbij, wees 5 weg');
    }

    public function test_lege_alle_matten_haalt_poules_van_de_mat_zonder_ze_te_verwijderen(): void
    {
        $toernooi = Toernooi::factory()->create();
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'mat_id' => $mat->id]);

        app(ToernooiService::class)->legeAlleMatten($toernooi);

        $poule->refresh();
        $this->assertNull($poule->mat_id, 'poule is matloos');
        $this->assertNull($poule->b_mat_id);
        $this->assertDatabaseHas('poules', ['id' => $poule->id]);
    }
}
