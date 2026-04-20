<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubUitnodigingTest extends TestCase
{
    use RefreshDatabase;

    private function maakUitnodiging(array $overrides = []): ClubUitnodiging
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $club = Club::factory()->create();

        return ClubUitnodiging::create(array_merge([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ], $overrides));
    }

    #[Test]
    public function token_is_auto_generated_when_not_provided(): void
    {
        $u = $this->maakUitnodiging();

        $this->assertNotEmpty($u->token);
        $this->assertSame(64, strlen($u->token));
    }

    #[Test]
    public function explicit_token_is_preserved(): void
    {
        $u = $this->maakUitnodiging(['token' => 'custom-token-123']);

        $this->assertSame('custom-token-123', $u->token);
    }

    #[Test]
    public function wachtwoord_hash_is_hidden_from_serialization(): void
    {
        $u = $this->maakUitnodiging();
        $u->setWachtwoord('geheim');

        $array = $u->fresh()->toArray();
        $this->assertArrayNotHasKey('wachtwoord_hash', $array);
    }

    #[Test]
    public function set_wachtwoord_hashes_and_marks_geregistreerd(): void
    {
        $u = $this->maakUitnodiging();
        $this->assertFalse($u->isGeregistreerd());

        $u->setWachtwoord('SterkWachtwoord123!');

        $u->refresh();
        $this->assertNotEmpty($u->wachtwoord_hash);
        $this->assertNotSame('SterkWachtwoord123!', $u->wachtwoord_hash, 'Plain mag NOOIT in DB.');
        $this->assertTrue($u->isGeregistreerd());
    }

    #[Test]
    public function check_wachtwoord_returns_true_for_correct_password(): void
    {
        $u = $this->maakUitnodiging();
        $u->setWachtwoord('geheim');

        $this->assertTrue($u->fresh()->checkWachtwoord('geheim'));
        $this->assertFalse($u->fresh()->checkWachtwoord('verkeerd'));
    }

    #[Test]
    public function update_laatst_ingelogd_persists_timestamp(): void
    {
        $u = $this->maakUitnodiging();
        $this->assertNull($u->laatst_ingelogd_op);

        $u->updateLaatstIngelogd();

        $this->assertNotNull($u->fresh()->laatst_ingelogd_op);
    }

    #[Test]
    public function relationships_to_toernooi_and_club_load(): void
    {
        $u = $this->maakUitnodiging();

        $this->assertInstanceOf(Toernooi::class, $u->toernooi);
        $this->assertInstanceOf(Club::class, $u->club);
    }
}
