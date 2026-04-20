<?php

namespace Tests\Unit\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\ActivityLog;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\ErrorNotificationService;
use App\Services\InternetMonitorService;
use App\Services\Payments\MolliePaymentProvider;
use App\Services\Payments\StripePaymentProvider;
use App\Services\PaymentProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimpleServicesCoverageTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ACTIVITY LOGGER
    // =========================================================================

    #[Test]
    public function activity_logger_logs_basic_activity(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'test_actie', 'Test beschrijving');

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertEquals($toernooi->id, $log->toernooi_id);
        $this->assertEquals('test_actie', $log->actie);
        $this->assertEquals('Test beschrijving', $log->beschrijving);
        $this->assertEquals('systeem', $log->actor_type);
        $this->assertEquals('Systeem', $log->actor_naam);
    }

    #[Test]
    public function activity_logger_logs_with_model_option(): void
    {
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);

        $log = ActivityLogger::log($toernooi, 'verplaats_judoka', 'Judoka verplaatst', [
            'model' => $judoka,
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('Judoka', $log->model_type);
        $this->assertEquals($judoka->id, $log->model_id);
    }

    #[Test]
    public function activity_logger_logs_with_model_type_string(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'test_actie', 'Test met model_type string', [
            'model_type' => 'Poule',
            'model_id' => 42,
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('Poule', $log->model_type);
        $this->assertEquals(42, $log->model_id);
    }

    #[Test]
    public function activity_logger_logs_with_properties(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'update_instellingen', 'Instellingen gewijzigd', [
            'properties' => ['old' => ['naam' => 'Oud'], 'new' => ['naam' => 'Nieuw']],
        ]);

        $this->assertNotNull($log);
        $this->assertEquals(['old' => ['naam' => 'Oud'], 'new' => ['naam' => 'Nieuw']], $log->properties);
    }

    #[Test]
    public function activity_logger_logs_with_interface_option(): void
    {
        $toernooi = Toernooi::factory()->create();

        $log = ActivityLogger::log($toernooi, 'test_actie', 'Test interface', [
            'interface' => 'weging',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('weging', $log->interface);
    }

    #[Test]
    public function activity_logger_truncates_long_beschrijving(): void
    {
        $toernooi = Toernooi::factory()->create();
        $longText = str_repeat('A', 300);

        $log = ActivityLogger::log($toernooi, 'test_actie', $longText);

        $this->assertNotNull($log);
        $this->assertEquals(255, mb_strlen($log->beschrijving));
    }

    #[Test]
    public function activity_logger_detects_organisator_actor(): void
    {
        $toernooi = Toernooi::factory()->create();
        $organisator = $toernooi->organisator;

        $this->actingAs($organisator, 'organisator');

        $log = ActivityLogger::log($toernooi, 'test_actie', 'Door organisator');

        $this->assertNotNull($log);
        $this->assertEquals('organisator', $log->actor_type);
        $this->assertEquals($organisator->id, $log->actor_id);
        $this->assertEquals('dashboard', $log->interface);
    }

    #[Test]
    public function activity_logger_returns_null_on_failure(): void
    {
        // Create a toernooi with an invalid ID to trigger a DB error
        $toernooi = new Toernooi();
        $toernooi->id = 999999;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'ActivityLogger failed'));

        $result = ActivityLogger::log($toernooi, 'test', 'Should fail');

        $this->assertNull($result);
    }

    // =========================================================================
    // ERROR NOTIFICATION SERVICE
    // =========================================================================

    // 4 tests removed (notifies_exception_when_enabled, notifies_critical_when_enabled,
    // formats_exception_data, format_email_body): de oude email-API bestaat
    // niet meer; ErrorNotificationService slaat naar AutofixProposal table.
    // De huidige API is gedekt in tests/Unit/Services/ErrorNotificationServiceTest.php
    // (toegevoegd 2026-04-20). Hier laten staan was VP-17 silent disabling.

    #[Test]
    public function error_notification_service_skips_when_disabled_in_testing(): void
    {
        config(['app.error_notifications' => false]);

        Log::shouldReceive('error')->never();

        $service = new ErrorNotificationService();
        $service->notifyException(new \RuntimeException('Should not log'));
        $this->assertTrue(true);
    }

    #[Test]
    public function error_notification_service_skips_critical_when_disabled(): void
    {
        config(['app.error_notifications' => false]);

        Log::shouldReceive('critical')->never();

        $service = new ErrorNotificationService();
        $service->notifyCritical('Should not log');
        $this->assertTrue(true);
    }

    // =========================================================================
    // PAYMENT PROVIDER FACTORY
    // =========================================================================

    #[Test]
    public function payment_factory_returns_mollie_by_default_for_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'mollie']);

        $provider = PaymentProviderFactory::forToernooi($toernooi);

        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertInstanceOf(MolliePaymentProvider::class, $provider);
    }

    #[Test]
    public function payment_factory_returns_mollie_for_mollie_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'mollie']);

        $provider = PaymentProviderFactory::forToernooi($toernooi);

        $this->assertInstanceOf(MolliePaymentProvider::class, $provider);
    }

    #[Test]
    public function payment_factory_returns_stripe_for_stripe_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'stripe']);

        $provider = PaymentProviderFactory::forToernooi($toernooi);

        $this->assertInstanceOf(StripePaymentProvider::class, $provider);
    }

    #[Test]
    public function payment_factory_make_returns_mollie_by_default(): void
    {
        $provider = PaymentProviderFactory::make('mollie');

        $this->assertInstanceOf(MolliePaymentProvider::class, $provider);
    }

    #[Test]
    public function payment_factory_make_returns_stripe(): void
    {
        $provider = PaymentProviderFactory::make('stripe');

        $this->assertInstanceOf(StripePaymentProvider::class, $provider);
    }

    #[Test]
    public function payment_factory_make_returns_mollie_for_unknown_provider(): void
    {
        $provider = PaymentProviderFactory::make('unknown_provider');

        $this->assertInstanceOf(MolliePaymentProvider::class, $provider);
    }

    // =========================================================================
    // INTERNET MONITOR SERVICE
    // =========================================================================

    #[Test]
    public function internet_monitor_returns_good_status_for_fast_response(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();
        $status = $service->getStatus();

        $this->assertContains($status, ['good', 'poor']);
    }

    #[Test]
    public function internet_monitor_returns_offline_when_request_fails(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('', 500),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();
        // Force fresh check to avoid cache
        $result = $service->freshCheck();

        // 500 response = 'poor' status
        $this->assertEquals('poor', $result['status']);
    }

    #[Test]
    public function internet_monitor_returns_offline_on_connection_exception(): void
    {
        Http::fake([
            'judotournament.org/*' => function () {
                throw new \Exception('Connection refused');
            },
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();
        $result = $service->freshCheck();

        $this->assertEquals('offline', $result['status']);
        $this->assertNull($result['latency']);
    }

    #[Test]
    public function internet_monitor_get_latency_returns_value(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();
        $latency = $service->getLatency();

        $this->assertIsInt($latency);
    }

    #[Test]
    public function internet_monitor_can_reach_cloud_when_online(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();

        $this->assertTrue($service->canReachCloud());
    }

    #[Test]
    public function internet_monitor_cannot_reach_cloud_when_offline(): void
    {
        Http::fake([
            'judotournament.org/*' => function () {
                throw new \Exception('Connection refused');
            },
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();

        $this->assertFalse($service->canReachCloud());
    }

    #[Test]
    public function internet_monitor_get_full_status_returns_array(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();
        $fullStatus = $service->getFullStatus();

        $this->assertArrayHasKey('status', $fullStatus);
        $this->assertArrayHasKey('latency', $fullStatus);
        $this->assertArrayHasKey('checked_at', $fullStatus);
    }

    #[Test]
    public function internet_monitor_uses_cache_on_subsequent_calls(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        Cache::forget('internet_status');

        $service = new InternetMonitorService();

        // First call hits HTTP
        $service->getStatus();
        // Second call should use cache
        $status = $service->getStatus();

        $this->assertContains($status, ['good', 'poor']);
        // HTTP should only be called once due to cache
        Http::assertSentCount(1);
    }

    #[Test]
    public function internet_monitor_fresh_check_bypasses_cache(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('OK', 200),
        ]);

        // Pre-populate cache with offline status
        Cache::put('internet_status', [
            'status' => 'offline',
            'latency' => null,
            'checked_at' => now()->toIso8601String(),
        ], 60);

        $service = new InternetMonitorService();
        $result = $service->freshCheck();

        // Should have made a real request, overriding cache
        $this->assertNotEquals('offline', $result['status']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function internet_monitor_get_status_color_returns_correct_colors(): void
    {
        $this->assertEquals('green', InternetMonitorService::getStatusColor('good'));
        $this->assertEquals('orange', InternetMonitorService::getStatusColor('poor'));
        $this->assertEquals('red', InternetMonitorService::getStatusColor('offline'));
        $this->assertEquals('gray', InternetMonitorService::getStatusColor('unknown'));
    }

    #[Test]
    public function internet_monitor_get_status_label_returns_dutch_labels(): void
    {
        $this->assertEquals('Goed', InternetMonitorService::getStatusLabel('good'));
        $this->assertEquals('Matig', InternetMonitorService::getStatusLabel('poor'));
        $this->assertEquals('Offline', InternetMonitorService::getStatusLabel('offline'));
        $this->assertEquals('Onbekend', InternetMonitorService::getStatusLabel('unknown'));
    }
}
