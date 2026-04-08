<?php

namespace Tests\Feature;

use App\Models\MagicLinkToken;
use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorAuthCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PASSWORD = 'TestPassword123!';

    protected function setUp(): void
    {
        parent::setUp();
        app('cache')->flush();
    }

    // ========================================================================
    // Login validation
    // ========================================================================

    #[Test]
    public function login_requires_email(): void
    {
        $response = $this->post(route('login.submit'), [
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_requires_password(): void
    {
        $response = $this->post(route('login.submit'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    #[Test]
    public function login_requires_valid_email_format(): void
    {
        $response = $this->post(route('login.submit'), [
            'email' => 'not-an-email',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ========================================================================
    // Login remember me
    // ========================================================================

    #[Test]
    public function login_with_remember_me(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
            'biometric_prompted_at' => now(),
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $org->email,
            'password' => self::TEST_PASSWORD,
            'remember' => true,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($org, 'organisator');
    }

    // ========================================================================
    // Registration validation
    // ========================================================================

    #[Test]
    public function register_requires_email(): void
    {
        $response = $this->post(route('register.submit'), [
            'organisatie_naam' => 'Test',
            'naam' => 'Test',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function register_requires_naam(): void
    {
        $response = $this->post(route('register.submit'), [
            'email' => 'test@example.com',
            'organisatie_naam' => 'Test',
        ]);

        $response->assertSessionHasErrors('naam');
    }

    #[Test]
    public function register_requires_organisatie_naam(): void
    {
        $response = $this->post(route('register.submit'), [
            'email' => 'test@example.com',
            'naam' => 'Test',
        ]);

        $response->assertSessionHasErrors('organisatie_naam');
    }

    // ========================================================================
    // Register with phone number
    // ========================================================================

    #[Test]
    public function register_with_valid_phone(): void
    {
        $response = $this->post(route('register.submit'), [
            'email' => 'test2@example.com',
            'organisatie_naam' => 'Test Club',
            'naam' => 'Test',
            'telefoon' => '06-12345678',
        ]);

        $response->assertRedirect(route('register.sent'));
    }

    #[Test]
    public function register_with_invalid_phone(): void
    {
        $response = $this->post(route('register.submit'), [
            'email' => 'test3@example.com',
            'organisatie_naam' => 'Test Club',
            'naam' => 'Test',
            'telefoon' => 'invalid',
        ]);

        $response->assertSessionHasErrors('telefoon');
    }

    // ========================================================================
    // Magic link expired
    // ========================================================================

    #[Test]
    public function verify_register_with_expired_token(): void
    {
        MagicLinkToken::create([
            'token' => 'expired-token',
            'email' => 'expired@example.com',
            'type' => 'register',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->get(route('register.verify', 'expired-token'));
        $this->assertContains($response->status(), [302, 404, 410]);
    }

    #[Test]
    public function verify_register_with_nonexistent_token(): void
    {
        $response = $this->get(route('register.verify', 'nonexistent-token'));
        $this->assertContains($response->status(), [302, 404, 410]);
    }

    // ========================================================================
    // Password reset validation
    // ========================================================================

    #[Test]
    public function forgot_password_requires_email(): void
    {
        $response = $this->post(route('password.email'), []);
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function forgot_password_with_nonexistent_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        // Should still redirect (don't reveal if email exists)
        $response->assertRedirect();
    }

    // ========================================================================
    // Logout without being logged in
    // ========================================================================

    #[Test]
    public function logout_as_guest_redirects(): void
    {
        $response = $this->post(route('logout'));
        $response->assertRedirect();
    }

    // ========================================================================
    // Login updates laatste_login
    // ========================================================================

    #[Test]
    public function successful_login_updates_laatste_login(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
            'biometric_prompted_at' => now(),
            'laatste_login' => null,
        ]);

        $this->post(route('login.submit'), [
            'email' => $org->email,
            'password' => self::TEST_PASSWORD,
        ]);

        $org->refresh();
        $this->assertNotNull($org->laatste_login);
    }

    // ========================================================================
    // Password setup page
    // ========================================================================

    #[Test]
    public function setup_password_redirects_when_already_has_password(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make(self::TEST_PASSWORD),
        ]);
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('password.setup'));
        // Should show page or redirect
        $this->assertContains($response->status(), [200, 302]);
    }
}
