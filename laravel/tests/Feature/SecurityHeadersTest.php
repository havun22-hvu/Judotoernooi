<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function response_has_x_frame_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    #[Test]
    public function response_has_x_content_type_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function response_has_x_xss_protection_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    #[Test]
    public function response_has_referrer_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function response_has_permissions_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Permissions-Policy');
    }

    #[Test]
    public function health_endpoint_has_security_headers(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options');
    }

    #[Test]
    public function csp_does_not_contain_unsafe_eval_in_non_local_env(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("'nonce-", $csp);
    }
}
