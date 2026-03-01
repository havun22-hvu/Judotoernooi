<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\PaymentResult;
use App\Models\Toernooi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripePaymentProvider implements PaymentProviderInterface
{
    private ?StripeClient $stripe = null;

    private function getStripeClient(): StripeClient
    {
        if (!$this->stripe) {
            $this->stripe = new StripeClient(config('services.stripe.secret'));
        }

        return $this->stripe;
    }

    /**
     * Get Stripe client for a connected account's operations.
     */
    private function getApiKeyForToernooi(Toernooi $toernooi): ?string
    {
        if ($toernooi->mollie_mode === 'connect' && $toernooi->stripe_access_token) {
            return Crypt::decryptString($toernooi->stripe_access_token);
        }

        return null;
    }

    public function createPayment(Toernooi $toernooi, array $data): PaymentResult
    {
        $stripe = $this->getStripeClient();
        $amountInCents = (int) round(((float) $data['amount']['value']) * 100);

        $sessionParams = [
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($data['amount']['currency'] ?? 'eur'),
                    'product_data' => [
                        'name' => $data['description'] ?? 'Inschrijving toernooi',
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => $data['redirectUrl'],
            'cancel_url' => $data['cancelUrl'] ?? $data['redirectUrl'],
            'metadata' => $data['metadata'] ?? [],
        ];

        // Connect mode: payment goes to organizer's Stripe account
        if ($toernooi->mollie_mode === 'connect' && $toernooi->stripe_account_id) {
            $platformFee = $this->getPlatformFeeAmount($toernooi, $amountInCents);

            $sessionParams['payment_intent_data'] = [
                'application_fee_amount' => $platformFee,
                'transfer_data' => [
                    'destination' => $toernooi->stripe_account_id,
                ],
            ];
        }

        $session = $stripe->checkout->sessions->create($sessionParams);

        Log::info('Stripe checkout session created', [
            'toernooi_id' => $toernooi->id,
            'session_id' => $session->id,
            'amount' => $data['amount']['value'],
        ]);

        return PaymentResult::fromStripe($session);
    }

    public function createPlatformPayment(array $data): PaymentResult
    {
        $stripe = $this->getStripeClient();
        $amountInCents = (int) round(((float) $data['amount']['value']) * 100);

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($data['amount']['currency'] ?? 'eur'),
                    'product_data' => [
                        'name' => $data['description'] ?? 'JudoToernooi Upgrade',
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => $data['redirectUrl'],
            'cancel_url' => $data['cancelUrl'] ?? $data['redirectUrl'],
            'metadata' => $data['metadata'] ?? [],
        ]);

        Log::info('Stripe platform checkout session created', [
            'session_id' => $session->id,
            'amount' => $data['amount']['value'],
        ]);

        return PaymentResult::fromStripe($session);
    }

    public function getPayment(Toernooi $toernooi, string $paymentId): PaymentResult
    {
        $stripe = $this->getStripeClient();
        $session = $stripe->checkout->sessions->retrieve($paymentId);

        return PaymentResult::fromStripe($session);
    }

    public function getPlatformPayment(string $paymentId): PaymentResult
    {
        $stripe = $this->getStripeClient();
        $session = $stripe->checkout->sessions->retrieve($paymentId);

        return PaymentResult::fromStripe($session);
    }

    public function getOAuthAuthorizeUrl(Toernooi $toernooi): string
    {
        $state = $this->generateOAuthState($toernooi);

        $params = [
            'client_id' => config('services.stripe.client_id'),
            'response_type' => 'code',
            'scope' => 'read_write',
            'redirect_uri' => route('stripe.callback'),
            'state' => $state,
            'stripe_user[email]' => $toernooi->organisator->email ?? '',
        ];

        return 'https://connect.stripe.com/oauth/authorize?' . http_build_query($params);
    }

    public function handleOAuthCallback(Toernooi $toernooi, string $code): void
    {
        $stripe = $this->getStripeClient();

        $response = $stripe->oauth->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $toernooi->update([
            'mollie_mode' => 'connect',
            'stripe_account_id' => $response->stripe_user_id,
            'stripe_access_token' => Crypt::encryptString($response->access_token),
            'stripe_refresh_token' => $response->refresh_token
                ? Crypt::encryptString($response->refresh_token)
                : null,
            'stripe_publishable_key' => $response->stripe_publishable_key ?? null,
        ]);

        Log::info('Stripe account connected', [
            'toernooi_id' => $toernooi->id,
            'stripe_account_id' => $response->stripe_user_id,
        ]);
    }

    public function disconnect(Toernooi $toernooi): void
    {
        // Deauthorize the connected account
        if ($toernooi->stripe_account_id) {
            try {
                $stripe = $this->getStripeClient();
                $stripe->oauth->deauthorize([
                    'client_id' => config('services.stripe.client_id'),
                    'stripe_user_id' => $toernooi->stripe_account_id,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to deauthorize Stripe account', [
                    'toernooi_id' => $toernooi->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $toernooi->update([
            'mollie_mode' => 'platform',
            'stripe_account_id' => null,
            'stripe_access_token' => null,
            'stripe_refresh_token' => null,
            'stripe_publishable_key' => null,
        ]);
    }

    public function isAvailable(): bool
    {
        return !empty(config('services.stripe.secret'));
    }

    public function isSimulationMode(): bool
    {
        return config('app.env') !== 'production' && !config('services.stripe.secret');
    }

    public function simulatePayment(array $data): PaymentResult
    {
        $paymentId = 'cs_simulated_' . uniqid();

        return new PaymentResult(
            id: $paymentId,
            status: 'open',
            checkoutUrl: route('betaling.simulate', ['payment_id' => $paymentId]),
            amount: $data['amount']['value'] ?? null,
            currency: $data['amount']['currency'] ?? 'EUR',
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function calculateTotalAmount(Toernooi $toernooi, float $baseAmount): float
    {
        if ($toernooi->mollie_mode !== 'platform') {
            return $baseAmount;
        }

        $toeslag = $toernooi->platform_toeslag ?? 0.50;

        if ($toernooi->platform_toeslag_percentage) {
            return $baseAmount * (1 + ($toeslag / 100));
        }

        return $baseAmount + $toeslag;
    }

    public function getName(): string
    {
        return 'stripe';
    }

    /**
     * Calculate platform fee in cents for Connect payments.
     */
    private function getPlatformFeeAmount(Toernooi $toernooi, int $amountInCents): int
    {
        $toeslag = $toernooi->platform_toeslag ?? 0.50;

        if ($toernooi->platform_toeslag_percentage) {
            return (int) round($amountInCents * ($toeslag / 100));
        }

        return (int) round($toeslag * 100);
    }

    private function generateOAuthState(Toernooi $toernooi): string
    {
        return base64_encode(json_encode([
            'toernooi_id' => $toernooi->id,
            'provider' => 'stripe',
            'timestamp' => time(),
            'hash' => hash_hmac('sha256', $toernooi->id . ':stripe', config('app.key')),
        ]));
    }

    /**
     * Validate OAuth state and return toernooi ID.
     */
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

            $expectedHash = hash_hmac('sha256', $data['toernooi_id'] . ':stripe', config('app.key'));

            if (!hash_equals($expectedHash, $data['hash'])) {
                return null;
            }

            if (time() - ($data['timestamp'] ?? 0) > 3600) {
                return null;
            }

            return (int) $data['toernooi_id'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify Stripe webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }
}
