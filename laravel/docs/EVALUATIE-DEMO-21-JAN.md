# Evaluatie Demo 21 Januari 2026

## Samenvatting
Demo aan Cees op staging omgeving. Meerdere kritieke bugs ontdekt tijdens live demonstratie.

## Problemen tijdens demo

### 1. Spreker Interface Error (Kritiek)
**Symptoom:** "unsupported operand types: int + string" op iPad spreker PWA
**Oorzaak:** PHP 8 strict type checking - `score_wit` en `score_blauw` zijn strings in database
**Getroffen bestanden:**
- `app/Http/Controllers/RoleToegang.php` (iPad PWA route)
- `app/Http/Controllers/BlokController.php`
- `app/Http/Controllers/PubliekController.php`

**Fix:** Explicit type casting toegevoegd:
```php
// Oud (broken):
$jp += $w->score_wit ?? 0;

// Nieuw (fixed):
$jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
```

### 2. Band Sortering (Medium)
**Symptoom:** Wit en groen banden werden samen gegroepeerd
**Oorzaak:** `BandHelper::BAND_VOLGORDE` was omgekeerd (wit=0, zwart=6)
**Fix:** Volgorde gecorrigeerd naar kyu systeem (zwart=0, wit=6)

### 3. Poule Titels bij Vaste Gewichtsklassen (Low)
**Symptoom:** Gewichtsrange getoond ipv gewichtsklasse bij vaste categorieën
**Oorzaak:** Geen check op `max_kg_verschil == 0`
**Fix:** Conditie toegevoegd in `PouleIndelingService::maakPouleTitel()`

## Root Cause Analysis

| Probleem | Root Cause | Had voorkomen kunnen worden door |
|----------|------------|----------------------------------|
| Type error | PHP 8 strict mode niet getest | Type safety audit |
| Band sortering | Refactor zonder volledige test | Unit tests voor BandHelper |
| Poule titels | Edge case niet gedocumenteerd | Acceptance criteria per categorie type |

## Verbeterpunten

### Proces
- [ ] **Pre-demo checklist** maken en doorlopen
- [ ] **Staging zelf testen** voordat klant erbij komt
- [ ] **PHP error logs** monitoren tijdens development

### Technisch
- [ ] **Type safety audit** uitvoeren op alle score berekeningen
- [ ] **Unit tests** voor BandHelper en score calculaties
- [ ] **End-to-end test script** voor volledige toernooi flow

### Documentatie
- [ ] Edge cases documenteren per feature
- [ ] Breaking changes loggen bij refactors

## Pre-Demo Checklist (Nieuw)

Doorloop ALTIJD voor elke demo:

### 1. Data Setup
- [ ] Test toernooi met realistische data
- [ ] Minstens 20 judoka's verdeeld over 3+ categorieën
- [ ] Mix van banden (wit t/m bruin)

### 2. Weging Flow
- [ ] QR scan werkt
- [ ] Gewicht registreren werkt
- [ ] Buiten-klasse warning toontlocal site is vast gelopen 


### 3. Poule Generatie
- [ ] Poules genereren zonder errors
- [ ] Titels correct (leeftijd, gewicht, band)
- [ ] Judoka's correct gesorteerd op band

### 4. Wedstrijd Flow
- [ ] Schema genereren
- [ ] Mat interface: scores invoeren
- [ ] Spreker interface: standings tonen

### 5. PWA's testen
- [ ] Mat PWA op tablet
- [ ] Spreker PWA op iPad
- [ ] Weging PWA op telefoon

## Lessons Learned

1. **PHP 8 strict mode is unforgiving** - altijd explicit casten bij database velden
2. **Refactors zonder tests zijn risico** - BandHelper wijziging had unit test nodig
3. **Staging is geen development** - test daar alsof het productie is
4. **Demo prep ≠ feature complete** - doorloop hele flow handmatig
