<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToernooiTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisator_id',
        'naam',
        'beschrijving',
        'instellingen',
        'max_judokas',
        'inschrijfgeld',
        'betaling_actief',
        'portal_modus',
    ];

    protected $casts = [
        'instellingen' => 'array',
        'betaling_actief' => 'boolean',
        'inschrijfgeld' => 'decimal:2',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    /**
     * Create a template from an existing toernooi
     */
    public static function createFromToernooi(Toernooi $toernooi, string $naam, ?string $beschrijving = null): self
    {
        $organisator = $toernooi->organisatoren()->first();

        if (!$organisator) {
            throw new \Exception('Toernooi has no organisator');
        }

        // Extract all relevant settings
        $instellingen = [
            // Categories
            'gewichtsklassen' => $toernooi->gewichtsklassen,
            'gewichtsklassen_is_preset' => $toernooi->gewichtsklassen_is_preset,
            'eliminatie_gewichtsklassen' => $toernooi->eliminatie_gewichtsklassen,

            // Poule settings
            'max_per_poule' => $toernooi->max_per_poule,
            'wedstrijdtijd' => $toernooi->wedstrijdtijd,
            'wedstrijdtijd_finale' => $toernooi->wedstrijdtijd_finale,
            'pauze_tussen_wedstrijden' => $toernooi->pauze_tussen_wedstrijden,
            'golden_score_tijd' => $toernooi->golden_score_tijd,

            // Weight settings
            'gewicht_tolerantie' => $toernooi->gewicht_tolerantie,

            // Payment settings
            'betaling_actief' => $toernooi->betaling_actief,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'mollie_mode' => $toernooi->mollie_mode,

            // Portal settings
            'portal_modus' => $toernooi->portal_modus,

            // Capacity
            'max_judokas' => $toernooi->max_judokas,
            'judokas_per_coach' => $toernooi->judokas_per_coach,

            // Display settings
            'toon_clubs_publiek' => $toernooi->toon_clubs_publiek,
        ];

        return self::create([
            'organisator_id' => $organisator->id,
            'naam' => $naam,
            'beschrijving' => $beschrijving,
            'instellingen' => $instellingen,
            'max_judokas' => $toernooi->max_judokas,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'betaling_actief' => $toernooi->betaling_actief,
            'portal_modus' => $toernooi->portal_modus,
        ]);
    }

    /**
     * Apply this template to a new toernooi
     */
    public function applyToToernooi(Toernooi $toernooi): void
    {
        $instellingen = $this->instellingen ?? [];

        $toernooi->update([
            'gewichtsklassen' => $instellingen['gewichtsklassen'] ?? null,
            'gewichtsklassen_is_preset' => $instellingen['gewichtsklassen_is_preset'] ?? false,
            'eliminatie_gewichtsklassen' => $instellingen['eliminatie_gewichtsklassen'] ?? null,
            'max_per_poule' => $instellingen['max_per_poule'] ?? 4,
            'wedstrijdtijd' => $instellingen['wedstrijdtijd'] ?? 180,
            'wedstrijdtijd_finale' => $instellingen['wedstrijdtijd_finale'] ?? 240,
            'pauze_tussen_wedstrijden' => $instellingen['pauze_tussen_wedstrijden'] ?? 60,
            'golden_score_tijd' => $instellingen['golden_score_tijd'] ?? null,
            'gewicht_tolerantie' => $instellingen['gewicht_tolerantie'] ?? 0.5,
            'betaling_actief' => $instellingen['betaling_actief'] ?? false,
            'inschrijfgeld' => $instellingen['inschrijfgeld'] ?? null,
            'mollie_mode' => $instellingen['mollie_mode'] ?? 'platform',
            'portal_modus' => $instellingen['portal_modus'] ?? 'volledig',
            'max_judokas' => $instellingen['max_judokas'] ?? null,
            'judokas_per_coach' => $instellingen['judokas_per_coach'] ?? 5,
            'toon_clubs_publiek' => $instellingen['toon_clubs_publiek'] ?? true,
        ]);
    }
}
