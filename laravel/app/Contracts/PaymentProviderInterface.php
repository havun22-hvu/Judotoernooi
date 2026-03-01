<?php

namespace App\Contracts;

use App\DTOs\PaymentResult;
use App\Models\Toernooi;

interface PaymentProviderInterface
{
    /**
     * Create a payment for a tournament (coach registration fees).
     */
    public function createPayment(Toernooi $toernooi, array $data): PaymentResult;

    /**
     * Create a platform payment (upgrade fees to JudoToernooi).
     */
    public function createPlatformPayment(array $data): PaymentResult;

    /**
     * Get payment status by provider payment ID.
     */
    public function getPayment(Toernooi $toernooi, string $paymentId): PaymentResult;

    /**
     * Get platform payment status (no toernooi OAuth needed).
     */
    public function getPlatformPayment(string $paymentId): PaymentResult;

    /**
     * Get OAuth authorization URL for connecting organizer's account.
     */
    public function getOAuthAuthorizeUrl(Toernooi $toernooi): string;

    /**
     * Exchange OAuth code for tokens and save to tournament.
     */
    public function handleOAuthCallback(Toernooi $toernooi, string $code): void;

    /**
     * Disconnect provider account from tournament.
     */
    public function disconnect(Toernooi $toernooi): void;

    /**
     * Check if provider service is available.
     */
    public function isAvailable(): bool;

    /**
     * Check if we're in simulation mode (no real API keys).
     */
    public function isSimulationMode(): bool;

    /**
     * Simulate a payment (for staging/local testing).
     */
    public function simulatePayment(array $data): PaymentResult;

    /**
     * Calculate total amount including platform fee.
     */
    public function calculateTotalAmount(Toernooi $toernooi, float $baseAmount): float;

    /**
     * Get provider name identifier.
     */
    public function getName(): string;
}
