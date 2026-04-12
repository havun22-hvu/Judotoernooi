<?php

namespace Tests\Feature;

use App\Models\Organisator;
use App\Models\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Organisator
    {
        return Organisator::factory()->sitebeheerder()->create();
    }

    private function createKlant(): Organisator
    {
        return Organisator::factory()->create();
    }

    // ========================================================================
    // Index
    // ========================================================================

    #[Test]
    public function index_requires_sitebeheerder(): void
    {
        $org = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('admin.alerts'));
        $response->assertStatus(403);
    }

    #[Test]
    public function index_loads_for_sitebeheerder(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        SystemAlert::fire('security', 'high', 'Test alert');

        $response = $this->get(route('admin.alerts'));
        $response->assertStatus(200);
    }

    #[Test]
    public function index_filters_by_type(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        SystemAlert::fire('security', 'high', 'Security alert');
        SystemAlert::fire('autofix', 'low', 'AutoFix alert');

        $response = $this->get(route('admin.alerts', ['type' => 'security']));
        $response->assertStatus(200);
    }

    #[Test]
    public function index_filters_unread(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $read = SystemAlert::fire('security', 'high', 'Read alert');
        $read->update(['is_read' => true]);
        SystemAlert::fire('security', 'high', 'Unread alert');

        $response = $this->get(route('admin.alerts', ['unread' => '1']));
        $response->assertStatus(200);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_alerts(): void
    {
        $response = $this->get(route('admin.alerts'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Mark Read
    // ========================================================================

    #[Test]
    public function mark_read_updates_alert(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        $alert = SystemAlert::fire('security', 'high', 'Test alert');
        $alert->refresh();
        $this->assertFalse($alert->is_read);

        $response = $this->post(route('admin.alerts.markRead', $alert));
        $response->assertRedirect();

        $alert->refresh();
        $this->assertTrue($alert->is_read);
    }

    #[Test]
    public function mark_read_forbidden_for_non_admin(): void
    {
        $org = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $alert = SystemAlert::fire('security', 'high', 'Test alert');

        $response = $this->post(route('admin.alerts.markRead', $alert));
        $response->assertStatus(403);
    }

    // ========================================================================
    // Mark All Read
    // ========================================================================

    #[Test]
    public function mark_all_read_updates_all_alerts(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'organisator');

        SystemAlert::fire('security', 'high', 'Alert 1');
        SystemAlert::fire('autofix', 'low', 'Alert 2');

        $this->assertEquals(2, SystemAlert::unread()->count());

        $response = $this->post(route('admin.alerts.markAllRead'));
        $response->assertRedirect();

        $this->assertEquals(0, SystemAlert::unread()->count());
    }

    #[Test]
    public function mark_all_read_forbidden_for_non_admin(): void
    {
        $org = $this->createKlant();
        $this->actingAs($org, 'organisator');

        $response = $this->post(route('admin.alerts.markAllRead'));
        $response->assertStatus(403);
    }
}
