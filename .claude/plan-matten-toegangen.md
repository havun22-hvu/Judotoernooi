---
title: Plan — matten verlagen + mat-toegangen synchroniseren
type: claude
scope: judotoernooi
last_updated: 2026-06-21
status: PLAN — wacht op akkoord (geen code tot "ga maar")
---

# Plan: aantal matten aanpassen → mat-toegangen + poules meebewegen

## Afgesproken gedrag
- **Aantal matten verhogen** → nieuwe matten + nieuwe mat-toegangen erbij. Geen waarschuwing.
- **Aantal matten verlagen, GEEN poules op matten** → matten weg + bijbehorende mat-toegangen weg. Geen waarschuwing.
- **Aantal matten verlagen, WÉL poules op matten** → eerst waarschuwing:
  > "Alle poules worden matloos — je moet ze opnieuw indelen via 'Blokken → Verdeel over matten'. Doorgaan?"
  - Bij **OK**: álle matten leeg (alle poules → `mat_id = null`, hun wedstrijdschema vervalt), nieuw aantal matten toegepast, mat-toegangen gesynchroniseerd.
  - Bij **annuleren**: niets gebeurt, aantal matten blijft staan. (poules worden NOOIT verwijderd.)
- **Mat-toegangen** volgen altijd het werkelijke aantal matten (erbij/eraf).
- **Device-toegangen-lijst** (Organisatie-tab) ververst **direct** na de wijziging.

## Aanpak

### 1. Backend — sync-logica (`ToernooiService`)
- **`syncMatToegangen(Toernooi)`** (nieuw): mat-toegangen gelijktrekken met de werkelijke matten —
  ontbrekende toevoegen, wezen (toegang voor niet-bestaande mat) verwijderen. Aanroepen vanuit
  `syncMatten()`, zodat het meeloopt bij elke toernooi-opslag.
- **`legeAlleMatten(Toernooi)`** (nieuw, of in `syncMatten`): zet alle poules `mat_id = null` en
  wist hun wedstrijdschema (`poule->wedstrijden()->delete()` — TE VERIFIËREN of dat het juiste is),
  zodat na het verlagen alle matten leeg zijn en `syncMatten` ze kan verwijderen.

### 2. Backend — `ToernooiController@update`
- Detecteer of `aantal_matten` daalt t.o.v. de huidige waarde.
- Als dalen + er staan poules op matten (`poules where mat_id not null` bestaan):
  - **Alleen uitvoeren als de gebruiker bevestigd heeft** (flag uit het form, bv. `matten_legen_bevestigd=1`).
  - Bevestigd → `legeAlleMatten()` → daarna `syncMatten()` (verwijdert nu de lege overtollige matten) → `syncMatToegangen()`.
  - Niet bevestigd → de verlaging NIET doorvoeren (oude `aantal_matten` behouden) + waarschuwing terug,
    zodat de frontend alsnog kan bevestigen. (vangnet; normaal vangt de frontend het af.)

### 3. Frontend — waarschuwing (`toernooi/edit.blade.php`, `aantal_matten`-veld)
- Bij `change` op `#aantal_matten` met een **lagere** waarde dan de huidige, EN er staan poules op matten:
  - Toon `confirm(...)` met bovenstaande tekst.
  - Bij OK → zet de bevestig-flag in het form (hidden input) en laat de auto-save doorgaan.
  - Bij annuleren → zet het veld terug op de oude waarde, geen save.
- "Staan er poules op matten?" → als Blade-variabele meegeven (`$heeftPoulesOpMatten = $toernooi->poules()->whereNotNull('mat_id')->exists()`).
- CSP-veilig: geen inline `on*=`; via de bestaande change-listener / `cspActions` (dit bestand is
  net volledig CSP-schoon gemaakt — zo houden).

### 4. Frontend — device-toegangen-lijst direct verversen
- Het device-toegangen-component (`partials/device-toegangen.blade.php`) laadt via `loadToegangen()` in `init()`.
- Na een geslaagde mat-aantal-save: een window-event dispatchen (bv. `matten-gewijzigd`); het component
  luistert (`window.addEventListener('matten-gewijzigd', () => this.loadToegangen())`) en re-fetcht.
- De index-endpoint draait `syncMatToegangen` al, dus de re-fetch toont meteen de juiste lijst.

## Bestanden
- `app/Services/ToernooiService.php` — `syncMatToegangen()` + `legeAlleMatten()` + aanroep in `syncMatten()`.
- `app/Http/Controllers/ToernooiController.php` — verlaging-detectie + bevestig-flag-flow in `update()`.
- `app/Http/Controllers/DeviceToegangBeheerController.php` — `index` de gedeelde sync laten gebruiken (DRY).
- `resources/views/pages/toernooi/edit.blade.php` — `$heeftPoulesOpMatten`, confirm-flow, hidden flag, dispatch event.
- `resources/views/pages/toernooi/partials/device-toegangen.blade.php` — luister op het event + re-fetch.

## Te verifiëren tijdens bouw
- Of "schema vervalt" = `poule->wedstrijden()->delete()` of een ander veld (gegenereerde schema vs poule-indeling).
- Of `syncMatten` ná `legeAlleMatten` de matten daadwerkelijk verwijdert (matten zijn dan leeg → `whereDoesntHave('poules')` slaagt).
- Hoe de auto-save de bevestig-flag meestuurt (de auto-save serialiseert het hele form → hidden input gaat mee).

## Risico's
- Destructief bij verlagen (matloos maken). Vangnet = bevestiging verplicht (frontend + backend-flag).
- Auto-save + confirm-timing: confirm moet vóór de save afgehandeld zijn (debounce/verloop).
- CSP: niets inline toevoegen; via delegation.

## Test
- PHPUnit: `ToernooiService::syncMatToegangen` (add+remove), `legeAlleMatten`, en `update`-flow (verlagen
  met/zonder poules, met/zonder bevestiging).
- Handmatig op staging: matten verhogen/verlagen, met en zonder poules, lijst ververst direct.
