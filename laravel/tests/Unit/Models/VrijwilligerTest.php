<?php

namespace Tests\Unit\Models;

use App\Models\Organisator;
use App\Models\Vrijwilliger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VrijwilligerTest extends TestCase
{
    use RefreshDatabase;

    private function maakVrijwilliger(array $overrides = []): Vrijwilliger
    {
        $org = Organisator::factory()->create();

        return Vrijwilliger::create(array_merge([
            'organisator_id' => $org->id,
            'voornaam' => 'Henk',
            'functie' => 'mat',
        ], $overrides));
    }

    #[Test]
    public function functies_constant_lists_known_roles(): void
    {
        $this->assertContains('mat', Vrijwilliger::FUNCTIES);
        $this->assertContains('weging', Vrijwilliger::FUNCTIES);
        $this->assertContains('spreker', Vrijwilliger::FUNCTIES);
        $this->assertContains('hoofdjury', Vrijwilliger::FUNCTIES);
    }

    #[Test]
    public function get_functie_label_capitalises(): void
    {
        $this->assertSame('Mat', $this->maakVrijwilliger(['functie' => 'mat'])->getFunctieLabel());
        $this->assertSame('Weging', $this->maakVrijwilliger(['functie' => 'weging'])->getFunctieLabel());
    }

    #[Test]
    public function get_whats_app_url_returns_empty_for_missing_phone(): void
    {
        $vrijwilliger = $this->maakVrijwilliger(['telefoonnummer' => null]);

        $this->assertSame('', $vrijwilliger->getWhatsAppUrl('Hoi!'));
    }

    #[Test]
    public function get_whats_app_url_converts_06_to_international_format(): void
    {
        $url = $this->maakVrijwilliger(['telefoonnummer' => '06-12345678'])
            ->getWhatsAppUrl('Hoi');

        $this->assertStringContainsString('https://wa.me/31612345678', $url);
        $this->assertStringContainsString('text=Hoi', $url);
    }

    #[Test]
    public function get_whats_app_url_strips_spaces_and_dashes(): void
    {
        $url = $this->maakVrijwilliger(['telefoonnummer' => '06 12 34 56 78'])
            ->getWhatsAppUrl('test');

        $this->assertStringContainsString('31612345678', $url);
    }

    #[Test]
    public function get_whats_app_url_url_encodes_message(): void
    {
        $url = $this->maakVrijwilliger(['telefoonnummer' => '0612345678'])
            ->getWhatsAppUrl('Hoi & welkom!');

        $this->assertStringContainsString('text=Hoi+%26+welkom%21', $url);
    }

    #[Test]
    public function relationship_to_organisator(): void
    {
        $v = $this->maakVrijwilliger();

        $this->assertInstanceOf(Organisator::class, $v->organisator);
    }
}
