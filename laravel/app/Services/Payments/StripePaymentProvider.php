<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\PaymentResult;
use App\Models\Toernooi;
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
     * Check if tournament has a connected Stripe account.
     */
    private function hasConnectedAccount(Toernooi $toernooi): bool
    {
        return $toernooi->payment_provider === 'stripe' && !empty($toernooi->stripe_account_id);
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
        if ($this->hasConnectedAccount($toernooi)) {
            $sessionParams['payment_intent_data'] = [
                'transfer_data' => [
                    'destination' => $toernooi->stripe_account_id,
                ],
            ];

            // No application fee — organizer receives full amount
            // JudoToernooi earns nothing on registration fees
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

    /**
     * Create a connected account and return the Account Link onboarding URL.
     * Replaces legacy OAuth flow — no ca_... Client ID needed.
     */
    public function getOAuthAuthorizeUrl(Toernooi $toernooi): string
    {
        $stripe = $this->getStripeClient();

        // Create connected account if not exists
        if (!$toernooi->stripe_account_id) {
            $account = $stripe->accounts->create([
                'type' => 'standard',
                'email' => $toernooi->organisator->email ?? null,
                'metadata' => [
                    'toernooi_id' => $toernooi->id,
                ],
            ]);

            $toernooi->update([
                'stripe_account_id' => $account->id,
                'payment_provider' => 'stripe',
            ]);

            Log::info('Stripe connected account created', [
                'toernooi_id' => $toernooi->id,
                'account_id' => $account->id,
            ]);
        }

        // Create Account Link for onboarding
        $accountLink = $stripe->accountLinks->create([
            'account' => $toernooi->stripe_account_id,
            'refresh_url' => route('stripe.authorize', $toernooi->routeParams()),
            'return_url' => route('stripe.callback') . '?toernooi_id=' . $toernooi->id . '&hash=' . $this->generateCallbackHash($toernooi),
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    /**
     * Verify connected account is fully onboarded after return from Stripe.
     */
    public function handleOAuthCallback(Toernooi $toernooi, string $code): void
    {
        $stripe = $this->getStripeClient();
        $account = $stripe->accounts->retrieve($toernooi->stripe_account_id);

        if ($account->charges_enabled && $account->payouts_enabled) {
            Log::info('Stripe account fully onboarded', [
                'toernooi_id' => $toernooi->id,
                'stripe_account_id' => $account->id,
            ]);
        } else {
            Log::warning('Stripe account not fully onboarded yet', [
                'toernooi_id' => $toernooi->id,
                'stripe_account_id' => $account->id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ]);
        }
    }

    public function disconnect(Toernooi $toernooi): void
    {
        if ($toernooi->stripe_account_id) {
            try {
                $stripe = $this->getStripeClient();
                $stripe->accounts->delete($toernooi->stripe_account_id);
            } catch (\Exception $e) {
                Log::warning('Failed to delete Stripe connected account', [
                    'toernooi_id' => $toernooi->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $toernooi->update([
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
        // Connect mode: no platform fee, organizer gets everything
        if ($this->hasConnectedAccount($toernooi)) {
            return $baseAmount;
        }

        // Platform mode: add toeslag
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
     * Generate HMAC hash for callback URL verification.
     */
    public function generateCallbackHash(Toernooi $toernooi): string
    {
        return hash_hmac('sha256', $toernooi->id . ':stripe:' . date('Y-m-d'), config('app.key'));
    }

    /**
     * Validate callback hash and return toernooi ID.
     */
    public function validateCallbackHash(?int $toernooiId, ?string $hash): bool
    {
        if (!$toernooiId || !$hash) {
            return false;
        }

        $expected = hash_hmac('sha256', $toernooiId . ':stripe:' . date('Y-m-d'), config('app.key'));

        return hash_equals($expected, $hash);
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
