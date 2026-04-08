<?php

namespace Tests\Unit\Models;

use App\Models\AutofixProposal;
use App\Models\Betaling;
use App\Models\ChatMessage;
use App\Models\Club;
use App\Models\CoachCheckin;
use App\Models\CoachKaart;
use App\Models\DeviceToegang;
use App\Models\EmailLog;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\SyncQueueItem;
use App\Models\SyncStatus;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Models\TvKoppeling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediumModelsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function maakToernooi(): Toernooi
    {
        $org = Organisator::factory()->create();
        return Toernooi::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakClub(Organisator $org = null): Club
    {
        return Club::factory()->create([
            'organisator_id' => ($org ?? Organisator::factory()->create())->id,
        ]);
    }

    // ========================================================================
    // Betaling
    // ========================================================================

    #[Test]
    public function betaling_belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test123',
            'bedrag' => 25.00,
            'aantal_judokas' => 5,
            'status' => 'open',
        ]);

        $this->assertInstanceOf(Toernooi::class, $betaling->toernooi);
        $this->assertEquals($toernooi->id, $betaling->toernooi->id);
    }

    #[Test]
    public function betaling_belongs_to_club(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test124',
            'bedrag' => 25.00,
            'aantal_judokas' => 5,
            'status' => 'open',
        ]);

        $this->assertInstanceOf(Club::class, $betaling->club);
        $this->assertEquals($club->id, $betaling->club->id);
    }

    #[Test]
    public function betaling_has_many_judokas(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test125',
            'bedrag' => 25.00,
            'aantal_judokas' => 2,
            'status' => 'open',
        ]);

        Judoka::factory()->count(2)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'betaling_id' => $betaling->id,
        ]);

        $this->assertCount(2, $betaling->judokas);
    }

    #[Test]
    public function betaling_is_betaald_returns_true_when_paid(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test126',
            'bedrag' => 25.00,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_PAID,
        ]);

        $this->assertTrue($betaling->isBetaald());
    }

    #[Test]
    public function betaling_is_betaald_returns_false_when_not_paid(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test127',
            'bedrag' => 25.00,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_OPEN,
        ]);

        $this->assertFalse($betaling->isBetaald());
    }

    #[Test]
    public function betaling_is_pending_for_open_and_pending_statuses(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $open = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_open1',
            'bedrag' => 10.00,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_OPEN,
        ]);

        $pending = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_pending1',
            'bedrag' => 10.00,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_PENDING,
        ]);

        $paid = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_paid1',
            'bedrag' => 10.00,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_PAID,
        ]);

        $this->assertTrue($open->isPending());
        $this->assertTrue($pending->isPending());
        $this->assertFalse($paid->isPending());
    }

    #[Test]
    public function betaling_markeer_als_betaald_updates_status_and_judokas(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_test128',
            'bedrag' => 25.00,
            'aantal_judokas' => 2,
            'status' => Betaling::STATUS_OPEN,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'betaling_id' => $betaling->id,
        ]);

        $betaling->markeerAlsBetaald();
        $betaling->refresh();
        $judoka->refresh();

        $this->assertEquals(Betaling::STATUS_PAID, $betaling->status);
        $this->assertNotNull($betaling->betaald_op);
        $this->assertNotNull($judoka->betaald_op);
    }

    #[Test]
    public function betaling_casts_bedrag_and_betaald_op(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $betaling = Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'tr_cast1',
            'bedrag' => 25.50,
            'aantal_judokas' => 1,
            'status' => Betaling::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        $betaling->refresh();
        $this->assertEquals('25.50', $betaling->bedrag);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $betaling->betaald_op);
    }

    // ========================================================================
    // ChatMessage
    // ========================================================================

    #[Test]
    public function chat_message_belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'mat',
            'naar_id' => 1,
            'bericht' => 'Test bericht',
        ]);

        $this->assertInstanceOf(Toernooi::class, $msg->toernooi);
    }

    #[Test]
    public function chat_message_is_voor_iedereen(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'iedereen',
            'bericht' => 'Broadcast',
        ]);

        $this->assertTrue($msg->isVoor('mat', 1));
        $this->assertTrue($msg->isVoor('weging'));
        $this->assertTrue($msg->isVoor('hoofdjury'));
    }

    #[Test]
    public function chat_message_is_voor_alle_matten(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'alle_matten',
            'bericht' => 'Alle matten bericht',
        ]);

        $this->assertTrue($msg->isVoor('mat', 1));
        $this->assertTrue($msg->isVoor('mat', 2));
        $this->assertFalse($msg->isVoor('weging'));
    }

    #[Test]
    public function chat_message_is_voor_direct_message(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => 3,
            'bericht' => 'Direct bericht',
        ]);

        $this->assertTrue($msg->isVoor('mat', 3));
        $this->assertFalse($msg->isVoor('mat', 1));
        $this->assertFalse($msg->isVoor('weging'));
    }

    #[Test]
    public function chat_message_is_voor_type_without_id(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => null,
            'bericht' => 'Naar alle mat types',
        ]);

        // When naar_id is null, any mat id matches
        $this->assertTrue($msg->isVoor('mat', 1));
        $this->assertTrue($msg->isVoor('mat'));
    }

    #[Test]
    public function chat_message_markeer_gelezen(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Help nodig',
        ]);

        $this->assertNull($msg->gelezen_op);

        $msg->markeerGelezen();
        $msg->refresh();
        $this->assertNotNull($msg->gelezen_op);

        // Calling again should not change the timestamp
        $originalTimestamp = $msg->gelezen_op->toDateTimeString();
        $msg->markeerGelezen();
        $msg->refresh();
        $this->assertEquals($originalTimestamp, $msg->gelezen_op->toDateTimeString());
    }

    #[Test]
    public function chat_message_scope_voor(): void
    {
        $toernooi = $this->maakToernooi();

        // Direct message to mat 1
        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => 1,
            'bericht' => 'Bericht voor mat 1',
        ]);

        // Broadcast to iedereen
        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'iedereen',
            'bericht' => 'Broadcast',
        ]);

        // Broadcast to alle_matten
        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'alle_matten',
            'bericht' => 'Alle matten',
        ]);

        // Direct message to mat 2 (should not appear for mat 1)
        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => 2,
            'bericht' => 'Bericht voor mat 2',
        ]);

        // Mat 1 should see: direct + iedereen + alle_matten = 3
        $mat1Messages = ChatMessage::voor('mat', 1)->get();
        $this->assertCount(3, $mat1Messages);

        // Weging should see: only iedereen = 1
        $wegingMessages = ChatMessage::voor('weging', 1)->get();
        $this->assertCount(1, $wegingMessages);
    }

    #[Test]
    public function chat_message_scope_ongelezen(): void
    {
        $toernooi = $this->maakToernooi();

        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Ongelezen',
        ]);

        ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => 2,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Gelezen',
            'gelezen_op' => now(),
        ]);

        $this->assertCount(1, ChatMessage::ongelezen()->get());
    }

    #[Test]
    public function chat_message_afzender_naam_accessor(): void
    {
        $toernooi = $this->maakToernooi();

        $hoofdjury = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'iedereen',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Hoofdjury', $hoofdjury->afzender_naam);

        // Test fallback with van_id but no DeviceToegang
        $mat = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => 5,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Mat 5', $mat->afzender_naam);

        // Test without van_id
        $weging = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'weging',
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Weging', $weging->afzender_naam);

        // Test unknown type
        $unknown = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'onbekend_type',
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Onbekend', $unknown->afzender_naam);
    }

    #[Test]
    public function chat_message_ontvanger_naam_accessor(): void
    {
        $toernooi = $this->maakToernooi();

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Hoofdjury', $msg->ontvanger_naam);

        $msg2 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'alle_matten',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Alle matten', $msg2->ontvanger_naam);

        $msg3 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'iedereen',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Iedereen', $msg3->ontvanger_naam);

        $msg4 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => 3,
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Mat 3', $msg4->ontvanger_naam);

        // Test unknown type
        $msg5 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'onbekend_type',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Onbekend', $msg5->ontvanger_naam);
    }

    // ========================================================================
    // CoachCheckin
    // ========================================================================

    #[Test]
    public function coach_checkin_belongs_to_toernooi_and_coach_kaart(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Coach Test',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '1234',
        ]);

        $checkin = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach Test',
            'club_naam' => 'Test Club',
            'actie' => 'in',
        ]);

        $this->assertInstanceOf(CoachKaart::class, $checkin->coachKaart);
        $this->assertInstanceOf(Toernooi::class, $checkin->toernooi);
    }

    #[Test]
    public function coach_checkin_scope_vandaag(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Coach',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '1234',
        ]);

        CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'in',
        ]);

        $this->assertCount(1, CoachCheckin::vandaag()->get());
    }

    #[Test]
    public function coach_checkin_scope_voor_club(): void
    {
        $toernooi = $this->maakToernooi();
        $club1 = $this->maakClub();
        $club2 = $this->maakClub();

        $kaart1 = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club1->id,
            'naam' => 'Coach 1',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '1234',
        ]);

        $kaart2 = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club2->id,
            'naam' => 'Coach 2',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '5678',
        ]);

        CoachCheckin::create([
            'coach_kaart_id' => $kaart1->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach 1',
            'club_naam' => 'Club 1',
            'actie' => 'in',
        ]);

        CoachCheckin::create([
            'coach_kaart_id' => $kaart2->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach 2',
            'club_naam' => 'Club 2',
            'actie' => 'in',
        ]);

        $this->assertCount(1, CoachCheckin::voorClub($club1->id)->get());
    }

    #[Test]
    public function coach_checkin_is_in_uit_geforceerd(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Coach',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '1234',
        ]);

        $inCheckin = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'in',
        ]);

        $uitCheckin = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'uit',
        ]);

        $geforceerd = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'uit_geforceerd',
            'geforceerd_door' => 'hoofdjury',
        ]);

        $this->assertTrue($inCheckin->isIn());
        $this->assertFalse($inCheckin->isUit());
        $this->assertFalse($inCheckin->isGeforceerd());

        $this->assertFalse($uitCheckin->isIn());
        $this->assertTrue($uitCheckin->isUit());
        $this->assertFalse($uitCheckin->isGeforceerd());

        $this->assertFalse($geforceerd->isIn());
        $this->assertTrue($geforceerd->isUit());
        $this->assertTrue($geforceerd->isGeforceerd());
    }

    #[Test]
    public function coach_checkin_get_foto_url(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $kaart = CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Coach',
            'qr_code' => 'CK' . strtoupper(\Illuminate\Support\Str::random(10)),
            'pincode' => '1234',
        ]);

        $withFoto = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'in',
            'foto' => 'coaches/test.jpg',
        ]);

        $withoutFoto = CoachCheckin::create([
            'coach_kaart_id' => $kaart->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach',
            'club_naam' => 'Club',
            'actie' => 'in',
        ]);

        $this->assertNotNull($withFoto->getFotoUrl());
        $this->assertStringContainsString('coaches/test.jpg', $withFoto->getFotoUrl());
        $this->assertNull($withoutFoto->getFotoUrl());
    }

    // ========================================================================
    // EmailLog
    // ========================================================================

    #[Test]
    public function email_log_belongs_to_toernooi_and_club(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $log = EmailLog::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'type' => 'uitnodiging',
            'recipients' => 'test@example.com',
            'subject' => 'Uitnodiging',
            'status' => 'sent',
        ]);

        $this->assertInstanceOf(Toernooi::class, $log->toernooi);
        $this->assertInstanceOf(Club::class, $log->club);
    }

    #[Test]
    public function email_log_log_sent_static(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        // With string recipients
        $log1 = EmailLog::logSent(
            $toernooi->id,
            'uitnodiging',
            'test@example.com',
            'Uitnodiging toernooi',
            'Samenvatting',
            $club->id
        );

        $this->assertEquals('sent', $log1->status);
        $this->assertEquals('test@example.com', $log1->recipients);

        // With array recipients
        $log2 = EmailLog::logSent(
            $toernooi->id,
            'herinnering',
            ['a@test.com', 'b@test.com'],
            'Herinnering',
        );

        $this->assertEquals('a@test.com, b@test.com', $log2->recipients);
        $this->assertNull($log2->club_id);
    }

    #[Test]
    public function email_log_log_failed_static(): void
    {
        $toernooi = $this->maakToernooi();

        $log = EmailLog::logFailed(
            $toernooi->id,
            'correctie',
            ['fail@test.com'],
            'Correctie verzoek',
            'SMTP timeout',
        );

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('SMTP timeout', $log->error_message);
        $this->assertEquals('fail@test.com', $log->recipients);
    }

    #[Test]
    public function email_log_is_successful(): void
    {
        $toernooi = $this->maakToernooi();

        $sent = EmailLog::logSent($toernooi->id, 'uitnodiging', 'a@b.com', 'Sub');
        $failed = EmailLog::logFailed($toernooi->id, 'uitnodiging', 'a@b.com', 'Sub', 'Error');

        $this->assertTrue($sent->isSuccessful());
        $this->assertFalse($failed->isSuccessful());
    }

    #[Test]
    public function email_log_get_type_naam_attribute(): void
    {
        $toernooi = $this->maakToernooi();

        $uitnodiging = EmailLog::logSent($toernooi->id, 'uitnodiging', 'a@b.com', 'Sub');
        $correctie = EmailLog::logSent($toernooi->id, 'correctie', 'a@b.com', 'Sub');
        $herinnering = EmailLog::logSent($toernooi->id, 'herinnering', 'a@b.com', 'Sub');
        $custom = EmailLog::logSent($toernooi->id, 'bevestiging', 'a@b.com', 'Sub');

        $this->assertEquals('Uitnodiging', $uitnodiging->type_naam);
        $this->assertEquals('Correctie verzoek', $correctie->type_naam);
        $this->assertEquals('Herinnering', $herinnering->type_naam);
        $this->assertEquals('Bevestiging', $custom->type_naam);
    }

    // ========================================================================
    // AutofixProposal
    // ========================================================================

    #[Test]
    public function autofix_proposal_is_pending(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'stack...',
            'code_context' => 'code...',
            'approval_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
        ]);

        $this->assertTrue($proposal->isPending());
        $this->assertFalse($proposal->isApproved());
    }

    #[Test]
    public function autofix_proposal_is_approved(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'stack...',
            'code_context' => 'code...',
            'approval_token' => bin2hex(random_bytes(32)),
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertFalse($proposal->isPending());
        $this->assertTrue($proposal->isApproved());
    }

    #[Test]
    public function autofix_proposal_recently_analyzed(): void
    {
        AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'stack...',
            'code_context' => 'code...',
            'approval_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
        ]);

        $this->assertTrue(AutofixProposal::recentlyAnalyzed('RuntimeException', 'app/Test.php', 10));
        $this->assertFalse(AutofixProposal::recentlyAnalyzed('RuntimeException', 'app/Other.php', 10));
        $this->assertFalse(AutofixProposal::recentlyAnalyzed('DifferentException', 'app/Test.php', 10));
    }

    #[Test]
    public function autofix_proposal_casts_datetime_fields(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'stack...',
            'code_context' => 'code...',
            'approval_token' => bin2hex(random_bytes(32)),
            'status' => 'applied',
            'email_sent_at' => now(),
            'approved_at' => now(),
            'applied_at' => now(),
        ]);

        $proposal->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $proposal->email_sent_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $proposal->approved_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $proposal->applied_at);
    }

    // ========================================================================
    // SyncQueueItem
    // ========================================================================

    #[Test]
    public function sync_queue_item_belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooi();

        $item = SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => ['id' => 1, 'status' => 'nieuw'],
        ]);

        $this->assertInstanceOf(Toernooi::class, $item->toernooi);
    }

    #[Test]
    public function sync_queue_item_scope_unsynced(): void
    {
        $toernooi = $this->maakToernooi();

        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
        ]);

        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 2,
            'action' => 'update',
            'payload' => [],
            'synced_at' => now(),
        ]);

        $this->assertCount(1, SyncQueueItem::unsynced()->get());
    }

    #[Test]
    public function sync_queue_item_scope_failed(): void
    {
        $toernooi = $this->maakToernooi();

        // Unsynced without error (not failed)
        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
        ]);

        // Unsynced with error (failed)
        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 2,
            'action' => 'update',
            'payload' => [],
            'error_message' => 'Connection timeout',
        ]);

        // Synced (not failed)
        SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 3,
            'action' => 'update',
            'payload' => [],
            'synced_at' => now(),
        ]);

        $this->assertCount(1, SyncQueueItem::failed()->get());
    }

    #[Test]
    public function sync_queue_item_scope_for_toernooi(): void
    {
        $toernooi1 = $this->maakToernooi();
        $toernooi2 = $this->maakToernooi();

        SyncQueueItem::create([
            'toernooi_id' => $toernooi1->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
        ]);

        SyncQueueItem::create([
            'toernooi_id' => $toernooi2->id,
            'table_name' => 'wedstrijden',
            'record_id' => 2,
            'action' => 'create',
            'payload' => [],
        ]);

        $this->assertCount(1, SyncQueueItem::forToernooi($toernooi1->id)->get());
    }

    #[Test]
    public function sync_queue_item_mark_synced(): void
    {
        $toernooi = $this->maakToernooi();

        $item = SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
            'error_message' => 'Previous error',
        ]);

        $item->markSynced();
        $item->refresh();

        $this->assertNotNull($item->synced_at);
        $this->assertNull($item->error_message);
    }

    #[Test]
    public function sync_queue_item_mark_failed(): void
    {
        $toernooi = $this->maakToernooi();

        $item = SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => [],
        ]);

        $item->markFailed('Connection refused');
        $item->refresh();

        $this->assertEquals('Connection refused', $item->error_message);
        $this->assertNull($item->synced_at);
    }

    #[Test]
    public function sync_queue_item_queue_change_with_toernooi_id(): void
    {
        $toernooi = $this->maakToernooi();
        $club = $this->maakClub();

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $item = SyncQueueItem::queueChange($judoka, 'create');

        $this->assertEquals($toernooi->id, $item->toernooi_id);
        $this->assertEquals('judokas', $item->table_name);
        $this->assertEquals($judoka->id, $item->record_id);
        $this->assertEquals('create', $item->action);
    }

    #[Test]
    public function sync_queue_item_queue_change_throws_without_toernooi_id(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot determine toernooi_id');

        // Create a model without toernooi_id
        $proposal = AutofixProposal::create([
            'exception_class' => 'Test',
            'exception_message' => 'Test',
            'file' => 'test.php',
            'line' => 1,
            'stack_trace' => 'stack',
            'code_context' => 'code',
            'approval_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
        ]);

        SyncQueueItem::queueChange($proposal, 'create');
    }

    #[Test]
    public function sync_queue_item_casts_payload_to_array(): void
    {
        $toernooi = $this->maakToernooi();

        $item = SyncQueueItem::create([
            'toernooi_id' => $toernooi->id,
            'table_name' => 'wedstrijden',
            'record_id' => 1,
            'action' => 'create',
            'payload' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $item->refresh();
        $this->assertIsArray($item->payload);
        $this->assertEquals('value', $item->payload['key']);
    }

    // ========================================================================
    // SyncStatus
    // ========================================================================

    #[Test]
    public function sync_status_belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'idle',
            'records_synced' => 0,
        ]);

        $this->assertInstanceOf(Toernooi::class, $status->toernooi);
    }

    #[Test]
    public function sync_status_get_or_create(): void
    {
        $toernooi = $this->maakToernooi();

        $status1 = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $this->assertEquals('idle', $status1->status);
        $this->assertEquals(0, $status1->records_synced);

        // Calling again should return the same record
        $status2 = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $this->assertEquals($status1->id, $status2->id);
    }

    #[Test]
    public function sync_status_is_healthy(): void
    {
        $toernooi = $this->maakToernooi();

        $healthy = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'success',
            'last_sync_at' => now()->subMinutes(2),
            'records_synced' => 10,
        ]);

        $this->assertTrue($healthy->isHealthy());

        // Not healthy: wrong status
        $failed = SyncStatus::getOrCreate($toernooi->id, 'local_to_cloud');
        $failed->update(['status' => 'failed', 'last_sync_at' => now()]);
        $failed->refresh();
        $this->assertFalse($failed->isHealthy());
    }

    #[Test]
    public function sync_status_is_healthy_returns_false_without_last_sync(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'success',
            'records_synced' => 0,
        ]);

        $this->assertFalse($status->isHealthy());
    }

    #[Test]
    public function sync_status_is_syncing(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'syncing',
            'records_synced' => 0,
        ]);

        $this->assertTrue($status->isSyncing());
    }

    #[Test]
    public function sync_status_start_sync(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $status->startSync();
        $status->refresh();

        $this->assertEquals('syncing', $status->status);
        $this->assertNull($status->error_message);
    }

    #[Test]
    public function sync_status_complete_sync(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $status->startSync();
        $status->completeSync(42);
        $status->refresh();

        $this->assertEquals('success', $status->status);
        $this->assertNotNull($status->last_sync_at);
        $this->assertEquals(42, $status->records_synced);
        $this->assertNull($status->error_message);
    }

    #[Test]
    public function sync_status_fail_sync(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $status->startSync();
        $status->failSync('Database connection lost');
        $status->refresh();

        $this->assertEquals('failed', $status->status);
        $this->assertEquals('Database connection lost', $status->error_message);
    }

    #[Test]
    public function sync_status_get_status_label(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'idle',
            'records_synced' => 0,
        ]);

        $this->assertEquals('Wachtend', $status->getStatusLabel());

        $status->update(['status' => 'syncing']);
        $status->refresh();
        $this->assertEquals('Bezig...', $status->getStatusLabel());

        $status->update(['status' => 'success']);
        $status->refresh();
        $this->assertEquals('Geslaagd', $status->getStatusLabel());

        $status->update(['status' => 'failed']);
        $status->refresh();
        $this->assertEquals('Mislukt', $status->getStatusLabel());
    }

    #[Test]
    public function sync_status_get_time_since_sync(): void
    {
        $toernooi = $this->maakToernooi();

        $status = SyncStatus::create([
            'toernooi_id' => $toernooi->id,
            'direction' => 'cloud_to_local',
            'status' => 'idle',
            'records_synced' => 0,
        ]);

        $this->assertNull($status->getTimeSinceSync());

        $status->update(['last_sync_at' => now()->subMinutes(5)]);
        $status->refresh();
        $this->assertNotNull($status->getTimeSinceSync());
        $this->assertIsString($status->getTimeSinceSync());
    }

    // ========================================================================
    // ToernooiBetaling
    // ========================================================================

    #[Test]
    public function toernooi_betaling_belongs_to_toernooi_and_organisator(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $org->id,
            'mollie_payment_id' => 'tr_toernooi1',
            'bedrag' => 49.00,
            'tier' => '51-100',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ]);

        $this->assertInstanceOf(Toernooi::class, $betaling->toernooi);
        $this->assertInstanceOf(Organisator::class, $betaling->organisator);
    }

    #[Test]
    public function toernooi_betaling_is_betaald(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $paid = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $org->id,
            'mollie_payment_id' => 'tr_paid_t',
            'bedrag' => 49.00,
            'tier' => '51-100',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        $open = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $org->id,
            'mollie_payment_id' => 'tr_open_t',
            'bedrag' => 49.00,
            'tier' => '51-100',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ]);

        $this->assertTrue($paid->isBetaald());
        $this->assertFalse($open->isBetaald());
    }

    #[Test]
    public function toernooi_betaling_status_constants(): void
    {
        $this->assertEquals('open', ToernooiBetaling::STATUS_OPEN);
        $this->assertEquals('paid', ToernooiBetaling::STATUS_PAID);
        $this->assertEquals('failed', ToernooiBetaling::STATUS_FAILED);
        $this->assertEquals('expired', ToernooiBetaling::STATUS_EXPIRED);
        $this->assertEquals('canceled', ToernooiBetaling::STATUS_CANCELED);
    }

    #[Test]
    public function toernooi_betaling_casts(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $org->id,
            'mollie_payment_id' => 'tr_cast_t',
            'bedrag' => 49.95,
            'tier' => '51-100',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        $betaling->refresh();
        $this->assertEquals('49.95', $betaling->bedrag);
        $this->assertIsInt($betaling->max_judokas);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $betaling->betaald_op);
    }

    // ========================================================================
    // TvKoppeling
    // ========================================================================

    #[Test]
    public function tv_koppeling_belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooi();

        $koppeling = TvKoppeling::create([
            'code' => '1234',
            'toernooi_id' => $toernooi->id,
            'mat_nummer' => 1,
            'expires_at' => now()->addHours(1),
        ]);

        $this->assertInstanceOf(Toernooi::class, $koppeling->toernooi);
    }

    #[Test]
    public function tv_koppeling_generate_code(): void
    {
        $code = TvKoppeling::generateCode();

        $this->assertIsString($code);
        $this->assertEquals(4, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
    }

    #[Test]
    public function tv_koppeling_is_expired(): void
    {
        $toernooi = $this->maakToernooi();

        $expired = TvKoppeling::create([
            'code' => '0001',
            'toernooi_id' => $toernooi->id,
            'expires_at' => now()->subHour(),
        ]);

        $active = TvKoppeling::create([
            'code' => '0002',
            'toernooi_id' => $toernooi->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($active->isExpired());
    }

    #[Test]
    public function tv_koppeling_is_linked(): void
    {
        $toernooi = $this->maakToernooi();

        $linked = TvKoppeling::create([
            'code' => '0003',
            'toernooi_id' => $toernooi->id,
            'expires_at' => now()->addHour(),
            'linked_at' => now(),
        ]);

        $unlinked = TvKoppeling::create([
            'code' => '0004',
            'toernooi_id' => $toernooi->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($linked->isLinked());
        $this->assertFalse($unlinked->isLinked());
    }

    #[Test]
    public function tv_koppeling_casts_datetime_fields(): void
    {
        $toernooi = $this->maakToernooi();

        $koppeling = TvKoppeling::create([
            'code' => '0005',
            'toernooi_id' => $toernooi->id,
            'expires_at' => now()->addHour(),
            'linked_at' => now(),
        ]);

        $koppeling->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $koppeling->expires_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $koppeling->linked_at);
    }

    // ========================================================================
    // ChatMessage — DeviceToegang lookup paths (extra coverage)
    // ========================================================================

    #[Test]
    public function chat_message_afzender_naam_with_device_toegang_mat(): void
    {
        $toernooi = $this->maakToernooi();

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Jan',
            'rol' => 'mat',
            'mat_nummer' => 3,
            'code' => 'MAT003',
        ]);

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'mat',
            'van_id' => $toegang->id,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Mat 3', $msg->afzender_naam);
    }

    #[Test]
    public function chat_message_afzender_naam_with_device_toegang_naam(): void
    {
        $toernooi = $this->maakToernooi();

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Piet Weging',
            'rol' => 'weging',
            'code' => 'WEG001',
        ]);

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'weging',
            'van_id' => $toegang->id,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Piet Weging', $msg->afzender_naam);
    }

    #[Test]
    public function chat_message_ontvanger_naam_with_device_toegang_mat(): void
    {
        $toernooi = $this->maakToernooi();

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Klaas',
            'rol' => 'mat',
            'mat_nummer' => 2,
            'code' => 'MAT002',
        ]);

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'naar_id' => $toegang->id,
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Mat 2', $msg->ontvanger_naam);
    }

    #[Test]
    public function chat_message_ontvanger_naam_with_device_toegang_naam(): void
    {
        $toernooi = $this->maakToernooi();

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Anna Spreker',
            'rol' => 'spreker',
            'code' => 'SPR001',
        ]);

        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'spreker',
            'naar_id' => $toegang->id,
            'bericht' => 'Test',
        ]);

        $this->assertEquals('Anna Spreker', $msg->ontvanger_naam);
    }

    // ========================================================================
    // ToernooiBetaling — markeerAlsBetaald (extra coverage)
    // ========================================================================

    #[Test]
    public function toernooi_betaling_markeer_als_betaald(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'plan_type' => 'free',
        ]);

        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $org->id,
            'mollie_payment_id' => 'tr_mark_test',
            'bedrag' => 49.00,
            'tier' => '51-100',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ]);

        $betaling->markeerAlsBetaald();
        $betaling->refresh();
        $toernooi->refresh();

        $this->assertTrue($betaling->isBetaald());
        $this->assertNotNull($betaling->betaald_op);
        $this->assertEquals('paid', $toernooi->plan_type);
        $this->assertEquals('51-100', $toernooi->paid_tier);
        $this->assertEquals(100, $toernooi->paid_max_judokas);
    }

    // ========================================================================
    // TvKoppeling — generateCode cleanup path (extra coverage)
    // ========================================================================

    #[Test]
    public function tv_koppeling_generate_code_returns_four_digit_string(): void
    {
        $code = TvKoppeling::generateCode();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
    }

    // ========================================================================
    // ChatMessage — remaining accessor branches
    // ========================================================================

    #[Test]
    public function chat_message_afzender_naam_weging_with_id_fallback(): void
    {
        $toernooi = $this->maakToernooi();

        // weging with van_id but no DeviceToegang → fallback "Weging #X"
        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'weging',
            'van_id' => 99999,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Weging #99999', $msg->afzender_naam);

        // spreker with van_id → "Spreker #X"
        $msg2 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'spreker',
            'van_id' => 2,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Spreker #2', $msg2->afzender_naam);

        // dojo with van_id → "Dojo #X"
        $msg3 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'dojo',
            'van_id' => 1,
            'naar_type' => 'hoofdjury',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Dojo #1', $msg3->afzender_naam);
    }

    #[Test]
    public function chat_message_ontvanger_naam_weging_with_id_fallback(): void
    {
        $toernooi = $this->maakToernooi();

        // weging with naar_id but no DeviceToegang → "Weging #X"
        $msg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'weging',
            'naar_id' => 99999,
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Weging #99999', $msg->ontvanger_naam);

        // spreker with naar_id → "Spreker #X"
        $msg2 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'spreker',
            'naar_id' => 2,
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Spreker #2', $msg2->ontvanger_naam);

        // dojo with naar_id → "Dojo #X"
        $msg3 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'dojo',
            'naar_id' => 1,
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Dojo #1', $msg3->ontvanger_naam);

        // mat without naar_id → "Alle matten"
        $msg4 = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'naar_type' => 'mat',
            'bericht' => 'Test',
        ]);
        $this->assertEquals('Alle matten', $msg4->ontvanger_naam);
    }
}
