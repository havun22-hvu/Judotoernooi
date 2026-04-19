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

    protected function setUp(): void
    {
        parent::setUp();
        // Rate-limiter counters live in the array cache driver during tests
        // (config/cache.php). Flushing it isolates this file's tests so the
        // rate-limit test can't bleed 429s into the happy-path ones.
        \Illuminate\Support\Facades\Cache::flush();
    }

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

    public function test_login_is_rate_limited(): void
    {
        Organisator::factory()->create([
            'email' => 'rate-test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        // login limiter (AppServiceProvider): perMinute(5) per IP — 6th
        // attempt must hit 429. setUp() clears the counter, so 5 wrong
        // attempts followed by 1 more = 429.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.submit'), [
                'email' => 'rate-test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $sixth = $this->post(route('login.submit'), [
            'email' => 'rate-test@example.com',
            'password' => 'wrong-password',
        ]);

        $sixth->assertStatus(429);
    }
}
