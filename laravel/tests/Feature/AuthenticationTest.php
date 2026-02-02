<?php

namespace Tests\Feature;

use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_login_with_valid_credentials(): void
    {
        $organisator = Organisator::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/organisator/dashboard');
        $this->assertAuthenticatedAs($organisator, 'organisator');
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials(): void
    {
        Organisator::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('organisator');
    }

    /** @test */
    public function authenticated_user_can_logout(): void
    {
        $organisator = Organisator::factory()->create();

        $response = $this->actingAs($organisator, 'organisator')
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest('organisator');
    }

    /** @test */
    public function login_is_rate_limited(): void
    {
        Organisator::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Attempt 6 logins (limit is 5/minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // Last attempt should be rate limited
        $response->assertStatus(429);
    }
}
