<?php

namespace Tests\Unit\Models;

use App\Models\AuthDevice;
use App\Models\Organisator;
use App\Models\QrLoginToken;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\ToernooiTemplate;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use App\Models\WimpelUitreiking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplexModelsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $organisator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organisator = Organisator::factory()->create();
    }

    // ========================================================================
    // AuthDevice
    // ========================================================================

    #[Test]
    public function auth_device_belongs_to_organisator(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $this->assertInstanceOf(Organisator::class, $device->organisator);
        $this->assertEquals($this->organisator->id, $device->organisator->id);
    }

    #[Test]
    public function auth_device_create_for_organisator(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator, [
            'name' => 'Test Phone',
            'browser' => 'Chrome',
            'os' => 'Android',
            'ip' => '192.168.1.1',
        ]);

        $this->assertEquals($this->organisator->id, $device->organisator_id);
        $this->assertEquals('Test Phone', $device->device_name);
        $this->assertEquals('Chrome', $device->browser);
        $this->assertEquals('Android', $device->os);
        $this->assertEquals('192.168.1.1', $device->ip_address);
        $this->assertTrue($device->is_active);
        $this->assertNotNull($device->token);
        $this->assertEquals(64, strlen($device->token));
        $this->assertNotNull($device->last_used_at);
        $this->assertNotNull($device->expires_at);
    }

    #[Test]
    public function auth_device_create_for_organisator_defaults(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $this->assertEquals('Onbekend apparaat', $device->device_name);
        $this->assertEquals('Unknown', $device->browser);
        $this->assertEquals('Unknown', $device->os);
        $this->assertNull($device->ip_address);
    }

    #[Test]
    public function auth_device_is_valid_when_active_and_not_expired(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $this->assertTrue($device->isValid());
    }

    #[Test]
    public function auth_device_is_not_valid_when_inactive(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['is_active' => false]);

        $this->assertFalse($device->isValid());
    }

    #[Test]
    public function auth_device_is_not_valid_when_expired(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($device->isValid());
    }

    #[Test]
    public function auth_device_find_by_token(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $found = AuthDevice::findByToken($device->token);
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    #[Test]
    public function auth_device_find_by_token_returns_null_for_inactive(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['is_active' => false]);

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    #[Test]
    public function auth_device_find_by_token_returns_null_for_expired(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['expires_at' => now()->subDay()]);

        $this->assertNull(AuthDevice::findByToken($device->token));
    }

    #[Test]
    public function auth_device_find_by_fingerprint(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-abc123']);

        $found = AuthDevice::findByFingerprint($this->organisator->id, 'fp-abc123');
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    #[Test]
    public function auth_device_find_by_fingerprint_returns_null_for_inactive(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-abc123', 'is_active' => false]);

        $this->assertNull(AuthDevice::findByFingerprint($this->organisator->id, 'fp-abc123'));
    }

    #[Test]
    public function auth_device_find_active_by_fingerprint_requires_pin(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-abc123']);

        // No pin set yet -> not found
        $this->assertNull(AuthDevice::findActiveByFingerprint('fp-abc123'));

        // Set pin -> found
        $device->setPin('1234');
        $found = AuthDevice::findActiveByFingerprint('fp-abc123');
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    #[Test]
    public function auth_device_find_registered_by_fingerprint_with_pin(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-reg']);
        $device->setPin('5678');

        $found = AuthDevice::findRegisteredByFingerprint('fp-reg');
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    #[Test]
    public function auth_device_find_registered_by_fingerprint_with_biometric(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-bio']);
        $device->enableBiometric();

        $found = AuthDevice::findRegisteredByFingerprint('fp-bio');
        $this->assertNotNull($found);
        $this->assertEquals($device->id, $found->id);
    }

    #[Test]
    public function auth_device_find_registered_by_fingerprint_returns_null_without_pin_or_biometric(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);
        $device->update(['device_fingerprint' => 'fp-none']);

        $this->assertNull(AuthDevice::findRegisteredByFingerprint('fp-none'));
    }

    #[Test]
    public function auth_device_set_and_verify_pin(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $this->assertFalse($device->hasPin());
        $this->assertFalse($device->verifyPin('1234'));

        $device->setPin('1234');

        $this->assertTrue($device->hasPin());
        $this->assertTrue($device->verifyPin('1234'));
        $this->assertFalse($device->verifyPin('0000'));
    }

    #[Test]
    public function auth_device_enable_biometric(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        // has_biometric defaults to false in DB but may be null when not explicitly set
        $this->assertEmpty($device->has_biometric);

        $device->enableBiometric();

        $this->assertTrue($device->fresh()->has_biometric);
    }

    #[Test]
    public function auth_device_find_or_create_creates_new(): void
    {
        $device = AuthDevice::findOrCreateForOrganisator($this->organisator, 'fp-new', [
            'name' => 'New Device',
            'browser' => 'Firefox',
            'os' => 'Linux',
            'ip' => '10.0.0.1',
        ]);

        $this->assertEquals($this->organisator->id, $device->organisator_id);
        $this->assertEquals('fp-new', $device->device_fingerprint);
        $this->assertEquals('New Device', $device->device_name);
    }

    #[Test]
    public function auth_device_find_or_create_returns_existing(): void
    {
        $existing = AuthDevice::findOrCreateForOrganisator($this->organisator, 'fp-existing');
        $found = AuthDevice::findOrCreateForOrganisator($this->organisator, 'fp-existing');

        $this->assertEquals($existing->id, $found->id);
        // Should only be 1 device in DB
        $this->assertEquals(1, AuthDevice::where('device_fingerprint', 'fp-existing')->count());
    }

    #[Test]
    public function auth_device_casts(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator);

        $this->assertIsBool($device->is_active);
        // has_biometric is cast to boolean but defaults to null when not explicitly set via createForOrganisator
        // Verify it's cast correctly when explicitly set
        $device->update(['has_biometric' => true]);
        $this->assertIsBool($device->fresh()->has_biometric);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $device->last_used_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $device->expires_at);
    }

    // ========================================================================
    // QrLoginToken
    // ========================================================================

    #[Test]
    public function qr_token_relationships(): void
    {
        $approver = Organisator::factory()->create();
        $token = QrLoginToken::generate(['browser' => 'Chrome']);
        $token->approve($approver);

        $fresh = $token->fresh();
        $this->assertInstanceOf(Organisator::class, $fresh->organisator);
        $this->assertInstanceOf(Organisator::class, $fresh->approvedBy);
        $this->assertEquals($approver->id, $fresh->approvedBy->id);
    }

    #[Test]
    public function qr_token_generate(): void
    {
        $token = QrLoginToken::generate(['browser' => 'Safari', 'os' => 'iOS']);

        $this->assertNotNull($token->token);
        $this->assertEquals(64, strlen($token->token));
        $this->assertEquals('pending', $token->status);
        $this->assertIsArray($token->device_info);
        $this->assertEquals('Safari', $token->device_info['browser']);
        $this->assertTrue($token->expires_at->isFuture());
    }

    #[Test]
    public function qr_token_find_by_token(): void
    {
        $token = QrLoginToken::generate();

        $found = QrLoginToken::findByToken($token->token);
        $this->assertNotNull($found);
        $this->assertEquals($token->id, $found->id);
    }

    #[Test]
    public function qr_token_find_by_token_returns_null_when_expired(): void
    {
        $token = QrLoginToken::generate();
        $token->update(['expires_at' => now()->subMinute()]);

        $this->assertNull(QrLoginToken::findByToken($token->token));
    }

    #[Test]
    public function qr_token_is_pending(): void
    {
        $token = QrLoginToken::generate();

        $this->assertTrue($token->isPending());

        $token->update(['status' => 'approved']);
        $this->assertFalse($token->fresh()->isPending());
    }

    #[Test]
    public function qr_token_is_pending_false_when_expired(): void
    {
        $token = QrLoginToken::generate();
        $token->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($token->fresh()->isPending());
    }

    #[Test]
    public function qr_token_is_approved(): void
    {
        $token = QrLoginToken::generate();
        $this->assertFalse($token->isApproved());

        $token->approve($this->organisator);
        $this->assertTrue($token->isApproved());
    }

    #[Test]
    public function qr_token_is_valid(): void
    {
        $token = QrLoginToken::generate();
        $this->assertTrue($token->isValid());

        $token->update(['status' => 'used']);
        $this->assertFalse($token->fresh()->isValid());
    }

    #[Test]
    public function qr_token_approve(): void
    {
        $token = QrLoginToken::generate();
        $result = $token->approve($this->organisator);

        $this->assertTrue($result);
        $this->assertEquals('approved', $token->status);
        $this->assertNotNull($token->approved_at);
        $this->assertEquals($this->organisator->id, $token->approved_by_organisator_id);
        $this->assertEquals($this->organisator->id, $token->organisator_id);
    }

    #[Test]
    public function qr_token_mark_used(): void
    {
        $token = QrLoginToken::generate();
        $result = $token->markUsed();

        $this->assertTrue($result);
        $this->assertEquals('used', $token->status);
    }

    #[Test]
    public function qr_token_mark_expired(): void
    {
        $token = QrLoginToken::generate();
        $result = $token->markExpired();

        $this->assertTrue($result);
        $this->assertEquals('expired', $token->status);
    }

    #[Test]
    public function qr_token_casts(): void
    {
        $token = QrLoginToken::generate(['key' => 'value']);

        $this->assertIsArray($token->device_info);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->expires_at);
    }

    // ========================================================================
    // StamJudoka
    // ========================================================================

    #[Test]
    public function stam_judoka_belongs_to_organisator(): void
    {
        $stam = StamJudoka::factory()->create(['organisator_id' => $this->organisator->id]);

        $this->assertInstanceOf(Organisator::class, $stam->organisator);
        $this->assertEquals($this->organisator->id, $stam->organisator->id);
    }

    #[Test]
    public function stam_judoka_has_many_judokas(): void
    {
        $stam = StamJudoka::factory()->create(['organisator_id' => $this->organisator->id]);

        $this->assertCount(0, $stam->judokas);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $stam->judokas());
    }

    #[Test]
    public function stam_judoka_has_many_wimpel_punten_log(): void
    {
        $stam = StamJudoka::factory()->create(['organisator_id' => $this->organisator->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $stam->wimpelPuntenLog());
        $this->assertCount(0, $stam->wimpelPuntenLog);
    }

    #[Test]
    public function stam_judoka_has_many_wimpel_uitreikingen(): void
    {
        $stam = StamJudoka::factory()->create(['organisator_id' => $this->organisator->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $stam->wimpelUitreikingen());
        $this->assertCount(0, $stam->wimpelUitreikingen);
    }

    #[Test]
    public function stam_judoka_scope_actief(): void
    {
        StamJudoka::factory()->create(['organisator_id' => $this->organisator->id, 'actief' => true]);
        StamJudoka::factory()->create(['organisator_id' => $this->organisator->id, 'actief' => false]);

        $this->assertEquals(1, StamJudoka::actief()->count());
    }

    #[Test]
    public function stam_judoka_scope_met_wimpel(): void
    {
        StamJudoka::factory()->create([
            'organisator_id' => $this->organisator->id,
            'wimpel_punten_totaal' => 10,
            'wimpel_is_nieuw' => false,
        ]);
        StamJudoka::factory()->create([
            'organisator_id' => $this->organisator->id,
            'wimpel_punten_totaal' => 0,
            'wimpel_is_nieuw' => true,
        ]);
        StamJudoka::factory()->create([
            'organisator_id' => $this->organisator->id,
            'wimpel_punten_totaal' => 0,
            'wimpel_is_nieuw' => false,
        ]);

        $this->assertEquals(2, StamJudoka::metWimpel()->count());
    }

    #[Test]
    public function stam_judoka_herbereken_wimpel_punten(): void
    {
        $stam = StamJudoka::factory()->create([
            'organisator_id' => $this->organisator->id,
            'wimpel_punten_totaal' => 0,
        ]);

        // Create log entries
        WimpelPuntenLog::create([
            'stam_judoka_id' => $stam->id,
            'punten' => 5,
            'type' => 'automatisch',
        ]);
        WimpelPuntenLog::create([
            'stam_judoka_id' => $stam->id,
            'punten' => 3,
            'type' => 'handmatig',
            'notitie' => 'Bonus',
        ]);

        $stam->herberekenWimpelPunten();

        $this->assertEquals(8, $stam->fresh()->wimpel_punten_totaal);
    }

    #[Test]
    public function stam_judoka_get_eerstvolgende_wimpel_milestone(): void
    {
        $stam = StamJudoka::factory()->metPunten(15)->create([
            'organisator_id' => $this->organisator->id,
        ]);

        // Milestone below current points (already reached)
        WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 10,
            'omschrijving' => 'Brons',
            'volgorde' => 1,
        ]);

        // Next milestone (above current points)
        $nextMilestone = WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 20,
            'omschrijving' => 'Zilver',
            'volgorde' => 2,
        ]);

        // Far milestone
        WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 50,
            'omschrijving' => 'Goud',
            'volgorde' => 3,
        ]);

        $result = $stam->getEerstvolgendeWimpelMilestone();
        $this->assertNotNull($result);
        $this->assertEquals($nextMilestone->id, $result->id);
        $this->assertEquals('Zilver', $result->omschrijving);
    }

    #[Test]
    public function stam_judoka_get_eerstvolgende_wimpel_milestone_returns_null_when_all_reached(): void
    {
        $stam = StamJudoka::factory()->metPunten(100)->create([
            'organisator_id' => $this->organisator->id,
        ]);

        WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 10,
            'omschrijving' => 'Brons',
            'volgorde' => 1,
        ]);

        $this->assertNull($stam->getEerstvolgendeWimpelMilestone());
    }

    #[Test]
    public function stam_judoka_get_bereikte_wimpel_milestones(): void
    {
        $stam = StamJudoka::factory()->metPunten(15)->create([
            'organisator_id' => $this->organisator->id,
        ]);

        $bereikt = WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 10,
            'omschrijving' => 'Brons',
            'volgorde' => 1,
        ]);

        $nietBereikt = WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 50,
            'omschrijving' => 'Goud',
            'volgorde' => 3,
        ]);

        // Milestone manually awarded via uitreiking (above points)
        $handmatig = WimpelMilestone::create([
            'organisator_id' => $this->organisator->id,
            'punten' => 30,
            'omschrijving' => 'Zilver',
            'volgorde' => 2,
        ]);

        WimpelUitreiking::create([
            'stam_judoka_id' => $stam->id,
            'wimpel_milestone_id' => $handmatig->id,
            'uitgereikt' => true,
            'uitgereikt_at' => now(),
        ]);

        $result = $stam->getBereikteWimpelMilestones();

        // Should include: Brons (punten <= 15), Zilver (handmatig uitgereikt)
        // Should not include: Goud (punten > 15 AND not manually awarded)
        $this->assertCount(2, $result);
        $ids = $result->pluck('id')->toArray();
        $this->assertContains($bereikt->id, $ids);
        $this->assertContains($handmatig->id, $ids);
        $this->assertNotContains($nietBereikt->id, $ids);
    }

    #[Test]
    public function stam_judoka_casts(): void
    {
        $stam = StamJudoka::factory()->create([
            'organisator_id' => $this->organisator->id,
            'gewicht' => 45.5,
        ]);

        $this->assertIsInt($stam->geboortejaar);
        $this->assertIsBool($stam->actief);
        $this->assertIsInt($stam->wimpel_punten_totaal);
        $this->assertIsBool($stam->wimpel_is_nieuw);
    }

    // ========================================================================
    // ToernooiTemplate
    // ========================================================================

    #[Test]
    public function toernooi_template_belongs_to_organisator(): void
    {
        $template = ToernooiTemplate::create([
            'organisator_id' => $this->organisator->id,
            'naam' => 'Test Template',
            'instellingen' => ['max_per_poule' => 4],
        ]);

        $this->assertInstanceOf(Organisator::class, $template->organisator);
        $this->assertEquals($this->organisator->id, $template->organisator->id);
    }

    #[Test]
    public function toernooi_template_create_from_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
            'max_judokas' => 100,
            'betaling_actief' => true,
            'inschrijfgeld' => 15.00,
            'gewicht_tolerantie' => 0.5,
            'judokas_per_coach' => 8,
        ]);

        // Attach organisator via pivot (createFromToernooi uses organisatoren()->first())
        $toernooi->organisatoren()->attach($this->organisator->id, ['rol' => 'eigenaar']);

        $template = ToernooiTemplate::createFromToernooi($toernooi, 'Mijn Template', 'Een beschrijving');

        $this->assertEquals('Mijn Template', $template->naam);
        $this->assertEquals('Een beschrijving', $template->beschrijving);
        $this->assertEquals($this->organisator->id, $template->organisator_id);
        $this->assertEquals(100, $template->max_judokas);
        $this->assertTrue($template->betaling_actief);
        $this->assertIsArray($template->instellingen);
        $this->assertEquals(0.5, $template->instellingen['gewicht_tolerantie']);
        $this->assertEquals(8, $template->instellingen['judokas_per_coach']);
    }

    #[Test]
    public function toernooi_template_create_from_toernooi_throws_without_organisator(): void
    {
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
        ]);
        // Do NOT attach via pivot

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Toernooi has no organisator');

        ToernooiTemplate::createFromToernooi($toernooi, 'Test');
    }

    #[Test]
    public function toernooi_template_apply_to_toernooi(): void
    {
        $template = ToernooiTemplate::create([
            'organisator_id' => $this->organisator->id,
            'naam' => 'Apply Template',
            'instellingen' => [
                'gewicht_tolerantie' => 1.0,
                'betaling_actief' => true,
                'inschrijfgeld' => 20.00,
                'mollie_mode' => 'direct',
                'portal_modus' => 'mutaties',
                'max_judokas' => 150,
                'judokas_per_coach' => 10,
            ],
        ]);

        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
            'betaling_actief' => false,
            'max_judokas' => null,
        ]);

        $template->applyToToernooi($toernooi);

        $toernooi->refresh();
        // Assert on columns that exist in the database
        $this->assertTrue($toernooi->betaling_actief);
        $this->assertEquals(150, $toernooi->max_judokas);
        $this->assertEquals(10, $toernooi->judokas_per_coach);
        $this->assertEquals('direct', $toernooi->mollie_mode);
    }

    #[Test]
    public function toernooi_template_apply_to_toernooi_uses_defaults(): void
    {
        $template = ToernooiTemplate::create([
            'organisator_id' => $this->organisator->id,
            'naam' => 'Empty Template',
            'instellingen' => [],
        ]);

        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
            'betaling_actief' => true,
        ]);

        $template->applyToToernooi($toernooi);

        $toernooi->refresh();
        // Empty instellingen should apply defaults for existing columns
        $this->assertFalse($toernooi->betaling_actief);
        $this->assertEquals(5, $toernooi->judokas_per_coach);
    }

    #[Test]
    public function toernooi_template_apply_with_null_instellingen(): void
    {
        $template = ToernooiTemplate::create([
            'organisator_id' => $this->organisator->id,
            'naam' => 'Null Template',
            'instellingen' => null,
        ]);

        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
            'betaling_actief' => true,
        ]);

        // Should not throw - null instellingen uses defaults
        $template->applyToToernooi($toernooi);

        $toernooi->refresh();
        $this->assertFalse($toernooi->betaling_actief);
        $this->assertEquals(5, $toernooi->judokas_per_coach);
    }

    #[Test]
    public function toernooi_template_casts(): void
    {
        $template = ToernooiTemplate::create([
            'organisator_id' => $this->organisator->id,
            'naam' => 'Cast Test',
            'instellingen' => ['key' => 'value'],
            'betaling_actief' => true,
            'inschrijfgeld' => 12.50,
        ]);

        $this->assertIsArray($template->instellingen);
        $this->assertIsBool($template->betaling_actief);
    }
}
