<?php

namespace App\Services;

use App\Models\Toernooi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class MollieService
{
    private string $apiUrl;
    private string $oauthUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.mollie.api_url');
        $this->oauthUrl = config('services.mollie.oauth_url');
    }

    /*
    |--------------------------------------------------------------------------
    | API Key Resolution
    |--------------------------------------------------------------------------
    */

    /**
     * Get the appropriate API key for a tournament
     * - Connect mode: use tournament's OAuth access token
     * - Platform mode: use platform's API key
     */
    public function getApiKeyForToernooi(Toernooi $toernooi): string
    {
        if ($toernooi->mollie_mode === 'connect' && $toernooi->mollie_access_token) {
            return $this->decryptToken($toernooi->mollie_access_token);
        }

        // Platform mode: use platform keys
        return $this->getPlatformApiKey();
    }

    /**
     * Get platform API key (test or live based on environment)
     */
    public function getPlatformApiKey(): string
    {
        return app()->environment('production')
            ? config('services.mollie.platform_key')
            : config('services.mollie.platform_test_key');
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Creation
    |--------------------------------------------------------------------------
    */

    /**
     * Create a payment for a tournament
     */
    public function createPayment(Toernooi $toernooi, array $data): object
    {
        $apiKey = $this->getApiKeyForToernooi($toernooi);

        // Add platform fee if in platform mode
        if ($toernooi->mollie_mode === 'platform') {
            $data = $this->addPlatformFeeToDescription($toernooi, $data);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/payments', $data);

        if (!$response->successful()) {
            Log::error('Mollie payment creation failed', [
                'toernooi_id' => $toernooi->id,
                'mode' => $toernooi->mollie_mode,
                'error' => $response->body(),
            ]);
            throw new \Exception('Mollie API error: ' . $response->body());
        }

        return $response->object();
    }

    /**
     * Get payment status
     */
    public function getPayment(Toernooi $toernooi, string $paymentId): object
    {
        $apiKey = $this->getApiKeyForToernooi($toernooi);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get($this->apiUrl . '/payments/' . $paymentId);

        if (!$response->successful()) {
            throw new \Exception('Mollie API error: ' . $response->body());
        }

        return $response->object();
    }

    /**
     * Add platform fee info to payment description
     */
    private function addPlatformFeeToDescription(Toernooi $toernooi, array $data): array
    {
        $toeslag = $toernooi->platform_toeslag ?? config('services.mollie.default_platform_fee');

        if ($toeslag > 0) {
            $data['description'] .= ' (incl. €' . number_format($toeslag, 2, ',', '') . ' platformkosten)';
        }

        return $data;
    }

    /**
     * Calculate total amount including platform fee
     */
    public function calculateTotalAmount(Toernooi $toernooi, float $baseAmount): float
    {
        if ($toernooi->mollie_mode !== 'platform') {
            return $baseAmount;
        }

        $toeslag = $toernooi->platform_toeslag ?? config('services.mollie.default_platform_fee');

        if ($toernooi->platform_toeslag_percentage) {
            return $baseAmount * (1 + ($toeslag / 100));
        }

        return $baseAmount + $toeslag;
    }

    /*
    |--------------------------------------------------------------------------
    | OAuth Flow (Mollie Connect)
    |--------------------------------------------------------------------------
    */

    /**
     * Generate OAuth authorization URL for tournament organizer
     */
    public function getOAuthAuthorizeUrl(Toernooi $toernooi): string
    {
        $params = [
            'client_id' => config('services.mollie.client_id'),
            'redirect_uri' => config('services.mollie.redirect_uri'),
            'state' => $this->generateOAuthState($toernooi),
            'scope' => 'payments.read payments.write organizations.read',
            'response_type' => 'code',
            'approval_prompt' => 'auto',
        ];

        return $this->oauthUrl . '/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access tokens
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->post($this->oauthUrl . '/tokens', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.mollie.client_id'),
            'client_secret' => config('services.mollie.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.mollie.redirect_uri'),
        ]);

        if (!$response->successful()) {
            Log::error('Mollie OAuth token exchange failed', [
                'error' => $response->body(),
            ]);
            throw new \Exception('OAuth token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh expired access token
     */
    public function refreshAccessToken(Toernooi $toernooi): array
    {
        $refreshToken = $this->decryptToken($toernooi->mollie_refresh_token);

        $response = Http::asForm()->post($this->oauthUrl . '/tokens', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.mollie.client_id'),
            'client_secret' => config('services.mollie.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            Log::error('Mollie OAuth token refresh failed', [
                'toernooi_id' => $toernooi->id,
                'error' => $response->body(),
            ]);
            throw new \Exception('OAuth token refresh failed: ' . $response->body());
        }

        $tokens = $response->json();

        // Update tournament with new tokens
        $toernooi->update([
            'mollie_access_token' => $this->encryptToken($tokens['access_token']),
            'mollie_refresh_token' => $this->encryptToken($tokens['refresh_token']),
            'mollie_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens;
    }

    /**
     * Get organization info from Mollie
     */
    public function getOrganization(string $accessToken): object
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($this->apiUrl . '/organizations/me');

        if (!$response->successful()) {
            throw new \Exception('Failed to get organization: ' . $response->body());
        }

        return $response->object();
    }

    /**
     * Save OAuth tokens to tournament
     */
    public function saveTokensToToernooi(Toernooi $toernooi, array $tokens): void
    {
        // Get organization name
        $org = $this->getOrganization($tokens['access_token']);

        $toernooi->update([
            'mollie_mode' => 'connect',
            'mollie_access_token' => $this->encryptToken($tokens['access_token']),
            'mollie_refresh_token' => $this->encryptToken($tokens['refresh_token']),
            'mollie_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'mollie_onboarded' => true,
            'mollie_organization_name' => $org->name ?? null,
        ]);
    }

    /**
     * Disconnect Mollie account from tournament
     */
    public function disconnectFromToernooi(Toernooi $toernooi): void
    {
        $toernooi->update([
            'mollie_mode' => 'platform',
            'mollie_account_id' => null,
            'mollie_access_token' => null,
            'mollie_refresh_token' => null,
            'mollie_token_expires_at' => null,
            'mollie_onboarded' => false,
            'mollie_organization_name' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Token Management
    |--------------------------------------------------------------------------
    */

    /**
     * Check if tokens need refresh and refresh if needed
     */
    public function ensureValidToken(Toernooi $toernooi): void
    {
        if ($toernooi->mollie_mode !== 'connect') {
            return;
        }

        // Refresh if expires within 5 minutes
        if ($toernooi->mollie_token_expires_at && $toernooi->mollie_token_expires_at->lt(now()->addMinutes(5))) {
            $this->refreshAccessToken($toernooi);
        }
    }

    private function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    private function decryptToken(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    private function generateOAuthState(Toernooi $toernooi): string
    {
        return base64_encode(json_encode([
            'toernooi_id' => $toernooi->id,
            'timestamp' => time(),
            'hash' => hash_hmac('sha256', $toernooi->id, config('app.key')),
        ]));
    }

    public function validateOAuthState(string $state): ?int
    {
        try {
            $data = json_decode(base64_decode($state), true);

            if (!$data || !isset($data['toernooi_id'], $data['hash'])) {
                return null;
            }

            $expectedHash = hash_hmac('sha256', $data['toernooi_id'], config('app.key'));

            if (!hash_equals($expectedHash, $data['hash'])) {
                return null;
            }

            // State expired after 1 hour
            if (time() - ($data['timestamp'] ?? 0) > 3600) {
                return null;
            }

            return (int) $data['toernooi_id'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simulation Mode (Staging)
    |--------------------------------------------------------------------------
    */

    /**
     * Check if we're in simulation mode
     */
    public function isSimulationMode(): bool
    {
        return config('app.env') !== 'production' && !config('services.mollie.platform_test_key');
    }

    /**
     * Simulate a payment response (for staging without Mollie keys)
     */
    public function simulatePayment(array $data): object
    {
        $paymentId = 'tr_simulated_' . uniqid();

        return (object) [
            'id' => $paymentId,
            'status' => 'open',
            'amount' => $data['amount'],
            'description' => $data['description'],
            'redirectUrl' => $data['redirectUrl'],
            'webhookUrl' => $data['webhookUrl'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            '_links' => (object) [
                'checkout' => (object) [
                    'href' => route('betaling.simulate', ['payment_id' => $paymentId]),
                ],
            ],
        ];
    }

    /**
     * Simulate payment status update
     */
    public function simulatePaymentStatus(string $paymentId, string $status): object
    {
        return (object) [
            'id' => $paymentId,
            'status' => $status,
        ];
    }
}
