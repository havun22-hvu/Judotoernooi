<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to all responses.
 *
 * These headers protect against common web vulnerabilities:
 * - XSS attacks
 * - Clickjacking
 * - MIME type sniffing
 * - Information disclosure
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking - page cannot be embedded in iframe
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Prevent information disclosure
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Content Security Policy (allow same origin + inline styles for Tailwind)
        if (!app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Needed for Alpine.js
                "style-src 'self' 'unsafe-inline'", // Needed for Tailwind
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self' wss:", // WebSocket for Reverb
                "frame-ancestors 'self'",
            ]));
        }

        // HSTS - force HTTPS (only in production)
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Permissions Policy - disable unnecessary browser features
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
        ]));

        return $response;
    }
}
