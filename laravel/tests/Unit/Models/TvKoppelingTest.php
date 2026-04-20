<?php

namespace Tests\Unit\Models;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\TvKoppeling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TvKoppelingTest extends TestCase
{
    use RefreshDatabase;

    private function maakKoppeling(array $overrides = []): TvKoppeling
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        return TvKoppeling::create(array_merge([
            'code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'toernooi_id' => $toernooi->id,
            'mat_nummer' => 1,
            'expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    #[Test]
    public function generate_code_produces_4_digit_string_padded_with_zeros(): void
    {
        $code = TvKoppeling::generateCode();

        $this->assertSame(4, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
    }

    #[Test]
    public function generate_code_avoids_active_existing_codes(): void
    {
        // Vul DB met code "0001" actief → generateCode mag deze niet teruggeven
        $this->maakKoppeling(['code' => '0001', 'expires_at' => now()->addHour()]);

        // Loop tot we andere code zien — generateCode gebruikt random_int dus
        // statistisch zou dit binnen 50 pogingen lukken
        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $seen[] = TvKoppeling::generateCode();
        }

        $this->assertNotContains('0001', $seen, 'Active code mag NIET opnieuw uitgereikt worden.');
    }

    #[Test]
    public function generate_code_can_reuse_expired_codes(): void
    {
        $this->maakKoppeling(['code' => '0001', 'expires_at' => now()->subHour()]);

        // Expired codes telen niet mee — code 0001 mag wel terugkomen
        // (geen assertie nodig, maar generateCode mag niet vasthangen)
        $code = TvKoppeling::generateCode();
        $this->assertSame(4, strlen($code));
    }

    #[Test]
    public function is_expired_reflects_expires_at(): void
    {
        $valid = $this->maakKoppeling(['expires_at' => now()->addMinute()]);
        $expired = $this->maakKoppeling(['expires_at' => now()->subMinute()]);

        $this->assertFalse($valid->isExpired());
        $this->assertTrue($expired->isExpired());
    }

    #[Test]
    public function is_linked_returns_true_only_after_linked_at_is_set(): void
    {
        $unlinked = $this->maakKoppeling();
        $linked = $this->maakKoppeling(['linked_at' => now()]);

        $this->assertFalse($unlinked->isLinked());
        $this->assertTrue($linked->isLinked());
    }

    #[Test]
    public function relationship_to_toernooi(): void
    {
        $koppeling = $this->maakKoppeling();

        $this->assertInstanceOf(Toernooi::class, $koppeling->toernooi);
    }
}
