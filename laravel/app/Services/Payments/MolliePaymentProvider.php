<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\PaymentResult;
use App\Models\Toernooi;
use App\Services\MollieService;

class MolliePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private MollieService $mollieService
    ) {}

    public function createPayment(Toernooi $toernooi, array $data): PaymentResult
    {
        $this->mollieService->ensureValidToken($toernooi);
        $payment = $this->mollieService->createPayment($toernooi, $data);

        return PaymentResult::fromMollie($payment);
    }

    public function createPlatformPayment(array $data): PaymentResult
    {
        $apiKey = $this->mollieService->getPlatformApiKey();

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post(config('services.mollie.api_url') . '/payments', $data);

        if (!$response->successful()) {
            throw \App\Exceptions\MollieException::apiError('/payments', $response->body(), $response->status());
        }

        $result = $response->object();

        if (!isset($result->id) || !isset($result->_links->checkout->href)) {
            throw \App\Exceptions\MollieException::invalidResponse('/payments', json_encode($result) ?: 'empty');
        }

        return PaymentResult::fromMollie($result);
    }

    public function getPayment(Toernooi $toernooi, string $paymentId): PaymentResult
    {
        $this->mollieService->ensureValidToken($toernooi);
        $payment = $this->mollieService->getPayment($toernooi, $paymentId);

        return PaymentResult::fromMollie($payment);
    }

    public function getPlatformPayment(string $paymentId): PaymentResult
    {
        $apiKey = $this->mollieService->getPlatformApiKey();

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get(config('services.mollie.api_url') . '/payments/' . $paymentId);

        if (!$response->successful()) {
            throw \App\Exceptions\MollieException::apiError('/payments/' . $paymentId, $response->body(), $response->status());
        }

        return PaymentResult::fromMollie($response->object());
    }

    public function getOAuthAuthorizeUrl(Toernooi $toernooi): string
    {
        return $this->mollieService->getOAuthAuthorizeUrl($toernooi);
    }

    public function handleOAuthCallback(Toernooi $toernooi, string $code): void
    {
        $tokens = $this->mollieService->exchangeCodeForTokens($code);
        $this->mollieService->saveTokensToToernooi($toernooi, $tokens);
    }

    public function disconnect(Toernooi $toernooi): void
    {
        $this->mollieService->disconnectFromToernooi($toernooi);
    }

    public function isAvailable(): bool
    {
        return $this->mollieService->isAvailable();
    }

    public function isSimulationMode(): bool
    {
        return $this->mollieService->isSimulationMode();
    }

    public function simulatePayment(array $data): PaymentResult
    {
        $payment = $this->mollieService->simulatePayment($data);

        return PaymentResult::fromMollie($payment);
    }

    public function calculateTotalAmount(Toernooi $toernooi, float $baseAmount): float
    {
        return $this->mollieService->calculateTotalAmount($toernooi, $baseAmount);
    }

    public function getName(): string
    {
        return 'mollie';
    }
}
