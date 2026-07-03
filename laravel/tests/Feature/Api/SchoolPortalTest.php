<?php

namespace Tests\Feature\Api;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HavunClub school-portal fill API (POST /api/school-portal/{code}/inschrijvingen).
 *
 * Scenario 2: a school invited to another organiser's tournament fills its
 * portal from HavunClub, authorised by portal code + 5-digit PIN.
 */
class SchoolPortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Toernooi, 1: Club, 2: string, 3: string}
     */
    private function portal(string $portaalModus = 'volledig'): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'portaal_modus' => $portaalModus,
        ]);
        $club = Club::factory()->create();
        // Unique code per portal so the PIN throttle key never bleeds between
        // tests (RefreshDatabase resets ids, so a fixed code would collide).
        $code = Str::upper(Str::random(12));
        $pin = '12345';
        $toernooi->clubs()->attach($club->id, ['portal_code' => $code, 'pincode' => $pin]);

        return [$toernooi, $club, $code, $pin];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'pincode' => '12345',
            'havunclub_judoka_id' => 'hcl-1',
            'voornaam' => 'Sanne',
            'achternaam' => 'de Vries',
            'geboortedatum' => '2014-05-09',
            'geslacht' => 'vrouw',
            'band' => 'geel',
            'gewicht' => 30,
        ], $overrides);
    }

    #[Test]
    public function fills_portal_with_valid_code_and_pin(): void
    {
        [$toernooi, $club, $code] = $this->portal();

        $response = $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload());

        $response->assertOk()->assertJsonStructure(['id']);

        $judoka = Judoka::find($response->json('id'));
        $this->assertSame($toernooi->id, $judoka->toernooi_id);
        $this->assertSame($club->id, $judoka->club_id);
        $this->assertSame('hcl-1', $judoka->havunclub_ref);
        $this->assertSame('Sanne de Vries', $judoka->naam);
        $this->assertSame(2014, $judoka->geboortejaar);
        $this->assertSame('V', $judoka->geslacht);
        $this->assertNull($judoka->stam_judoka_id);
    }

    #[Test]
    public function rejects_unknown_portal_code(): void
    {
        $this->postJson('/api/school-portal/DOESNOTEXIST/inschrijvingen', $this->payload())
            ->assertStatus(404);
    }

    #[Test]
    public function rejects_wrong_pin(): void
    {
        [, , $code] = $this->portal();

        $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload(['pincode' => '99999']))
            ->assertStatus(401);

        $this->assertSame(0, Judoka::count());
    }

    #[Test]
    public function throttles_after_five_wrong_pins(): void
    {
        [$toernooi, , $code] = $this->portal();
        RateLimiter::clear("school-portal-api-pin:{$toernooi->id}:{$code}");

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload(['pincode' => '00000']))
                ->assertStatus(401);
        }

        // 6th attempt — even with the CORRECT pin — is locked out.
        $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload())
            ->assertStatus(429);
    }

    #[Test]
    public function is_idempotent_on_havunclub_id(): void
    {
        [, , $code] = $this->portal();

        $first = $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload())->json('id');
        $second = $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload([
            'band' => 'oranje', // changed field is ignored on re-push
        ]))->json('id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Judoka::count());
    }

    #[Test]
    public function rejects_when_portal_mode_is_not_volledig(): void
    {
        [, , $code] = $this->portal('mutaties');

        $this->postJson("/api/school-portal/{$code}/inschrijvingen", $this->payload())
            ->assertStatus(422);

        $this->assertSame(0, Judoka::count());
    }

    #[Test]
    public function validates_required_fields(): void
    {
        [, , $code] = $this->portal();

        $this->postJson("/api/school-portal/{$code}/inschrijvingen", [
            'voornaam' => 'Sanne',
        ])->assertStatus(422)->assertJsonValidationErrors(['pincode', 'achternaam']);
    }
}
