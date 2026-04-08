<?php

namespace Tests\Feature;

use App\Models\AuthDevice;
use App\Models\AutofixProposal;
use App\Models\DeviceToegang;
use App\Models\MagicLinkToken;
use App\Models\Organisator;
use App\Models\QrLoginToken;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PASSWORD = 'TestPassword123!';
    private const TEST_NEW_PW = 'test-new-pw';
    private const TEST_RESET_PW = 'test-reset-pw';
    private const FINGERPRINT = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private Organisator $organisator;
    private Organisator $sitebeheerder;

    protected function setUp(): void
    {
        parent::setUp();
        app('cache')->flush();
        RateLimiter::clear('magic-link:127.0.0.1');
        RateLimiter::clear('password-reset:127.0.0.1');
        RateLimiter::clear('pin-login:' . self::FINGERPRINT);

        $this->organisator = Organisator::factory()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
            'biometric_prompted_at' => now(),
        ]);

        $this->sitebeheerder = Organisator::factory()->sitebeheerder()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
            'biometric_prompted_at' => now(),
        ]);
    }

    // ========================================================================
    // OrganisatorAuthController — showLogin
    // ========================================================================

    #[Test]
    public function show_login_page_as_guest(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
    }

    #[Test]
    public function show_login_redirects_when_already_authenticated(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->get(route('login'));
        $response->assertRedirect();
    }

    #[Test]
    public function login_with_wrong_credentials(): void
    {
        $response = $this->post(route('login.submit'), [
            'email' => $this->organisator->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('organisator');
    }

    #[Test]
    public function login_sitebeheerder_redirects_to_admin(): void
    {
        $response = $this->post(route('login.submit'), [
            'email' => $this->sitebeheerder->email,
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect(route('admin.index'));
        $this->assertAuthenticatedAs($this->sitebeheerder, 'organisator');
    }

    #[Test]
    public function login_restores_locale_preference(): void
    {
        $this->organisator->update(['locale' => 'en']);

        $this->post(route('login.submit'), [
            'email' => $this->organisator->email,
            'password' => self::TEST_PASSWORD,
        ]);

        $this->assertEquals('en', session('locale'));
    }

    #[Test]
    public function login_offers_biometric_setup_when_not_prompted(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
            'biometric_prompted_at' => null,
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $org->email,
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect(route('auth.setup-pin'));
        $org->refresh();
        $this->assertNotNull($org->biometric_prompted_at);
    }

    // ========================================================================
    // OrganisatorAuthController — register
    // ========================================================================

    #[Test]
    public function show_register_page_as_guest(): void
    {
        $response = $this->get(route('register'));
        $response->assertStatus(200);
    }

    #[Test]
    public function show_register_redirects_when_authenticated(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    #[Test]
    public function register_rate_limited_after_3_attempts(): void
    {
        // Hit rate limiter 3 times
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('magic-link:127.0.0.1', 600);
        }

        $response = $this->post(route('register.submit'), [
            'organisatie_naam' => 'Test Club',
            'naam' => 'Test Person',
            'email' => 'ratelimit@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function magic_link_sent_page_shows(): void
    {
        $response = $this->get(route('register.sent'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // OrganisatorAuthController — verifyRegister
    // ========================================================================

    #[Test]
    public function verify_register_creates_new_organisator(): void
    {
        $token = MagicLinkToken::generate('newuser@example.com', 'register', [
            'organisatie_naam' => 'Nieuwe Club',
            'naam' => 'Nieuwe Organisator',
            'telefoon' => '06-12345678',
        ]);

        $response = $this->get(route('register.verify', $token->token));

        $response->assertRedirect(route('password.setup'));
        $this->assertDatabaseHas('organisators', ['email' => 'newuser@example.com']);
        $this->assertAuthenticated('organisator');
    }

    #[Test]
    public function verify_register_logs_in_existing_organisator(): void
    {
        $token = MagicLinkToken::generate($this->organisator->email, 'register');

        $response = $this->get(route('register.verify', $token->token));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->organisator, 'organisator');
    }

    #[Test]
    public function verify_register_existing_sitebeheerder_redirects_admin(): void
    {
        $token = MagicLinkToken::generate($this->sitebeheerder->email, 'register');

        $response = $this->get(route('register.verify', $token->token));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->sitebeheerder, 'organisator');
    }

    #[Test]
    public function verify_register_with_used_token_fails(): void
    {
        $token = MagicLinkToken::generate('used@example.com', 'register');
        $token->markUsed();

        $response = $this->get(route('register.verify', $token->token));
        $response->assertRedirect(route('register'));
    }

    // ========================================================================
    // OrganisatorAuthController — password setup
    // ========================================================================

    #[Test]
    public function show_setup_password_without_password(): void
    {
        $org = Organisator::factory()->create(['password' => null]);
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('password.setup'));
        $response->assertStatus(200);
    }

    #[Test]
    public function setup_password_stores_password(): void
    {
        $org = Organisator::factory()->create(['password' => null]);
        $this->actingAs($org, 'organisator');

        $response = $this->post(route('password.setup.store'), [
            'password' => self::TEST_NEW_PW,
            'password_confirmation' => self::TEST_NEW_PW,
        ]);

        $response->assertRedirect();
        $org->refresh();
        $this->assertTrue(Hash::check(self::TEST_NEW_PW, $org->password));
    }

    #[Test]
    public function setup_password_requires_confirmation(): void
    {
        $org = Organisator::factory()->create(['password' => null]);
        $this->actingAs($org, 'organisator');

        $response = $this->post(route('password.setup.store'), [
            'password' => self::TEST_NEW_PW,
            'password_confirmation' => 'DifferentPass!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ========================================================================
    // OrganisatorAuthController — forgot/reset password
    // ========================================================================

    #[Test]
    public function show_forgot_password_page(): void
    {
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);
    }

    #[Test]
    public function send_reset_link_for_existing_user(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => $this->organisator->email,
        ]);

        $response->assertRedirect(route('password.sent'));
        $this->assertDatabaseHas('magic_link_tokens', [
            'email' => $this->organisator->email,
            'type' => 'password_reset',
        ]);
    }

    #[Test]
    public function send_reset_link_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('password-reset:127.0.0.1', 600);
        }

        $response = $this->post(route('password.email'), [
            'email' => $this->organisator->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function reset_sent_page_shows(): void
    {
        $response = $this->get(route('password.sent'));
        $response->assertStatus(200);
    }

    #[Test]
    public function show_reset_password_with_valid_token(): void
    {
        $token = MagicLinkToken::generate($this->organisator->email, 'password_reset');

        $response = $this->get(route('password.reset', $token->token));
        $response->assertStatus(200);
    }

    #[Test]
    public function show_reset_password_with_invalid_token(): void
    {
        $response = $this->get(route('password.reset', 'invalid-token'));
        $response->assertRedirect(route('password.request'));
    }

    #[Test]
    public function reset_password_with_valid_token(): void
    {
        $token = MagicLinkToken::generate($this->organisator->email, 'password_reset');

        $response = $this->post(route('password.update'), [
            'token' => $token->token,
            'password' => self::TEST_RESET_PW,
            'password_confirmation' => self::TEST_RESET_PW,
        ]);

        $response->assertRedirect();
        $this->organisator->refresh();
        $this->assertTrue(Hash::check(self::TEST_RESET_PW, $this->organisator->password));
        $this->assertAuthenticated('organisator');
    }

    #[Test]
    public function reset_password_sitebeheerder_redirects_admin(): void
    {
        $token = MagicLinkToken::generate($this->sitebeheerder->email, 'password_reset');

        $response = $this->post(route('password.update'), [
            'token' => $token->token,
            'password' => self::TEST_RESET_PW,
            'password_confirmation' => self::TEST_RESET_PW,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->sitebeheerder, 'organisator');
    }

    #[Test]
    public function reset_password_with_invalid_token(): void
    {
        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'password' => self::TEST_RESET_PW,
            'password_confirmation' => self::TEST_RESET_PW,
        ]);

        $response->assertRedirect(route('password.request'));
    }

    #[Test]
    public function reset_password_for_nonexistent_user(): void
    {
        $token = MagicLinkToken::create([
            'email' => 'nonexistent@example.com',
            'token' => 'valid-token-no-user',
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'valid-token-no-user',
            'password' => self::TEST_RESET_PW,
            'password_confirmation' => self::TEST_RESET_PW,
        ]);

        $response->assertRedirect(route('password.request'));
    }

    // ========================================================================
    // OrganisatorAuthController — logout
    // ========================================================================

    #[Test]
    public function logout_clears_session(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest('organisator');
    }

    // ========================================================================
    // PinAuthController — checkDevice
    // ========================================================================

    #[Test]
    public function pin_check_device_no_device_found(): void
    {
        $response = $this->postJson('/auth/pin/check-device', [
            'fingerprint' => self::FINGERPRINT,
        ]);

        $response->assertOk();
        $response->assertJson([
            'has_device' => false,
            'has_pin' => false,
            'has_biometric' => false,
        ]);
    }

    #[Test]
    public function pin_check_device_with_registered_device(): void
    {
        $device = AuthDevice::create([
            'organisator_id' => $this->organisator->id,
            'token' => 'test-token-123',
            'device_fingerprint' => self::FINGERPRINT,
            'is_active' => true,
            'has_biometric' => true,
            'expires_at' => now()->addDays(30),
        ]);
        $device->setPin('12345');

        $response = $this->postJson('/auth/pin/check-device', [
            'fingerprint' => self::FINGERPRINT,
        ]);

        $response->assertOk();
        $response->assertJson([
            'has_device' => true,
            'has_pin' => true,
            'has_biometric' => true,
        ]);
    }

    #[Test]
    public function pin_check_device_validates_fingerprint(): void
    {
        $response = $this->postJson('/auth/pin/check-device', [
            'fingerprint' => 'too-short',
        ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // PinAuthController — loginWithPin
    // ========================================================================

    #[Test]
    public function pin_login_with_correct_pin(): void
    {
        $device = AuthDevice::create([
            'organisator_id' => $this->organisator->id,
            'token' => 'test-token-pin',
            'device_fingerprint' => self::FINGERPRINT,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ]);
        $device->setPin('12345');

        $response = $this->postJson('/auth/pin/login', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['redirect']);
    }

    #[Test]
    public function pin_login_with_wrong_pin(): void
    {
        $device = AuthDevice::create([
            'organisator_id' => $this->organisator->id,
            'token' => 'test-token-wrong-pin',
            'device_fingerprint' => self::FINGERPRINT,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ]);
        $device->setPin('12345');

        $response = $this->postJson('/auth/pin/login', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '99999',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function pin_login_device_not_found(): void
    {
        $response = $this->postJson('/auth/pin/login', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function pin_login_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('pin-login:' . self::FINGERPRINT, 60);
        }

        $response = $this->postJson('/auth/pin/login', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertStatus(429);
    }

    #[Test]
    public function pin_login_sitebeheerder_gets_admin_redirect(): void
    {
        $device = AuthDevice::create([
            'organisator_id' => $this->sitebeheerder->id,
            'token' => 'test-token-admin-pin',
            'device_fingerprint' => self::FINGERPRINT,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ]);
        $device->setPin('12345');

        $response = $this->postJson('/auth/pin/login', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertStringContainsString('admin', $response->json('redirect'));
    }

    // ========================================================================
    // PinAuthController — setupPin
    // ========================================================================

    #[Test]
    public function pin_setup_requires_auth(): void
    {
        $response = $this->postJson('/auth/pin/setup', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function pin_setup_creates_device_with_pin(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->postJson('/auth/pin/setup', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => '12345',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $device = AuthDevice::where('organisator_id', $this->organisator->id)
            ->where('device_fingerprint', self::FINGERPRINT)
            ->first();
        $this->assertNotNull($device);
        $this->assertTrue($device->hasPin());
    }

    #[Test]
    public function pin_setup_validates_pin_format(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->postJson('/auth/pin/setup', [
            'fingerprint' => self::FINGERPRINT,
            'pin' => 'abcde',
        ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // PinAuthController — enableBiometric
    // ========================================================================

    #[Test]
    public function enable_biometric_requires_auth(): void
    {
        $response = $this->postJson('/auth/pin/biometric', [
            'fingerprint' => self::FINGERPRINT,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function enable_biometric_on_existing_device(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $device = AuthDevice::create([
            'organisator_id' => $this->organisator->id,
            'token' => 'test-token-bio',
            'device_fingerprint' => self::FINGERPRINT,
            'is_active' => true,
            'has_biometric' => false,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/auth/pin/biometric', [
            'fingerprint' => self::FINGERPRINT,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $device->refresh();
        $this->assertTrue($device->has_biometric);
    }

    #[Test]
    public function enable_biometric_creates_device_if_not_found(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $response = $this->postJson('/auth/pin/biometric', [
            'fingerprint' => self::FINGERPRINT,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $device = AuthDevice::where('organisator_id', $this->organisator->id)
            ->where('device_fingerprint', self::FINGERPRINT)
            ->first();
        $this->assertNotNull($device);
        $this->assertTrue($device->has_biometric);
    }

    // ========================================================================
    // PasskeyController — tokenLogin
    // ========================================================================

    #[Test]
    public function token_login_with_valid_token(): void
    {
        $device = AuthDevice::createForOrganisator($this->organisator, [
            'name' => 'Test Device',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'ip' => '127.0.0.1',
        ]);

        $response = $this->get('/auth/token-login/' . $device->token);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->organisator, 'organisator');
    }

    #[Test]
    public function token_login_sitebeheerder_redirects_admin(): void
    {
        $device = AuthDevice::createForOrganisator($this->sitebeheerder);

        $response = $this->get('/auth/token-login/' . $device->token);

        $response->assertRedirect(route('admin.index'));
    }

    #[Test]
    public function token_login_with_invalid_token(): void
    {
        $response = $this->get('/auth/token-login/invalid-token');

        $response->assertRedirect(route('organisator.login'));
        $this->assertGuest('organisator');
    }

    // ========================================================================
    // PasskeyController — QR flow
    // ========================================================================

    #[Test]
    public function qr_status_with_invalid_token(): void
    {
        $response = $this->getJson('/auth/qr/nonexistent/status');

        $response->assertStatus(404);
        $response->assertJson(['status' => 'invalid']);
    }

    #[Test]
    public function qr_status_with_pending_token(): void
    {
        $qrToken = QrLoginToken::generate(['browser' => 'Chrome', 'os' => 'Windows']);

        $response = $this->getJson('/auth/qr/' . $qrToken->token . '/status');

        $response->assertOk();
        $response->assertJson(['status' => 'pending']);
    }

    #[Test]
    public function qr_status_with_expired_token(): void
    {
        $qrToken = QrLoginToken::create([
            'token' => 'expired-qr-token',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/auth/qr/expired-qr-token/status');

        $response->assertOk();
        $response->assertJson(['status' => 'expired']);
    }

    #[Test]
    public function qr_status_with_approved_token_returns_complete_url(): void
    {
        $qrToken = QrLoginToken::create([
            'token' => 'approved-qr-token',
            'status' => 'approved',
            'organisator_id' => $this->organisator->id,
            'expires_at' => now()->addMinutes(5),
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/auth/qr/approved-qr-token/status');

        $response->assertOk();
        $response->assertJson(['status' => 'approved']);
        $response->assertJsonStructure(['complete_url']);
    }

    #[Test]
    public function qr_approve_show_with_valid_token(): void
    {
        $qrToken = QrLoginToken::generate();

        $response = $this->get('/auth/qr/approve/' . $qrToken->token);

        $response->assertStatus(200);
    }

    #[Test]
    public function qr_approve_show_with_expired_token(): void
    {
        $qrToken = QrLoginToken::create([
            'token' => 'expired-approve-token',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->get('/auth/qr/approve/expired-approve-token');

        $response->assertRedirect(route('organisator.login'));
    }

    #[Test]
    public function qr_approve_requires_auth(): void
    {
        $qrToken = QrLoginToken::generate();

        $response = $this->postJson('/auth/qr/approve/' . $qrToken->token);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function qr_approve_with_authenticated_user(): void
    {
        $this->actingAs($this->organisator, 'organisator');
        $qrToken = QrLoginToken::generate();

        $response = $this->postJson('/auth/qr/approve/' . $qrToken->token);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $qrToken->refresh();
        $this->assertEquals('approved', $qrToken->status);
    }

    #[Test]
    public function qr_approve_with_expired_token_fails(): void
    {
        $this->actingAs($this->organisator, 'organisator');

        $qrToken = QrLoginToken::create([
            'token' => 'expired-approve-post',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/auth/qr/approve/expired-approve-post');

        $response->assertStatus(400);
    }

    #[Test]
    public function qr_complete_with_approved_token(): void
    {
        $qrToken = QrLoginToken::create([
            'token' => 'complete-qr-token',
            'status' => 'approved',
            'organisator_id' => $this->organisator->id,
            'expires_at' => now()->addMinutes(5),
            'approved_at' => now(),
        ]);

        $response = $this->get('/auth/qr/complete/complete-qr-token');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->organisator, 'organisator');
        $qrToken->refresh();
        $this->assertEquals('used', $qrToken->status);
    }

    #[Test]
    public function qr_complete_sitebeheerder_redirects_admin(): void
    {
        $qrToken = QrLoginToken::create([
            'token' => 'complete-admin-qr',
            'status' => 'approved',
            'organisator_id' => $this->sitebeheerder->id,
            'expires_at' => now()->addMinutes(5),
            'approved_at' => now(),
        ]);

        $response = $this->get('/auth/qr/complete/complete-admin-qr');

        $response->assertRedirect(route('admin.index'));
    }

    #[Test]
    public function qr_complete_with_invalid_token(): void
    {
        $response = $this->get('/auth/qr/complete/nonexistent');

        $response->assertRedirect(route('organisator.login'));
    }

    #[Test]
    public function qr_complete_with_pending_token_fails(): void
    {
        $qrToken = QrLoginToken::generate();

        $response = $this->get('/auth/qr/complete/' . $qrToken->token);

        $response->assertRedirect(route('organisator.login'));
    }

    // ========================================================================
    // DeviceToegangController — show & verify
    // ========================================================================

    #[Test]
    public function device_toegang_show_with_valid_code(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        $response = $this->get("/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}");

        $response->assertStatus(200);
    }

    #[Test]
    public function device_toegang_show_with_invalid_code(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);

        $response = $this->get("/{$this->organisator->slug}/{$toernooi->slug}/toegang/INVALID_CODE");

        $response->assertStatus(404);
    }

    #[Test]
    public function device_toegang_verify_with_correct_pin(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'hoofdjury',
        ]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}/verify",
            ['pincode' => $toegang->pincode]
        );

        $response->assertRedirect();
        $toegang->refresh();
        $this->assertTrue($toegang->isGebonden());
    }

    #[Test]
    public function device_toegang_verify_with_wrong_pin(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
        ]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}/verify",
            ['pincode' => '9999']
        );

        $response->assertSessionHasErrors('pincode');
    }

    #[Test]
    public function device_toegang_verify_with_invalid_code(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/INVALID/verify",
            ['pincode' => '1234']
        );

        $response->assertStatus(404);
    }

    // ========================================================================
    // DeviceToegangController — redirectToNew (legacy)
    // ========================================================================

    #[Test]
    public function legacy_toegang_redirect_with_valid_code(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        $response = $this->get("/toegang/{$toegang->code}");

        $response->assertRedirect();
    }

    #[Test]
    public function legacy_toegang_redirect_with_invalid_code(): void
    {
        $response = $this->get('/toegang/NONEXISTENT');

        $response->assertStatus(404);
    }

    // ========================================================================
    // DeviceToegangController — redirectInterfaceToNew (legacy)
    // ========================================================================

    #[Test]
    public function legacy_interface_redirect_mat(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        $response = $this->get("/mat/{$toegang->id}");

        $response->assertRedirect();
    }

    #[Test]
    public function legacy_interface_redirect_invalid_toegang(): void
    {
        $response = $this->get('/mat/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function legacy_interface_redirect_unknown_role(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        // The legacy route closure passes the role string, test with invalid
        // We can't directly hit an unknown role through existing routes,
        // so we call the controller method directly
        $controller = app(\App\Http\Controllers\DeviceToegangController::class);
        $result = $controller->redirectInterfaceToNew($toegang->id, 'unknown');

        $this->assertEquals(302, $result->getStatusCode());
        $this->assertEquals(url('/'), $result->getTargetUrl());
    }

    // ========================================================================
    // DeviceToegangController — getDeviceInfo edge cases
    // ========================================================================

    #[Test]
    public function device_toegang_verify_detects_device_info(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
        ]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}/verify",
            ['pincode' => $toegang->pincode],
            ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1']
        );

        $response->assertRedirect();
        $toegang->refresh();
        $this->assertStringContainsString('iPhone', $toegang->device_info);
        $this->assertStringContainsString('Safari', $toegang->device_info);
    }

    // ========================================================================
    // DeviceToegangBeheerController — CRUD
    // ========================================================================

    #[Test]
    public function device_beheer_index_requires_auth(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);

        $response = $this->getJson("/{$this->organisator->slug}/toernooi/{$toernooi->slug}/api/device-toegang");

        // Without auth, should return 401
        $response->assertStatus(401);
    }

    #[Test]
    public function device_beheer_index_returns_toegangen(): void
    {
        // Use sitebeheerder — hasAccessToToernooi always true
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'hoofdjury',
            'naam' => 'Test Jury',
        ]);

        $response = $this->getJson("/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang");

        $response->assertOk();
        $response->assertJsonFragment(['naam' => 'Test Jury']);
    }

    #[Test]
    public function device_beheer_store_creates_toegang(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->postJson("/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang", [
            'naam' => 'Nieuwe Jury',
            'telefoon' => '06-11111111',
            'email' => 'jury@test.com',
            'rol' => 'hoofdjury',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['naam' => 'Nieuwe Jury']);
        $this->assertDatabaseHas('device_toegangen', [
            'toernooi_id' => $toernooi->id,
            'naam' => 'Nieuwe Jury',
        ]);
    }

    #[Test]
    public function device_beheer_store_mat_with_nummer(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->postJson("/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang", [
            'rol' => 'mat',
            'mat_nummer' => 3,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['mat_nummer' => 3]);
    }

    #[Test]
    public function device_beheer_store_validates_rol(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->postJson("/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang", [
            'rol' => 'invalid_role',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function device_beheer_update_toegang(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'hoofdjury',
            'naam' => 'Old Name',
        ]);

        $response = $this->putJson(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/{$toegang->id}",
            [
                'naam' => 'Updated Name',
                'telefoon' => '06-99999999',
                'email' => 'updated@test.com',
                'rol' => 'weging',
            ]
        );

        $response->assertOk();
        $response->assertJsonFragment(['naam' => 'Updated Name']);
        $response->assertJsonFragment(['rol' => 'weging']);
    }

    #[Test]
    public function device_beheer_reset_clears_binding(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
            'device_token' => 'bound-token',
            'device_info' => 'iPhone Safari',
            'gebonden_op' => now(),
        ]);

        $response = $this->postJson(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/{$toegang->id}/reset"
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $toegang->refresh();
        $this->assertNull($toegang->device_token);
    }

    #[Test]
    public function device_beheer_regenerate_pin(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
        ]);

        $response = $this->postJson(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/{$toegang->id}/regenerate-pin"
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['pincode']);
    }

    #[Test]
    public function device_beheer_destroy(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'spreker',
        ]);
        $id = $toegang->id;

        $response = $this->deleteJson(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/{$id}"
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('device_toegangen', ['id' => $id]);
    }

    #[Test]
    public function device_beheer_reset_all(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'mat_nummer' => 1,
            'device_token' => 'token-1',
            'gebonden_op' => now(),
        ]);
        DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
            'device_token' => 'token-2',
            'gebonden_op' => now(),
        ]);

        $response = $this->postJson(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/reset-all"
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals(0, $toernooi->deviceToegangen()->whereNotNull('device_token')->count());
    }

    #[Test]
    public function device_beheer_qr_code_returns_svg(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->get(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/qr?url=https://example.com"
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
    }

    #[Test]
    public function device_beheer_qr_code_without_url(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->sitebeheerder->id]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->get(
            "/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang/qr"
        );

        $response->assertStatus(400);
    }

    // ========================================================================
    // AutoFixController — show
    // ========================================================================

    #[Test]
    public function autofix_show_with_valid_token(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'TypeError',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'test stack trace',
            'code_context' => 'test code context',
            'claude_analysis' => 'Test analysis',
            'approval_token' => 'test-autofix-token',
            'status' => 'pending',
        ]);

        $response = $this->get(route('autofix.show', 'test-autofix-token'));

        $response->assertStatus(200);
    }

    #[Test]
    public function autofix_show_with_invalid_token(): void
    {
        $response = $this->get(route('autofix.show', 'nonexistent-token'));

        $response->assertStatus(404);
    }

    // ========================================================================
    // AutoFixController — reject
    // ========================================================================

    #[Test]
    public function autofix_reject_pending_proposal(): void
    {
        $proposal = AutofixProposal::create([
            'exception_class' => 'TypeError',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'test stack trace',
            'code_context' => 'test code context',
            'claude_analysis' => 'Test analysis',
            'approval_token' => 'reject-token',
            'status' => 'pending',
        ]);

        $response = $this->post(route('autofix.reject', 'reject-token'));

        $response->assertRedirect();
        $proposal->refresh();
        $this->assertEquals('rejected', $proposal->status);
    }

    #[Test]
    public function autofix_reject_already_processed(): void
    {
        AutofixProposal::create([
            'exception_class' => 'TypeError',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'test stack trace',
            'code_context' => 'test code context',
            'claude_analysis' => 'Test analysis',
            'approval_token' => 'already-rejected-token',
            'status' => 'rejected',
        ]);

        $response = $this->post(route('autofix.reject', 'already-rejected-token'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function autofix_reject_nonexistent_token(): void
    {
        $response = $this->post(route('autofix.reject', 'does-not-exist'));

        $response->assertStatus(404);
    }

    // ========================================================================
    // AutoFixController — approve
    // ========================================================================

    #[Test]
    public function autofix_approve_already_processed(): void
    {
        AutofixProposal::create([
            'exception_class' => 'TypeError',
            'exception_message' => 'Test error',
            'file' => 'app/Test.php',
            'line' => 10,
            'stack_trace' => 'test stack trace',
            'code_context' => 'test code context',
            'claude_analysis' => 'Test analysis',
            'approval_token' => 'already-approved-token',
            'status' => 'approved',
        ]);

        $response = $this->post(route('autofix.approve', 'already-approved-token'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function autofix_approve_nonexistent_token(): void
    {
        $response = $this->post(route('autofix.approve', 'does-not-exist'));

        $response->assertStatus(404);
    }

    // ========================================================================
    // DeviceToegangController — show with already-bound device (cookie)
    // ========================================================================

    #[Test]
    public function device_toegang_show_already_bound_redirects(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
            'device_token' => 'my-device-token',
            'gebonden_op' => now(),
        ]);

        $response = $this->withCookie('device_token_' . $toegang->id, 'my-device-token')
            ->get("/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}");

        $response->assertRedirect();
    }

    // ========================================================================
    // DeviceToegangBeheerController — syncMatToegangen auto-creates mats
    // ========================================================================

    #[Test]
    public function device_beheer_index_auto_creates_mat_toegangen(): void
    {
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->sitebeheerder->id,
            'aantal_matten' => 3,
        ]);
        $this->actingAs($this->sitebeheerder, 'organisator');

        $response = $this->getJson("/{$this->sitebeheerder->slug}/toernooi/{$toernooi->slug}/api/device-toegang");

        $response->assertOk();

        // Should have auto-created 3 mat toegangen
        $matCount = DeviceToegang::where('toernooi_id', $toernooi->id)
            ->where('rol', 'mat')
            ->count();
        $this->assertEquals(3, $matCount);
    }

    // ========================================================================
    // DeviceToegangController — verify for different roles
    // ========================================================================

    #[Test]
    public function device_toegang_verify_spreker_role(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'spreker',
        ]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}/verify",
            ['pincode' => $toegang->pincode]
        );

        $response->assertRedirect();
    }

    #[Test]
    public function device_toegang_verify_dojo_role(): void
    {
        $toernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'dojo',
        ]);

        $response = $this->post(
            "/{$this->organisator->slug}/{$toernooi->slug}/toegang/{$toegang->code}/verify",
            ['pincode' => $toegang->pincode]
        );

        $response->assertRedirect();
    }
}
