<?php

namespace Tests\Unit\Enums;

use App\Enums\AanwezigheidsStatus;
use App\Enums\Band;
use App\Enums\Geslacht;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnumsTest extends TestCase
{
    // ========================================================================
    // AanwezigheidsStatus
    // ========================================================================

    #[Test]
    public function aanwezigheid_labels(): void
    {
        $this->assertEquals('Onbekend', AanwezigheidsStatus::ONBEKEND->label());
        $this->assertEquals('Aanwezig', AanwezigheidsStatus::AANWEZIG->label());
        $this->assertEquals('Afwezig', AanwezigheidsStatus::AFWEZIG->label());
        $this->assertEquals('Afgemeld', AanwezigheidsStatus::AFGEMELD->label());
    }

    #[Test]
    public function aanwezigheid_kleuren(): void
    {
        $this->assertEquals('#FFFFFF', AanwezigheidsStatus::ONBEKEND->kleur());
        $this->assertEquals('#D9EAD3', AanwezigheidsStatus::AANWEZIG->kleur());
        $this->assertEquals('#F4CCCC', AanwezigheidsStatus::AFWEZIG->kleur());
        $this->assertEquals('#FCE5CD', AanwezigheidsStatus::AFGEMELD->kleur());
    }

    // ========================================================================
    // Band
    // ========================================================================

    #[Test]
    public function band_labels(): void
    {
        $this->assertEquals('Wit', Band::WIT->label());
        $this->assertEquals('Geel', Band::GEEL->label());
        $this->assertEquals('Oranje', Band::ORANJE->label());
        $this->assertEquals('Groen', Band::GROEN->label());
        $this->assertEquals('Blauw', Band::BLAUW->label());
        $this->assertEquals('Bruin', Band::BRUIN->label());
        $this->assertEquals('Zwart', Band::ZWART->label());
    }

    #[Test]
    public function band_kleur_codes(): void
    {
        $this->assertEquals('#FFFFFF', Band::WIT->kleurCode());
        $this->assertEquals('#000000', Band::ZWART->kleurCode());
    }

    #[Test]
    public function band_from_string(): void
    {
        $this->assertEquals(Band::WIT, Band::fromString('wit'));
        $this->assertEquals(Band::WIT, Band::fromString('Wit'));
        $this->assertEquals(Band::GROEN, Band::fromString('groen (3e kyu)'));
        $this->assertNull(Band::fromString('onbekend'));
    }

    #[Test]
    public function band_from_string_extracts_first_word(): void
    {
        $this->assertEquals(Band::BLAUW, Band::fromString('blauw (2e kyu)'));
    }

    #[Test]
    public function band_from_string_searches_in_string(): void
    {
        $this->assertEquals(Band::ORANJE, Band::fromString('de oranje band'));
    }

    #[Test]
    public function band_to_kleur(): void
    {
        $this->assertEquals('Wit', Band::toKleur('wit'));
        $this->assertEquals('Geel', Band::toKleur(5));
        $this->assertEquals('', Band::toKleur(null));
        $this->assertEquals('', Band::toKleur(''));
        $this->assertEquals('Groen', Band::toKleur('groen (3e kyu)'));
    }

    #[Test]
    public function band_niveau(): void
    {
        $this->assertEquals(0, Band::WIT->niveau());  // beginner (wit=0)
        $this->assertEquals(6, Band::ZWART->niveau());  // expert (zwart=6)
    }

    #[Test]
    public function band_sort_niveau(): void
    {
        $this->assertEquals(1, Band::WIT->sortNiveau());  // beginner (1-indexed, wit=1)
        $this->assertEquals(7, Band::ZWART->sortNiveau());  // expert (zwart=7)
    }

    #[Test]
    public function band_get_sort_niveau_static(): void
    {
        $this->assertEquals(1, Band::getSortNiveau('wit'));     // beginner
        $this->assertEquals(7, Band::getSortNiveau('zwart'));   // expert
        $this->assertEquals(7, Band::getSortNiveau(null));      // unknown = treat as beginner
        $this->assertEquals(7, Band::getSortNiveau(''));         // unknown = treat as beginner
    }

    #[Test]
    public function band_past_in_filter_tm(): void
    {
        // tm_groen = wit, geel, oranje, groen
        $this->assertTrue(Band::pastInFilter('wit', 'tm_groen'));
        $this->assertTrue(Band::pastInFilter('groen', 'tm_groen'));
        $this->assertFalse(Band::pastInFilter('blauw', 'tm_groen'));
        $this->assertFalse(Band::pastInFilter('zwart', 'tm_groen'));
    }

    #[Test]
    public function band_past_in_filter_vanaf(): void
    {
        // vanaf_blauw = blauw, bruin, zwart
        $this->assertTrue(Band::pastInFilter('blauw', 'vanaf_blauw'));
        $this->assertTrue(Band::pastInFilter('zwart', 'vanaf_blauw'));
        $this->assertFalse(Band::pastInFilter('groen', 'vanaf_blauw'));
        $this->assertFalse(Band::pastInFilter('wit', 'vanaf_blauw'));
    }

    #[Test]
    public function band_past_in_filter_null_returns_true(): void
    {
        $this->assertTrue(Band::pastInFilter(null, 'tm_groen'));
        $this->assertTrue(Band::pastInFilter('wit', null));
        $this->assertTrue(Band::pastInFilter(null, null));
    }

    #[Test]
    public function band_strip_kyu_deprecated(): void
    {
        $this->assertEquals('Groen', Band::stripKyu('groen (3e kyu)'));
    }

    // ========================================================================
    // Geslacht
    // ========================================================================

    #[Test]
    public function geslacht_labels(): void
    {
        $this->assertEquals('Man', Geslacht::MAN->label());
        $this->assertEquals('Vrouw', Geslacht::VROUW->label());
    }

    #[Test]
    public function geslacht_from_string(): void
    {
        $this->assertEquals(Geslacht::MAN, Geslacht::fromString('M'));
        $this->assertEquals(Geslacht::MAN, Geslacht::fromString('man'));
        $this->assertEquals(Geslacht::MAN, Geslacht::fromString('Jongen'));
        $this->assertEquals(Geslacht::MAN, Geslacht::fromString('HEREN'));
        $this->assertEquals(Geslacht::MAN, Geslacht::fromString('heer'));
        $this->assertEquals(Geslacht::VROUW, Geslacht::fromString('V'));
        $this->assertEquals(Geslacht::VROUW, Geslacht::fromString('vrouw'));
        $this->assertEquals(Geslacht::VROUW, Geslacht::fromString('Meisje'));
        $this->assertEquals(Geslacht::VROUW, Geslacht::fromString('DAMES'));
        $this->assertEquals(Geslacht::VROUW, Geslacht::fromString('dame'));
        $this->assertNull(Geslacht::fromString('X'));
    }
}
