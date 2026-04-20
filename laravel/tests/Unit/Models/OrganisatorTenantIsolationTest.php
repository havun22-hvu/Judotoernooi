<?php

namespace Tests\Unit\Models;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant-isolation at the model level. `hasAccessToToernooi` and
 * `ownsToernooi` are the gate that every tenant-scoped route relies on
 * (via `CheckToernooiRol` / `CheckRolSessie` middleware). A regression
 * here = organisator A reading organisator B's toernooi, judokas,
 * scores.
 *
 * These tests use a direct model check (no HTTP) so the contract is
 * pinned independent of routing changes. The accompanying middleware
 * tests prove the gate is actually CHECKED by the HTTP layer; these
 * prove the gate itself is correct.
 */
class OrganisatorTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organisator_cannot_access_someone_elses_toernooi(): void
    {
        $orgA = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $orgB = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $toernooiOfB = Toernooi::factory()->create(['organisator_id' => $orgB->id]);
        $orgB->toernooien()->attach($toernooiOfB->id, ['rol' => 'eigenaar']);

        // orgA should NOT see orgB's toernooi regardless of organisator_id on
        // the toernooi — the check is pivot-based.
        $this->assertFalse(
            $orgA->hasAccessToToernooi($toernooiOfB),
            'Cross-tenant access must be denied — pivot row is the only truth'
        );
        $this->assertFalse(
            $orgA->ownsToernooi($toernooiOfB),
            'ownsToernooi must require an eigenaar pivot row, not organisator_id'
        );
    }

    public function test_organisator_can_access_own_toernooi(): void
    {
        $org = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        $this->assertTrue($org->hasAccessToToernooi($toernooi));
        $this->assertTrue($org->ownsToernooi($toernooi));
    }

    public function test_beheerder_has_access_but_does_not_own(): void
    {
        // `rol` enum in the organisator_toernooi pivot is ['eigenaar',
        // 'beheerder'] — beheerder is the shared-access role that is NOT
        // owner.
        $eigenaar = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $beheerder = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $eigenaar->id]);
        $eigenaar->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
        $beheerder->toernooien()->attach($toernooi->id, ['rol' => 'beheerder']);

        $this->assertTrue(
            $beheerder->hasAccessToToernooi($toernooi),
            'Shared beheer is the whole point of a pivot row — beheerder must have access'
        );
        $this->assertFalse(
            $beheerder->ownsToernooi($toernooi),
            'Only eigenaar-rol grants ownership'
        );
    }

    public function test_sitebeheerder_has_access_to_every_toernooi(): void
    {
        $sitebeheerder = Organisator::factory()->create(['is_sitebeheerder' => true]);
        $otherOrg = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $stranger = Toernooi::factory()->create(['organisator_id' => $otherOrg->id]);

        $this->assertTrue(
            $sitebeheerder->hasAccessToToernooi($stranger),
            'Sitebeheerder is the support-override — must bypass the pivot check'
        );
    }

    public function test_organisator_id_on_toernooi_alone_does_not_grant_access(): void
    {
        // This is the subtle case: the Toernooi has an `organisator_id` FK
        // but the pivot row is missing. Historical data without a pivot
        // migration must NOT leak access.
        $org = Organisator::factory()->create(['is_sitebeheerder' => false]);
        $orphanToernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        // intentionally NO attach()

        $this->assertFalse(
            $org->hasAccessToToernooi($orphanToernooi),
            'pivot is the only truth — `organisator_id` on the toernooi '
            . 'row is legacy convenience, not authorization'
        );
    }
}
