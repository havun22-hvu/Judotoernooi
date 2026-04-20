<?php

namespace Tests\Unit\Models;

use App\Models\CoachCheckin;
use App\Models\CoachKaart;
use App\Models\Club;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function maakCheckin(array $overrides = []): CoachCheckin
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $club = Club::factory()->create();
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Coach',
            'qr_code' => 'qr-' . uniqid(),
        ]);

        return CoachCheckin::create(array_merge([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach Test',
            'club_naam' => $club->naam,
            'actie' => 'in',
        ], $overrides));
    }

    #[Test]
    public function is_in_returns_true_for_in_action(): void
    {
        $checkin = $this->maakCheckin(['actie' => 'in']);

        $this->assertTrue($checkin->isIn());
        $this->assertFalse($checkin->isUit());
        $this->assertFalse($checkin->isGeforceerd());
    }

    #[Test]
    public function is_uit_returns_true_for_uit_action(): void
    {
        $checkin = $this->maakCheckin(['actie' => 'uit']);

        $this->assertTrue($checkin->isUit());
        $this->assertFalse($checkin->isIn());
        $this->assertFalse($checkin->isGeforceerd());
    }

    #[Test]
    public function is_uit_geforceerd_counts_as_uit(): void
    {
        $checkin = $this->maakCheckin(['actie' => 'uit_geforceerd', 'geforceerd_door' => 'admin']);

        $this->assertTrue($checkin->isUit(), 'Geforceerde uitcheck telt ook als uit-actie.');
        $this->assertTrue($checkin->isGeforceerd());
    }

    #[Test]
    public function get_foto_url_returns_null_without_foto(): void
    {
        $this->assertNull($this->maakCheckin(['foto' => null])->getFotoUrl());
    }

    #[Test]
    public function get_foto_url_returns_storage_url_with_foto(): void
    {
        $url = $this->maakCheckin(['foto' => 'coaches/123.jpg'])->getFotoUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('storage/coaches/123.jpg', $url);
    }

    #[Test]
    public function vandaag_scope_filters_by_today_date(): void
    {
        $today = $this->maakCheckin();
        $yesterday = $this->maakCheckin();
        // DB::table bypass — Eloquent update zet `updated_at` automatisch en
        // overschrijft onze gewenste created_at niet consistent
        \DB::table('coach_checkins')
            ->where('id', $yesterday->id)
            ->update(['created_at' => now()->subDay()]);

        $vandaag = CoachCheckin::vandaag()->pluck('id')->all();

        $this->assertContains($today->id, $vandaag);
        $this->assertNotContains($yesterday->id, $vandaag);
    }

    #[Test]
    public function voor_club_scope_filters_via_coach_kaart_relation(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $kaartA = CoachKaart::create([
            'toernooi_id' => $toernooi->id, 'club_id' => $clubA->id,
            'naam' => 'A', 'qr_code' => 'qr-a-' . uniqid(),
        ]);
        $kaartB = CoachKaart::create([
            'toernooi_id' => $toernooi->id, 'club_id' => $clubB->id,
            'naam' => 'B', 'qr_code' => 'qr-b-' . uniqid(),
        ]);

        $voorA = CoachCheckin::create([
            'coach_kaart_id' => $kaartA->id, 'toernooi_id' => $toernooi->id,
            'naam' => 'A', 'club_naam' => $clubA->naam, 'actie' => 'in',
        ]);
        CoachCheckin::create([
            'coach_kaart_id' => $kaartB->id, 'toernooi_id' => $toernooi->id,
            'naam' => 'B', 'club_naam' => $clubB->naam, 'actie' => 'in',
        ]);

        $resultaat = CoachCheckin::voorClub($clubA->id)->pluck('id')->all();
        $this->assertSame([$voorA->id], $resultaat);
    }
}
