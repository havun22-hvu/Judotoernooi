<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Slug generation
    // ========================================================================

    #[Test]
    public function slug_generated_from_organisatie_naam_on_create(): void
    {
        $org = Organisator::factory()->create([
            'organisatie_naam' => 'Judoschool Amsterdam',
            'slug' => null,
        ]);

        $this->assertEquals('judoschool-amsterdam', $org->slug);
    }

    #[Test]
    public function slug_falls_back_to_naam_when_no_organisatie_naam(): void
    {
        $org = Organisator::factory()->create([
            'naam' => 'Jan de Boer',
            'organisatie_naam' => null,
            'slug' => null,
        ]);

        $this->assertEquals('jan-de-boer', $org->slug);
    }

    #[Test]
    public function slug_updates_when_organisatie_naam_changes(): void
    {
        $org = Organisator::factory()->create([
            'organisatie_naam' => 'Oude Naam',
            'slug' => null,
        ]);
        $this->assertEquals('oude-naam', $org->slug);

        $org->update(['organisatie_naam' => 'Nieuwe Naam']);
        $org->refresh();
        $this->assertEquals('nieuwe-naam', $org->slug);
    }

    #[Test]
    public function generate_unique_slug_appends_counter(): void
    {
        Organisator::factory()->create(['slug' => 'test-club']);

        $slug = Organisator::generateUniqueSlug('Test Club');
        $this->assertEquals('test-club-1', $slug);
    }

    #[Test]
    public function generate_unique_slug_excludes_self(): void
    {
        $org = Organisator::factory()->create(['slug' => 'test-club']);

        // With excludeId, should return 'test-club' (self)
        $slug = Organisator::generateUniqueSlug('Test Club', $org->id);
        $this->assertEquals('test-club', $slug);
    }

    // ========================================================================
    // hasRequiredKycFields
    // ========================================================================

    #[Test]
    public function has_required_kyc_fields_true_when_all_filled(): void
    {
        $org = Organisator::factory()->kycCompleet()->create();
        $this->assertTrue($org->hasRequiredKycFields());
    }

    #[Test]
    public function has_required_kyc_fields_false_when_missing(): void
    {
        $org = Organisator::factory()->create([
            'organisatie_naam' => null,
            'straat' => null,
            'postcode' => null,
            'plaats' => null,
            'contactpersoon' => null,
            'factuur_email' => null,
        ]);
        $this->assertFalse($org->hasRequiredKycFields());
    }

    // ========================================================================
    // canAddMorePresets
    // ========================================================================

    #[Test]
    public function can_add_more_presets_premium_always_true(): void
    {
        $org = Organisator::factory()->premium()->create();
        $this->assertTrue($org->canAddMorePresets());
    }

    #[Test]
    public function can_add_more_presets_free_limited_to_2(): void
    {
        $org = Organisator::factory()->create(['is_premium' => false]);
        // No presets yet
        $this->assertTrue($org->canAddMorePresets());
    }

    #[Test]
    public function get_max_presets_premium(): void
    {
        $org = Organisator::factory()->premium()->create();
        $this->assertEquals(PHP_INT_MAX, $org->getMaxPresets());
    }

    #[Test]
    public function get_max_presets_free(): void
    {
        $org = Organisator::factory()->create(['is_premium' => false]);
        $this->assertEquals(2, $org->getMaxPresets());
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function has_many_toernooi_templates(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->toernooiTemplates);
    }

    #[Test]
    public function has_many_gewichtsklassen_presets(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->gewichtsklassenPresets);
    }

    #[Test]
    public function has_many_vrijwilligers(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->vrijwilligers);
    }

    #[Test]
    public function has_many_auth_devices(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->authDevices);
    }

    #[Test]
    public function has_many_stam_judokas(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->stamJudokas);
    }

    #[Test]
    public function has_many_wimpel_milestones(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->wimpelMilestones);
    }

    #[Test]
    public function has_many_toernooi_betalingen(): void
    {
        $org = Organisator::factory()->create();
        $this->assertCount(0, $org->toernooiBetalingen);
    }

    // ========================================================================
    // getFactuurAdres
    // ========================================================================

    #[Test]
    public function factuur_adres_includes_all_parts(): void
    {
        $org = Organisator::factory()->create([
            'organisatie_naam' => 'Judoschool Test',
            'straat' => 'Teststraat 1',
            'postcode' => '1234AB',
            'plaats' => 'Amsterdam',
            'land' => 'Nederland',
        ]);

        $adres = $org->getFactuurAdres();
        $this->assertStringContainsString('Judoschool Test', $adres);
        $this->assertStringContainsString('Teststraat 1', $adres);
        $this->assertStringContainsString('1234AB Amsterdam', $adres);
        // Nederland should NOT appear (it's the default)
        $this->assertStringNotContainsString('Nederland', $adres);
    }

    #[Test]
    public function factuur_adres_includes_foreign_country(): void
    {
        $org = Organisator::factory()->create([
            'organisatie_naam' => 'Club Belgie',
            'straat' => 'Rue 1',
            'postcode' => '1000',
            'plaats' => 'Brussel',
            'land' => 'België',
        ]);

        $adres = $org->getFactuurAdres();
        $this->assertStringContainsString('België', $adres);
    }
}
