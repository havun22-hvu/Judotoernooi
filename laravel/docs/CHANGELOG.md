# Changelog

Alle belangrijke wijzigingen worden hier bijgehouden.

## [2026-01-24] - Docs Reorganisatie & Bug Fixes

### Verplaatst
- `2-FEATURES/PLANNING_INTROPAGE_PWA.md` → `4-PLANNING/` (was planning doc in features)
- `4-PLANNING/PLANNING_CHAT_REVERB.md` → `2-FEATURES/CHAT.md` (werkend feature)
- `EVALUATIE-DEMO-21-JAN.md` → `6-INTERNAL/` (intern document)

### Verwijderd
- `4-PLANNING/PLAN_GREEDY_PLUS_BAND.md` - Was geïmplementeerd
- `.claude/plan-refactor-classificatie.md` - Was geïmplementeerd

### Gewijzigd
- `README.md` - Links bijgewerkt naar nieuwe locaties
- `CLAUDE.md` - Test data regels toegevoegd

### Fixes
- Categorie overlap waarschuwing positie (was achter header)
- Niet-gecategoriseerde judoka's kunnen nu verwijderd/bekeken worden
- Middleware route model binding voor API routes

---

## [2026-01-21] - Chat & Import Verbeteringen

### Toegevoegd
- `2-FEATURES/IMPORT.md` - Import documentatie
- Import warnings zichtbaar per club met contactgegevens
- Telefoon veld in import en coach portal

### Fixes
- Groen/geel wedstrijd selectie in mat interface
- Import faalt NOOIT meer op null gewichtsklasse

---

## [2026-01-03] - Weging PWA & Authenticatie Cleanup

### Gewijzigd
- **Weging Interface** (`pages/weging/interface.blade.php`): Complete rewrite als standalone PWA
  - Geen navigatie meer (geen layouts.app)
  - Fixed layout: scanner bovenin (45%), controls onderin (55%)
  - Zoekinput altijd zichtbaar onder scanner (springt niet meer)
  - Blauwe kleur theme (#1e40af)
  - Scanner: 300px max-width, 220px qrbox
- **Dojo Scanner** (`pages/dojo/scanner.blade.php`): Layout verbeteringen
  - Scanner compacter in bovenste deel scherm
  - Betere mobiele ervaring

### Fixes
- **Tab redirect fix** (`ToernooiController.php`): Na bloktijden opslaan blijft user op Organisatie tab
  - Toegevoegd: `?tab=organisatie` parameter aan redirect
  - View leest tab parameter voor Alpine.js initialisatie

### Verwijderd
- **Legacy wachtwoorden sectie** (`pages/toernooi/edit.blade.php`): 135 regels verwijderd
  - Alleen organisator en superadmin login blijft
  - Toernooi-level wachtwoorden niet meer nodig

---

## [2026-01-01] - B-Groep Start Niveau Fix

### Fixes
- **EliminatieService.php**: B-start niveau werd verkeerd berekend voor DUBBELE rondes
  - Bug: `$bStartWedstrijden` werd berekend met `$eersteGolfVerliezers` (A1+A2)
  - Fix: Bij DUBBEL gebruik `$a1Verliezers` (A1 verliezers vechten eerst onderling)
  - Impact: N=16 had 43 wedstrijden i.p.v. 27, N=32 had 91 i.p.v. 59

### Getest
- N=12: 19 wedstrijden (SAMEN) ✓
- N=16: 27 wedstrijden (DUBBEL) ✓
- N=32: 59 wedstrijden (DUBBEL) ✓

---

## [2026-01-01] - Documentatie Reorganisatie

### Toegevoegd
- `2-FEATURES/ELIMINATIE/` subfolder met geconsolideerde documentatie
  - `README.md` - Overzicht eliminatie systeem
  - `FORMULES.md` - Wiskundige berekeningen (authoritative bron)
  - `SLOT-SYSTEEM.md` - Slot nummering en doorschuifregels
  - `TEST-MATRIX.md` - Verificatietabellen per N
- `4-PLANNING/` folder voor toekomstige features
- `5-REGLEMENT/` folder voor JBN reglementen
- `6-INTERNAL/` folder voor interne documentatie
- Dit CHANGELOG.md bestand

### Gewijzigd
- `README.md` - Nieuwe structuur met alle secties
- `2-FEATURES/ELIMINATIE_SYSTEEM.md` - Nu redirect naar subfolder

### Verwijderd
- `ELIMINATIE_BEREKENING.md` - Geconsolideerd in ELIMINATIE/FORMULES.md
- `SLOT_SYSTEEM.md` - Geconsolideerd in ELIMINATIE/SLOT-SYSTEEM.md
- `WEDSTRIJDSCHEMA.md` (root) - Duplicaat van 2-FEATURES versie
- `4-DEPLOYMENT/` - Lege folder

### Verplaatst
- `PLANNING_*.md` → `4-PLANNING/`
- `JBN-REGLEMENT-2026.md` → `5-REGLEMENT/`
- `LESSONS-LEARNED-AI-SAMENWERKING.md` → `6-INTERNAL/`
- `ROLLEN_HIERARCHIE.md` → `6-INTERNAL/`

### Fixes
- Inconsistentie in V1/V2 formule voor N=16, 32, 64 gedocumenteerd
- Correcte formule voor dubbele rondes toegevoegd in FORMULES.md

---

## Conventies

### Semantic Versioning voor Docs
- **Major**: Structuur wijzigingen, verwijderde secties
- **Minor**: Nieuwe documentatie, feature updates
- **Patch**: Fixes, typos, verduidelijkingen

### Format
```
## [YYYY-MM-DD] - Titel

### Toegevoegd
### Gewijzigd
### Verwijderd
### Verplaatst
### Fixes
```
