<?php

namespace Tests\Unit\Services;

use App\Services\InternetMonitorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage voor de internet-monitor service: cache + HTTP fallback +
 * status-classification (good/poor/offline). Toegevoegd 2026-04-20
 * om de gap richting 80 % Unit-coverage te dichten.
 */
class InternetMonitorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_status_returns_good_when_cloud_responds_quickly(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response(['ok' => true], 200),
        ]);

        $status = (new InternetMonitorService())->getStatus();

        $this->assertContains($status, ['good', 'poor'], 'Successful response → good (or poor if slow).');
    }

    public function test_get_status_returns_offline_when_request_throws(): void
    {
        Http::fake([
            'judotournament.org/*' => fn () => throw new \Exception('Network down'),
        ]);

        $this->assertSame('offline', (new InternetMonitorService())->getStatus());
    }

    public function test_get_status_returns_poor_on_5xx_response(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response('Server error', 500),
        ]);

        $this->assertSame('poor', (new InternetMonitorService())->getStatus());
    }

    public function test_get_status_uses_cache_on_repeated_calls(): void
    {
        $service = new InternetMonitorService();
        Cache::put('internet_status', ['status' => 'good', 'latency' => 42, 'checked_at' => now()->toIso8601String()], 60);

        // No Http::fake — cache hit should not trigger a real request.
        $this->assertSame('good', $service->getStatus());
        $this->assertSame(42, $service->getLatency());
    }

    public function test_can_reach_cloud_returns_false_when_offline(): void
    {
        Cache::put('internet_status', ['status' => 'offline', 'latency' => null, 'checked_at' => now()->toIso8601String()], 60);

        $this->assertFalse((new InternetMonitorService())->canReachCloud());
    }

    public function test_fresh_check_bypasses_cache_and_writes_new_value(): void
    {
        Cache::put('internet_status', ['status' => 'offline', 'latency' => null, 'checked_at' => now()->toIso8601String()], 60);
        Http::fake([
            'judotournament.org/*' => Http::response(['ok' => true], 200),
        ]);

        $result = (new InternetMonitorService())->freshCheck();

        $this->assertContains($result['status'], ['good', 'poor']);
        $this->assertNotSame('offline', $result['status'], 'Fresh check must ignore stale cache.');
    }

    public function test_get_full_status_returns_complete_payload(): void
    {
        Http::fake([
            'judotournament.org/*' => Http::response(['ok' => true], 200),
        ]);

        $payload = (new InternetMonitorService())->getFullStatus();

        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('latency', $payload);
        $this->assertArrayHasKey('checked_at', $payload);
    }

    public function test_get_status_color_maps_each_known_status(): void
    {
        $this->assertSame('green', InternetMonitorService::getStatusColor('good'));
        $this->assertSame('orange', InternetMonitorService::getStatusColor('poor'));
        $this->assertSame('red', InternetMonitorService::getStatusColor('offline'));
        $this->assertSame('gray', InternetMonitorService::getStatusColor('something-else'));
    }

    public function test_get_status_label_maps_each_known_status(): void
    {
        $this->assertSame('Goed', InternetMonitorService::getStatusLabel('good'));
        $this->assertSame('Matig', InternetMonitorService::getStatusLabel('poor'));
        $this->assertSame('Offline', InternetMonitorService::getStatusLabel('offline'));
        $this->assertSame('Onbekend', InternetMonitorService::getStatusLabel('xx'));
    }
}
