<?php

namespace Tests\Feature;

use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconstructed 2026-04-19 — restored after wrongful deletion in commit
 * f01b04 (2026-02-02). The original tests were removed instead of fixed
 * when factories diverged from the schema; they now match current
 * routing (named routes with organisator slug).
 *
 * VP-17: tests are reconstructed instead of left dropped.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        // biometric_prompted_at set so we skip the first-login PIN-setup
        // redirect — that's a separate flow with its own coverage.
        $organisator = Organisator::factory()->create([
            'email' => 'auth-test@example.com',
            'password' => bcrypt('correct-password'),
            'biometric_prompted_at' => now(),
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => 'auth-test@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('organisator.dashboard', ['organisator' => $organisator->slug]));
        $this->assertAuthenticatedAs($organisator, 'organisator');
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        Organisator::factory()->create([
            'email' => 'auth-test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => 'auth-test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('organisator');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $organisator = Organisator::factory()->create();

        $response = $this->actingAs($organisator, 'organisator')
            ->post(route('logout'));

        $response->assertRedirect();
        $this->assertGuest('organisator');
    }

    public function test_login_throttle_is_configured(): void
    {
        // The original test asserted a 429 after 6 attempts. The current
        // POST /login does not have throttle:auth middleware — flag this
        // as an explicit follow-up rather than silently dropping the test.
        $this->markTestIncomplete(
            'POST /login mist throttle:auth middleware — voeg toe in '
            . 'routes/web.php (line ~240) en activeer dan deze assertion.'
        );
    }
}
