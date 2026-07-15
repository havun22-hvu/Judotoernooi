---
title: Model-methodes & nieuwe logica toevoegen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Model-methodes & nieuwe logica toevoegen

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 9. Model Methodes Overzicht

### Judoka Model

```php
// Gewicht gerelateerd
$judoka->isVasteGewichtsklasse()      // bool - via categorie config
$judoka->isGewichtBinnenKlasse()      // bool - centrale check
$judoka->getEffectiefGewicht()        // float|null - gewogen > ingeschreven > klasse
$judoka->heeftAfwijkendGewicht()      // bool - gewogen maar buiten klasse

// Status gerelateerd
$judoka->isActief($wegingGesloten)    // bool - niet afwezig, wel gewogen indien vereist
$judoka->isAanwezig()                 // bool - aanwezigheid check
```

### Poule Model

```php
// Titel en ranges
$poule->getDisplayTitel()             // string - live berekende titel
$poule->getLeeftijdsRange()           // array|null - min/max/range
$poule->getGewichtsRange()            // array|null - min/max/range

// Categorie
$poule->isDynamisch()                 // bool - max_kg_verschil > 0
$poule->getCategorieConfig()          // array - volledige config
$poule->isProblematischNaWeging()     // array|null - range overschrijding details

// Statistieken
$poule->berekenAantalWedstrijden()    // int - verwacht aantal wedstrijden
$poule->herbereken()                  // void - update alle statistieken
```

---

## 10. Toevoegen Nieuwe Logica

Bij nieuwe business logic:

1. **Check of er al een methode bestaat** in Judoka/Poule model
2. **Maak een model methode** als de logica op meerdere plekken nodig is
3. **Documenteer de methode** met PHPDoc
4. **Test de methode** met unit tests

```php
/**
 * Check of judoka geschikt is voor kruisfinale
 * Vereist: gewogen, niet afwezig, in top 2 van poule
 */
public function isGeschiktVoorKruisfinale(): bool
{
    if (!$this->isActief(true)) return false;
    if ($this->gewicht_gewogen === null) return false;

    $poule = $this->poules()->first();
    if (!$poule) return false;

    $positie = $poule->pivot->eindpositie ?? 99;
    return $positie <= 2;
}
```

---

