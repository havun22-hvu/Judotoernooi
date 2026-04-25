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
    public function corp_is_cross_origin_for_social_sharing(): void
    {
        // Public tournament site — international participants share via social
        // media (FB/LinkedIn/X). same-origin would break OG-image previews.
        $this->get('/')->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    }

    #[Test]
    public function coop_allows_popups_for_share_flows(): void
    {
        // Preserve window.opener for share/OAuth popups while isolating
        // cross-origin attacks.
        $this->get('/')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
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
        // After @alpinejs/csp switch: Mozilla Observatory penalty -10 gone.
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("'nonce-", $csp);
    }

    #[Test]
    public function csp_does_not_allow_unsafe_inline_in_script_src(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $script = preg_match('/script-src\s+([^;]+)/', (string) $csp, $m) ? $m[1] : '';
        $this->assertStringNotContainsString(
            'unsafe-inline',
            $script,
            'script-src must NOT allow unsafe-inline — use nonces instead'
        );
    }

    #[Test]
    public function hsts_header_includes_preload_over_https(): void
    {
        // HSTS only emitted on secure requests in production env.
        \Illuminate\Support\Facades\URL::forceScheme('https');
        $this->app->detectEnvironment(fn () => 'production');
        $hsts = $this->get('/')->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts, 'HSTS must be set on HTTPS in production');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts, 'preload required for hstspreload.org submission');
    }
}
