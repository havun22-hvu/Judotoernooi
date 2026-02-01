# Code Standaarden - JudoToernooi

> **VERPLICHT**: Deze standaarden zijn bindend. Code die hier niet aan voldoet wordt NIET geaccepteerd.

## Kernprincipe

**Alle business logic hoort in MODEL METHODES, niet in views of controllers.**

Data komt uit de database, maar de INTERPRETATIE van die data moet via centrale methodes.

---

## 1. Gewichtsklasse Checks

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - string prefix check
$isVasteKlasse = str_starts_with($gewichtsklasse, '-') || str_starts_with($gewichtsklasse, '+');

// ❌ NOOIT DOEN - regex op gewichtsklasse string
$isPlusKlasse = str_starts_with($klasse, '+');
$limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));
if ($isPlusKlasse) {
    $pastInKlasse = $gewicht >= ($limiet - $tolerantie);
} else {
    $pastInKlasse = $gewicht <= ($limiet + $tolerantie);
}
```

### GOED (professioneel)

```php
// ✓ Gebruik model methode - checkt via categorie config (max_kg_verschil)
$isVasteKlasse = $judoka->isVasteGewichtsklasse();

// ✓ Gebruik model methode voor gewicht check
$pastInKlasse = $judoka->isGewichtBinnenKlasse(null, $tolerantie);
```

### Beschikbare methodes

| Methode | Doel | Bron |
|---------|------|------|
| `Judoka::isVasteGewichtsklasse()` | Check of vaste klasse (max_kg_verschil = 0) | Categorie config via poule |
| `Judoka::isGewichtBinnenKlasse()` | Check of gewicht binnen limiet | Model methode |
| `Judoka::getEffectiefGewicht()` | Haal gewicht op (gewogen > ingeschreven > klasse) | Model methode |
| `Poule::isDynamisch()` | Check of variabele klasse (max_kg_verschil > 0) | Categorie config |

---

## 2. Poule Titels

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - directe database kolom
$titel = $poule->titel;

// ❌ NOOIT DOEN - regex op titel string
preg_match('/(\d+-\d+j)/', $poule->titel, $lftMatch);
preg_match('/([\d.]+)-([\d.]+)kg/', $poule->titel, $kgMatch);
```

### GOED (professioneel)

```php
// ✓ Gebruik model methode - berekent live uit judoka's
$titel = $poule->getDisplayTitel();

// ✓ Gebruik model methodes voor ranges
$leeftijdRange = $poule->getLeeftijdsRange();  // ['min_jaar' => 4, 'max_jaar' => 6, 'range' => 2]
$gewichtRange = $poule->getGewichtsRange();    // ['min_kg' => 16.0, 'max_kg' => 18.6, 'range' => 2.6]
```

### Beschikbare methodes

| Methode | Doel | Bron |
|---------|------|------|
| `Poule::getDisplayTitel()` | Live titel met ranges | Berekend uit judoka's |
| `Poule::getLeeftijdsRange()` | Leeftijdsrange actieve judoka's | Berekend uit judoka's |
| `Poule::getGewichtsRange()` | Gewichtsrange gewogen judoka's | Berekend uit judoka's |
| `Poule::updateTitel()` | Update database titel (bij verplaatsing) | Wordt automatisch aangeroepen |

---

## 3. Categorie Configuratie

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - hardcoded waarden
$maxKgVerschil = 3;
$tolerantie = 0.5;

// ❌ NOOIT DOEN - string check voor categorie type
$isDynamisch = !str_starts_with($gewichtsklasse, '-') && !str_starts_with($gewichtsklasse, '+');
```

### GOED (professioneel)

```php
// ✓ Haal waarden uit categorie config
$config = $poule->getCategorieConfig();
$maxKgVerschil = $config['max_kg_verschil'] ?? 0;
$maxLftVerschil = $config['max_leeftijd_verschil'] ?? 0;

// ✓ Haal tolerantie uit toernooi
$tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

// ✓ Check via model methode
$isDynamisch = $poule->isDynamisch();  // Checkt max_kg_verschil > 0
```

### Configuratie structuur

```php
// Categorie config voorbeeld
[
    'label' => "Mini's 4-5j",
    'min_leeftijd' => 4,
    'max_leeftijd' => 5,
    'max_kg_verschil' => 3,      // 0 = vaste klasse, >0 = variabele klasse
    'max_leeftijd_verschil' => 1,
    'gewichtsklassen' => ['-24', '-27', '-30', '+30'],  // Alleen bij vaste klassen
]
```

---

## 4. JavaScript (Frontend)

### FOUT (amateuristisch)

```javascript
// ❌ NOOIT DOEN - string check in JS
const isVasteKlasse = klasse.startsWith('-') || klasse.startsWith('+');
```

### GOED (professioneel)

```javascript
// ✓ Backend stuurt pre-berekende waarde mee
const isVasteKlasse = judoka.is_vaste_klasse || false;
```

### API Response moet bevatten

```php
// In controller
return response()->json([
    'judoka' => [
        'id' => $judoka->id,
        'naam' => $judoka->naam,
        'gewichtsklasse' => $judoka->gewichtsklasse,
        'is_vaste_klasse' => $judoka->isVasteGewichtsklasse(),  // ← Pre-berekend!
        // ...
    ],
]);
```

---

## 5. Views (Blade Templates)

### FOUT (amateuristisch)

```blade
{{-- ❌ NOOIT DOEN - business logic in view --}}
@php
    $isPlusKlasse = str_starts_with($poule->gewichtsklasse, '+');
    $pouleLimiet = floatval(preg_replace('/[^0-9.]/', '', $poule->gewichtsklasse));
    if ($isPlusKlasse) {
        $isVerkeerdePoule = $judokaGewicht < ($pouleLimiet - $tolerantie);
    } else {
        $isVerkeerdePoule = $judokaGewicht > ($pouleLimiet + $tolerantie);
    }
@endphp
```

### GOED (professioneel)

```blade
{{-- ✓ Gebruik model methode --}}
@php
    $isAfwijkendGewicht = $judoka->isGewichtBinnenKlasse(null, $tolerantie) === false;
@endphp
```

---

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

## 7. Checklist voor Code Review

Voordat code gemerged wordt, check:

- [ ] Geen `str_starts_with()` op gewichtsklasse voor business logic
- [ ] Geen `preg_match()` op titel strings voor data extractie
- [ ] Geen `$poule->titel` direct - gebruik `getDisplayTitel()`
- [ ] Geen hardcoded tolerantie/max_kg waarden
- [ ] Geen dubbele logica - centrale methodes gebruiken
- [ ] JavaScript krijgt pre-berekende waarden van backend
- [ ] Views bevatten geen business logic

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

*Laatst bijgewerkt: 2 februari 2026*
