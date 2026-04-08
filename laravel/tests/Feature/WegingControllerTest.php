<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WegingControllerTest extends TestCase
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
            'gewicht_tolerantie' => 0.5,
            'max_wegingen' => 2,
        ]);
        // Link organisator to toernooi via pivot for middleware access check
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
    }

    private function actAsOrg(): self
    {
        return $this->actingAs($this->org, 'organisator');
    }

    private function wegingUrl(string $suffix = ''): string
    {
        $base = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging";
        return $base . ($suffix ? "/{$suffix}" : '');
    }

    // ========================================================================
    // Index
    // ========================================================================

    #[Test]
    public function weging_index_loads(): void
    {
        $this->actAsOrg();
        $response = $this->get($this->wegingUrl());
        $response->assertStatus(200);
    }

    #[Test]
    public function weging_index_requires_auth(): void
    {
        $response = $this->get($this->wegingUrl());
        $response->assertRedirect();
    }

    // ========================================================================
    // Registreer gewicht
    // ========================================================================

    #[Test]
    public function registreer_gewicht_succeeds_within_class(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
        ]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/{$judoka->id}/registreer";
        $response = $this->postJson($url, ['gewicht' => 29.0]);
        $response->assertJson(['success' => true, 'binnen_klasse' => true]);
    }

    #[Test]
    public function registreer_gewicht_zero_marks_absent(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
        ]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/{$judoka->id}/registreer";
        $response = $this->postJson($url, ['gewicht' => 0]);
        $response->assertJson(['success' => true, 'afwezig' => true]);
    }

    #[Test]
    public function registreer_gewicht_below_15_returns_error(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'gewichtsklasse' => '-30',
            'gewicht' => 28.5,
        ]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/{$judoka->id}/registreer";
        $response = $this->postJson($url, ['gewicht' => 10]);
        $response->assertStatus(400);
    }

    // ========================================================================
    // Markeer aanwezig / afwezig
    // ========================================================================

    #[Test]
    public function markeer_aanwezig_succeeds(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'onbekend',
        ]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/{$judoka->id}/aanwezig";
        $response = $this->postJson($url);
        $response->assertJson(['success' => true]);
        $judoka->refresh();
        $this->assertEquals('aanwezig', $judoka->aanwezigheid);
    }

    #[Test]
    public function markeer_afwezig_succeeds(): void
    {
        $this->actAsOrg();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'aanwezig',
        ]);

        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/{$judoka->id}/afwezig";
        $response = $this->postJson($url);
        $response->assertJson(['success' => true]);
        $judoka->refresh();
        $this->assertEquals('afwezig', $judoka->aanwezigheid);
    }

    // ========================================================================
    // Interface
    // ========================================================================

    #[Test]
    public function weging_interface_loads(): void
    {
        $this->actAsOrg();
        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/interface";
        $response = $this->get($url);
        $response->assertStatus(200);
    }

    // ========================================================================
    // Lijst JSON
    // ========================================================================

    #[Test]
    public function weging_lijst_json_returns_data(): void
    {
        $this->actAsOrg();
        $url = "/{$this->org->slug}/toernooi/{$this->toernooi->slug}/weging/lijst-json";
        $response = $this->getJson($url);
        $response->assertStatus(200);
    }
}
