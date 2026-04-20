<?php

namespace Tests\Feature;

use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unlocks the scoring skeleton that was left as `markTestIncomplete`
 * placeholders during VP-17 reconstruction (20-04 sessie). The
 * original plan was HTTP-level score-update Feature tests, but JT
 * doesn't expose a single `score.update` endpoint — the score
 * registration is a model method (`Wedstrijd::registreerUitslag`)
 * called from multiple controllers (MatController, score-submit flow).
 *
 * Testing that model method directly is the right scope: it's the
 * atomic unit of "a score gets recorded". Any controller that calls
 * it benefits; mocking the HTTP layer would just re-test Laravel's
 * routing and not the real logic.
 *
 * Each test creates a played wedstrijd with known judoka IDs, calls
 * `registreerUitslag`, and asserts that the wit/blauw scores landed
 * on the correct side based on who won.
 */
class ScoreRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registreer_uitslag_sets_winnaar_and_is_gespeeld(): void
    {
        $wedstrijd = Wedstrijd::factory()->create();
        $this->assertFalse($wedstrijd->is_gespeeld);

        $wedstrijd->registreerUitslag(
            winnaarId: $wedstrijd->judoka_wit_id,
            scoreWinnaar: '2',
            scoreVerliezer: '0',
            type: 'ippon',
        );
        $wedstrijd->refresh();

        $this->assertTrue($wedstrijd->is_gespeeld);
        $this->assertSame($wedstrijd->judoka_wit_id, $wedstrijd->winnaar_id);
        $this->assertNotNull($wedstrijd->gespeeld_op);
        $this->assertSame('ippon', $wedstrijd->uitslag_type);
    }

    public function test_score_assigned_to_correct_side_when_wit_wins(): void
    {
        $wedstrijd = Wedstrijd::factory()->create();

        $wedstrijd->registreerUitslag(
            winnaarId: $wedstrijd->judoka_wit_id,
            scoreWinnaar: '2',
            scoreVerliezer: '1',
            type: 'beslissing',
        );
        $wedstrijd->refresh();

        $this->assertSame('2', $wedstrijd->score_wit);
        $this->assertSame('1', $wedstrijd->score_blauw);
    }

    public function test_score_assigned_to_correct_side_when_blauw_wins(): void
    {
        $wedstrijd = Wedstrijd::factory()->create();

        $wedstrijd->registreerUitslag(
            winnaarId: $wedstrijd->judoka_blauw_id,
            scoreWinnaar: '2',
            scoreVerliezer: '0',
            type: 'waza-ari',
        );
        $wedstrijd->refresh();

        $this->assertSame('0', $wedstrijd->score_wit);
        $this->assertSame('2', $wedstrijd->score_blauw);
    }

    public function test_is_echt_gespeeld_true_only_when_winnaar_set(): void
    {
        // Wedstrijd is_gespeeld with a winnaar = echt gespeeld.
        $played = Wedstrijd::factory()->create();
        $played->registreerUitslag(
            winnaarId: $played->judoka_wit_id,
            scoreWinnaar: '1',
            scoreVerliezer: '0',
            type: 'wazari',
        );
        $played->refresh();

        $this->assertTrue($played->isEchtGespeeld());
        $this->assertFalse($played->isGelijk());
    }

    public function test_is_gelijk_detects_drawn_match(): void
    {
        // A drawn match has is_gespeeld=true but winnaar_id=null.
        $drawn = Wedstrijd::factory()->create([
            'is_gespeeld' => true,
            'winnaar_id' => null,
            'score_wit' => '0',
            'score_blauw' => '0',
            'uitslag_type' => 'gelijk',
            'gespeeld_op' => now(),
        ]);

        $this->assertTrue($drawn->isGelijk());
        $this->assertFalse($drawn->isEchtGespeeld());
    }

    public function test_unplayed_wedstrijd_is_neither_gelijk_nor_echt_gespeeld(): void
    {
        $unplayed = Wedstrijd::factory()->create();

        $this->assertFalse($unplayed->is_gespeeld);
        $this->assertFalse($unplayed->isGelijk());
        $this->assertFalse($unplayed->isEchtGespeeld());
    }

    public function test_verliezer_id_returns_opponent_after_registration(): void
    {
        $wedstrijd = Wedstrijd::factory()->create();
        $witId = $wedstrijd->judoka_wit_id;
        $blauwId = $wedstrijd->judoka_blauw_id;

        $wedstrijd->registreerUitslag(
            winnaarId: $witId,
            scoreWinnaar: '2',
            scoreVerliezer: '0',
            type: 'ippon',
        );
        $wedstrijd->refresh();

        $this->assertSame($blauwId, $wedstrijd->getVerliezerId());
    }

    public function test_verliezer_id_returns_null_on_unplayed_wedstrijd(): void
    {
        $wedstrijd = Wedstrijd::factory()->create();

        $this->assertNull($wedstrijd->getVerliezerId());
    }
}
