<?php

namespace Tests\Unit\Models;

use App\Models\ActivityLog;
use App\Models\ClubAanmelding;
use App\Models\CoachKaart;
use App\Models\CoachKaartWisseling;
use App\Models\Club;
use App\Models\GewichtsklassenPreset;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\Vrijwilliger;
use App\Models\Weging;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use App\Models\WimpelUitreiking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimpleModelsCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ========== ActivityLog ==========

    #[Test]
    public function activity_log_belongs_to_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();
        $log = ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'test_actie',
            'beschrijving' => 'Test beschrijving',
        ]);

        $this->assertInstanceOf(Toernooi::class, $log->toernooi);
        $this->assertEquals($toernooi->id, $log->toernooi->id);
    }

    #[Test]
    public function activity_log_scope_voor_actie(): void
    {
        $toernooi = Toernooi::factory()->create();
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'verplaats_judoka', 'beschrijving' => 'A']);
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'meld_af', 'beschrijving' => 'B']);
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'verplaats_judoka', 'beschrijving' => 'C']);

        $result = ActivityLog::voorActie('verplaats_judoka')->get();

        $this->assertCount(2, $result);
    }

    #[Test]
    public function activity_log_scope_voor_model_without_id(): void
    {
        $toernooi = Toernooi::factory()->create();
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'test', 'beschrijving' => 'A', 'model_type' => 'Judoka', 'model_id' => 1]);
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'test', 'beschrijving' => 'B', 'model_type' => 'Judoka', 'model_id' => 2]);
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'test', 'beschrijving' => 'C', 'model_type' => 'Poule']);

        $result = ActivityLog::voorModel('Judoka')->get();
        $this->assertCount(2, $result);
    }

    #[Test]
    public function activity_log_scope_voor_model_with_id(): void
    {
        $toernooi = Toernooi::factory()->create();
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'test', 'beschrijving' => 'A', 'model_type' => 'Judoka', 'model_id' => 1]);
        ActivityLog::create(['toernooi_id' => $toernooi->id, 'actie' => 'test', 'beschrijving' => 'B', 'model_type' => 'Judoka', 'model_id' => 2]);

        $result = ActivityLog::voorModel('Judoka', 1)->get();
        $this->assertCount(1, $result);
    }

    #[Test]
    public function activity_log_get_actie_naam_attribute_known_actions(): void
    {
        $toernooi = Toernooi::factory()->create();

        $knownActions = [
            'verplaats_judoka' => 'Verplaats judoka',
            'nieuwe_judoka' => 'Nieuwe judoka',
            'meld_af' => 'Afmelding',
            'herstel_judoka' => 'Herstel judoka',
            'verwijder_uit_poule' => 'Verwijder uit poule',
            'registreer_uitslag' => 'Uitslag geregistreerd',
            'plaats_judoka' => 'Judoka geplaatst',
            'verwijder_judoka' => 'Judoka verwijderd',
            'poule_klaar' => 'Poule klaar',
            'registreer_gewicht' => 'Gewicht geregistreerd',
            'markeer_aanwezig' => 'Aanwezig gemarkeerd',
            'markeer_afwezig' => 'Afwezig gemarkeerd',
            'genereer_poules' => 'Poules gegenereerd',
            'maak_poule' => 'Poule aangemaakt',
            'verwijder_poule' => 'Poule verwijderd',
            'sluit_weging' => 'Weging gesloten',
            'activeer_categorie' => 'Categorie geactiveerd',
            'reset_categorie' => 'Categorie gereset',
            'reset_alles' => 'Alles gereset',
            'reset_blok' => 'Blok gereset',
            'update_instellingen' => 'Instellingen bijgewerkt',
            'afsluiten' => 'Toernooi afgesloten',
            'verwijder_toernooi' => 'Toernooi verwijderd',
        ];

        foreach ($knownActions as $actie => $expected) {
            $log = ActivityLog::create([
                'toernooi_id' => $toernooi->id,
                'actie' => $actie,
                'beschrijving' => 'Test',
            ]);
            $this->assertEquals($expected, $log->actie_naam, "Failed for action: $actie");
        }
    }

    #[Test]
    public function activity_log_get_actie_naam_attribute_unknown_action(): void
    {
        $toernooi = Toernooi::factory()->create();
        $log = ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'onbekende_actie',
            'beschrijving' => 'Test',
        ]);

        $this->assertEquals('Onbekende actie', $log->actie_naam);
    }

    #[Test]
    public function activity_log_casts_properties_as_array(): void
    {
        $toernooi = Toernooi::factory()->create();
        $log = ActivityLog::create([
            'toernooi_id' => $toernooi->id,
            'actie' => 'test',
            'beschrijving' => 'Test',
            'properties' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $log->refresh();
        $this->assertIsArray($log->properties);
        $this->assertEquals('value', $log->properties['key']);
    }

    // ========== ClubAanmelding ==========

    #[Test]
    public function club_aanmelding_belongs_to_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();
        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $toernooi->id,
            'club_naam' => 'Test Club',
            'contact_naam' => 'Jan',
            'email' => 'jan@test.nl',
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(Toernooi::class, $aanmelding->toernooi);
    }

    #[Test]
    public function club_aanmelding_is_pending_returns_true(): void
    {
        $toernooi = Toernooi::factory()->create();
        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $toernooi->id,
            'club_naam' => 'Test Club',
            'status' => 'pending',
        ]);

        $this->assertTrue($aanmelding->isPending());
    }

    #[Test]
    public function club_aanmelding_is_pending_returns_false(): void
    {
        $toernooi = Toernooi::factory()->create();
        $aanmelding = ClubAanmelding::create([
            'toernooi_id' => $toernooi->id,
            'club_naam' => 'Test Club',
            'status' => 'goedgekeurd',
        ]);

        $this->assertFalse($aanmelding->isPending());
    }

    // ========== GewichtsklassenPreset ==========

    #[Test]
    public function gewichtsklassen_preset_belongs_to_organisator(): void
    {
        $organisator = Organisator::factory()->create();
        $preset = GewichtsklassenPreset::create([
            'organisator_id' => $organisator->id,
            'naam' => 'Standaard',
            'configuratie' => ['klassen' => ['-30', '-35', '+35']],
        ]);

        $this->assertInstanceOf(Organisator::class, $preset->organisator);
        $this->assertEquals($organisator->id, $preset->organisator->id);
    }

    #[Test]
    public function gewichtsklassen_preset_casts_configuratie_as_array(): void
    {
        $organisator = Organisator::factory()->create();
        $config = ['klassen' => ['-30', '-35', '+35'], 'leeftijd' => ['min' => 4, 'max' => 12]];
        $preset = GewichtsklassenPreset::create([
            'organisator_id' => $organisator->id,
            'naam' => 'Test Preset',
            'configuratie' => $config,
        ]);

        $preset->refresh();
        $this->assertIsArray($preset->configuratie);
        $this->assertEquals($config, $preset->configuratie);
    }

    // ========== Weging ==========

    #[Test]
    public function weging_belongs_to_judoka(): void
    {
        $judoka = Judoka::factory()->create();
        $weging = Weging::create([
            'judoka_id' => $judoka->id,
            'gewicht' => 32.5,
            'binnen_klasse' => true,
        ]);

        $this->assertInstanceOf(Judoka::class, $weging->judoka);
        $this->assertEquals($judoka->id, $weging->judoka->id);
    }

    #[Test]
    public function weging_casts_correctly(): void
    {
        $judoka = Judoka::factory()->create();
        $weging = Weging::create([
            'judoka_id' => $judoka->id,
            'gewicht' => 32.5,
            'binnen_klasse' => 1,
        ]);

        $weging->refresh();
        $this->assertIsBool($weging->binnen_klasse);
        $this->assertTrue($weging->binnen_klasse);
    }

    // ========== WimpelMilestone ==========

    #[Test]
    public function wimpel_milestone_belongs_to_organisator(): void
    {
        $organisator = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 100,
            'omschrijving' => 'Bronzen wimpel',
            'volgorde' => 1,
        ]);

        $this->assertInstanceOf(Organisator::class, $milestone->organisator);
        $this->assertEquals($organisator->id, $milestone->organisator->id);
    }

    #[Test]
    public function wimpel_milestone_casts_correctly(): void
    {
        $organisator = Organisator::factory()->create();
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 100,
            'omschrijving' => 'Test',
            'volgorde' => 2,
        ]);

        $milestone->refresh();
        $this->assertIsInt($milestone->punten);
        $this->assertIsInt($milestone->volgorde);
    }

    // ========== WimpelPuntenLog ==========

    #[Test]
    public function wimpel_punten_log_belongs_to_stam_judoka(): void
    {
        $stamJudoka = StamJudoka::factory()->create();
        $log = WimpelPuntenLog::create([
            'stam_judoka_id' => $stamJudoka->id,
            'punten' => 10,
            'type' => 'automatisch',
        ]);

        $this->assertInstanceOf(StamJudoka::class, $log->stamJudoka);
        $this->assertEquals($stamJudoka->id, $log->stamJudoka->id);
    }

    #[Test]
    public function wimpel_punten_log_belongs_to_toernooi(): void
    {
        $stamJudoka = StamJudoka::factory()->create();
        $toernooi = Toernooi::factory()->create();
        $log = WimpelPuntenLog::create([
            'stam_judoka_id' => $stamJudoka->id,
            'toernooi_id' => $toernooi->id,
            'punten' => 5,
            'type' => 'handmatig',
            'notitie' => 'Handmatige correctie',
        ]);

        $this->assertInstanceOf(Toernooi::class, $log->toernooi);
        $this->assertEquals($toernooi->id, $log->toernooi->id);
    }

    #[Test]
    public function wimpel_punten_log_casts_punten_as_integer(): void
    {
        $stamJudoka = StamJudoka::factory()->create();
        $log = WimpelPuntenLog::create([
            'stam_judoka_id' => $stamJudoka->id,
            'punten' => 15,
            'type' => 'automatisch',
        ]);

        $log->refresh();
        $this->assertIsInt($log->punten);
    }

    // ========== WimpelUitreiking ==========

    #[Test]
    public function wimpel_uitreiking_belongs_to_stam_judoka(): void
    {
        $organisator = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $organisator->id]);
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 50,
            'omschrijving' => 'Test milestone',
            'volgorde' => 1,
        ]);

        $uitreiking = WimpelUitreiking::create([
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'uitgereikt' => false,
        ]);

        $this->assertInstanceOf(StamJudoka::class, $uitreiking->stamJudoka);
    }

    #[Test]
    public function wimpel_uitreiking_belongs_to_milestone(): void
    {
        $organisator = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $organisator->id]);
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 50,
            'omschrijving' => 'Test',
            'volgorde' => 1,
        ]);

        $uitreiking = WimpelUitreiking::create([
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'uitgereikt' => false,
        ]);

        $this->assertInstanceOf(WimpelMilestone::class, $uitreiking->milestone);
        $this->assertEquals($milestone->id, $uitreiking->milestone->id);
    }

    #[Test]
    public function wimpel_uitreiking_belongs_to_toernooi(): void
    {
        $organisator = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $organisator->id]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 50,
            'omschrijving' => 'Test',
            'volgorde' => 1,
        ]);

        $uitreiking = WimpelUitreiking::create([
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'toernooi_id' => $toernooi->id,
            'uitgereikt' => true,
            'uitgereikt_at' => now(),
        ]);

        $this->assertInstanceOf(Toernooi::class, $uitreiking->toernooi);
    }

    #[Test]
    public function wimpel_uitreiking_casts_correctly(): void
    {
        $organisator = Organisator::factory()->create();
        $stamJudoka = StamJudoka::factory()->create(['organisator_id' => $organisator->id]);
        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => 50,
            'omschrijving' => 'Test',
            'volgorde' => 1,
        ]);

        $uitreiking = WimpelUitreiking::create([
            'stam_judoka_id' => $stamJudoka->id,
            'wimpel_milestone_id' => $milestone->id,
            'uitgereikt' => 1,
            'uitgereikt_at' => '2026-04-08 12:00:00',
        ]);

        $uitreiking->refresh();
        $this->assertIsBool($uitreiking->uitgereikt);
        $this->assertTrue($uitreiking->uitgereikt);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $uitreiking->uitgereikt_at);
    }

    // ========== Vrijwilliger ==========

    #[Test]
    public function vrijwilliger_belongs_to_organisator(): void
    {
        $organisator = Organisator::factory()->create();
        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $organisator->id,
            'voornaam' => 'Jan',
            'telefoonnummer' => '0612345678',
            'functie' => 'mat',
        ]);

        $this->assertInstanceOf(Organisator::class, $vrijwilliger->organisator);
    }

    #[Test]
    public function vrijwilliger_get_functie_label(): void
    {
        $organisator = Organisator::factory()->create();
        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $organisator->id,
            'voornaam' => 'Piet',
            'functie' => 'hoofdjury',
        ]);

        $this->assertEquals('Hoofdjury', $vrijwilliger->getFunctieLabel());
    }

    #[Test]
    public function vrijwilliger_get_whatsapp_url_with_dutch_mobile(): void
    {
        $organisator = Organisator::factory()->create();
        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $organisator->id,
            'voornaam' => 'Kees',
            'telefoonnummer' => '06-12345678',
            'functie' => 'weging',
        ]);

        $url = $vrijwilliger->getWhatsAppUrl('Hallo!');

        $this->assertStringStartsWith('https://wa.me/31612345678', $url);
        $this->assertStringContainsString('text=', $url);
    }

    #[Test]
    public function vrijwilliger_get_whatsapp_url_with_landline(): void
    {
        $organisator = Organisator::factory()->create();
        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $organisator->id,
            'voornaam' => 'Dirk',
            'telefoonnummer' => '020-1234567',
            'functie' => 'spreker',
        ]);

        $url = $vrijwilliger->getWhatsAppUrl('Test');

        // 020 -> +3120
        $this->assertStringStartsWith('https://wa.me/31201234567', $url);
    }

    #[Test]
    public function vrijwilliger_get_whatsapp_url_empty_phone(): void
    {
        $organisator = Organisator::factory()->create();
        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $organisator->id,
            'voornaam' => 'Leeg',
            'telefoonnummer' => null,
            'functie' => 'dojo',
        ]);

        $this->assertEquals('', $vrijwilliger->getWhatsAppUrl('Test'));
    }

    #[Test]
    public function vrijwilliger_functies_constant(): void
    {
        $this->assertEquals(['mat', 'weging', 'spreker', 'dojo', 'hoofdjury'], Vrijwilliger::FUNCTIES);
        $this->assertCount(5, Vrijwilliger::FUNCTIES);
    }

    // ========== CoachKaartWisseling ==========

    #[Test]
    public function coach_kaart_wisseling_belongs_to_coach_kaart(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test Coach',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Nieuwe Coach',
            'geactiveerd_op' => now(),
        ]);

        $this->assertInstanceOf(CoachKaart::class, $wisseling->coachKaart);
        $this->assertEquals($kaart->id, $wisseling->coachKaart->id);
    }

    #[Test]
    public function coach_kaart_wisseling_get_foto_url_with_foto(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Coach',
            'foto' => 'coaches/foto.jpg',
            'geactiveerd_op' => now(),
        ]);

        $url = $wisseling->getFotoUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('storage/coaches/foto.jpg', $url);
    }

    #[Test]
    public function coach_kaart_wisseling_get_foto_url_without_foto(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Coach',
            'foto' => null,
            'geactiveerd_op' => now(),
        ]);

        $this->assertNull($wisseling->getFotoUrl());
    }

    #[Test]
    public function coach_kaart_wisseling_is_huidige_coach_true(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Huidige Coach',
            'geactiveerd_op' => now(),
            'overgedragen_op' => null,
        ]);

        $this->assertTrue($wisseling->isHuidigeCoach());
    }

    #[Test]
    public function coach_kaart_wisseling_is_huidige_coach_false(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Vorige Coach',
            'geactiveerd_op' => now()->subHour(),
            'overgedragen_op' => now(),
        ]);

        $this->assertFalse($wisseling->isHuidigeCoach());
    }

    #[Test]
    public function coach_kaart_wisseling_casts_correctly(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test',
        ]);

        $wisseling = CoachKaartWisseling::create([
            'coach_kaart_id' => $kaart->id,
            'naam' => 'Coach',
            'geactiveerd_op' => '2026-04-08 10:00:00',
            'overgedragen_op' => '2026-04-08 12:00:00',
        ]);

        $wisseling->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $wisseling->geactiveerd_op);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $wisseling->overgedragen_op);
    }
}
