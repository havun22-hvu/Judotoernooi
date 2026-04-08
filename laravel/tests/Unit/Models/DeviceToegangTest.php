<?php

namespace Tests\Unit\Models;

use App\Models\DeviceToegang;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceToegangTest extends TestCase
{
    use RefreshDatabase;

    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
    }

    private function maakToegang(array $attrs = []): DeviceToegang
    {
        return DeviceToegang::create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test Device',
            'rol' => 'mat',
            'mat_nummer' => 1,
        ], $attrs));
    }

    // ========================================================================
    // Auto-generated fields on create
    // ========================================================================

    #[Test]
    public function code_generated_on_create(): void
    {
        $toegang = $this->maakToegang();
        $this->assertNotEmpty($toegang->code);
        $this->assertEquals(12, strlen($toegang->code));
    }

    #[Test]
    public function pincode_generated_on_create(): void
    {
        $toegang = $this->maakToegang();
        $this->assertNotEmpty($toegang->pincode);
        $this->assertEquals(4, strlen($toegang->pincode));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $toegang->pincode);
    }

    #[Test]
    public function codes_are_unique(): void
    {
        $t1 = $this->maakToegang();
        $t2 = $this->maakToegang();
        $this->assertNotEquals($t1->code, $t2->code);
    }

    // ========================================================================
    // Static generators
    // ========================================================================

    #[Test]
    public function generate_code_returns_12_char_uppercase(): void
    {
        $code = DeviceToegang::generateCode();
        $this->assertEquals(12, strlen($code));
        $this->assertEquals(strtoupper($code), $code);
    }

    #[Test]
    public function generate_pincode_returns_4_digits(): void
    {
        $pin = DeviceToegang::generatePincode();
        $this->assertEquals(4, strlen($pin));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
    }

    #[Test]
    public function generate_device_token_is_64_chars(): void
    {
        $token = DeviceToegang::generateDeviceToken();
        $this->assertEquals(64, strlen($token));
    }

    // ========================================================================
    // Display code
    // ========================================================================

    #[Test]
    public function get_display_code_returns_first_4_chars(): void
    {
        $toegang = $this->maakToegang();
        $display = $toegang->getDisplayCode();

        $this->assertEquals(4, strlen($display));
        $this->assertEquals(substr($toegang->code, 0, 4), $display);
    }

    #[Test]
    public function find_by_display_code_finds_correct_device(): void
    {
        $toegang = $this->maakToegang();
        $displayCode = $toegang->getDisplayCode();

        $found = DeviceToegang::findByDisplayCode($displayCode);
        $this->assertNotNull($found);
        $this->assertEquals($toegang->id, $found->id);
    }

    #[Test]
    public function find_by_display_code_returns_null_for_unknown(): void
    {
        $found = DeviceToegang::findByDisplayCode('ZZZZ');
        $this->assertNull($found);
    }

    // ========================================================================
    // Relationship
    // ========================================================================

    #[Test]
    public function belongs_to_toernooi(): void
    {
        $toegang = $this->maakToegang();
        $this->assertNotNull($toegang->toernooi);
        $this->assertEquals($this->toernooi->id, $toegang->toernooi->id);
    }

    // ========================================================================
    // Device binding
    // ========================================================================

    #[Test]
    public function is_gebonden_when_device_token_set(): void
    {
        $toegang = $this->maakToegang(['device_token' => 'abc123']);
        $this->assertTrue($toegang->isGebonden());
    }

    #[Test]
    public function is_not_gebonden_when_no_token(): void
    {
        $toegang = $this->maakToegang(['device_token' => null]);
        $this->assertFalse($toegang->isGebonden());
    }

    #[Test]
    public function bind_stores_device_info(): void
    {
        $toegang = $this->maakToegang();
        $toegang->bind('token_123', 'Chrome on Windows');
        $toegang->refresh();

        $this->assertEquals('token_123', $toegang->device_token);
        $this->assertEquals('Chrome on Windows', $toegang->device_info);
        $this->assertNotNull($toegang->gebonden_op);
        $this->assertNotNull($toegang->laatst_actief);
    }

    #[Test]
    public function reset_clears_device_info(): void
    {
        $toegang = $this->maakToegang([
            'device_token' => 'old_token',
            'device_info' => 'Old Device',
            'gebonden_op' => now(),
        ]);

        $toegang->reset();
        $toegang->refresh();

        $this->assertNull($toegang->device_token);
        $this->assertNull($toegang->device_info);
        $this->assertNull($toegang->gebonden_op);
    }

    #[Test]
    public function update_laatst_actief_sets_timestamp(): void
    {
        $toegang = $this->maakToegang(['laatst_actief' => null]);
        $toegang->updateLaatstActief();
        $toegang->refresh();

        $this->assertNotNull($toegang->laatst_actief);
    }

    // ========================================================================
    // Label
    // ========================================================================

    #[Test]
    public function get_label_for_mat_role_includes_number(): void
    {
        $toegang = $this->maakToegang(['rol' => 'mat', 'mat_nummer' => 3]);
        $this->assertEquals('Mat 3', $toegang->getLabel());
    }

    #[Test]
    public function get_label_for_non_mat_role(): void
    {
        $toegang = $this->maakToegang(['rol' => 'spreker', 'mat_nummer' => null]);
        $this->assertEquals('Spreker', $toegang->getLabel());
    }

    #[Test]
    public function get_label_for_hoofdjury(): void
    {
        $toegang = $this->maakToegang(['rol' => 'hoofdjury', 'mat_nummer' => null]);
        $this->assertEquals('Hoofdjury', $toegang->getLabel());
    }

    // ========================================================================
    // Status text
    // ========================================================================

    #[Test]
    public function get_status_text_when_gebonden(): void
    {
        $toegang = $this->maakToegang([
            'device_token' => 'token',
            'device_info' => 'iPhone Safari',
        ]);
        $this->assertEquals('iPhone Safari', $toegang->getStatusText());
    }

    #[Test]
    public function get_status_text_when_gebonden_no_info(): void
    {
        $toegang = $this->maakToegang([
            'device_token' => 'token',
            'device_info' => null,
        ]);
        $this->assertEquals('Gebonden', $toegang->getStatusText());
    }

    #[Test]
    public function get_status_text_when_not_gebonden(): void
    {
        $toegang = $this->maakToegang(['device_token' => null]);
        $this->assertEquals('Wacht op binding', $toegang->getStatusText());
    }
}
