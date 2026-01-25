<?php

namespace App\Models\Concerns;

use App\Helpers\BandHelper;

/**
 * Categorisatie Check Methods for Toernooi model.
 *
 * Handles judoka categorization based on leeftijd, geslacht, band, gewicht.
 * BELANGRIJK: Leeftijdscategorieën zijn HARDE grenzen!
 */
trait HasCategorieBepaling
{
    /**
     * Get judoka's die niet in een categorie passen.
     * Dit is een CONFIGURATIE probleem (geen categorie past).
     * Anders dan orphans (wel categorie, geen gewichtsmatch).
     *
     * BELANGRIJK: Leeftijdscategorieën zijn HARDE grenzen!
     * Een 8-jarige mag NOOIT doorvallen naar Heren alleen omdat band niet past.
     */
    public function getNietGecategoriseerdeJudokas(): \Illuminate\Database\Eloquent\Collection
    {
        $config = $this->getAlleGewichtsklassen();
        $toernooiJaar = $this->datum?->year ?? (int) date('Y');

        // Sorteer config op max_leeftijd (jong → oud)
        uasort($config, fn($a, $b) => ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99));

        return $this->judokas()
            ->get()
            ->filter(function ($judoka) use ($config, $toernooiJaar) {
                $leeftijd = $toernooiJaar - $judoka->geboortejaar;
                $geslacht = strtoupper($judoka->geslacht ?? '');
                $band = $judoka->band ?? '';

                // Vind de eerste (laagste) max_leeftijd waar judoka in past
                $eersteMatchLeeftijd = null;
                foreach ($config as $cat) {
                    $maxLeeftijd = $cat['max_leeftijd'] ?? 99;
                    if ($leeftijd <= $maxLeeftijd) {
                        $eersteMatchLeeftijd = $maxLeeftijd;
                        break;
                    }
                }

                // Als geen leeftijdsmatch → niet gecategoriseerd
                if ($eersteMatchLeeftijd === null) {
                    return true;
                }

                // Check ALLEEN categorieën met deze max_leeftijd (niet doorvallen!)
                foreach ($config as $cat) {
                    $maxLeeftijd = $cat['max_leeftijd'] ?? 99;

                    // Skip categorieën met andere max_leeftijd
                    if ($maxLeeftijd !== $eersteMatchLeeftijd) continue;

                    $catGeslacht = strtoupper($cat['geslacht'] ?? 'gemengd');
                    if ($catGeslacht === 'MEISJES') $catGeslacht = 'V';
                    if ($catGeslacht === 'JONGENS') $catGeslacht = 'M';

                    // Check geslacht
                    if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) continue;

                    // Check band filter
                    $bandFilter = $cat['band_filter'] ?? '';
                    if (!empty($bandFilter) && !BandHelper::pastInFilter($band, $bandFilter)) continue;

                    // Categorie gevonden - judoka is gecategoriseerd
                    return false;
                }

                // Geen categorie met juiste leeftijd past → NIET GECATEGORISEERD
                return true;
            });
    }

    /**
     * Tel aantal niet-gecategoriseerde judoka's (cached voor performance).
     */
    public function countNietGecategoriseerd(): int
    {
        return $this->getNietGecategoriseerdeJudokas()->count();
    }

    /**
     * Get sort value for a leeftijdsklasse label (youngest first).
     * Uses max_leeftijd from config, falls back to U-number parsing.
     */
    public function getLeeftijdsklasseSortValue(string $leeftijdsklasse): int
    {
        $config = $this->getAlleGewichtsklassen();

        // Find category by label in config
        foreach ($config as $cat) {
            $label = $cat['label'] ?? '';
            if ($label === $leeftijdsklasse) {
                return (int) ($cat['max_leeftijd'] ?? 99);
            }
        }

        // Fallback: parse U-number (U11 → 11)
        if (preg_match('/U(\d+)/', $leeftijdsklasse, $matches)) {
            return (int) $matches[1];
        }

        return 99;
    }

    /**
     * Bepaal leeftijdsklasse label op basis van toernooi config (NIET hardcoded enum).
     * Zoekt de eerste categorie waar judoka in past qua leeftijd, geslacht en band.
     *
     * BELANGRIJK: Een 6-jarige in U7 mag NOOIT doorvallen naar U11!
     * Check alleen categorieën met de eerste leeftijdsmatch.
     *
     * @return string|null Label van de categorie, of null als geen match
     */
    public function bepaalLeeftijdsklasse(int $leeftijd, string $geslacht, ?string $band = null): ?string
    {
        $config = $this->getAlleGewichtsklassen();
        if (empty($config)) {
            return null;
        }

        $geslacht = strtoupper($geslacht);

        // Config is al gesorteerd op max_leeftijd (jong → oud) door getAlleGewichtsklassen()

        // STAP 1: Vind de eerste (laagste) max_leeftijd waar judoka in past
        $eersteMatchLeeftijd = null;
        foreach ($config as $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);
            if ($leeftijd <= $maxLeeftijd) {
                $eersteMatchLeeftijd = $maxLeeftijd;
                break;
            }
        }

        // Geen leeftijdsmatch → niet gecategoriseerd
        if ($eersteMatchLeeftijd === null) {
            return null;
        }

        // STAP 2: Check ALLEEN categorieën met deze max_leeftijd
        foreach ($config as $key => $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);

            // Skip categorieën met andere max_leeftijd
            if ($maxLeeftijd !== $eersteMatchLeeftijd) {
                continue;
            }

            // Geslacht moet passen (gemengd past altijd)
            $catGeslacht = strtoupper($cat['geslacht'] ?? 'GEMENGD');
            if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) {
                continue;
            }

            // Band filter moet passen (als ingesteld)
            $bandFilter = $cat['band_filter'] ?? '';
            if (!empty($bandFilter) && !empty($band) && !BandHelper::pastInFilter($band, $bandFilter)) {
                continue;
            }

            // Match gevonden
            return $cat['label'] ?? $key;
        }

        return null; // Geen categorie past binnen de leeftijdscategorie
    }

    /**
     * Bepaal gewichtsklasse op basis van gewicht en toernooi config.
     *
     * BELANGRIJK: Een 6-jarige in U7 mag NOOIT doorvallen naar U11!
     * Check alleen categorieën met de eerste leeftijdsmatch.
     *
     * @return string|null Gewichtsklasse (bijv. "-38" of "+73"), of null als geen match
     */
    public function bepaalGewichtsklasse(float $gewicht, int $leeftijd, string $geslacht, ?string $band = null): ?string
    {
        $config = $this->getAlleGewichtsklassen();
        if (empty($config)) {
            return null;
        }

        $geslacht = strtoupper($geslacht);
        $tolerantie = $this->gewicht_tolerantie ?? 0.5;

        // Config is al gesorteerd op max_leeftijd (jong → oud) door getAlleGewichtsklassen()

        // STAP 1: Vind de eerste (laagste) max_leeftijd waar judoka in past
        $eersteMatchLeeftijd = null;
        foreach ($config as $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);
            if ($leeftijd <= $maxLeeftijd) {
                $eersteMatchLeeftijd = $maxLeeftijd;
                break;
            }
        }

        // Geen leeftijdsmatch → niet gecategoriseerd
        if ($eersteMatchLeeftijd === null) {
            return null;
        }

        // STAP 2: Check ALLEEN categorieën met deze max_leeftijd
        foreach ($config as $key => $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);

            // Skip categorieën met andere max_leeftijd
            if ($maxLeeftijd !== $eersteMatchLeeftijd) {
                continue;
            }

            $catGeslacht = strtoupper($cat['geslacht'] ?? 'GEMENGD');
            if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) {
                continue;
            }

            $bandFilter = $cat['band_filter'] ?? '';
            if (!empty($bandFilter) && !empty($band) && !BandHelper::pastInFilter($band, $bandFilter)) {
                continue;
            }

            // Categorie gevonden - bepaal gewichtsklasse
            $gewichten = $cat['gewichten'] ?? [];
            if (empty($gewichten)) {
                return null; // Dynamische categorie, geen vaste klassen
            }

            foreach ($gewichten as $klasse) {
                $klasseInt = (int) preg_replace('/[^0-9-]/', '', $klasse);
                if ($klasseInt > 0) {
                    // Plus categorie (laatste)
                    return "+{$klasseInt}";
                } else {
                    // Minus categorie
                    $limiet = abs($klasseInt);
                    if ($gewicht <= $limiet + $tolerantie) {
                        return "-{$limiet}";
                    }
                }
            }

            // Geen gewichtsklasse past, fallback naar plus
            $laatsteKlasse = end($gewichten);
            $laatsteInt = abs((int) preg_replace('/[^0-9]/', '', $laatsteKlasse));
            return "+{$laatsteInt}";
        }

        return null; // Geen categorie past binnen de leeftijdscategorie
    }
}
