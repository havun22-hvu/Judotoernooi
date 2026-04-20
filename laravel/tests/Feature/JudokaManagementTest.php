<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconstruction skeleton 2026-04-19 — was dropped in commit f01b04
 * (VP-17 violation). Each test is restored as `markTestIncomplete` with
 * the original intent + a TODO so the work is VISIBLE in qv:scan
 * --only=test-erosion instead of silently disappearing.
 *
 * Reactivate per test: implement the missing factory/middleware setup,
 * then drop the markTestIncomplete line.
 */
class JudokaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organisator_can_view_judoka_list(): void
    {
        $this->markTestIncomplete(
            'TODO restore: setUp() must create Organisator + Toernooi + '
            . 'pivot row in organisator_toernooi (Organisator::hasAccessToToernooi '
            . 'checks pivot, not organisator_id). Then GET '
            . 'route("toernooi.judoka.index", [organisator, toernooi]) → 200.'
        );
    }

    public function test_organisator_can_create_judoka(): void
    {
        $this->markTestIncomplete(
            'TODO restore: idem setUp + assert POST route("toernooi.judoka.store") '
            . 'with valid payload (naam/voornaam/achternaam/geboortejaar/'
            . 'geslacht/band/gewicht/club_id) creates DB row + redirect.'
        );
    }

    public function test_organisator_can_update_judoka(): void
    {
        $this->markTestIncomplete(
            'TODO restore: PATCH route("toernooi.judoka.update", '
            . '[organisator, toernooi, judoka]) with new naam → DB updated.'
        );
    }

    public function test_judoka_creation_validates_required_fields(): void
    {
        $this->markTestIncomplete(
            'TODO restore: POST without naam → assertSessionHasErrors("naam"). '
            . 'Verifieer current required-rules in JudokaController/JudokaRequest.'
        );
    }

    public function test_judoka_weight_must_be_within_valid_range(): void
    {
        $this->markTestIncomplete(
            'TODO restore: POST with gewicht=5 → assertSessionHasErrors("gewicht"). '
            . 'Min/max range mogelijk gewijzigd sinds 2026-02 — controleer rules.'
        );
    }
}
