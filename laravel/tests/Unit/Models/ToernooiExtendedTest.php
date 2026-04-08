<?php

namespace Tests\Unit\Models;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
    }

    // ========================================================================
    // Wachtwoord Methods
    // ========================================================================

    #[Test]
    public function set_wachtwoord_stores_hash(): void
    {
        $this->toernooi->setWachtwoord('admin', 'geheim123');
        $this->toernooi->refresh();
        $this->assertNotNull($this->toernooi->wachtwoord_admin);
        $this->assertTrue(password_verify('geheim123', $this->toernooi->wachtwoord_admin));
    }

    #[Test]
    public function check_wachtwoord_returns_true_for_correct(): void
    {
        $this->toernooi->setWachtwoord('jury', 'jury_pass');
        $this->assertTrue($this->toernooi->checkWachtwoord('jury', 'jury_pass'));
    }

    #[Test]
    public function check_wachtwoord_returns_false_for_wrong(): void
    {
        $this->toernooi->setWachtwoord('weging', 'correct');
        $this->assertFalse($this->toernooi->checkWachtwoord('weging', 'wrong'));
    }

    #[Test]
    public function check_wachtwoord_returns_false_for_null(): void
    {
        $this->toernooi->setWachtwoord('mat', 'pass');
        $this->assertFalse($this->toernooi->checkWachtwoord('mat', null));
    }

    #[Test]
    public function check_wachtwoord_returns_false_for_invalid_rol(): void
    {
        $this->assertFalse($this->toernooi->checkWachtwoord('invalid_role', 'pass'));
    }

    #[Test]
    public function check_wachtwoord_returns_false_when_no_hash(): void
    {
        $this->assertFalse($this->toernooi->checkWachtwoord('admin', 'pass'));
    }

    #[Test]
    public function set_wachtwoord_ignores_invalid_rol(): void
    {
        $this->toernooi->setWachtwoord('invalid', 'pass');
        $this->toernooi->refresh();
        // Should not crash and no field changed
        $this->assertNull($this->toernooi->wachtwoord_admin);
    }

    #[Test]
    public function heeft_wachtwoord_returns_true_when_set(): void
    {
        $this->toernooi->setWachtwoord('spreker', 'pass');
        $this->assertTrue($this->toernooi->heeftWachtwoord('spreker'));
    }

    #[Test]
    public function heeft_wachtwoord_returns_false_when_empty(): void
    {
        $this->assertFalse($this->toernooi->heeftWachtwoord('admin'));
    }

    // ========================================================================
    // Match Duration
    // ========================================================================

    #[Test]
    public function match_duration_defaults_to_180(): void
    {
        $this->assertEquals(180, $this->toernooi->getMatchDuration());
    }

    #[Test]
    public function match_duration_finale_defaults_to_240(): void
    {
        $this->assertEquals(240, $this->toernooi->getMatchDurationFinale());
    }

    #[Test]
    public function match_duration_for_categorie_uses_shiai_time(): void
    {
        $this->toernooi->gewichtsklassen = [
            'miniemen_m' => [
                'label' => 'Miniemen M',
                'shiai_time' => 120,
                'gewichten' => ['-24', '-27', '+27'],
            ],
        ];
        $this->toernooi->save();

        $this->assertEquals(120, $this->toernooi->getMatchDurationForCategorie('miniemen_m'));
    }

    #[Test]
    public function match_duration_for_unknown_categorie_falls_back(): void
    {
        $this->assertEquals(180, $this->toernooi->getMatchDurationForCategorie('nonexistent'));
    }

    #[Test]
    public function match_duration_for_null_categorie_falls_back(): void
    {
        $this->assertEquals(180, $this->toernooi->getMatchDurationForCategorie(null));
    }

    #[Test]
    public function match_rules_for_categorie(): void
    {
        $this->toernooi->gewichtsklassen = [
            'cadetten_m' => [
                'label' => 'Cadetten M',
                'shime_waza' => true,
                'kansetsu_waza' => false,
                'gewichten' => ['-55', '-60', '+60'],
            ],
        ];
        $this->toernooi->save();

        $rules = $this->toernooi->getMatchRulesForCategorie('cadetten_m');
        $this->assertTrue($rules['shime_waza']);
        $this->assertFalse($rules['kansetsu_waza']);
    }

    #[Test]
    public function match_rules_for_missing_categorie(): void
    {
        $rules = $this->toernooi->getMatchRulesForCategorie(null);
        $this->assertFalse($rules['shime_waza']);
        $this->assertFalse($rules['kansetsu_waza']);
    }

    // ========================================================================
    // Gewichtsklassen Methods
    // ========================================================================

    #[Test]
    public function alle_gewichtsklassen_filters_metadata_keys(): void
    {
        $this->toernooi->gewichtsklassen = [
            '_meta' => ['version' => 1],
            'miniemen_m' => ['label' => 'Miniemen M', 'max_leeftijd' => 10, 'gewichten' => []],
            'cadetten_m' => ['label' => 'Cadetten M', 'max_leeftijd' => 16, 'gewichten' => []],
        ];
        $this->toernooi->save();

        $result = $this->toernooi->getAlleGewichtsklassen();
        $this->assertArrayNotHasKey('_meta', $result);
        $this->assertArrayHasKey('miniemen_m', $result);
        $this->assertArrayHasKey('cadetten_m', $result);
    }

    #[Test]
    public function alle_gewichtsklassen_sorted_by_max_leeftijd(): void
    {
        $this->toernooi->gewichtsklassen = [
            'cadetten' => ['label' => 'Cadetten', 'max_leeftijd' => 16, 'gewichten' => []],
            'miniemen' => ['label' => 'Miniemen', 'max_leeftijd' => 10, 'gewichten' => []],
        ];
        $this->toernooi->save();

        $keys = array_keys($this->toernooi->getAlleGewichtsklassen());
        $this->assertEquals('miniemen', $keys[0]);
        $this->assertEquals('cadetten', $keys[1]);
    }

    #[Test]
    public function gewichtsklassen_voor_leeftijd_returns_gewichten(): void
    {
        $this->toernooi->gewichtsklassen = [
            'miniemen_m' => ['gewichten' => ['-24', '-27', '+27']],
        ];
        $this->toernooi->save();

        $result = $this->toernooi->getGewichtsklassenVoorLeeftijd('miniemen_m');
        $this->assertEquals(['-24', '-27', '+27'], $result);
    }

    #[Test]
    public function gewichtsklassen_voor_unknown_leeftijd_returns_empty(): void
    {
        $result = $this->toernooi->getGewichtsklassenVoorLeeftijd('doesnt_exist');
        $this->assertEquals([], $result);
    }

    #[Test]
    public function categorie_volgorde_returns_ordered_labels(): void
    {
        $this->toernooi->gewichtsklassen = [
            'a' => ['label' => 'Alpha', 'max_leeftijd' => 10, 'gewichten' => []],
            'b' => ['label' => 'Bravo', 'max_leeftijd' => 12, 'gewichten' => []],
        ];
        $this->toernooi->save();

        $volgorde = $this->toernooi->getCategorieVolgorde();
        $this->assertEquals(0, $volgorde['Alpha']);
        $this->assertEquals(1, $volgorde['Bravo']);
    }

    #[Test]
    public function categorie_key_by_label_finds_key(): void
    {
        $this->toernooi->gewichtsklassen = [
            'cat_a' => ['label' => 'Miniemen M', 'max_leeftijd' => 10, 'gewichten' => []],
        ];
        $this->toernooi->save();

        $this->assertEquals('cat_a', $this->toernooi->getCategorieKeyByLabel('Miniemen M'));
        $this->assertNull($this->toernooi->getCategorieKeyByLabel('Nonexistent'));
    }

    // ========================================================================
    // Role URL / Code Methods
    // ========================================================================

    #[Test]
    public function regenerate_role_code_generates_new_code(): void
    {
        $oldCode = $this->toernooi->code_hoofdjury;
        $newCode = $this->toernooi->regenerateRoleCode('hoofdjury');

        $this->assertNotNull($newCode);
        $this->assertNotEquals($oldCode, $newCode);
    }

    #[Test]
    public function regenerate_role_code_returns_null_for_invalid(): void
    {
        $this->assertNull($this->toernooi->regenerateRoleCode('invalid'));
    }

    #[Test]
    public function get_role_url_for_valid_roles(): void
    {
        foreach (['hoofdjury', 'weging', 'mat', 'spreker', 'dojo'] as $rol) {
            $url = $this->toernooi->getRoleUrl($rol);
            $this->assertNotNull($url, "URL for role {$rol} should not be null");
            $this->assertStringContainsString('/', $url);
        }
    }

    #[Test]
    public function get_role_url_returns_null_for_invalid(): void
    {
        $this->assertNull($this->toernooi->getRoleUrl('invalid'));
    }

    // ========================================================================
    // Status / Accessors
    // ========================================================================

    #[Test]
    public function bezettings_percentage_null_without_max(): void
    {
        $this->toernooi->max_judokas = null;
        $this->toernooi->save();
        $this->assertNull($this->toernooi->bezettings_percentage);
    }

    #[Test]
    public function bezettings_percentage_calculates_correctly(): void
    {
        $this->toernooi->max_judokas = 100;
        $this->toernooi->save();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(50)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertEquals(50, $this->toernooi->bezettings_percentage);
    }

    #[Test]
    public function is_bijna_80_procent_vol(): void
    {
        $this->toernooi->max_judokas = 10;
        $this->toernooi->save();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(8)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertTrue($this->toernooi->isBijna80ProcentVol());
    }

    #[Test]
    public function plaatsen_over_attribute(): void
    {
        $this->toernooi->max_judokas = 10;
        $this->toernooi->save();
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(7)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertEquals(3, $this->toernooi->plaatsen_over);
    }

    #[Test]
    public function plaatsen_over_null_without_max(): void
    {
        $this->toernooi->max_judokas = null;
        $this->toernooi->save();
        $this->assertNull($this->toernooi->plaatsen_over);
    }

    #[Test]
    public function is_wedstrijddag_gestart(): void
    {
        Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => true,
        ]);

        $this->assertTrue($this->toernooi->isWedstrijddagGestart());
    }

    #[Test]
    public function is_wedstrijddag_niet_gestart(): void
    {
        Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => false,
        ]);

        $this->assertFalse($this->toernooi->isWedstrijddagGestart());
    }

    #[Test]
    public function is_open_toernooi(): void
    {
        $this->toernooi->update(['toernooi_type' => 'open']);
        $this->assertTrue($this->toernooi->isOpenToernooi());

        $this->toernooi->update(['toernooi_type' => 'clubwedstrijd']);
        $this->assertFalse($this->toernooi->isOpenToernooi());
    }

    #[Test]
    public function can_use_eliminatie(): void
    {
        $paid = Toernooi::factory()->create(['plan_type' => 'paid', 'organisator_id' => $this->org->id]);
        $this->assertTrue($paid->canUseEliminatie());

        $free = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $this->assertFalse($free->canUseEliminatie());
    }

    // ========================================================================
    // Staffel Prijs
    // ========================================================================

    #[Test]
    public function staffel_prijs_returns_correct_price(): void
    {
        $this->assertEquals(20, Toernooi::getStaffelPrijs('51-100'));
        $this->assertEquals(100, Toernooi::getStaffelPrijs('401-500'));
        $this->assertNull(Toernooi::getStaffelPrijs('unknown'));
    }

    // ========================================================================
    // Route params
    // ========================================================================

    #[Test]
    public function route_params_with_merges_correctly(): void
    {
        $params = $this->toernooi->routeParamsWith(['judoka' => 42]);
        $this->assertEquals($this->org->slug, $params['organisator']);
        $this->assertEquals($this->toernooi->slug, $params['toernooi']);
        $this->assertEquals(42, $params['judoka']);
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function toernooi_has_many_blokken(): void
    {
        Blok::factory()->count(2)->create(['toernooi_id' => $this->toernooi->id]);
        $this->assertEquals(2, $this->toernooi->blokken()->count());
    }

    #[Test]
    public function toernooi_has_many_clubs_via_pivot(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, ['portal_code' => 'ABC123', 'pincode' => '1234']);

        $this->assertEquals(1, $this->toernooi->clubs()->count());
    }

    #[Test]
    public function totaal_judokas_attribute(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(3)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertEquals(3, $this->toernooi->totaal_judokas);
    }
}
