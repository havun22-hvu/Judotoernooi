<?php

namespace Tests\Feature;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconstruction skeleton 2026-04-19 — was dropped in commit f01b04
 * (VP-17 violation). See JudokaManagementTest header for the
 * placeholder rationale.
 */
class ScoreRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_registration_validates_score_values(): void
    {
        $this->markTestIncomplete(
            'TODO restore: setUp() needs Organisator + Toernooi + Poule + '
            . 'Wedstrijd + 2 Judokas (wit/blauw). Verify score-validation '
            . 'rejects negative or non-integer values via the score-update endpoint.'
        );
    }

    public function test_valid_scores_are_accepted(): void
    {
        $this->markTestIncomplete(
            'TODO restore: POST score with valid wit/blauw values and assert '
            . 'Wedstrijd row updated with the scores + winner determination.'
        );
    }

    public function test_score_zero_is_valid(): void
    {
        $this->markTestIncomplete(
            'TODO restore: 0-0 should be accepted (legitimate result before '
            . 'overtime/golden-score) — assert no validation error.'
        );
    }

    public function test_wedstrijd_id_must_exist(): void
    {
        $this->markTestIncomplete(
            'TODO restore: POST score with non-existent wedstrijd_id → '
            . '404 (or sessionHasErrors). Tests resource-binding integrity.'
        );
    }
}
