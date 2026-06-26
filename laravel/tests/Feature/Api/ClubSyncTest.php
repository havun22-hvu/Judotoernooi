<?php

namespace Tests\Feature\Api;

use App\Models\ClubApiToken;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HavunClub integration API (POST /api/judokas, /api/inschrijvingen, results).
 *
 * Covers auth, idempotent upsert, field mapping, tenant isolation and results.
 */
class ClubSyncTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Organisator $org): string
    {
        $plain = ClubApiToken::generateToken();
        ClubApiToken::create([
            'organisator_id' => $org->id,
            'token' => $plain,
            'label' => 'HavunClub',
            'actief' => true,
        ]);

        return $plain;
    }

    private function auth(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    #[Test]
    public function rejects_request_without_token(): void
    {
        $this->postJson('/api/judokas', [])->assertStatus(401);
    }

    #[Test]
    public function rejects_invalid_token(): void
    {
        $this->withHeaders($this->auth('jtc_nonexistent'))
            ->postJson('/api/judokas', [])
            ->assertStatus(401);
    }

    #[Test]
    public function upserts_judoka_and_maps_fields(): void
    {
        $org = Organisator::factory()->create();
        $token = $this->tokenFor($org);

        $response = $this->withHeaders($this->auth($token))->postJson('/api/judokas', [
            'voornaam' => 'Sanne',
            'achternaam' => 'de Vries',
            'geboortedatum' => '2014-05-09',
            'geslacht' => 'vrouw',
            'band' => 'geel',
        ]);

        $response->assertOk()->assertJsonStructure(['id']);

        $stam = StamJudoka::find($response->json('id'));
        $this->assertSame($org->id, $stam->organisator_id);
        $this->assertSame('Sanne de Vries', $stam->naam);
        $this->assertSame(2014, $stam->geboortejaar);
        $this->assertSame('V', $stam->geslacht);
    }

    #[Test]
    public function upsert_is_idempotent_on_judotoernooi_id(): void
    {
        $org = Organisator::factory()->create();
        $token = $this->tokenFor($org);

        $first = $this->withHeaders($this->auth($token))->postJson('/api/judokas', [
            'voornaam' => 'Tim', 'achternaam' => 'Bakker',
            'geboortedatum' => '2013-01-01', 'geslacht' => 'M', 'band' => 'wit',
        ])->json('id');

        $second = $this->withHeaders($this->auth($token))->postJson('/api/judokas', [
            'judotoernooi_id' => $first,
            'voornaam' => 'Tim', 'achternaam' => 'Bakker-Jansen',
            'geboortedatum' => '2013-01-01', 'geslacht' => 'M', 'band' => 'geel',
        ])->json('id');

        $this->assertSame($first, $second);
        $this->assertSame(1, StamJudoka::where('organisator_id', $org->id)->count());
        $this->assertSame('Tim Bakker-Jansen', StamJudoka::find($first)->naam);
    }

    #[Test]
    public function enters_stam_judoka_into_tournament_idempotently(): void
    {
        $org = Organisator::factory()->create();
        $token = $this->tokenFor($org);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $stam = StamJudoka::factory()->create([
            'organisator_id' => $org->id, 'gewicht' => 32.0,
        ]);

        $first = $this->withHeaders($this->auth($token))->postJson('/api/inschrijvingen', [
            'toernooi_id' => $toernooi->id,
            'judoka_id' => $stam->id,
        ]);
        $first->assertOk()->assertJsonStructure(['id']);

        $second = $this->withHeaders($this->auth($token))->postJson('/api/inschrijvingen', [
            'toernooi_id' => $toernooi->id,
            'judoka_id' => $stam->id,
        ]);

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Judoka::where('toernooi_id', $toernooi->id)->count());
        $this->assertSame($stam->id, Judoka::find($first->json('id'))->stam_judoka_id);
    }

    #[Test]
    public function cannot_enter_into_another_tenants_tournament(): void
    {
        $orgA = Organisator::factory()->create();
        $orgB = Organisator::factory()->create();
        $tokenA = $this->tokenFor($orgA);

        $toernooiB = Toernooi::factory()->create(['organisator_id' => $orgB->id]);
        $stamA = StamJudoka::factory()->create(['organisator_id' => $orgA->id]);

        $this->withHeaders($this->auth($tokenA))->postJson('/api/inschrijvingen', [
            'toernooi_id' => $toernooiB->id,
            'judoka_id' => $stamA->id,
        ])->assertStatus(404);
    }

    #[Test]
    public function returns_results_with_placements(): void
    {
        $org = Organisator::factory()->create();
        $token = $this->tokenFor($org);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id, 'gewichtsklasse' => '-32',
        ]);
        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $judoka->poules()->attach($poule->id, [
            'eindpositie' => 1, 'gewonnen' => 2, 'verloren' => 0, 'gelijk' => 0,
        ]);

        $response = $this->withHeaders($this->auth($token))
            ->getJson("/api/toernooien/{$toernooi->id}/resultaten");

        $response->assertOk()->assertJsonFragment([
            'judoka_id' => $judoka->id,
            'gewichtsklasse' => '-32',
            'resultaat' => 1,
            'partijen' => 2,
        ]);
    }
}
