# Plan: LCD Display Verbeteren

> **Datum:** 2026-03-29
> **Status:** Taak 1, 4, 5, 6 AFGEROND. Taak 2 en 3 staan open.
> **Blade view:** `laravel/resources/views/pages/mat/scoreboard-live.blade.php`

## Achtergrond

De JudoScoreBoard Android app bedient het scorebord (timer, scores, shido's, osaekomi).
Het LCD display is een Blade webpagina die via Reverb WebSocket live meekijkt.
De app stuurt events bij elke state change → backend broadcast → display update.

**Spiegeling:** Display is gespiegeld t.o.v. bediening (blauw/wit omgedraaid).

### Architectuur (na taak 5+6)

```
Match groen gezet / verwijderd in JudoToernooi
  → Backend broadcast op BEIDE channels:
     1. scoreboard.{toernooiId}.{matId} → Android app (ScoreboardAssignment)
     2. scoreboard-display.{toernooiId}.{matId} → LCD display (ScoreboardEvent)
  → Beide interfaces reageren direct

Scoreboard bediening (timer, scores, osaekomi)
  → App POST /api/scoreboard/event
  → Backend broadcast op scoreboard-display channel
  → LCD display update
```

## Taken

### ~~1. Osaekomi tijden tonen op display~~ ✅ DONE

Geïmplementeerd op 2026-03-29. De Blade view:
- Heeft `osaekomiTimes` state + `renderOsaekomiTimes()` functie
- Verwerkt `osaekomi_times` uit `osaekomi.start`, `osaekomi.stop` en `score.update` events
- Toont knipperende tijden met zone labels (`12s → WAZA-ARI`)
- CSS blink animatie op `.osaekomi-time-entry`
- Reset bij `match.start`

### 2. LCD proporties vergroten voor TV — OPEN

**Wat:** Display moet leesbaar zijn op TV/groot scherm op afstand (3-10 meter).

**Te doen:**
- Timer: veel groter (denk 15-20vw font size)
- Scores (Y/W/I): grotere cijfers, duidelijk contrast
- Namen: groot genoeg om te lezen op afstand
- Shido kaarten: groter, duidelijk zichtbaar
- Osaekomi timer: groot, met zone indicator
- Test op 1920x1080 (standaard TV resolutie)
- Gebruik vw/vh units voor schaalbaarheid
- Minimale witruimte — alles moet zo groot mogelijk

### 3. LCD link updaten in device-toegangen — OPEN

**Wat:** De "LCD Display" knop bij device-toegangen (Instellingen → Organisatie) linkt nog naar de oude lange URL.

**Te doen:**
- Zoek in de Blade views waar de LCD link wordt getoond
- Update naar `/tv/{eerste 4 tekens van code}` format
- Gebruik `$deviceToegang->getDisplayCode()` helper (bestaat al)

### ~~4. Backend: osaekomi_times doorlaten~~ ✅ NIET NODIG

Controller gebruikt `$request->all()` — alle data wordt automatisch doorgestuurd.

### ~~5. Backend: match.assign broadcasten naar LCD~~ ✅ DONE

Geïmplementeerd op 2026-03-29. Bij groen zetten:
- `MatController::doSetHuidigeWedstrijd()` broadcast nu op BEIDE channels
- `ScoreboardAssignment` (app channel) + `ScoreboardEvent` met `match.assign` (display channel)
- LCD toont namen direct bij kleurbeurt, zonder omweg via app
- Blade view: `match.assign` handler reset scores, toont namen, zet timer op standby

### ~~6. Backend: match.unassign bij verwijderen kleurbeurt~~ ✅ DONE

Geïmplementeerd op 2026-03-29. Bij verwijderen kleurbeurt:
- `ScoreboardAssignment` met lege match array (app channel)
- `ScoreboardEvent` met `match.unassign` (display channel)
- LCD reset naar standby (namen weg, scores 0, timer reset)
- Android app: ControlScreen luistert op `scoreboard.assignment`, toont alert bij:
  - Lege match (unassign) → "Wedstrijd verwijderd" → terug naar WaitingScreen
  - Andere match.id (reassign) → "Nieuwe wedstrijd" → terug naar WaitingScreen
- WaitingScreen guard: accepteert alleen assignments met `match.id`

## Event types

### Events vanuit Android app (via `POST /api/scoreboard/event`)

| Event | Extra data |
|-------|-----------|
| `match.start` | wedstrijd_id, judoka_wit, judoka_blauw, poule_naam, match_duration |
| `timer.start` | timestamp, remaining, golden_score |
| `timer.stop` | remaining |
| `timer.reset` | duration |
| `score.update` | scores (wit/blauw: yuko, wazaari, ippon, shido), osaekomi_times |
| `osaekomi.start` | judoka, timestamp, osaekomi_times |
| `osaekomi.stop` | judoka, time, osaekomi_times |
| `match.end` | winner, uitslag_type |

### Events vanuit backend (bij mat-wijzigingen in webinterface)

| Event | Channel | Trigger | Data |
|-------|---------|---------|------|
| `scoreboard.assignment` | `scoreboard.{t}.{m}` | Groen gezet | match object (of leeg bij unassign) |
| `match.assign` | `scoreboard-display.{t}.{m}` | Groen gezet | judoka namen, poule, duration |
| `match.unassign` | `scoreboard-display.{t}.{m}` | Kleurbeurt verwijderd | (leeg) |
