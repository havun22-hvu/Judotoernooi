<?php

namespace Tests\Feature;

use App\Models\MagicLinkToken;
use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorAuthTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Login Page (GET)
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
    // Registration Page (GET)
    // ========================================================================

    #[Test]
    public function register_page_loads_for_guests(): void
    {
        $response = $this->get('/registreren');

        $response->assertStatus(200);
    }

    // ========================================================================
    // Forgot Password Page (GET)
    // ========================================================================

    #[Test]
    public function forgot_password_page_loads(): void
    {
        $response = $this->get('/wachtwoord-vergeten');

        $response->assertStatus(200);
    }

    // ========================================================================
    // MagicLinkToken Model (unit-level, no HTTP)
    // ========================================================================

    #[Test]
    public function magic_link_token_can_be_generated(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register', [
            'organisatie_naam' => 'Test Club',
            'naam' => 'Jan',
        ]);

        $this->assertNotNull($token);
        $this->assertEquals('test@example.com', $token->email);
        $this->assertEquals('register', $token->type);
        $this->assertNotEmpty($token->token);
        $this->assertFalse($token->isExpired());
        $this->assertFalse($token->isUsed());
    }

    #[Test]
    public function magic_link_token_can_be_found_by_valid_token(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register');

        $found = MagicLinkToken::findValid($token->token, 'register');

        $this->assertNotNull($found);
        $this->assertEquals($token->id, $found->id);
    }

    #[Test]
    public function magic_link_token_not_found_with_wrong_type(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register');

        $found = MagicLinkToken::findValid($token->token, 'password_reset');

        $this->assertNull($found);
    }

    #[Test]
    public function magic_link_token_becomes_invalid_after_use(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register');
        $token->markUsed();

        $this->assertTrue($token->isUsed());
        $this->assertNull(MagicLinkToken::findValid($token->token, 'register'));
    }

    #[Test]
    public function magic_link_token_stores_metadata(): void
    {
        $token = MagicLinkToken::generate('test@example.com', 'register', [
            'organisatie_naam' => 'Judoschool Amsterdam',
            'naam' => 'Jan de Tester',
            'telefoon' => '06-12345678',
        ]);

        $fresh = $token->fresh();
        $metadata = $fresh->metadata;

        $this->assertEquals('Judoschool Amsterdam', $metadata['organisatie_naam']);
        $this->assertEquals('Jan de Tester', $metadata['naam']);
    }

    #[Test]
    public function generating_new_token_cleans_old_unused_tokens(): void
    {
        $old = MagicLinkToken::generate('test@example.com', 'register');
        $oldId = $old->id;

        $new = MagicLinkToken::generate('test@example.com', 'register');

        // Old token should be deleted
        $this->assertNull(MagicLinkToken::find($oldId));
        // New token exists
        $this->assertNotNull(MagicLinkToken::findValid($new->token, 'register'));
    }

    // ========================================================================
    // Organisator Model Auth
    // ========================================================================

    #[Test]
    public function organisator_factory_creates_valid_user(): void
    {
        $org = Organisator::factory()->create();

        $this->assertNotEmpty($org->naam);
        $this->assertNotEmpty($org->email);
        $this->assertNotEmpty($org->slug);
        $this->assertFalse($org->is_sitebeheerder);
    }

    #[Test]
    public function sitebeheerder_factory_state_works(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();

        $this->assertTrue($admin->is_sitebeheerder);
        $this->assertTrue($admin->isSitebeheerder());
    }
}
