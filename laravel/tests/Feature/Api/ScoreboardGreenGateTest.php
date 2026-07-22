<?php

namespace Tests\Feature\Api;

use App\Models\Blok;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Groene-vlag gate voor de JudoScoreBoard API.
 *
 * De app mag via /api/scoreboard/result alléén de groene (actieve) wedstrijd scoren.
 * Een niet-groene POST ontstaat door timing (verschoven beurt / late offline-flush) en
 * moet geweigerd worden vóór enige write — anders draait verwerkUitslag de bracket-
 * doorschuif voor een verkeerde wedstrijd. green-check laat de app dit vooraf zien.
 *
 * Contract: .claude/plan-scoreboard-groen-gate.md
 */
class ScoreboardGreenGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Toernooi met één mat, een groene wedstrijd + een tweede (niet-groene) wedstrijd
     * en een gebonden scoreboard-device.
     *
     * @return array{toernooi: Toernooi, mat: Mat, groen: Wedstrijd, ander: Wedstrijd, token: string}
     */
    private function maakOpstelling(int $matNummer = 1): array
    {
        $toernooi = Toernooi::factory()->create();
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $matNummer]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        $maakWedstrijd = fn () => Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => Judoka::factory()->create(['toernooi_id' => $toernooi->id])->id,
            'judoka_blauw_id' => Judoka::factory()->create(['toernooi_id' => $toernooi->id])->id,
            'is_gespeeld' => false,
        ]);

        $groen = $maakWedstrijd();
        $ander = $maakWedstrijd();
        $mat->update(['actieve_wedstrijd_id' => $groen->id]);

        $token = DeviceToegang::generateDeviceToken();
        DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Tafel',
            'telefoon' => '0600000000',
            'email' => 'tafel@example.test',
            'rol' => 'scoreboard',
            'mat_nummer' => $matNummer,
            'api_token' => $token,
        ]);

        return compact('toernooi', 'mat', 'groen', 'ander', 'token');
    }

    // ---- green-check -------------------------------------------------------

    public function test_green_check_reports_true_for_the_active_match(): void
    {
        $o = $this->maakOpstelling();

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->getJson('/api/scoreboard/green-check?wedstrijd_id=' . $o['groen']->id)
            ->assertOk()
            ->assertJson([
                'groen' => true,
                'actieve_wedstrijd_id' => $o['groen']->id,
            ]);
    }

    public function test_green_check_reports_false_and_returns_the_current_green_match(): void
    {
        $o = $this->maakOpstelling();

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->getJson('/api/scoreboard/green-check?wedstrijd_id=' . $o['ander']->id)
            ->assertOk()
            ->assertJson([
                'groen' => false,
                'reden' => 'niet_groen',
                'actieve_wedstrijd_id' => $o['groen']->id,
                'match' => ['id' => $o['groen']->id],
            ]);
    }

    public function test_green_check_reports_no_active_match_when_the_mat_is_empty(): void
    {
        $o = $this->maakOpstelling();
        $o['mat']->update(['actieve_wedstrijd_id' => null]);

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->getJson('/api/scoreboard/green-check?wedstrijd_id=' . $o['groen']->id)
            ->assertOk()
            ->assertJson([
                'groen' => false,
                'reden' => 'geen_actieve_wedstrijd',
                'actieve_wedstrijd_id' => null,
                'match' => null,
            ]);
    }

    public function test_green_check_rejects_a_match_from_another_tournament(): void
    {
        $eigen = $this->maakOpstelling();
        $vreemd = $this->maakOpstelling(2);

        $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->getJson('/api/scoreboard/green-check?wedstrijd_id=' . $vreemd['groen']->id)
            ->assertNotFound();
    }

    // ---- result()-gate -----------------------------------------------------

    public function test_result_rejects_a_non_green_match_without_writing(): void
    {
        $o = $this->maakOpstelling();

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->postJson('/api/scoreboard/result', [
                'wedstrijd_id' => $o['ander']->id,
                'winnaar_id' => $o['ander']->judoka_wit_id,
                'uitslag_type' => 'ippon',
            ])
            ->assertStatus(409)
            ->assertJson([
                'error' => 'niet_groen',
                'actieve_wedstrijd_id' => $o['groen']->id,
            ]);

        // Geen write, geen doorschuif.
        $this->assertDatabaseHas('wedstrijden', [
            'id' => $o['ander']->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);
    }

    public function test_result_rejects_an_already_played_non_green_match(): void
    {
        $o = $this->maakOpstelling();
        // De niet-groene wedstrijd is al gespeeld — een correctie hoort via het web,
        // niet via deze endpoint. De gate weigert 'm ongeacht is_gespeeld.
        $o['ander']->update(['is_gespeeld' => true, 'winnaar_id' => $o['ander']->judoka_wit_id]);

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->postJson('/api/scoreboard/result', [
                'wedstrijd_id' => $o['ander']->id,
                'winnaar_id' => $o['ander']->judoka_blauw_id,
                'uitslag_type' => 'ippon',
            ])
            ->assertStatus(409)
            ->assertJson(['error' => 'niet_groen']);

        // Oude uitslag ongewijzigd.
        $this->assertDatabaseHas('wedstrijden', [
            'id' => $o['ander']->id,
            'winnaar_id' => $o['ander']->judoka_wit_id,
        ]);
    }

    public function test_result_accepts_the_green_match(): void
    {
        $o = $this->maakOpstelling();

        $this->withHeader('Authorization', 'Bearer ' . $o['token'])
            ->postJson('/api/scoreboard/result', [
                'wedstrijd_id' => $o['groen']->id,
                'winnaar_id' => $o['groen']->judoka_wit_id,
                'uitslag_type' => 'ippon',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('wedstrijden', [
            'id' => $o['groen']->id,
            'is_gespeeld' => true,
            'winnaar_id' => $o['groen']->judoka_wit_id,
        ]);
    }
}
