<?php

namespace Tests\Unit\Models;

use App\Models\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemAlertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fire_creates_alert(): void
    {
        $alert = SystemAlert::fire(
            type: 'security',
            severity: 'critical',
            title: 'Brute force detected',
            message: 'Too many login attempts',
            metadata: ['ip' => '1.2.3.4'],
            source: 'auth'
        );

        $this->assertInstanceOf(SystemAlert::class, $alert);
        $this->assertTrue($alert->exists);
        $this->assertEquals('security', $alert->type);
        $this->assertEquals('critical', $alert->severity);
        $this->assertEquals('Brute force detected', $alert->title);
        $this->assertEquals('Too many login attempts', $alert->message);
        $this->assertEquals(['ip' => '1.2.3.4'], $alert->metadata);
        $this->assertEquals('auth', $alert->source);
        $alert->refresh();
        $this->assertFalse($alert->is_read);
    }

    #[Test]
    public function fire_works_with_minimal_params(): void
    {
        $alert = SystemAlert::fire('autofix', 'low', 'Fix applied');

        $this->assertTrue($alert->exists);
        $this->assertNull($alert->message);
        $this->assertNull($alert->metadata);
        $this->assertNull($alert->source);
    }

    #[Test]
    public function scope_unread_filters_correctly(): void
    {
        SystemAlert::fire('security', 'high', 'Unread 1');
        SystemAlert::fire('security', 'high', 'Unread 2');
        $read = SystemAlert::fire('security', 'low', 'Read');
        $read->update(['is_read' => true]);

        $this->assertEquals(2, SystemAlert::unread()->count());
    }

    #[Test]
    public function scope_of_type_filters_correctly(): void
    {
        SystemAlert::fire('security', 'high', 'Security');
        SystemAlert::fire('autofix', 'low', 'AutoFix');
        SystemAlert::fire('security', 'medium', 'Security 2');

        $this->assertEquals(2, SystemAlert::ofType('security')->count());
        $this->assertEquals(1, SystemAlert::ofType('autofix')->count());
    }

    #[Test]
    public function severity_color_attribute_returns_correct_colors(): void
    {
        $this->assertEquals('red', SystemAlert::fire('t', 'critical', 'x')->severity_color);
        $this->assertEquals('orange', SystemAlert::fire('t', 'high', 'x')->severity_color);
        $this->assertEquals('yellow', SystemAlert::fire('t', 'medium', 'x')->severity_color);
        $this->assertEquals('blue', SystemAlert::fire('t', 'low', 'x')->severity_color);
        $this->assertEquals('gray', SystemAlert::fire('t', 'unknown', 'x')->severity_color);
    }

    #[Test]
    public function metadata_is_cast_to_array(): void
    {
        $alert = SystemAlert::fire('security', 'high', 'Test', metadata: ['key' => 'value', 'nested' => ['a' => 1]]);
        $alert->refresh();

        $this->assertIsArray($alert->metadata);
        $this->assertEquals('value', $alert->metadata['key']);
    }

    #[Test]
    public function resolved_at_is_cast_to_datetime(): void
    {
        $alert = SystemAlert::fire('security', 'high', 'Test');
        $alert->update(['resolved_at' => now()]);
        $alert->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $alert->resolved_at);
    }
}
