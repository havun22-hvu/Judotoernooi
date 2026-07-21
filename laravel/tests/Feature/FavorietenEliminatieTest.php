<?php

namespace Tests\Feature;

use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * De favorieten-tab toont voor eliminatie-poules de status van dat moment
 * (komende partij / medaille / afgevallen), niet de round-robin ranglijst.
 */
class FavorietenEliminatieTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;
    private Poule $poule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id, 'aantal_matten' => 1]);
        Mat::create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $this->poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'type' => 'eliminatie',
        ]);
    }

    private function judoka(string $naam): Judoka
    {
        return Judoka::factory()->create(['toernooi_id' => $this->toernooi->id, 'naam' => $naam]);
    }

    private function postFavorieten(array $ids)
    {
        return $this->postJson(
            "/{$this->org->slug}/{$this->toernooi->slug}/favorieten",
            ['judoka_ids' => $ids]
        );
    }

    private function favoriet(array $json, int $id): ?array
    {
        foreach ($json['poules'] as $poule) {
            foreach ($poule['judokas'] as $j) {
                if ($j['id'] === $id) {
                    return $j;
                }
            }
        }
        return null;
    }

    #[Test]
    public function toont_komende_partij_met_groep_ronde_en_tegenstander(): void
    {
        $abel = $this->judoka('Abel');
        $rival = $this->judoka('Rival');
        $this->poule->judokas()->attach($abel->id, ['positie' => 1]);
        $this->poule->judokas()->attach($rival->id, ['positie' => 2]);

        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => $rival->id,
            'ronde' => 'halve_finale',
            'is_gespeeld' => false,
        ]);

        $json = $this->postFavorieten([$abel->id])->assertOk()->json();
        $elim = $this->favoriet($json, $abel->id)['eliminatie'];

        $this->assertSame('komt', $elim['status']);
        $this->assertSame('A', $elim['groep']);
        $this->assertSame('1/2', $elim['ronde_naam']);
        $this->assertSame('Rival', $elim['tegenstander']['naam']);
        $this->assertNull($elim['eindpositie']);
    }

    #[Test]
    public function tegenstander_onbekend_als_slot_leeg_is(): void
    {
        $abel = $this->judoka('Abel');
        $this->poule->judokas()->attach($abel->id, ['positie' => 1]);

        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => null,
            'ronde' => 'kwartfinale',
            'is_gespeeld' => false,
        ]);

        $elim = $this->favoriet($this->postFavorieten([$abel->id])->assertOk()->json(), $abel->id)['eliminatie'];

        $this->assertSame('komt', $elim['status']);
        $this->assertSame('A', $elim['groep']);
        $this->assertSame('1/4', $elim['ronde_naam']);
        $this->assertNull($elim['tegenstander']);
    }

    #[Test]
    public function toont_eindplaats_bij_medaille_zonder_komende_partij(): void
    {
        $abel = $this->judoka('Abel');
        $this->poule->judokas()->attach($abel->id, ['positie' => 1, 'eindpositie' => 1]);

        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => $this->judoka('Rival')->id,
            'ronde' => 'finale',
            'is_gespeeld' => true,
        ]);

        $elim = $this->favoriet($this->postFavorieten([$abel->id])->assertOk()->json(), $abel->id)['eliminatie'];

        $this->assertSame('medaille', $elim['status']);
        $this->assertSame('1e', $elim['eindpositie']);
    }

    #[Test]
    public function gedeelde_derde_plaats(): void
    {
        $abel = $this->judoka('Abel');
        $andereBrons = $this->judoka('Ander');
        $this->poule->judokas()->attach($abel->id, ['positie' => 3, 'eindpositie' => 3]);
        $this->poule->judokas()->attach($andereBrons->id, ['positie' => 4, 'eindpositie' => 3]);

        $elim = $this->favoriet($this->postFavorieten([$abel->id])->assertOk()->json(), $abel->id)['eliminatie'];

        $this->assertSame('medaille', $elim['status']);
        $this->assertSame('3e (gedeeld)', $elim['eindpositie']);
    }

    #[Test]
    public function afgevallen_toont_groep_en_ronde_van_de_verloren_partij(): void
    {
        $abel = $this->judoka('Abel');
        $rival = $this->judoka('Rival');
        $this->poule->judokas()->attach($abel->id, ['positie' => 5, 'eindpositie' => 5]);
        $this->poule->judokas()->attach($rival->id, ['positie' => 6]);

        // Abel verloor in de B-1/8 finale (winnaar = rival).
        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => $rival->id,
            'ronde' => 'b_achtste_finale',
            'is_gespeeld' => true,
            'winnaar_id' => $rival->id,
        ]);

        $elim = $this->favoriet($this->postFavorieten([$abel->id])->assertOk()->json(), $abel->id)['eliminatie'];

        $this->assertSame('afgevallen', $elim['status']);
        $this->assertSame('B', $elim['groep']);
        $this->assertSame('1/8', $elim['ronde_naam']);
        $this->assertNull($elim['eindpositie']);
    }

    #[Test]
    public function afgevallen_kiest_de_laatste_verloren_partij_B_na_A(): void
    {
        $abel = $this->judoka('Abel');
        $rival = $this->judoka('Rival');
        $this->poule->judokas()->attach($abel->id, ['positie' => 5, 'eindpositie' => 5]);
        $this->poule->judokas()->attach($rival->id, ['positie' => 6]);

        // Eerst verloren in de A-kwartfinale (zakt naar B), daarna definitief in de B-halve finale.
        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => $rival->id,
            'ronde' => 'kwartfinale',
            'is_gespeeld' => true,
            'winnaar_id' => $rival->id,
        ]);
        Wedstrijd::factory()->create([
            'poule_id' => $this->poule->id,
            'judoka_wit_id' => $abel->id,
            'judoka_blauw_id' => $rival->id,
            'ronde' => 'b_halve_finale',
            'is_gespeeld' => true,
            'winnaar_id' => $rival->id,
        ]);

        $elim = $this->favoriet($this->postFavorieten([$abel->id])->assertOk()->json(), $abel->id)['eliminatie'];

        $this->assertSame('afgevallen', $elim['status']);
        $this->assertSame('B', $elim['groep']);
        $this->assertSame('1/2', $elim['ronde_naam']);
    }

    #[Test]
    public function round_robin_poule_krijgt_geen_eliminatie_info(): void
    {
        $rrPoule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id, 'type' => 'voorronde']);
        $judoka = $this->judoka('RR');
        $rrPoule->judokas()->attach($judoka->id, ['positie' => 1]);

        $elim = $this->favoriet($this->postFavorieten([$judoka->id])->assertOk()->json(), $judoka->id)['eliminatie'];

        $this->assertNull($elim);
    }
}
