<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JudokaExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewicht_tolerantie' => 0.5,
        ]);
        $this->club = Club::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakJudoka(array $attrs = []): Judoka
    {
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ], $attrs));
    }

    // ========================================================================
    // formatNaam
    // ========================================================================

    #[Test]
    public function format_naam_capitalizes_correctly(): void
    {
        $this->assertEquals('Jansen, Pieter', Judoka::formatNaam('jansen, pieter'));
    }

    #[Test]
    public function format_naam_handles_tussenvoegsels(): void
    {
        $this->assertEquals('Pieter van de Berg', Judoka::formatNaam('pieter van de berg'));
    }

    #[Test]
    public function format_naam_handles_den_der(): void
    {
        $this->assertEquals('Jan van den Berg', Judoka::formatNaam('jan van den berg'));
    }

    // ========================================================================
    // QR code generation on create
    // ========================================================================

    #[Test]
    public function qr_code_generated_on_create(): void
    {
        $judoka = $this->maakJudoka();
        $this->assertNotEmpty($judoka->qr_code);
    }

    // ========================================================================
    // isBetaald / isKlaarVoorBetaling
    // ========================================================================

    #[Test]
    public function is_betaald_returns_true_when_betaald(): void
    {
        $judoka = $this->maakJudoka(['betaald_op' => now()]);
        $this->assertTrue($judoka->isBetaald());
    }

    #[Test]
    public function is_betaald_returns_false_when_not_betaald(): void
    {
        $judoka = $this->maakJudoka(['betaald_op' => null]);
        $this->assertFalse($judoka->isBetaald());
    }

    #[Test]
    public function is_klaar_voor_betaling(): void
    {
        $judoka = $this->maakJudoka([
            'naam' => 'Test Judoka',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 25.0,
            'betaald_op' => null,
        ]);
        $this->assertTrue($judoka->isKlaarVoorBetaling());
    }

    // ========================================================================
    // isAanwezig / isActief
    // ========================================================================

    #[Test]
    public function is_aanwezig_returns_true_for_aanwezig(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'aanwezig']);
        $this->assertTrue($judoka->isAanwezig());
    }

    #[Test]
    public function is_aanwezig_returns_false_for_afwezig(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'afwezig']);
        $this->assertFalse($judoka->isAanwezig());
    }

    #[Test]
    public function is_actief_returns_false_for_afwezig(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'afwezig']);
        $this->assertFalse($judoka->isActief());
    }

    #[Test]
    public function is_actief_returns_false_when_weging_closed_not_weighed(): void
    {
        $judoka = $this->maakJudoka([
            'aanwezigheid' => 'onbekend',
            'gewicht_gewogen' => null,
        ]);
        $this->assertFalse($judoka->isActief(wegingGesloten: true));
    }

    #[Test]
    public function is_actief_returns_true_when_weging_open(): void
    {
        $judoka = $this->maakJudoka([
            'aanwezigheid' => 'onbekend',
            'gewicht_gewogen' => null,
        ]);
        $this->assertTrue($judoka->isActief(wegingGesloten: false));
    }

    // ========================================================================
    // isGewichtBinnenKlasse
    // ========================================================================

    #[Test]
    public function gewicht_binnen_minus_klasse(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30', 'gewicht_gewogen' => 29.5]);
        $this->assertTrue($judoka->isGewichtBinnenKlasse(29.5));
    }

    #[Test]
    public function gewicht_buiten_minus_klasse(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30', 'gewicht_gewogen' => 32.0]);
        $this->assertFalse($judoka->isGewichtBinnenKlasse(32.0));
    }

    #[Test]
    public function gewicht_binnen_plus_klasse(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '+70', 'gewicht_gewogen' => 72.0]);
        $this->assertTrue($judoka->isGewichtBinnenKlasse(72.0));
    }

    #[Test]
    public function gewicht_buiten_plus_klasse(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '+70', 'gewicht_gewogen' => 65.0]);
        $this->assertFalse($judoka->isGewichtBinnenKlasse(65.0));
    }

    #[Test]
    public function gewicht_within_tolerance(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => '-30']);
        $this->assertTrue($judoka->isGewichtBinnenKlasse(30.4, 0.5));
        $this->assertFalse($judoka->isGewichtBinnenKlasse(31.0, 0.5));
    }

    #[Test]
    public function gewicht_variable_class_always_passes(): void
    {
        $judoka = $this->maakJudoka(['gewichtsklasse' => 'variabel']);
        $this->assertTrue($judoka->isGewichtBinnenKlasse(99.0));
    }

    // ========================================================================
    // moetUitPouleVerwijderd
    // ========================================================================

    #[Test]
    public function moet_uit_poule_verwijderd_for_afwezig(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'afwezig']);
        $this->assertTrue($judoka->moetUitPouleVerwijderd());
    }

    #[Test]
    public function moet_uit_poule_verwijderd_for_afgemeld(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'afgemeld']);
        $this->assertTrue($judoka->moetUitPouleVerwijderd());
    }

    #[Test]
    public function niet_uit_poule_verwijderd_for_aanwezig(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'aanwezig']);
        $this->assertFalse($judoka->moetUitPouleVerwijderd());
    }

    // ========================================================================
    // heeftAfwijkendGewicht
    // ========================================================================

    #[Test]
    public function heeft_afwijkend_gewicht_when_out_of_class(): void
    {
        $judoka = $this->maakJudoka([
            'gewichtsklasse' => '-30',
            'gewicht_gewogen' => 35.0,
        ]);
        $this->assertTrue($judoka->heeftAfwijkendGewicht(0.5));
    }

    #[Test]
    public function geen_afwijkend_gewicht_when_in_class(): void
    {
        $judoka = $this->maakJudoka([
            'gewichtsklasse' => '-30',
            'gewicht_gewogen' => 29.0,
        ]);
        $this->assertFalse($judoka->heeftAfwijkendGewicht(0.5));
    }

    #[Test]
    public function geen_afwijkend_gewicht_when_not_weighed(): void
    {
        $judoka = $this->maakJudoka([
            'gewichtsklasse' => '-30',
            'gewicht_gewogen' => null,
        ]);
        $this->assertFalse($judoka->heeftAfwijkendGewicht());
    }

    // ========================================================================
    // getEffectiefGewicht
    // ========================================================================

    #[Test]
    public function effectief_gewicht_uses_gewogen_first(): void
    {
        $judoka = $this->maakJudoka([
            'gewicht' => 28.0,
            'gewicht_gewogen' => 29.5,
        ]);
        $this->assertEquals(29.5, $judoka->getEffectiefGewicht());
    }

    #[Test]
    public function effectief_gewicht_falls_back_to_gewicht(): void
    {
        $judoka = $this->maakJudoka([
            'gewicht' => 28.0,
            'gewicht_gewogen' => null,
        ]);
        $this->assertEquals(28.0, $judoka->getEffectiefGewicht());
    }

    #[Test]
    public function effectief_gewicht_extracts_from_klasse(): void
    {
        $judoka = $this->maakJudoka([
            'gewicht' => null,
            'gewicht_gewogen' => null,
            'gewichtsklasse' => '-30',
        ]);
        $this->assertEquals(30.0, $judoka->getEffectiefGewicht());
    }

    // ========================================================================
    // isVolledig
    // ========================================================================

    #[Test]
    public function is_volledig_true_when_all_fields(): void
    {
        $judoka = $this->maakJudoka([
            'naam' => 'Test',
            'geboortejaar' => 2015,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 25.0,
        ]);
        $this->assertTrue($judoka->isVolledig());
    }

    #[Test]
    public function is_volledig_false_when_missing_fields(): void
    {
        $judoka = $this->maakJudoka([
            'naam' => 'Test',
            'geboortejaar' => null,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 25.0,
        ]);
        $this->assertFalse($judoka->isVolledig());
    }

    // ========================================================================
    // getOntbrekendeVelden
    // ========================================================================

    #[Test]
    public function get_ontbrekende_velden_lists_missing(): void
    {
        $judoka = $this->maakJudoka([
            'naam' => '',
            'geboortejaar' => null,
            'geslacht' => '',
            'band' => '',
            'gewicht' => null,
        ]);
        $velden = $judoka->getOntbrekendeVelden();
        $this->assertContains('naam', $velden);
        $this->assertContains('geboortejaar', $velden);
        $this->assertContains('geslacht', $velden);
        $this->assertContains('band', $velden);
        $this->assertContains('gewicht', $velden);
    }

    // ========================================================================
    // isSynced / isGewijzigdNaSync
    // ========================================================================

    #[Test]
    public function is_synced_when_synced_at_set(): void
    {
        $judoka = $this->maakJudoka(['synced_at' => now()]);
        $this->assertTrue($judoka->isSynced());
    }

    #[Test]
    public function is_not_synced_when_null(): void
    {
        $judoka = $this->maakJudoka(['synced_at' => null]);
        $this->assertFalse($judoka->isSynced());
    }

    // ========================================================================
    // bepaalGewichtsklasse (static)
    // ========================================================================

    #[Test]
    public function bepaal_gewichtsklasse_finds_correct_class(): void
    {
        $klassen = ['-20', '-24', '-28', '+28'];
        $this->assertEquals('-20', Judoka::bepaalGewichtsklasse(18.0, $klassen));
        $this->assertEquals('-24', Judoka::bepaalGewichtsklasse(22.0, $klassen));
        $this->assertEquals('-28', Judoka::bepaalGewichtsklasse(26.0, $klassen));
        $this->assertEquals('+28', Judoka::bepaalGewichtsklasse(32.0, $klassen));
    }

    #[Test]
    public function bepaal_gewichtsklasse_returns_null_for_empty(): void
    {
        $this->assertNull(Judoka::bepaalGewichtsklasse(25.0, []));
    }

    // ========================================================================
    // isTeCorrigeren
    // ========================================================================

    #[Test]
    public function is_te_corrigeren_when_status_set(): void
    {
        $judoka = $this->maakJudoka(['import_status' => 'te_corrigeren']);
        $this->assertTrue($judoka->isTeCorrigeren());
    }

    // ========================================================================
    // leeftijd attribute
    // ========================================================================

    #[Test]
    public function leeftijd_attribute_calculates_correctly(): void
    {
        $judoka = $this->maakJudoka(['geboortejaar' => (int)date('Y') - 10]);
        $this->assertEquals(10, $judoka->leeftijd);
    }

    // ========================================================================
    // Relations
    // ========================================================================

    #[Test]
    public function judoka_belongs_to_toernooi(): void
    {
        $judoka = $this->maakJudoka();
        $this->assertNotNull($judoka->toernooi);
        $this->assertEquals($this->toernooi->id, $judoka->toernooi->id);
    }

    #[Test]
    public function judoka_belongs_to_club(): void
    {
        $judoka = $this->maakJudoka();
        $this->assertNotNull($judoka->club);
        $this->assertEquals($this->club->id, $judoka->club->id);
    }

    // ========================================================================
    // Enum attributes
    // ========================================================================

    #[Test]
    public function band_enum_attribute(): void
    {
        $judoka = $this->maakJudoka(['band' => 'wit']);
        $this->assertNotNull($judoka->bandEnum);
    }

    #[Test]
    public function geslacht_enum_attribute(): void
    {
        $judoka = $this->maakJudoka(['geslacht' => 'M']);
        $this->assertNotNull($judoka->geslachtEnum);
    }

    #[Test]
    public function aanwezigheid_enum_attribute(): void
    {
        $judoka = $this->maakJudoka(['aanwezigheid' => 'aanwezig']);
        $this->assertNotNull($judoka->aanwezigheidEnum);
    }
}
