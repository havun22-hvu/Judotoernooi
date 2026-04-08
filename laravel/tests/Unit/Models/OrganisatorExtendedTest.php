<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\GewichtsklassenPreset;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorExtendedTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Route key
    // ========================================================================

    #[Test]
    public function route_key_name_is_slug(): void
    {
        $org = Organisator::factory()->create();
        $this->assertEquals('slug', $org->getRouteKeyName());
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function has_many_clubs(): void
    {
        $org = Organisator::factory()->create();
        Club::factory()->count(2)->create(['organisator_id' => $org->id]);
        $this->assertEquals(2, $org->clubs()->count());
    }

    #[Test]
    public function toernooien_relationship(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        $this->assertEquals(1, $org->toernooien()->count());
    }

    // ========================================================================
    // ownsToernooi / hasAccessToToernooi
    // ========================================================================

    #[Test]
    public function owns_toernooi_true_for_owner(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
        $this->assertTrue($org->ownsToernooi($toernooi));
    }

    #[Test]
    public function owns_toernooi_false_for_beheerder(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create();
        $org->toernooien()->attach($toernooi->id, ['rol' => 'beheerder']);
        $this->assertFalse($org->ownsToernooi($toernooi));
    }

    #[Test]
    public function owns_toernooi_false_without_pivot(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create();
        $this->assertFalse($org->ownsToernooi($toernooi));
    }

    #[Test]
    public function has_access_via_pivot(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create();
        $org->toernooien()->attach($toernooi->id, ['rol' => 'beheerder']);

        $this->assertTrue($org->hasAccessToToernooi($toernooi));
    }

    #[Test]
    public function has_access_as_sitebeheerder(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $toernooi = Toernooi::factory()->create();
        $this->assertTrue($admin->hasAccessToToernooi($toernooi));
    }

    #[Test]
    public function no_access_without_pivot(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create();
        $this->assertFalse($org->hasAccessToToernooi($toernooi));
    }

    // ========================================================================
    // updateLaatsteLogin
    // ========================================================================

    #[Test]
    public function update_laatste_login_sets_timestamp(): void
    {
        $org = Organisator::factory()->create(['laatste_login' => null]);
        $org->updateLaatsteLogin();
        $org->refresh();

        $this->assertNotNull($org->laatste_login);
    }

    // ========================================================================
    // KYC
    // ========================================================================

    #[Test]
    public function is_kyc_compleet(): void
    {
        $org = Organisator::factory()->kycCompleet()->create();
        $this->assertTrue($org->isKycCompleet());
    }

    #[Test]
    public function is_not_kyc_compleet(): void
    {
        $org = Organisator::factory()->create(['kyc_compleet' => false]);
        $this->assertFalse($org->isKycCompleet());
    }

    #[Test]
    public function mark_kyc_compleet(): void
    {
        $org = Organisator::factory()->kycCompleet()->create(['kyc_compleet' => false]);
        $org->markKycCompleet();
        $org->refresh();

        $this->assertTrue($org->isKycCompleet());
    }

    // ========================================================================
    // Presets limit
    // ========================================================================

    #[Test]
    public function get_max_presets_for_free_user(): void
    {
        $org = Organisator::factory()->create();
        $this->assertIsInt($org->getMaxPresets());
    }

    // ========================================================================
    // Factuur adres
    // ========================================================================

    #[Test]
    public function get_factuur_adres_returns_string(): void
    {
        $org = Organisator::factory()->kycCompleet()->create();
        $adres = $org->getFactuurAdres();
        $this->assertIsString($adres);
    }

    #[Test]
    public function get_factuur_adres_empty_without_kyc(): void
    {
        $org = Organisator::factory()->create([
            'straat' => null,
            'postcode' => null,
            'plaats' => null,
        ]);
        $adres = $org->getFactuurAdres();
        $this->assertIsString($adres);
    }
}
