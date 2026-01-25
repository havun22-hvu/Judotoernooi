<?php

namespace App\Models\Concerns;

/**
 * Mollie Payment Methods for Toernooi model.
 *
 * Handles Mollie Connect (organizer's own Mollie) and platform payments.
 */
trait HasMolliePayments
{
    /**
     * Check if tournament uses Mollie Connect (organizer's own Mollie)
     */
    public function usesMollieConnect(): bool
    {
        return $this->mollie_mode === 'connect' && $this->mollie_onboarded;
    }

    /**
     * Check if tournament uses platform mode (JudoToernooi's Mollie)
     */
    public function usesPlatformPayments(): bool
    {
        return $this->mollie_mode === 'platform' || !$this->mollie_onboarded;
    }

    /**
     * Check if Mollie is properly configured for this tournament
     */
    public function hasMollieConfigured(): bool
    {
        if ($this->mollie_mode === 'connect') {
            return $this->mollie_onboarded && !empty($this->mollie_access_token);
        }

        // Platform mode: check if platform keys are configured
        return !empty(config('services.mollie.platform_key'))
            || !empty(config('services.mollie.platform_test_key'));
    }

    /**
     * Get the platform fee for this tournament
     */
    public function getPlatformFee(): float
    {
        if ($this->mollie_mode !== 'platform') {
            return 0;
        }

        return $this->platform_toeslag ?? config('services.mollie.default_platform_fee', 0.50);
    }

    /**
     * Calculate total payment amount including platform fee
     */
    public function calculatePaymentAmount(int $aantalJudokas): float
    {
        $baseAmount = $aantalJudokas * ($this->inschrijfgeld ?? 0);

        if ($this->mollie_mode !== 'platform') {
            return $baseAmount;
        }

        $fee = $this->getPlatformFee();

        if ($this->platform_toeslag_percentage) {
            return $baseAmount * (1 + ($fee / 100));
        }

        return $baseAmount + $fee;
    }

    /**
     * Get Mollie status display text
     */
    public function getMollieStatusText(): string
    {
        if (!$this->betaling_actief) {
            return 'Betalingen uitgeschakeld';
        }

        if ($this->mollie_mode === 'connect') {
            return $this->mollie_onboarded
                ? 'Gekoppeld: ' . ($this->mollie_organization_name ?? 'Eigen Mollie')
                : 'Niet gekoppeld';
        }

        return 'Via JudoToernooi platform';
    }
}
