# Handover: B-Groep Bracket Fix

**Datum:** 25 december 2024
**Status:** In progress

---

## Huidige Probleem

Bij 21 judoka's (D=16, V=5) worden er **2 wedstrijden** aangemaakt in B 1/4 deel 2, maar er zouden **4** moeten zijn.

### Test case
- **Aantal judoka's:** 21
- **D (doel):** 16 (grootste macht van 2 ≤ 21)
- **V (voorronde):** 5 (21 - 16)
- **A-structuur:** 5 voorrondes + 8x 1/8 finale + 4x 1/4 + 2x 1/2 + finale

### Verwachte B-groep structuur (D=16)

| Ronde | Wedstrijden | Instroom |
|-------|-------------|----------|
| B voorronde | max(0, V+8-16) = 0 | - |
| B 1/8 | 8 | A voorronde verliezers (5) + A 1/8 verliezers (8) |
| B 1/4 deel 1 | 4 | 8 B 1/8 winnaars |
| **B 1/4 deel 2** | **4** | 4 B 1/4-1 winnaars + 4 A 1/4 verliezers |
| B 1/2 deel 1 | 2 | 4 B 1/4-2 winnaars |
| Brons | 2 | 2 B 1/2-1 winnaars + 2 A 1/2 verliezers |

### Wat er fout gaat

De code in `EliminatieService::genereerBPoule()` maakt wel 4 wedstrijden aan (regel 585):
```php
for ($i = 0; $i < 4; $i++) {  // Dit is correct
```

Maar ergens wordt dit niet correct weergegeven of er is een ander probleem met de bracket generatie.

---

## Gerelateerde Fixes (vandaag gedaan)

1. **EliminatieService.php** - Routing fix voor A verliezers:
   - A 1/4 verliezers → B 1/4 deel 2 (WIT) - directe plaatsing
   - A 1/2 verliezers → Brons (WIT) - directe plaatsing
   - Skip B-voorronde check voor kwartfinale/halve_finale verliezers

2. **BlokController.php** - Corrupte `resetCategorie` functie gerepareerd

3. **poules.blade.php** - `updateProblematischePoules()` wordt nu aangeroepen bij drag & drop

---

## Te Onderzoeken Morgen

1. **Waarom toont de bracket maar 2 wedstrijden in B 1/4 deel 2?**
   - Check database: zijn er echt 4 wedstrijden aangemaakt met `ronde = 'b_kwartfinale_2'`?
   - Check view: worden alle wedstrijden weergegeven?

2. **Regenereer bracket en controleer:**
   ```bash
   cd laravel
   php artisan tinker
   ```
   ```php
   $poule = \App\Models\Poule::find(ID);
   $weds = $poule->wedstrijden()->where('ronde', 'b_kwartfinale_2')->get();
   dd($weds->count()); // Moet 4 zijn
   ```

3. **Check de view template** voor B-groep weergave

---

## Documentatie

Zie voor volledige specificatie:
- `docs/ELIMINATIE_BEREKENING.md` - Wiskundige formules
- `docs/2-FEATURES/ELIMINATIE_SYSTEEM.md` - Systeem overzicht

---

## Commits vandaag

- `344324d` - Fix B-group routing and repair corrupted BlokController
- `e14a42d` - Fix: update problematic poules indicator when judokas are moved
- `f3e7e43` - Fix: improve problematic poules removal with better debugging
