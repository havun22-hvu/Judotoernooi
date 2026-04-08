<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubControllerTest extends TestCase
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

    // ========================================================================
    // Organisator level - Clubs index
    // ========================================================================

    #[Test]
    public function clubs_index_loads_for_owner(): void
    {
        $this->actingAs($this->org, 'organisator');
        $response = $this->get(route('organisator.clubs.index', ['organisator' => $this->org->slug]));
        $response->assertStatus(200);
    }

    #[Test]
    public function clubs_index_creates_default_club(): void
    {
        $this->actingAs($this->org, 'organisator');
        $this->get(route('organisator.clubs.index', ['organisator' => $this->org->slug]));

        $this->assertDatabaseHas('clubs', [
            'organisator_id' => $this->org->id,
            'naam' => $this->org->naam,
        ]);
    }

    #[Test]
    public function clubs_index_forbidden_for_other_user(): void
    {
        $other = Organisator::factory()->create();
        $this->actingAs($other, 'organisator');

        $response = $this->get(route('organisator.clubs.index', ['organisator' => $this->org->slug]));
        $response->assertStatus(403);
    }

    #[Test]
    public function sitebeheerder_can_access_any_clubs(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->get(route('organisator.clubs.index', ['organisator' => $this->org->slug]));
        $response->assertStatus(200);
    }

    // ========================================================================
    // Store club
    // ========================================================================

    #[Test]
    public function store_club_creates_record(): void
    {
        $this->actingAs($this->org, 'organisator');

        $response = $this->post(route('organisator.clubs.store', ['organisator' => $this->org->slug]), [
            'naam' => 'Test Club',
            'email' => 'club@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clubs', [
            'organisator_id' => $this->org->id,
            'naam' => 'Test Club',
        ]);
    }

    // ========================================================================
    // Update club
    // ========================================================================

    #[Test]
    public function update_club_updates_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->put(route('organisator.clubs.update', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]), [
            'naam' => 'Updated Club',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'naam' => 'Updated Club',
        ]);
    }

    // ========================================================================
    // Delete club
    // ========================================================================

    #[Test]
    public function delete_club_removes_record(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $response = $this->delete(route('organisator.clubs.destroy', [
            'organisator' => $this->org->slug,
            'club' => $club->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
    }

    // ========================================================================
    // Toernooi level - Club index
    // ========================================================================

    #[Test]
    public function toernooi_club_index_loads(): void
    {
        $this->actingAs($this->org, 'organisator');
        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/club";
        $response = $this->get($url);
        $response->assertStatus(200);
    }

    // ========================================================================
    // Toggle club
    // ========================================================================

    #[Test]
    public function toggle_club_works(): void
    {
        $this->actingAs($this->org, 'organisator');
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/club/{$club->id}/toggle";
        $response = $this->post($url);
        $response->assertRedirect();
    }
}
