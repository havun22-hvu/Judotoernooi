<?php

namespace App\Models\Concerns;

/**
 * Portaal Modus Methods for Toernooi model.
 *
 * Handles portal access modes: volledig, mutaties, uit
 */
trait HasPortaalModus
{
    /**
     * Check if portal allows new registrations (volledig mode)
     */
    public function portaalMagInschrijven(): bool
    {
        return $this->portaal_modus === 'volledig';
    }

    /**
     * Check if portal allows mutations/edits (mutaties or volledig mode)
     */
    public function portaalMagWijzigen(): bool
    {
        return in_array($this->portaal_modus, ['mutaties', 'volledig']);
    }

    /**
     * Check if portal is completely disabled (uit mode)
     */
    public function portaalIsUit(): bool
    {
        return $this->portaal_modus === 'uit' || empty($this->portaal_modus);
    }

    /**
     * Get portaal modus display text
     */
    public function getPortaalModusText(): string
    {
        return match($this->portaal_modus) {
            'volledig' => 'Volledig (inschrijven + wijzigen)',
            'mutaties' => 'Alleen mutaties (wijzigen)',
            default => 'Uit (alleen bekijken)',
        };
    }
}
