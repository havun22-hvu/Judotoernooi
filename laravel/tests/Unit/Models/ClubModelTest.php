<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubModelTest extends TestCase
{
    use RefreshDatabase;

    private function maakOrgMetClub(): array
    {
        $org = Organisator::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $org->id]);
        return [$org, $club];
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function it_belongs_to_organisator(): void
    {
        [$org, $club] = $this->maakOrgMetClub();

        $this->assertInstanceOf(Organisator::class, $club->organisator);
        $this->assertEquals($org->id, $club->organisator->id);
    }

    #[Test]
    public function it_has_many_judokas(): void
    {
        [$org, $club] = $this->maakOrgMetClub();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        Judoka::factory()->count(3)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertCount(3, $club->judokas);
    }

    // ========================================================================
    // Portal Code Generation
    // ========================================================================

    #[Test]
    public function generate_portal_code_returns_12_char_string(): void
    {
        $code = Club::generatePortalCode();

        $this->assertIsString($code);
        $this->assertEquals(12, strlen($code));
    }

    #[Test]
    public function generate_portal_code_is_unique_each_call(): void
    {
        $code1 = Club::generatePortalCode();
        $code2 = Club::generatePortalCode();

        $this->assertNotEquals($code1, $code2);
    }

    // ========================================================================
    // Pincode
    // ========================================================================

    #[Test]
    public function generate_pincode_returns_5_digit_string(): void
    {
        $pin = Club::generatePincode();

        $this->assertIsString($pin);
        $this->assertEquals(5, strlen($pin));
        $this->assertMatchesRegularExpression('/^\d{5}$/', $pin);
    }

    #[Test]
    public function check_pincode_validates_correctly(): void
    {
        [$org, $club] = $this->maakOrgMetClub();
        $club->update(['pincode' => '12345']);

        $this->assertTrue($club->checkPincode('12345'));
        $this->assertFalse($club->checkPincode('99999'));
    }

    // ========================================================================
    // Portal URL
    // ========================================================================

    #[Test]
    public function portal_url_returns_null_when_not_linked(): void
    {
        [$org, $club] = $this->maakOrgMetClub();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $url = $club->getPortalUrl($toernooi);

        $this->assertNull($url);
    }

    #[Test]
    public function portal_url_returns_url_when_linked(): void
    {
        [$org, $club] = $this->maakOrgMetClub();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTCODE1234',
            'pincode' => '12345',
        ]);

        $url = $club->getPortalUrl($toernooi);

        $this->assertNotNull($url);
        $this->assertStringContainsString('TESTCODE1234', $url);
    }

    // ========================================================================
    // Find or Create by Name
    // ========================================================================

    #[Test]
    public function find_or_create_creates_new_club(): void
    {
        $org = Organisator::factory()->create();

        $club = Club::findOrCreateByName('Nieuwe Club', $org->id);

        $this->assertNotNull($club);
        $this->assertEquals('Nieuwe Club', $club->naam);
        $this->assertEquals($org->id, $club->organisator_id);
    }

    #[Test]
    public function find_or_create_finds_existing_club(): void
    {
        [$org, $club] = $this->maakOrgMetClub();

        $found = Club::findOrCreateByName($club->naam, $org->id);

        $this->assertEquals($club->id, $found->id);
    }
}
