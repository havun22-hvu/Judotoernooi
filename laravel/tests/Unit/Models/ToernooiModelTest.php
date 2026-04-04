<?php

namespace Tests\Unit\Models;

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

class ToernooiModelTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Slug Generation
    // ========================================================================

    #[Test]
    public function it_generates_slug_on_create(): void
    {
        $toernooi = Toernooi::factory()->create(['naam' => 'Herfsttoernooi 2026']);

        $this->assertEquals('herfsttoernooi-2026', $toernooi->slug);
    }

    #[Test]
    public function it_generates_unique_slug_within_same_organisator(): void
    {
        $organisator = Organisator::factory()->create();

        $t1 = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'Open Toernooi',
        ]);
        $t2 = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'naam' => 'Open Toernooi',
        ]);

        $this->assertEquals('open-toernooi', $t1->slug);
        $this->assertEquals('open-toernooi-1', $t2->slug);
    }

    #[Test]
    public function same_name_different_organisator_gets_same_slug(): void
    {
        $org1 = Organisator::factory()->create();
        $org2 = Organisator::factory()->create();

        $t1 = Toernooi::factory()->create([
            'organisator_id' => $org1->id,
            'naam' => 'Jeugdtoernooi',
        ]);
        $t2 = Toernooi::factory()->create([
            'organisator_id' => $org2->id,
            'naam' => 'Jeugdtoernooi',
        ]);

        $this->assertEquals('jeugdtoernooi', $t1->slug);
        $this->assertEquals('jeugdtoernooi', $t2->slug);
    }

    #[Test]
    public function slug_updates_when_name_changes(): void
    {
        $toernooi = Toernooi::factory()->create(['naam' => 'Oud Toernooi']);
        $this->assertEquals('oud-toernooi', $toernooi->slug);

        $toernooi->update(['naam' => 'Nieuw Toernooi']);
        $this->assertEquals('nieuw-toernooi', $toernooi->fresh()->slug);
    }

    // ========================================================================
    // Role Codes
    // ========================================================================

    #[Test]
    public function it_generates_role_codes_on_create(): void
    {
        $toernooi = Toernooi::factory()->create();

        $this->assertNotEmpty($toernooi->code_hoofdjury);
        $this->assertNotEmpty($toernooi->code_weging);
        $this->assertNotEmpty($toernooi->code_mat);
        $this->assertNotEmpty($toernooi->code_spreker);
        $this->assertNotEmpty($toernooi->code_dojo);
    }

    #[Test]
    public function role_codes_are_unique_per_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();

        $codes = [
            $toernooi->code_hoofdjury,
            $toernooi->code_weging,
            $toernooi->code_mat,
            $toernooi->code_spreker,
            $toernooi->code_dojo,
        ];

        $this->assertCount(5, array_unique($codes));
    }

    // ========================================================================
    // Route Key & Params
    // ========================================================================

    #[Test]
    public function route_key_name_is_slug(): void
    {
        $toernooi = Toernooi::factory()->create();

        $this->assertEquals('slug', $toernooi->getRouteKeyName());
    }

    #[Test]
    public function route_params_include_organisator_and_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toernooi->load('organisator');

        $params = $toernooi->routeParams();

        $this->assertArrayHasKey('organisator', $params);
        $this->assertArrayHasKey('toernooi', $params);
        $this->assertEquals($toernooi->slug, $params['toernooi']);
    }

    // ========================================================================
    // Pool Size Methods
    // ========================================================================

    #[Test]
    public function default_poule_grootte_voorkeur(): void
    {
        $toernooi = Toernooi::factory()->create(['poule_grootte_voorkeur' => null]);

        $this->assertEquals([5, 4, 6, 3], $toernooi->getPouleGrootteVoorkeurOfDefault());
    }

    #[Test]
    public function min_and_max_judokas_poule_from_preference(): void
    {
        $toernooi = Toernooi::factory()->create(['poule_grootte_voorkeur' => [4, 5, 6]]);

        $this->assertEquals(4, $toernooi->min_judokas_poule);
        $this->assertEquals(6, $toernooi->max_judokas_poule);
    }

    // ========================================================================
    // Status Methods
    // ========================================================================

    #[Test]
    public function inschrijving_open_without_deadline(): void
    {
        $toernooi = Toernooi::factory()->create(['inschrijving_deadline' => null]);

        $this->assertTrue($toernooi->isInschrijvingOpen());
    }

    #[Test]
    public function inschrijving_closed_after_deadline(): void
    {
        $toernooi = Toernooi::factory()->create([
            'inschrijving_deadline' => now()->subDays(2),
        ]);

        $this->assertFalse($toernooi->isInschrijvingOpen());
    }

    #[Test]
    public function is_afgesloten(): void
    {
        $open = Toernooi::factory()->create(['afgesloten_at' => null]);
        $afgesloten = Toernooi::factory()->afgesloten()->create();

        $this->assertFalse($open->isAfgesloten());
        $this->assertTrue($afgesloten->isAfgesloten());
    }

    #[Test]
    public function max_judokas_bereikt(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'max_judokas' => 2,
        ]);
        $club = Club::factory()->create(['organisator_id' => $organisator->id]);

        Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $this->assertFalse($toernooi->isMaxJudokasBereikt());

        Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $this->assertTrue($toernooi->isMaxJudokasBereikt());
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function it_has_organisator_relationship(): void
    {
        $toernooi = Toernooi::factory()->create();

        $this->assertInstanceOf(Organisator::class, $toernooi->organisator);
    }

    #[Test]
    public function it_has_many_judokas(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $club = Club::factory()->create(['organisator_id' => $organisator->id]);

        Judoka::factory()->count(3)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertCount(3, $toernooi->judokas);
    }

    #[Test]
    public function it_has_many_poules(): void
    {
        $toernooi = Toernooi::factory()->create();

        Poule::factory()->count(2)->create(['toernooi_id' => $toernooi->id]);

        $this->assertCount(2, $toernooi->poules);
    }

    // ========================================================================
    // Casts
    // ========================================================================

    #[Test]
    public function gewichtsklassen_is_cast_to_array(): void
    {
        $klassen = ['minis' => ['label' => "Mini's", 'max_leeftijd' => 6]];
        $toernooi = Toernooi::factory()->create(['gewichtsklassen' => $klassen]);

        $this->assertIsArray($toernooi->fresh()->gewichtsklassen);
        $this->assertEquals("Mini's", $toernooi->fresh()->gewichtsklassen['minis']['label']);
    }

    #[Test]
    public function boolean_casts_work_correctly(): void
    {
        $toernooi = Toernooi::factory()->create([
            'weging_verplicht' => 1,
            'betaling_actief' => 0,
        ]);

        $fresh = $toernooi->fresh();
        $this->assertTrue($fresh->weging_verplicht);
        $this->assertFalse($fresh->betaling_actief);
    }
}
