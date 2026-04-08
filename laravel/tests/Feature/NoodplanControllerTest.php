<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoodplanControllerTest extends TestCase
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
            'plan_type' => 'paid',
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    private function url(string $suffix = ''): string
    {
        return "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/noodplan" . ($suffix ? "/{$suffix}" : '');
    }

    #[Test]
    public function noodplan_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url());
        $response->assertStatus(200);
    }

    #[Test]
    public function noodplan_requires_auth(): void
    {
        $response = $this->get($this->url());
        $response->assertRedirect();
    }

    #[Test]
    public function weeglijst_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url('weeglijst'));
        $response->assertStatus(200);
    }

    #[Test]
    public function poules_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url('poules'));
        $response->assertStatus(200);
    }

    #[Test]
    public function leeg_schema_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url('leeg-schema/5'));
        $response->assertStatus(200);
    }

    #[Test]
    public function contactlijst_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url('contactlijst'));
        $response->assertStatus(200);
    }

    #[Test]
    public function instellingen_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->url('instellingen'));
        $response->assertStatus(200);
    }

    #[Test]
    public function free_tier_noodplan_loads(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);
        $this->actAsOrg();
        $response = $this->get($this->url());
        $response->assertStatus(200);
    }
}
