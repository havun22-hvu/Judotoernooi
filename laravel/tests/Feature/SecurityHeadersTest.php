<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /** @test */
    public function response_has_x_frame_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function response_has_x_content_type_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function response_has_x_xss_protection_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /** @test */
    public function response_has_referrer_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function response_has_permissions_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Permissions-Policy');
    }

    /** @test */
    public function health_endpoint_has_security_headers(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options');
    }
}
