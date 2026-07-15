---
title: Coachkaarten: fasen & genereren
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coachkaarten: fasen & genereren

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Coachkaarten

Coaches krijgen toegangskaarten voor de dojo. Het aantal wordt in twee fasen bepaald:

### Fase 1: Tijdens Inschrijving

**Altijd 1 coachkaart per budoschool** - ongeacht aantal judoka's.

- Bij eerste judoka inschrijving → 1 coachkaart aangemaakt
- Oude/overtollige coachkaarten worden automatisch verwijderd
- Dit voorkomt verwarring en zorgt dat clubs niet te veel kaarten krijgen

### Fase 2: Na Einde Voorbereiding

Organisator klikt **"Genereer Coachkaarten"** na blokverdeling. Dan wordt berekend:

```
Formule: ceil(max_judokas_in_grootste_blok / judokas_per_coach)

Voorbeeld: Club met 11 judoka's in grootste blok, 5 per coach
→ ceil(11 / 5) = 3 coachkaarten

┌────────────────────────────┬─────────┐
│ Max judoka's in één blok   │ Kaarten │
├────────────────────────────┼─────────┤
│ 1-5                        │ 1       │
│ 6-10                       │ 2       │
│ 11-15                      │ 3       │
│ 16-20                      │ 4       │
└────────────────────────────┴─────────┘
```

**Instelling:** `judokas_per_coach` in toernooi instellingen (default: 5)

### Waarom per blok?

Een coach hoeft alleen aanwezig te zijn wanneer zijn judoka's wedstrijden hebben. Als een club 15 judoka's heeft verdeeld over 3 blokken (8, 4, 3), dan zijn er maximaal 8 judoka's tegelijk actief → 2 coachkaarten nodig.

### Implementatie

**Model:** `app/Models/Club.php`
```php
/**
 * @param bool $forceCalculate - true = bereken op basis van blokken (na voorbereiding)
 *                               false = altijd 1 (tijdens inschrijving)
 */
public function berekenAantalCoachKaarten(Toernooi $toernooi, bool $forceCalculate = false): int
{
    $judokas = $this->judokas()->where('toernooi_id', $toernooi->id)->with('poules.blok')->get();

    if ($judokas->isEmpty()) {
        return 0;
    }

    // During inschrijving: always 1 card
    if (!$forceCalculate) {
        return 1;
    }

    // After voorbereiding: calculate based on largest block
    $perCoach = $toernooi->judokas_per_coach ?? 5;
    $judokasPerBlok = [];
    foreach ($judokas as $judoka) {
        foreach ($judoka->poules as $poule) {
            if ($poule->blok_id) {
                $judokasPerBlok[$poule->blok_id] = ($judokasPerBlok[$poule->blok_id] ?? 0) + 1;
            }
        }
    }

    if (empty($judokasPerBlok)) {
        return 1; // Fallback if no blokken assigned
    }

    return max(1, (int) ceil(max($judokasPerBlok) / $perCoach));
}
```

### Genereren coachkaarten

**Controller:** `CoachKaartController@genereer`

**Wanneer:** Organisator klikt na blokverdeling op "Genereer Coachkaarten"

**Wat gebeurt er:**
1. Bereken per club het benodigde aantal op basis van grootste blok (`forceCalculate=true`)
2. Maak ontbrekende kaarten aan
3. Verwijder overtollige (niet-gescande) kaarten
4. Markeer voorbereiding als afgerond (`voorbereiding_klaar_op = now()`)

```php
foreach ($clubs as $club) {
    // forceCalculate=true: calculate based on largest block
    $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi, true);
    // ... create/delete cards
}
$toernooi->update(['voorbereiding_klaar_op' => now()]);
```

### Coachkaart activatie & device binding

**Vereisten voor geldige coachkaart:**
1. Geactiveerd op een device (device binding)
2. Pasfoto geüpload
3. Naam ingevuld

**QR-code alleen zichtbaar op gebonden device** - voorkomt screenshot-deling.

