<?php

namespace Tests\Feature;

use App\Models\MagicLinkToken;
use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorAuthExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function getTestSecret(): string
    {
        return 'geheim' . '123';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Clear all rate limiters between tests to avoid 429 responses
        RateLimiter::clear('login|127.0.0.1');
        RateLimiter::clear('login:127.0.0.1');
        RateLimiter::clear('magic-link:127.0.0.1');
        RateLimiter::clear('password-reset:127.0.0.1');
        // Clear any SHA1-hashed keys used by ThrottleRequests middleware
        app('cache')->flush();
    }

    // ========================================================================
    // Login
    // ========================================================================

    #[Test]
    public function login_page_loads(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
    }

    #[Test]
    public function login_redirects_when_already_authenticated(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('login'));
        $response->assertRedirect();
    }

    #[Test]
    public function login_succeeds_with_valid_credentials(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make($this->getTestSecret()),
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $org->email,
            'password' => $this->getTestSecret(),
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($org, 'organisator');
    }

    #[Test]
    public function login_fails_with_wrong_password(): void
    {
        $org = Organisator::factory()->create([
            'password' => Hash::make($this->getTestSecret()),
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $org->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    #[Test]
    public function login_fails_with_nonexistent_email(): void
    {
        $response = $this->post(route('login.submit'), [
            'email' => 'nobody@example.com',
            'password' => $this->getTestSecret(),
        ]);

        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    #[Test]
    public function sitebeheerder_redirects_to_admin(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create([
            'password' => Hash::make($this->getTestSecret()),
            'biometric_prompted_at' => now(), // Skip biometric prompt
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $admin->email,
            'password' => $this->getTestSecret(),
        ]);

        $response->assertRedirect(route('admin.index'));
    }

    // ========================================================================
    // Logout
    // ========================================================================

    #[Test]
    public function logout_logs_user_out(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->post(route('logout'));
        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    // ========================================================================
    // Registration
    // ========================================================================

    #[Test]
    public function register_page_loads_for_guests(): void
    {
        $response = $this->get(route('register'));
        $response->assertStatus(200);
    }

    #[Test]
    public function register_redirects_when_authenticated(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    #[Test]
    public function register_sends_magic_link(): void
    {
        $response = $this->post(route('register.submit'), [
            'email' => 'new@example.com',
            'organisatie_naam' => 'Test Organisatie',
            'naam' => 'Test Organisator',
        ]);

        $response->assertRedirect(route('register.sent'));
    }

    // ========================================================================
    // Password Reset
    // ========================================================================

    #[Test]
    public function forgot_password_page_loads(): void
    {
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);
    }

    #[Test]
    public function forgot_password_sends_reset_link(): void
    {
        $org = Organisator::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $org->email,
        ]);

        $response->assertRedirect(route('password.sent'));
    }

    // ========================================================================
    // Magic Link Verification
    // ========================================================================

    #[Test]
    public function verify_register_with_valid_token(): void
    {
        $token = MagicLinkToken::create([
            'token' => 'test-token-123',
            'email' => 'new@example.com',
            'naam' => 'Test',
            'type' => 'register',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->get(route('register.verify', 'test-token-123'));
        // verifyRegister creates user and redirects to dashboard
        $response->assertRedirect();
        $this->assertAuthenticatedAs(Organisator::where('email', 'new@example.com')->first(), 'organisator');
    }

    // ========================================================================
    // Password Setup
    // ========================================================================

    #[Test]
    public function setup_password_requires_auth(): void
    {
        $response = $this->get(route('password.setup'));
        $response->assertRedirect();
    }

    #[Test]
    public function setup_password_page_loads_for_authenticated(): void
    {
        $org = Organisator::factory()->create(['password' => null]);
        $this->actingAs($org, 'organisator');

        $response = $this->get(route('password.setup'));
        $response->assertStatus(200);
    }
}
