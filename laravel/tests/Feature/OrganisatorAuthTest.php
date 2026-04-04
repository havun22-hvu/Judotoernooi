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
    // Login Page
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
    // Login Submit
    // ========================================================================

    #[Test]
    public function login_with_valid_credentials_redirects(): void
    {
        $org = Organisator::factory()->create([
            'email' => 'test@example.com',
            'biometric_prompted_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($org, 'organisator');
    }

    #[Test]
    public function login_with_invalid_credentials_returns_error(): void
    {
        Organisator::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('organisator');
    }

    #[Test]
    public function login_requires_email_and_password(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    // ========================================================================
    // Logout
    // ========================================================================

    #[Test]
    public function logout_clears_session(): void
    {
        $org = Organisator::factory()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->post('/logout');

        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    // ========================================================================
    // Registration (Magic Link)
    // ========================================================================

    #[Test]
    public function register_page_loads_for_guests(): void
    {
        $response = $this->get('/registreren');

        $response->assertStatus(200);
    }

    #[Test]
    public function register_sends_magic_link_email(): void
    {
        Mail::fake();

        $response = $this->post('/registreren', [
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
    public function register_creates_magic_link_token(): void
    {
        Mail::fake();

        $this->post('/registreren', [
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
        $response = $this->post('/registreren', []);

        $response->assertSessionHasErrors(['organisatie_naam', 'naam', 'email']);
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
    public function forgot_password_sends_reset_link(): void
    {
        Mail::fake();

        Organisator::factory()->create(['email' => 'henk@test.nl']);

        $response = $this->post('/wachtwoord-vergeten', [
            'email' => 'henk@test.nl',
        ]);

        $response->assertRedirect(route('password.sent'));
        Mail::assertSent(MagicLinkMail::class);
    }

    #[Test]
    public function forgot_password_does_not_reveal_nonexistent_email(): void
    {
        Mail::fake();

        $response = $this->post('/wachtwoord-vergeten', [
            'email' => 'niemand@test.nl',
        ]);

        // Should still redirect to "sent" page (no email enumeration)
        $response->assertRedirect(route('password.sent'));
        Mail::assertNothingSent();
    }
}
