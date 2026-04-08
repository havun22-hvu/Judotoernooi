<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
    }

    // ========================================================================
    // Auto-generated fields on create
    // ========================================================================

    #[Test]
    public function portal_code_generated_on_create(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->assertNotEmpty($club->portal_code);
        $this->assertEquals(12, strlen($club->portal_code));
    }

    #[Test]
    public function pincode_generated_on_create(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->assertNotEmpty($club->pincode);
        $this->assertEquals(5, strlen($club->pincode));
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function has_many_coaches(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->assertCount(0, $club->coaches);
    }

    #[Test]
    public function has_many_coach_kaarten(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->assertCount(0, $club->coachKaarten);
    }

    #[Test]
    public function toernooien_relationship(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club->toernooien()->attach($toernooi->id, ['portal_code' => 'TEST123', 'pincode' => '12345']);

        $this->assertCount(1, $club->toernooien);
    }

    // ========================================================================
    // Portal code per toernooi
    // ========================================================================

    #[Test]
    public function get_portal_code_for_toernooi(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club->toernooien()->attach($toernooi->id, ['portal_code' => 'MYCODE123', 'pincode' => '12345']);

        $this->assertEquals('MYCODE123', $club->getPortalCodeForToernooi($toernooi));
    }

    #[Test]
    public function get_portal_code_for_unlinked_toernooi_returns_null(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $this->assertNull($club->getPortalCodeForToernooi($toernooi));
    }

    // ========================================================================
    // Pincode per toernooi
    // ========================================================================

    #[Test]
    public function get_pincode_for_toernooi(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club->toernooien()->attach($toernooi->id, ['portal_code' => 'CODE', 'pincode' => '54321']);

        $this->assertEquals('54321', $club->getPincodeForToernooi($toernooi));
    }

    #[Test]
    public function check_pincode_for_toernooi_correct(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club->toernooien()->attach($toernooi->id, ['portal_code' => 'CODE', 'pincode' => '54321']);

        $this->assertTrue($club->checkPincodeForToernooi($toernooi, '54321'));
        $this->assertFalse($club->checkPincodeForToernooi($toernooi, '99999'));
    }

    #[Test]
    public function regenerate_pincode_for_toernooi(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $club->toernooien()->attach($toernooi->id, ['portal_code' => 'CODE', 'pincode' => '00000']);

        $newPin = $club->regeneratePincodeForToernooi($toernooi);
        $this->assertEquals(5, strlen($newPin));
        $this->assertMatchesRegularExpression('/^\d{5}$/', $newPin);
    }

    // ========================================================================
    // Legacy methods
    // ========================================================================

    #[Test]
    public function get_legacy_portal_url(): void
    {
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'portal_code' => 'LEGACYCODE12',
        ]);

        $url = $club->getLegacyPortalUrl();
        $this->assertStringContainsString('/school/LEGACYCODE12', $url);
    }

    #[Test]
    public function regenerate_pincode_returns_new_pincode(): void
    {
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'pincode' => '00000',
        ]);

        $newPin = $club->regeneratePincode();
        $this->assertEquals(5, strlen($newPin));
        $club->refresh();
        $this->assertEquals($newPin, $club->pincode);
    }

    // ========================================================================
    // findOrCreateByName — case insensitive and fuzzy match
    // ========================================================================

    #[Test]
    public function find_or_create_case_insensitive_match(): void
    {
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'naam' => 'Judoclub Amsterdam',
        ]);

        // Note: SQLite LOWER() works differently, but the method should handle it
        $found = Club::findOrCreateByName('judoclub amsterdam', $this->org->id);
        $this->assertEquals($club->id, $found->id);
    }

    #[Test]
    public function find_or_create_fuzzy_match(): void
    {
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'naam' => 'Judo',
        ]);

        // "Judo" contains "Judo" - fuzzy match
        $found = Club::findOrCreateByName('Judo Amsterdam', $this->org->id);
        $this->assertEquals($club->id, $found->id);
    }

    #[Test]
    public function find_or_create_creates_with_afkorting(): void
    {
        $club = Club::findOrCreateByName('Heel Lange Clubnaam Hier', $this->org->id);

        $this->assertNotNull($club);
        $this->assertEquals('Heel Lange Clubnaam Hier', $club->naam);
        $this->assertEquals('Heel Lange', $club->afkorting); // first 10 chars
    }

    // ========================================================================
    // coachesVoorToernooi / coachKaartenVoorToernooi
    // ========================================================================

    #[Test]
    public function coaches_voor_toernooi_returns_scoped_query(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $this->assertCount(0, $club->coachesVoorToernooi($toernooi->id)->get());
    }

    #[Test]
    public function coach_kaarten_voor_toernooi_returns_scoped_query(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $this->assertCount(0, $club->coachKaartenVoorToernooi($toernooi->id)->get());
    }

    // ========================================================================
    // berekenAantalCoachKaarten
    // ========================================================================

    #[Test]
    public function bereken_coach_kaarten_returns_0_when_no_judokas(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        $this->assertEquals(0, $club->berekenAantalCoachKaarten($toernooi));
    }

    #[Test]
    public function bereken_coach_kaarten_returns_1_during_inschrijving(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);

        // Create judokas without poule assignment
        Judoka::factory()->count(3)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertEquals(1, $club->berekenAantalCoachKaarten($toernooi));
    }
}
