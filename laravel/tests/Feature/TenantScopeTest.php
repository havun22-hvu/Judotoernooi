<?php

namespace Tests\Feature;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards EnsureToernooiScope: a logged-in organisator must not be able to reach
 * a child resource (judoka/poule/...) of ANOTHER tournament by putting a foreign
 * id in their own tournament URL.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function url(string $suffix): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/{$suffix}";
    }

    #[Test]
    public function foreign_judoka_via_own_tournament_url_is_blocked(): void
    {
        $otherToernooi = Toernooi::factory()->create();
        $foreignJudoka = Judoka::factory()->create(['toernooi_id' => $otherToernooi->id]);

        $this->actingAs($this->org, 'organisator')
            ->get($this->url("judoka/{$foreignJudoka->id}/edit"))
            ->assertForbidden();
    }

    #[Test]
    public function foreign_poule_delete_via_own_tournament_url_is_blocked(): void
    {
        $otherToernooi = Toernooi::factory()->create();
        $foreignPoule = Poule::factory()->create(['toernooi_id' => $otherToernooi->id]);

        $this->actingAs($this->org, 'organisator')
            ->deleteJson($this->url("poule/{$foreignPoule->id}"))
            ->assertForbidden();

        // The foreign poule must still exist — the request never reached the controller.
        $this->assertDatabaseHas('poules', ['id' => $foreignPoule->id]);
    }

    #[Test]
    public function own_poule_is_not_blocked_by_the_scope_guard(): void
    {
        $ownPoule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        // Own child passes the scope guard (reaches the controller — any non-404 proves it).
        $this->actingAs($this->org, 'organisator')
            ->deleteJson($this->url("poule/{$ownPoule->id}"))
            ->assertStatus(200);
    }
}
