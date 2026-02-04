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

## 11. Error Handling

### Exception Classes

Gebruik custom exceptions voor duidelijke error categorisatie:

```
app/Exceptions/
├── JudoToernooiException.php      # Base exception (extends Exception)
├── MollieException.php            # Betalingen (extends JudoToernooiException)
├── ImportException.php            # Import fouten (extends JudoToernooiException)
└── ExternalServiceException.php   # Externe APIs/Python solver (extends JudoToernooiException)
```

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - generieke exception
throw new \Exception('Mollie API error: ' . $response->body());

// ❌ NOOIT DOEN - geen context bij logging
Log::error('Fout!');

// ❌ NOOIT DOEN - technische foutmelding aan gebruiker
return back()->with('error', $e->getMessage());
```

### GOED (professioneel)

```php
// ✓ Gebruik custom exception met context
throw MollieException::apiError('/payments', $response->body(), $response->status());

// ✓ Log met context
Log::error('Payment creation failed', [
    'toernooi_id' => $toernooi->id,
    'user_id' => auth()->id(),
    'error' => $e->getMessage(),
]);

// ✓ Gebruikersvriendelijke melding
return back()->with('error', $e->getUserMessage());
```

### Controller Pattern

```php
try {
    // Business logic
} catch (JudoToernooiException $e) {
    $e->log(); // Logt met juiste level en context
    return back()->with('error', $e->getUserMessage());
} catch (\Exception $e) {
    Log::error('Unexpected error', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    return back()->with('error', 'Er ging iets mis. Probeer opnieuw.');
}
```

### Log Levels

| Level | Wanneer | Voorbeeld |
|-------|---------|-----------|
| `Log::error()` | Systeem errors, crashes | Database connectie faalt |
| `Log::warning()` | Business logic failures | Betaling geannuleerd |
| `Log::info()` | Belangrijke events | Betaling geslaagd, import voltooid |
| `Log::debug()` | Development | Python solver output |

### Context Meegeven

```php
Log::info('Payment completed', [
    'user_id' => auth()->id(),
    'toernooi_id' => $toernooi->id,
    'payment_id' => $payment->id,
    'amount' => $payment->amount,
]);
```

### Database Transactions

```php
// ✓ Gebruik transactions voor meerdere DB writes
DB::transaction(function () use ($data) {
    // Multiple database operations
}, 3); // 3 retries on deadlock
```

---

## 12. External Service Calls

### Timeout Configuratie

```php
// ✓ Altijd timeout instellen
Http::timeout(15)
    ->connectTimeout(5)
    ->post($url, $data);
```

### Retry Logic

```php
// ✓ Retry voor transient errors
$response = Http::retry(2, 500) // 2 retries, 500ms sleep
    ->timeout(15)
    ->get($url);
```

### Fallback Strategie

```php
// ✓ Graceful degradation
try {
    $result = $this->pythonSolver($data);
} catch (ExternalServiceException $e) {
    $e->log();
    $result = $this->simpleFallback($data); // Altijd werkende fallback
}
```

---

## 13. Band Kleuren (GEEN kyu!)

### Volgorde beginner → expert (ENIGE BRON VAN WAARHEID: Band enum)

```
wit → geel → oranje → groen → blauw → bruin → zwart

niveau():      0      1       2        3        4        5       6   (beginner eerst)
sortNiveau():  1      2       3        4        5        6       7   (database sort_band)
enum value:    6      5       4        3        2        1       0   (expert eerst, voor filters)
```

### Opslag

**ALLEEN** lowercase kleur naam in database:
- ✓ `wit`, `geel`, `oranje`, `groen`, `blauw`, `bruin`, `zwart`
- ✗ `Geel (5e kyu)`, `5`, `5e kyu` - **NOOIT**

### Weergave (UI)

**ALLEEN** kleur naam met hoofdletter - **NOOIT kyu nummers**:
- ✓ `Wit`, `Geel`, `Groen`
- ✗ `Geel (5e kyu)`, `5e kyu` - **NOOIT**

### Code Gebruik

```php
// ✓ GOED - centrale methode voor weergave
{{ \App\Enums\Band::toKleur($judoka->band) }}

// ✓ GOED - enum voor logica
$bandEnum = Band::fromString($judoka->band);
$niveau = $bandEnum->niveau();  // 0=wit/beginner, 6=zwart/expert

// ✗ FOUT - direct tonen zonder conversie
{{ $judoka->band }}  // Kan "geel (5e kyu)" zijn als data fout is!

// ✗ FOUT - hardcoded kyu mapping
$kyu = match($band) { 'geel' => 5, 'groen' => 3, ... };
```

### Import

Bij import wordt kyu automatisch omgezet:
- Input: `"Geel (5e kyu)"` of `"5"` of `"geel"`
- Output (opslag): `"geel"`

```php
// ImportService::parseBand() doet dit automatisch
$band = Band::fromString($input);
return $band ? strtolower($band->name) : 'wit';
```

### Beschikbare Methodes (allemaal in Band enum)

| Methode | Doel | Voorbeeld |
|---------|------|-----------|
| `Band::toKleur($band)` | Weergave in UI | `"geel"` → `"Geel"` |
| `Band::fromString($str)` | String naar enum | `"geel (5e kyu)"` → `Band::GEEL` |
| `Band::getSortNiveau($str)` | Sortering (static) | `"geel"` → `2` |
| `Band::pastInFilter($band, $filter)` | Filter check | `"groen"`, `"tm_groen"` → `true` |
| `$band->niveau()` | Beginner→expert | `Band::WIT->niveau()` → `0` |
| `$band->sortNiveau()` | Database sort_band | `Band::WIT->sortNiveau()` → `1` |
| `$band->label()` | Kleur naam | `Band::GEEL->label()` → `"Geel"` |
| `$band->value` | Expert→beginner | `Band::ZWART->value` → `0` |

### Filters

```php
// "tm_groen" = beginners t/m groen (wit, geel, oranje, groen)
Band::pastInFilter('geel', 'tm_groen');   // true
Band::pastInFilter('blauw', 'tm_groen');  // false

// "vanaf_blauw" = gevorderden vanaf blauw (blauw, bruin, zwart)
Band::pastInFilter('bruin', 'vanaf_blauw'); // true
Band::pastInFilter('groen', 'vanaf_blauw'); // false
```

### BandHelper (DEPRECATED)

`BandHelper` is deprecated. Gebruik `Band` enum direct:

```php
// ✗ OUD
BandHelper::getSortNiveau($band);
BandHelper::pastInFilter($band, $filter);

// ✓ NIEUW
Band::getSortNiveau($band);
Band::pastInFilter($band, $filter);
```

---

*Laatst bijgewerkt: 4 februari 2026*
