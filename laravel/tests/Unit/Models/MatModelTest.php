<?php

namespace Tests\Unit\Models;

use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatModelTest extends TestCase
{
    use RefreshDatabase;

    private function maakToernooiMetMat(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        return [$toernooi, $mat];
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function it_belongs_to_toernooi(): void
    {
        [$toernooi, $mat] = $this->maakToernooiMetMat();

        $this->assertInstanceOf(Toernooi::class, $mat->toernooi);
        $this->assertEquals($toernooi->id, $mat->toernooi->id);
    }

    #[Test]
    public function it_has_many_poules(): void
    {
        [$toernooi, $mat] = $this->maakToernooiMetMat();

        Poule::factory()->count(2)->create([
            'toernooi_id' => $toernooi->id,
            'mat_id' => $mat->id,
        ]);

        $this->assertCount(2, $mat->poules);
    }

    // ========================================================================
    // Attributes
    // ========================================================================

    #[Test]
    public function label_attribute_returns_display_name(): void
    {
        [$toernooi, $mat] = $this->maakToernooiMetMat();

        $label = $mat->label;

        $this->assertNotEmpty($label);
    }

    #[Test]
    public function mat_has_nummer(): void
    {
        [$toernooi, $mat] = $this->maakToernooiMetMat();

        $this->assertIsInt($mat->nummer);
        $this->assertGreaterThan(0, $mat->nummer);
    }
}
