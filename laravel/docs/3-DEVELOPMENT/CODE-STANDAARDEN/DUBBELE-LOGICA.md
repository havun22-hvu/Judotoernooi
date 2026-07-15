---
title: Dubbele logica voorkomen & wanneer string-checks wél mogen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Dubbele logica voorkomen & wanneer string-checks wél mogen

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 6. Dubbele Logica Voorkomen

### Regel

Als dezelfde logica op meerdere plekken voorkomt, maak er een MODEL METHODE van.

### Voorbeeld: Effectief Gewicht

```php
// ❌ FOUT - zelfde logica 3x gekopieerd
// In Service A:
$gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
// In Service B:
$gewicht = $judoka->gewicht_gewogen !== null ? $judoka->gewicht_gewogen : $judoka->gewicht;
// In Controller:
$gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? null;

// ✓ GOED - centrale methode
$gewicht = $judoka->getEffectiefGewicht();
```

---

## 8. Wanneer WEL String Checks Gebruiken

String checks zijn ALLEEN toegestaan voor:

1. **Sortering** - `+` klassen na `-` klassen sorteren
2. **Display formatting** - voor UI weergave
3. **Validatie van user input** - bij formulier invoer

```php
// ✓ OK voor sortering
usort($klassen, function ($a, $b) {
    $aPlus = str_starts_with($a, '+') ? 1 : 0;
    $bPlus = str_starts_with($b, '+') ? 1 : 0;
    if ($aPlus !== $bPlus) return $aPlus - $bPlus;
    return floatval($a) - floatval($b);
});
```

---

