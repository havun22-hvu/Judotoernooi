---
title: Plan — Winnaar automatisch doorschuiven (eliminatie)
type: plan
scope: judotoernooi
created: 2026-06-12
status: ter goedkeuring
---

# Plan: Winnaar schuift automatisch door naar volgende ronde

## Doel
Bij elke eliminatie-uitslag (eerste registratie én correctie, A- én B-groep) de winnaar
automatisch in `volgende_wedstrijd_id` op het juiste slot (`winnaar_naar_slot`) plaatsen —
symmetrisch met de verliezer die al automatisch naar de B-poule gaat. Handmatige DnD blijft
als override werken. Byes worden NIET extra ge-cascade (besluit Henk 2026-06-12).

## Kernwijziging (1 bestand)

### `app/Services/EliminatieService.php` → `verwerkUitslag()` (regels ~612-673)

**Huidige structuur:**
- Correctie-blok `if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId)`:
  - `verwijderUitLatereRondes(...)` (cascade opschonen)
  - **winnaar in volgende slot plaatsen** ← zit NU alleen hier
  - `verwijderUitB(...)` (oude verliezer = nieuwe winnaar uit B halen, alleen A)
- Verliezer naar B (`if groep==='A' && verliezerId`)

**Nieuwe structuur:**
1. **Behoud** het correctie-blok, maar **haal de winnaar-plaatsing eruit** (regels 634-643).
   Cascade (`verwijderUitLatereRondes`) en `verwijderUitB` blijven in het correctie-blok.
2. **Nieuw, altijd-uitgevoerd blok** ná het correctie-blok en vóór verliezer-naar-B:
   ```php
   // Winnaar automatisch doorschuiven naar volgende ronde (A én B)
   if ($wedstrijd->volgende_wedstrijd_id) {
       $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
       if ($volgende) {
           $slot = $wedstrijd->winnaar_naar_slot; // 'wit' | 'blauw'
           if (in_array($slot, ['wit', 'blauw'], true)) {
               $veld = ($slot === 'wit') ? 'judoka_wit_id' : 'judoka_blauw_id';
               $volgende->update([$veld => $winnaarId]);
           } else {
               Log::warning("Wedstrijd {$wedstrijd->id} heeft volgende_wedstrijd_id "
                   . "maar geen geldige winnaar_naar_slot ({$slot}) — bracket configuratiefout");
           }
       }
   }
   ```
3. Verliezer-naar-B blijft ongewijzigd.

**Eigenschappen:**
- **Idempotent**: zelfde winnaar opnieuw plaatsen = zelfde waarde.
- **Eindpunten** (finale/brons, geen `volgende_wedstrijd_id`) → overgeslagen.
- **Volgorde**: cascade → winnaar doorschuiven → verliezer naar B (zo overschrijft de
  nieuwe winnaar netjes ná het opschonen).
- Gebruik bestaande conventie `'wit'/'blauw'` → veld (NIET de blueprint-notatie
  `judoka_wit_id` als slot-waarde).

## Realtime — GEEN wijziging
`Wedstrijd` heeft geen broadcast-trait, maar dat is niet nodig: `ScoreboardController`
(regel 227) en `MatUitslagController` dispatchen ná `verwerkUitslag` al een `MatUpdate`.
De mat-interface herlaadt daarop de bracket-HTML → doorgeschoven winnaar verschijnt live.
*(Blueprint-aanname "save() broadcast automatisch" is onjuist; dit pad werkt al.)*

## Tests

### Regressie-check eerst
Grep bestaande tests op de aanname "volgende wedstrijd blijft leeg na verwerkUitslag".
Zulke asserts moeten aangepast worden (nieuw gedrag = winnaar staat er nu wél).

### Nieuwe unit tests — `tests/Unit/Services/EliminatieServiceExtraTest.php`
1. A-winnaar schuift door naar juiste slot van volgende A-wedstrijd.
2. B-winnaar schuift door naar juiste slot van volgende B-wedstrijd.
3. Correctie: oude winnaar uit latere ronde verwijderd + nieuwe winnaar doorgeschoven.
4. Eindpunt (finale, geen `volgende_wedstrijd_id`) → geen plaatsing, geen exception.
5. Ontbrekende `winnaar_naar_slot` → `Log::warning`, geen plaatsing, geen crash.
6. Idempotent: 2× zelfde uitslag → winnaar staat 1× correct.

### Feature test — `tests/Feature/...`
E2e via `Api\ScoreboardController::registreerUitslag`: registreer uitslag in 4-judoka
bracket → assert winnaar staat in juiste slot van `volgende_wedstrijd_id`. Daarna correctie
→ assert nieuwe winnaar doorgeschoven, oude verwijderd.

## Volgorde van uitvoering
1. Wijzig `verwerkUitslag` (kernwijziging).
2. Draai bestaande eliminatie-tests → pas tests aan die oude aanname testen.
3. Voeg 6 nieuwe unit tests toe.
4. Voeg feature test toe.
5. `cd laravel && php artisan test --no-coverage` (LOKAAL, SQLite — NOOIT op server).
6. Commit (atomic) + push.

## Risico's
| Risico | Mitigatie |
|--------|-----------|
| Bestaande test verwacht leeg volgend slot | Regressie-check stap 2; aanpassen naar nieuw gedrag |
| Correctie-cascade conflict | Auto-advance staat ná cascade — volgorde gewaarborgd |
| DnD-bracket breekt | Niet aangeraakt (andere controller); blijft override |
| Bye dubbele plaatsing | Geen cascade toegevoegd; bye-flow ongemoeid |
| B-groep aparte mat | `MatUpdate` dispatch dekt beide mats al |

## Geraakte bestanden
- `app/Services/EliminatieService.php` (kernwijziging, ~12 regels)
- `tests/Unit/Services/EliminatieServiceExtraTest.php` (6 tests)
- `tests/Feature/...` (1 feature test)
- Docs: README.md + SLOT-SYSTEEM.md → **al bijgewerkt in Fase 1**
