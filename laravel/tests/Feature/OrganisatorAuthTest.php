<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorAuthTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Login Page (GET — no middleware issues)
    // ========================================================================

    #[Test]
    public function login_page_loads_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    #[Test]
    public function logged_in_user_is_redirected_from_login(): void
    {
        $org = Organisator::factory()->create([
            'biometric_prompted_at' => now(),
        ]);
        $this->actingAs($org, 'organisator');

        $response = $this->get('/login');

        $response->assertRedirect();
    }

    // ========================================================================
    // Login Submit (POST)
    // ========================================================================

    #[Test]
    public function login_with_valid_credentials_authenticates_user(): void
    {
        $org = Organisator::factory()->create([
            'email' => 'test@example.com',
            'biometric_prompted_at' => now(),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($org, 'organisator');
    }

    #[Test]
    public function login_with_wrong_password_fails(): void
    {
        Organisator::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest('organisator');
    }

    #[Test]
    public function login_validates_required_fields(): void
    {
        $response = $this->from('/login')->post('/login', []);

        // Should redirect back to login (validation error or auth error)
        $response->assertRedirect('/login');
    }

    // ========================================================================
    // Logout
    // ========================================================================

    #[Test]
    public function logout_clears_authentication(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $this->assertAuthenticatedAs($org, 'organisator');

        $response = $this->post('/logout');

        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    // ========================================================================
    // Registration Page (GET)
    // ========================================================================

    #[Test]
    public function register_page_loads_for_guests(): void
    {
        $response = $this->get('/registreren');

        $response->assertStatus(200);
    }

    // ========================================================================
    // Magic Link Registration (POST)
    // ========================================================================

    #[Test]
    public function register_sends_magic_link_email(): void
    {
        Mail::fake();

        $response = $this->from('/registreren')->post('/registreren', [
            'organisatie_naam' => 'Judoschool Test',
            'naam' => 'Jan de Tester',
            'email' => 'jan@test.nl',
            'telefoon' => '06-12345678',
        ]);

        $response->assertRedirect(route('register.sent'));
        Mail::assertSent(MagicLinkMail::class, function ($mail) {
            return $mail->hasTo('jan@test.nl');
        });
    }

    #[Test]
    public function register_creates_magic_link_token_in_database(): void
    {
        Mail::fake();

        $this->from('/registreren')->post('/registreren', [
            'organisatie_naam' => 'Judoschool Test',
            'naam' => 'Jan',
            'email' => 'jan@test.nl',
        ]);

        $this->assertDatabaseHas('magic_link_tokens', [
            'email' => 'jan@test.nl',
            'type' => 'register',
        ]);
    }

    #[Test]
    public function register_validates_required_fields(): void
    {
        $response = $this->from('/registreren')->post('/registreren', []);

        // Should redirect back (validation errors)
        $response->assertRedirect('/registreren');
    }

    // ========================================================================
    // Forgot Password
    // ========================================================================

    #[Test]
    public function forgot_password_page_loads(): void
    {
        $response = $this->get('/wachtwoord-vergeten');

        $response->assertStatus(200);
    }

    #[Test]
    public function forgot_password_sends_reset_link_for_existing_user(): void
    {
        Mail::fake();

        Organisator::factory()->create(['email' => 'henk@test.nl']);

        $response = $this->from('/wachtwoord-vergeten')->post('/wachtwoord-vergeten', [
            'email' => 'henk@test.nl',
        ]);

        $response->assertRedirect(route('password.sent'));
        Mail::assertSent(MagicLinkMail::class);
    }

    #[Test]
    public function forgot_password_does_not_reveal_nonexistent_email(): void
    {
        Mail::fake();

        $response = $this->from('/wachtwoord-vergeten')->post('/wachtwoord-vergeten', [
            'email' => 'niemand@test.nl',
        ]);

        // Should still redirect to "sent" page (prevent email enumeration)
        $response->assertRedirect(route('password.sent'));
        Mail::assertNothingSent();
    }

    // ========================================================================
    // Magic Link Token Model
    // ========================================================================

    #[Test]
    public function magic_link_token_can_be_generated_and_found(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register', [
            'organisatie_naam' => 'Test Club',
        ]);

        $this->assertNotNull($token);
        $this->assertEquals('test@example.com', $token->email);
        $this->assertEquals('register', $token->type);

        $found = MagicLinkToken::findValid($token->token, 'register');
        $this->assertNotNull($found);
        $this->assertEquals($token->id, $found->id);
    }

    #[Test]
    public function magic_link_token_expires_after_use(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register');
        $token->markUsed();

        $found = MagicLinkToken::findValid($token->token, 'register');
        $this->assertNull($found);
    }
}
