<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unlocks the judoka-management skeleton that was left with five
 * `markTestIncomplete` placeholders during VP-17 reconstruction
 * (2026-04-19). Rewrites them as real Feature tests following the
 * established BlokControllerTest pattern:
 *
 *  - Organisator + Toernooi factories
 *  - organisator_toernooi pivot (required by `hasAccessToToernooi`)
 *  - `actingAs($org, 'organisator')` via the organisator auth-guard
 *  - Route-based URL building (`/{org->slug}/toernooi/{toernooi->slug}/judoka`)
 *
 * Scope stays intentionally on CRUD-validation and authorization —
 * the rich poule/wedstrijd-scheduling integrations live in their own
 * Feature test-files.
 */
class JudokaManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function toernooiPath(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}"
            . ($suffix ? "/{$suffix}" : '');
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    public function test_organisator_can_view_judoka_index_page(): void
    {
        $response = $this->actAsOrg()->get($this->toernooiPath('judoka'));

        $this->assertContains(
            $response->status(),
            [200, 302],
            'Index should render (200) or redirect to the first tab (302) — '
            . 'but never 403/404 for the rightful organisator'
        );
    }

    public function test_judoka_store_rejects_missing_naam(): void
    {
        $response = $this->actAsOrg()->post($this->toernooiPath('judoka'), [
            'club_id' => null,
            'geboortejaar' => 2010,
        ]);

        $response->assertSessionHasErrors('naam');
        $this->assertSame(0, Judoka::count(), 'No judoka may be stored when naam is missing');
    }

    public function test_judoka_store_rejects_gewicht_below_10_kg(): void
    {
        $response = $this->actAsOrg()->post($this->toernooiPath('judoka'), [
            'naam' => 'Test Judoka',
            'gewicht' => 5, // Below the min:10 rule
        ]);

        $response->assertSessionHasErrors('gewicht');
        $this->assertSame(0, Judoka::count());
    }

    public function test_judoka_store_rejects_gewicht_above_200_kg(): void
    {
        $response = $this->actAsOrg()->post($this->toernooiPath('judoka'), [
            'naam' => 'Test Judoka',
            'gewicht' => 250, // Above the max:200 rule
        ]);

        $response->assertSessionHasErrors('gewicht');
        $this->assertSame(0, Judoka::count());
    }

    public function test_judoka_store_rejects_invalid_geslacht(): void
    {
        $response = $this->actAsOrg()->post($this->toernooiPath('judoka'), [
            'naam' => 'Test Judoka',
            'geslacht' => 'X', // Only 'M' or 'V' allowed
        ]);

        $response->assertSessionHasErrors('geslacht');
    }

    public function test_judoka_store_rejects_club_id_that_does_not_exist(): void
    {
        $response = $this->actAsOrg()->post($this->toernooiPath('judoka'), [
            'naam' => 'Test Judoka',
            'club_id' => 999999, // Does not exist
        ]);

        $response->assertSessionHasErrors('club_id');
    }

    public function test_unauthenticated_user_cannot_store_judoka(): void
    {
        // No actingAs() — must not reach the controller.
        $response = $this->post($this->toernooiPath('judoka'), [
            'naam' => 'Guest Attempt',
        ]);

        // Redirect to login (302) is the expected behaviour for an unauthenticated
        // write attempt under `auth:organisator` middleware.
        $response->assertStatus(302);
        $this->assertSame(0, Judoka::count(), 'Unauthenticated POST must not create records');
    }
}
