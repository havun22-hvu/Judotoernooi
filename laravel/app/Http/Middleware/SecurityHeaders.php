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
        // Generate CSP nonce for this request
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

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

        // Content Security Policy - strict, all assets bundled locally via Vite
        // External sources: cdn.jsdelivr.net (SortableJS, QRCode), cdnjs.cloudflare.com (html2canvas), unpkg.com (html5-qrcode), js.pusher.com (Reverb/Pusher), www.gstatic.com (Google Cast SDK)
        if (!app()->environment('local')) {
            // Alpine.js (bundled via Vite) requires 'unsafe-eval' for x-data/x-on expressions
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'none'",
                "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://unpkg.com https://js.pusher.com https://www.gstatic.com",
                "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self' wss://*.pusher.com https://nominatim.openstreetmap.org https://www.gstatic.com",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "manifest-src 'self'",
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
        // Camera is needed for QR scanning in weging/dojo interfaces
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=(self)',
            'microphone=()',
            'geolocation=()',
            'payment=()',
        ]));

        return $response;
    }
}
