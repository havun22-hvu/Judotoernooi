<?php

namespace Tests\Feature;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleToegangTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
    }

    // ========================================================================
    // Access via code (/team/{code})
    // ========================================================================

    #[Test]
    public function access_with_valid_hoofdjury_code(): void
    {
        $code = $this->toernooi->code_hoofdjury;
        $response = $this->get("/team/{$code}");
        $this->assertContains($response->status(), [200, 302]);
    }

    #[Test]
    public function access_with_valid_weging_code(): void
    {
        $code = $this->toernooi->code_weging;
        $response = $this->get("/team/{$code}");
        $this->assertContains($response->status(), [200, 302]);
    }

    #[Test]
    public function access_with_valid_mat_code(): void
    {
        $code = $this->toernooi->code_mat;
        $response = $this->get("/team/{$code}");
        $this->assertContains($response->status(), [200, 302]);
    }

    #[Test]
    public function access_with_valid_spreker_code(): void
    {
        $code = $this->toernooi->code_spreker;
        $response = $this->get("/team/{$code}");
        $this->assertContains($response->status(), [200, 302]);
    }

    #[Test]
    public function access_with_valid_dojo_code(): void
    {
        $code = $this->toernooi->code_dojo;
        $response = $this->get("/team/{$code}");
        $this->assertContains($response->status(), [200, 302]);
    }

    #[Test]
    public function access_with_invalid_code_returns_404(): void
    {
        $response = $this->get('/team/invalid_code_xyz_does_not_exist');
        $response->assertStatus(404);
    }

    // ========================================================================
    // Session-based interface pages (no session = redirect or 403)
    // ========================================================================

    #[Test]
    public function weging_interface_requires_session(): void
    {
        $response = $this->get('/weging');
        $this->assertContains($response->status(), [302, 403]);
    }

    #[Test]
    public function mat_interface_requires_session(): void
    {
        $response = $this->get('/mat');
        $this->assertContains($response->status(), [302, 403]);
    }

    #[Test]
    public function jury_interface_requires_session(): void
    {
        $response = $this->get('/jury');
        $this->assertContains($response->status(), [302, 403]);
    }

    #[Test]
    public function spreker_interface_requires_session(): void
    {
        $response = $this->get('/spreker');
        $this->assertContains($response->status(), [302, 403]);
    }
}
