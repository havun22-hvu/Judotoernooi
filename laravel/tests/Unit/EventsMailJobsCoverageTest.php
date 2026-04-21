<?php

namespace Tests\Unit;

use App\Events\Concerns\SafelyBroadcasts;
use App\Events\MatHeartbeat;
use App\Events\NewChatMessage;
use App\Events\TvLinked;
use App\Jobs\ImportJudokasJob;
use App\Mail\AutoFixProposalMail;
use App\Mail\ClubUitnodigingMail;
use App\Mail\CorrectieVerzoekMail;
use App\Mail\FactuurMail;
use App\Models\AutofixProposal;
use App\Models\ChatMessage;
use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\ImportService;
use App\Support\CircuitBreaker;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventsMailJobsCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ─── MatHeartbeat ───────────────────────────────────────────

    #[Test]
    public function mat_heartbeat_stores_toernooi_id_and_matten(): void
    {
        $matten = [
            ['mat' => 1, 'status' => 'bezig'],
            ['mat' => 2, 'status' => 'klaar'],
        ];

        $event = new MatHeartbeat(42, $matten);

        $this->assertSame(42, $event->toernooiId);
        $this->assertSame($matten, $event->matten);
    }

    #[Test]
    public function mat_heartbeat_broadcasts_on_toernooi_channel(): void
    {
        $event = new MatHeartbeat(7, []);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('toernooi.7', $channels[0]->name);
    }

    #[Test]
    public function mat_heartbeat_broadcast_as_returns_correct_name(): void
    {
        $event = new MatHeartbeat(1, []);
        $this->assertSame('mat.heartbeat', $event->broadcastAs());
    }

    #[Test]
    public function mat_heartbeat_broadcast_with_contains_required_keys(): void
    {
        $matten = [['mat' => 1]];
        $event = new MatHeartbeat(5, $matten);
        $data = $event->broadcastWith();

        $this->assertSame(5, $data['toernooi_id']);
        $this->assertSame($matten, $data['matten']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    // ─── NewChatMessage ─────────────────────────────────────────

    #[Test]
    public function new_chat_message_stores_message(): void
    {
        $toernooi = Toernooi::factory()->create();
        $chatMsg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'mat',
            'naar_id' => 3,
            'bericht' => 'Test bericht',
        ]);

        $event = new NewChatMessage($chatMsg);
        $this->assertSame($chatMsg->id, $event->message->id);
    }

    #[Test]
    public function new_chat_message_broadcasts_to_correct_channel_for_each_type(): void
    {
        $toernooi = Toernooi::factory()->create();

        $types = [
            'hoofdjury' => "chat.{$toernooi->id}.hoofdjury",
            'mat' => "chat.{$toernooi->id}.mat.3",
            'alle_matten' => "chat.{$toernooi->id}.alle_matten",
            'weging' => "chat.{$toernooi->id}.weging",
            'spreker' => "chat.{$toernooi->id}.spreker",
            'dojo' => "chat.{$toernooi->id}.dojo",
            'iedereen' => "chat.{$toernooi->id}.iedereen",
        ];

        foreach ($types as $naarType => $expectedChannel) {
            $chatMsg = ChatMessage::create([
                'toernooi_id' => $toernooi->id,
                'van_type' => 'hoofdjury',
                'van_id' => null,
                'naar_type' => $naarType,
                'naar_id' => $naarType === 'mat' ? 3 : null,
                'bericht' => "Test voor {$naarType}",
            ]);

            $event = new NewChatMessage($chatMsg);
            $channels = $event->broadcastOn();

            $this->assertCount(1, $channels, "Expected 1 channel for type {$naarType}");
            $this->assertSame($expectedChannel, $channels[0]->name, "Channel mismatch for {$naarType}");
        }
    }

    #[Test]
    public function new_chat_message_unknown_type_returns_empty_channels(): void
    {
        $toernooi = Toernooi::factory()->create();
        $chatMsg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'onbekend_type',
            'naar_id' => null,
            'bericht' => 'Test',
        ]);

        $event = new NewChatMessage($chatMsg);
        $this->assertEmpty($event->broadcastOn());
    }

    #[Test]
    public function new_chat_message_broadcast_as_returns_correct_name(): void
    {
        $toernooi = Toernooi::factory()->create();
        $chatMsg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'hoofdjury',
            'naar_id' => null,
            'bericht' => 'Test',
        ]);

        $event = new NewChatMessage($chatMsg);
        $this->assertSame('chat.message', $event->broadcastAs());
    }

    #[Test]
    public function new_chat_message_broadcast_with_contains_expected_keys(): void
    {
        $toernooi = Toernooi::factory()->create();
        $chatMsg = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => 'hoofdjury',
            'van_id' => null,
            'naar_type' => 'mat',
            'naar_id' => 1,
            'bericht' => 'Hallo',
        ]);

        $event = new NewChatMessage($chatMsg);
        $data = $event->broadcastWith();

        $this->assertSame($chatMsg->id, $data['id']);
        $this->assertSame('hoofdjury', $data['van_type']);
        $this->assertSame('mat', $data['naar_type']);
        $this->assertSame('Hallo', $data['bericht']);
        $this->assertArrayHasKey('created_at', $data);
    }

    // ─── TvLinked ───────────────────────────────────────────────

    #[Test]
    public function tv_linked_stores_code_and_redirect(): void
    {
        $event = new TvLinked('ABC123', '/dashboard');

        $this->assertSame('ABC123', $event->code);
        $this->assertSame('/dashboard', $event->redirect);
    }

    #[Test]
    public function tv_linked_broadcasts_on_correct_channel(): void
    {
        $event = new TvLinked('XYZ', '/tv');
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('tv-koppeling.XYZ', $channels[0]->name);
    }

    #[Test]
    public function tv_linked_broadcast_as_returns_correct_name(): void
    {
        $event = new TvLinked('A', '/');
        $this->assertSame('tv.linked', $event->broadcastAs());
    }

    #[Test]
    public function tv_linked_broadcast_with_contains_redirect(): void
    {
        $event = new TvLinked('CODE', '/go');
        $data = $event->broadcastWith();

        $this->assertSame(['redirect' => '/go'], $data);
    }

    // ─── SafelyBroadcasts Trait ─────────────────────────────────

    #[Test]
    public function safely_broadcasts_dispatch_fires_event_when_circuit_closed(): void
    {
        Cache::flush();
        Event::fake([MatHeartbeat::class]);

        MatHeartbeat::dispatch(1, [['mat' => 1]]);

        // With the circuit closed, the trait must actually dispatch the event.
        Event::assertDispatched(MatHeartbeat::class);
    }

    #[Test]
    public function safely_broadcasts_skips_when_circuit_open(): void
    {
        // Clear any throttle keys from previous tests
        Cache::flush();

        // Open the circuit breaker manually
        Cache::put('circuit_breaker:reverb:opened_at', time(), 300);
        Cache::put('circuit_breaker:reverb:failures', 5, 300);

        // Dispatch should skip broadcast and log warning
        Log::shouldReceive('warning')
            ->atLeast()->once();

        MatHeartbeat::dispatch(1, []);

        // Clean up
        Cache::forget('circuit_breaker:reverb:opened_at');
        Cache::forget('circuit_breaker:reverb:failures');
    }

    #[Test]
    public function safely_broadcasts_log_throttled_only_logs_once_per_minute(): void
    {
        Cache::flush();

        // Open the circuit
        Cache::put('circuit_breaker:reverb:opened_at', time(), 300);

        // First dispatch should log
        Log::shouldReceive('warning')->once();

        MatHeartbeat::dispatch(1, []);
        // Second dispatch within same minute should NOT log again (throttled)
        MatHeartbeat::dispatch(2, []);

        Cache::forget('circuit_breaker:reverb:opened_at');
    }

    // ─── AutoFixProposalMail ────────────────────────────────────

    #[Test]
    public function autofix_proposal_mail_can_be_constructed(): void
    {
        $proposal = new AutofixProposal([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Something went wrong in the application logic',
            'file' => 'app/Http/Controllers/TestController.php',
            'line' => 42,
            'status' => 'pending',
        ]);

        $mail = new AutoFixProposalMail($proposal);

        $this->assertSame($proposal, $mail->proposal);
        $this->assertNull($mail->attempts);
    }

    #[Test]
    public function autofix_proposal_mail_envelope_contains_exception_info(): void
    {
        $proposal = new AutofixProposal([
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Something went terribly wrong in the application',
            'file' => 'test.php',
            'line' => 1,
            'status' => 'pending',
        ]);

        $mail = new AutoFixProposalMail($proposal);
        $envelope = $mail->envelope();

        $this->assertStringContains('[AutoFix MISLUKT]', $envelope->subject);
        $this->assertStringContains('RuntimeException', $envelope->subject);
    }

    #[Test]
    public function autofix_proposal_mail_uses_correct_view(): void
    {
        $proposal = new AutofixProposal([
            'exception_class' => 'Error',
            'exception_message' => 'Test',
            'file' => 'test.php',
            'line' => 1,
            'status' => 'pending',
        ]);

        $mail = new AutoFixProposalMail($proposal);
        $content = $mail->content();

        $this->assertSame('emails.autofix-proposal', $content->view);
    }

    // ─── ClubUitnodigingMail ────────────────────────────────────

    #[Test]
    public function club_uitnodiging_mail_can_be_constructed(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $mail = new ClubUitnodigingMail($uitnodiging);

        $this->assertSame($uitnodiging->id, $mail->uitnodiging->id);
    }

    #[Test]
    public function club_uitnodiging_mail_subject_contains_toernooi_naam(): void
    {
        $toernooi = Toernooi::factory()->create(['naam' => 'Testtoernooi 2026']);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $mail = new ClubUitnodigingMail($uitnodiging);
        $envelope = $mail->envelope();

        $this->assertStringContains('Testtoernooi 2026', $envelope->subject);
        $this->assertStringContains('Uitnodiging', $envelope->subject);
    }

    #[Test]
    public function club_uitnodiging_mail_uses_correct_view(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $mail = new ClubUitnodigingMail($uitnodiging);
        $content = $mail->content();

        $this->assertSame('emails.club-uitnodiging', $content->view);
    }

    // ─── CorrectieVerzoekMail ───────────────────────────────────

    #[Test]
    public function correctie_verzoek_mail_can_be_constructed(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $judokas = new Collection(['judoka1', 'judoka2']);

        $mail = new CorrectieVerzoekMail($toernooi, $club, $judokas);

        $this->assertSame($toernooi->id, $mail->toernooi->id);
        $this->assertSame($club->id, $mail->club->id);
        $this->assertCount(2, $mail->judokas);
    }

    #[Test]
    public function correctie_verzoek_mail_subject_contains_toernooi_naam(): void
    {
        $toernooi = Toernooi::factory()->create(['naam' => 'Herfsttoernooi']);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $mail = new CorrectieVerzoekMail($toernooi, $club, new Collection());
        $envelope = $mail->envelope();

        $this->assertStringContains('Herfsttoernooi', $envelope->subject);
        $this->assertStringContains('Actie vereist', $envelope->subject);
    }

    #[Test]
    public function correctie_verzoek_mail_uses_correct_view(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $mail = new CorrectieVerzoekMail($toernooi, $club, new Collection());
        $content = $mail->content();

        $this->assertSame('emails.correctie-verzoek', $content->view);
    }

    // ─── FactuurMail ────────────────────────────────────────────

    #[Test]
    public function factuur_mail_can_be_constructed(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'tr_test001',
            'bedrag' => 49.95,
            'tier' => 'basis',
            'max_judokas' => 100,
            'status' => 'paid',
            'factuurnummer' => 'JT-2026-0001',
        ]);

        $mail = new FactuurMail($betaling, '/tmp/test.pdf');

        $this->assertSame($betaling->id, $mail->betaling->id);
        $this->assertSame('/tmp/test.pdf', $mail->pdfPath);
    }

    #[Test]
    public function factuur_mail_subject_contains_factuurnummer(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'tr_test042',
            'bedrag' => 49.95,
            'tier' => 'basis',
            'max_judokas' => 100,
            'status' => 'paid',
            'factuurnummer' => 'JT-2026-0042',
        ]);

        $mail = new FactuurMail($betaling, '/tmp/test.pdf');
        $envelope = $mail->envelope();

        $this->assertStringContains('JT-2026-0042', $envelope->subject);
        $this->assertStringContains('Factuur', $envelope->subject);
    }

    #[Test]
    public function factuur_mail_uses_correct_view(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'tr_test099',
            'bedrag' => 10.00,
            'tier' => 'basis',
            'max_judokas' => 50,
            'status' => 'paid',
            'factuurnummer' => 'JT-2026-0099',
        ]);

        $mail = new FactuurMail($betaling, '/tmp/invoice.pdf');
        $content = $mail->content();

        $this->assertSame('emails.factuur', $content->view);
    }

    #[Test]
    public function factuur_mail_has_pdf_attachment(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'tr_test100',
            'bedrag' => 10.00,
            'tier' => 'basis',
            'max_judokas' => 50,
            'status' => 'paid',
            'factuurnummer' => 'JT-2026-0100',
        ]);

        $mail = new FactuurMail($betaling, '/tmp/factuur.pdf');
        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
        $this->assertInstanceOf(Attachment::class, $attachments[0]);
    }

    // ─── ImportJudokasJob ───────────────────────────────────────

    #[Test]
    public function import_judokas_job_can_be_constructed(): void
    {
        $toernooi = Toernooi::factory()->create();

        $job = new ImportJudokasJob(
            $toernooi,
            [['Jan', 'Jansen', '60']],
            ['naam' => 0, 'achternaam' => 1, 'gewicht' => 2],
            ['naam', 'achternaam', 'gewicht']
        );

        $this->assertNotEmpty($job->getImportId());
        $this->assertStringStartsWith('import_' . $toernooi->id . '_', $job->getImportId());
    }

    #[Test]
    public function import_judokas_job_has_correct_timeout_and_tries(): void
    {
        $toernooi = Toernooi::factory()->create();

        $job = new ImportJudokasJob($toernooi, [], [], []);

        $this->assertSame(300, $job->timeout);
        $this->assertSame(1, $job->tries);
    }

    #[Test]
    public function import_judokas_job_get_progress_returns_null_for_unknown_id(): void
    {
        $result = ImportJudokasJob::getProgress('nonexistent_import_id');
        $this->assertNull($result);
    }

    #[Test]
    public function import_judokas_job_handle_processes_rows_via_import_service(): void
    {
        $toernooi = Toernooi::factory()->create();
        $header = ['naam', 'achternaam', 'gewicht'];
        $rows = [
            ['Jan', 'Jansen', '60'],
            ['Piet', 'Pietersen', '70'],
        ];
        $mapping = ['naam' => 0, 'achternaam' => 1, 'gewicht' => 2];

        $job = new ImportJudokasJob($toernooi, $rows, $mapping, $header);

        $importService = $this->mock(ImportService::class);
        $importService->shouldReceive('importeerJudokas')
            ->once()
            ->andReturn(['imported' => 2, 'errors' => []]);

        $job->handle($importService);

        // Verify progress was set to completed
        $progress = ImportJudokasJob::getProgress($job->getImportId());
        $this->assertNotNull($progress);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(2, $progress['total']);
    }

    #[Test]
    public function import_judokas_job_handle_sets_failed_status_on_exception(): void
    {
        $toernooi = Toernooi::factory()->create();
        $header = ['naam'];
        $rows = [['Jan']];
        $mapping = ['naam' => 0];

        $job = new ImportJudokasJob($toernooi, $rows, $mapping, $header);

        $importService = $this->mock(ImportService::class);
        $importService->shouldReceive('importeerJudokas')
            ->once()
            ->andThrow(new \Exception('Import error'));

        try {
            $job->handle($importService);
        } catch (\Exception $e) {
            // Expected
        }

        $progress = ImportJudokasJob::getProgress($job->getImportId());
        $this->assertSame('failed', $progress['status']);
        $this->assertSame('Import error', $progress['error']);
    }

    #[Test]
    public function import_judokas_job_failed_method_updates_progress(): void
    {
        $toernooi = Toernooi::factory()->create();
        $rows = [['a'], ['b'], ['c']];

        $job = new ImportJudokasJob($toernooi, $rows, [], []);
        $job->failed(new \RuntimeException('Queue failure'));

        $progress = ImportJudokasJob::getProgress($job->getImportId());
        $this->assertNotNull($progress);
        $this->assertSame('failed', $progress['status']);
        $this->assertSame(3, $progress['total']);
        $this->assertSame(0, $progress['processed']);
    }

    #[Test]
    public function import_judokas_job_handles_comma_separated_mapping(): void
    {
        $toernooi = Toernooi::factory()->create();
        $header = ['voornaam', 'achternaam', 'gewicht'];
        $rows = [['Jan', 'Jansen', '60']];
        // Comma-separated mapping means combine columns
        $mapping = ['naam' => '0,1', 'gewicht' => 2];

        $job = new ImportJudokasJob($toernooi, $rows, $mapping, $header);

        $importService = $this->mock(ImportService::class);
        $importService->shouldReceive('importeerJudokas')
            ->once()
            ->withArgs(function ($t, $data, $kolomMapping) {
                // comma-separated should be passed through as-is
                return $kolomMapping['naam'] === '0,1' && $kolomMapping['gewicht'] === 'gewicht';
            })
            ->andReturn([]);

        $job->handle($importService);
    }

    // ─── Helper ─────────────────────────────────────────────────

    /**
     * Assert that a string contains a substring (PHPUnit 10+ compatible).
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
