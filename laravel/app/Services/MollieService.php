<?php

namespace App\Services;

use App\Exceptions\MollieException;
use App\Models\Toernooi;
use App\Support\CircuitBreaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class MollieService
{
    private string $apiUrl;
    private string $oauthUrl;
    private string $oauthTokenUrl;
    private CircuitBreaker $circuitBreaker;

    // Timeout configuration (seconds)
    private const TIMEOUT = 15;
    private const CONNECT_TIMEOUT = 5;
    private const RETRY_TIMES = 2;
    private const RETRY_SLEEP_MS = 500;

    // Circuit breaker config
    private const CIRCUIT_FAILURE_THRESHOLD = 3;
    private const CIRCUIT_RECOVERY_TIMEOUT = 30;

    public function __construct()
    {
        $this->apiUrl = config('services.mollie.api_url');
        $this->oauthUrl = config('services.mollie.oauth_url');
        $this->oauthTokenUrl = config('services.mollie.oauth_token_url');

        // Circuit breaker prevents cascading failures when Mollie is down
        $this->circuitBreaker = new CircuitBreaker(
            'mollie',
            self::CIRCUIT_FAILURE_THRESHOLD,
            self::CIRCUIT_RECOVERY_TIMEOUT
        );
    }

    /**
     * Check if Mollie service is available (circuit not open).
     */
    public function isAvailable(): bool
    {
        return $this->circuitBreaker->isAvailable();
    }

    /**
     * Get circuit breaker status for monitoring.
     */
    public function getCircuitStatus(): array
    {
        return $this->circuitBreaker->getStatus();
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
     *
     * @throws MollieException
     */
    public function createPayment(Toernooi $toernooi, array $data): object
    {
        $apiKey = $this->getApiKeyForToernooi($toernooi);

        // Add platform fee if in platform mode
        if ($toernooi->mollie_mode === 'platform') {
            $data = $this->addPlatformFeeToDescription($toernooi, $data);
        }

        try {
            $response = $this->makeApiRequest('POST', '/payments', $data, $apiKey);

            Log::info('Mollie payment created', [
                'toernooi_id' => $toernooi->id,
                'payment_id' => $response->id ?? 'unknown',
                'amount' => $data['amount']['value'] ?? 'unknown',
            ]);

            return $response;
        } catch (MollieException $e) {
            $e->log();
            throw MollieException::paymentCreationFailed($e->getMessage(), $toernooi->id);
        }
    }

    /**
     * Get payment status
     *
     * @throws MollieException
     */
    public function getPayment(Toernooi $toernooi, string $paymentId): object
    {
        $apiKey = $this->getApiKeyForToernooi($toernooi);

        try {
            return $this->makeApiRequest('GET', '/payments/' . $paymentId, [], $apiKey);
        } catch (MollieException $e) {
            throw MollieException::paymentNotFound($paymentId);
        }
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
     *
     * @throws MollieException
     */
    public function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->asForm()
                ->post($this->oauthTokenUrl . '/tokens', [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.mollie.client_id'),
                    'client_secret' => config('services.mollie.client_secret'),
                    'code' => $code,
                    'redirect_uri' => config('services.mollie.redirect_uri'),
                ]);

            if (!$response->successful()) {
                throw MollieException::oauthError($response->body());
            }

            Log::info('Mollie OAuth tokens exchanged successfully');

            return $response->json();
        } catch (ConnectionException $e) {
            throw MollieException::timeout('oauth/tokens');
        }
    }

    /**
     * Refresh expired access token
     *
     * @throws MollieException
     */
    public function refreshAccessToken(Toernooi $toernooi): array
    {
        if (!$toernooi->mollie_refresh_token) {
            throw MollieException::tokenExpired($toernooi->id);
        }

        try {
            $refreshToken = $this->decryptToken($toernooi->mollie_refresh_token);

            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->asForm()
                ->post($this->oauthTokenUrl . '/tokens', [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.mollie.client_id'),
                    'client_secret' => config('services.mollie.client_secret'),
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                throw MollieException::tokenExpired($toernooi->id);
            }

            $tokens = $response->json();

            // Update tournament with new tokens
            $toernooi->update([
                'mollie_access_token' => $this->encryptToken($tokens['access_token']),
                'mollie_refresh_token' => $this->encryptToken($tokens['refresh_token']),
                'mollie_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            Log::info('Mollie OAuth token refreshed', ['toernooi_id' => $toernooi->id]);

            return $tokens;
        } catch (ConnectionException $e) {
            throw MollieException::timeout('oauth/tokens');
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw MollieException::tokenExpired($toernooi->id);
        }
    }

    /**
     * Get organization info from Mollie
     *
     * @throws MollieException
     */
    public function getOrganization(string $accessToken): object
    {
        try {
            return $this->makeApiRequest('GET', '/organizations/me', [], $accessToken);
        } catch (MollieException $e) {
            throw MollieException::apiError('/organizations/me', $e->getMessage());
        }
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

    public function validateOAuthState(?string $state): ?int
    {
        if ($state === null) {
            return null;
        }

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

    /*
    |--------------------------------------------------------------------------
    | API Request Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Make an API request to Mollie with circuit breaker, timeout, retry, and error handling.
     *
     * Circuit breaker prevents cascading failures:
     * - After 3 consecutive failures, blocks all requests for 30 seconds
     * - Allows app to fail fast instead of waiting for timeouts
     *
     * @throws MollieException
     */
    private function makeApiRequest(string $method, string $endpoint, array $data, string $apiKey): object
    {
        // Use circuit breaker to prevent cascading failures
        return $this->circuitBreaker->call(
            fn() => $this->executeApiRequest($method, $endpoint, $data, $apiKey),
            fn() => throw MollieException::apiError($endpoint, 'Service temporarily unavailable (circuit open)')
        );
    }

    /**
     * Execute the actual API request with retry logic.
     */
    private function executeApiRequest(string $method, string $endpoint, array $data, string $apiKey): object
    {
        $url = $this->apiUrl . $endpoint;
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::RETRY_TIMES) {
            $attempt++;

            try {
                $request = Http::timeout(self::TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ]);

                $response = match (strtoupper($method)) {
                    'GET' => $request->get($url),
                    'POST' => $request->post($url, $data),
                    'DELETE' => $request->delete($url),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
                };

                if ($response->successful()) {
                    return $response->object();
                }

                // Non-retryable errors (4xx) - don't count towards circuit breaker
                if ($response->clientError()) {
                    throw MollieException::apiError($endpoint, $response->body(), $response->status());
                }

                // Server error (5xx) - retry and count towards circuit
                $lastException = MollieException::apiError($endpoint, $response->body(), $response->status());

            } catch (ConnectionException $e) {
                $lastException = MollieException::timeout($endpoint);
            } catch (MollieException $e) {
                // Client errors (4xx) should not trigger circuit breaker
                if ($e->getCode() === MollieException::ERROR_API && str_contains($e->getMessage(), '4')) {
                    throw $e;
                }
                $lastException = $e;
            }

            // Wait before retry
            if ($attempt < self::RETRY_TIMES) {
                usleep(self::RETRY_SLEEP_MS * 1000);
            }
        }

        throw $lastException ?? MollieException::apiError($endpoint, 'Unknown error');
    }
}
