<?php

namespace Tests\Feature;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiControllerTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Dashboard
    // ========================================================================

    #[Test]
    public function organisator_dashboard_requires_authentication(): void
    {
        $org = Organisator::factory()->create();

        $response = $this->get("/{$org->slug}/dashboard");

        $response->assertRedirect();
    }

    #[Test]
    public function organisator_dashboard_loads_for_owner(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/dashboard");

        $response->assertStatus(200);
    }

    #[Test]
    public function organisator_cannot_access_other_organisator_dashboard(): void
    {
        $org1 = Organisator::factory()->create();
        $org2 = Organisator::factory()->create();
        $this->actingAs($org1, 'organisator');

        $response = $this->get("/{$org2->slug}/dashboard");

        $response->assertStatus(403);
    }

    #[Test]
    public function sitebeheerder_can_access_any_dashboard(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $org = Organisator::factory()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->get("/{$org->slug}/dashboard");

        $response->assertStatus(200);
    }

    // ========================================================================
    // Toernooi Create
    // ========================================================================

    #[Test]
    public function create_toernooi_page_loads(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/toernooi/nieuw");

        $response->assertStatus(200);
    }

    // NOTE: POST tests skipped — staging runs against MySQL with different
    // middleware behavior than SQLite test env. POST creates/updates need
    // a dedicated test environment setup.

    // ========================================================================
    // Toernooi Show / Edit
    // ========================================================================

    #[Test]
    public function toernooi_show_page_loads_for_owner(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function toernooi_edit_page_loads_for_owner(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $this->actingAs($org, 'organisator');

        $response = $this->get("/{$org->slug}/toernooi/{$toernooi->slug}/edit");

        $response->assertStatus(200);
    }
}
