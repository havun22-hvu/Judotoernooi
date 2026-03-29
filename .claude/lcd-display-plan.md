# Plan: LCD Display Verbeteren

> **Datum:** 2026-03-29
> **Status:** Taak 1 en 4 AFGEROND. Taak 2, 3, 5, 6 staan open.
> **Blade view:** `laravel/resources/views/pages/mat/scoreboard-live.blade.php`

## Achtergrond

De JudoScoreBoard Android app bedient het scorebord (timer, scores, shido's, osaekomi).
Het LCD display is een Blade webpagina die via Reverb WebSocket live meekijkt.
De app stuurt events bij elke state change → backend broadcast → display update.

**Spiegeling:** Display is gespiegeld t.o.v. bediening (blauw/wit omgedraaid).

### Huidige architectuur (probleem)

```
Match groen gezet in JudoToernooi
  → ScoreboardAssignment event op `scoreboard.{toernooiId}.{matId}` channel
  → Android app ontvangt, opent ControlScreen
  → App stuurt match.start event via API
  → Backend broadcast op `scoreboard-display` channel
  → LCD toont namen
```

**Gaten:**
- LCD krijgt namen pas als de app `match.start` stuurt (vertraging)
- Bij verwijderen kleurbeurt: app en LCD weten van niks
- Als app offline is, ziet LCD niks tot page refresh

### Gewenste architectuur

```
Match groen gezet / verwijderd in JudoToernooi
  → Backend broadcast DIRECT op BEIDE channels:
     1. `scoreboard.{toernooiId}.{matId}` → Android app
     2. `scoreboard-display.{toernooiId}.{matId}` → LCD display
  → Beide interfaces reageren direct
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

**Referentie:** Kijk naar professionele judo scoreborden (IJF stijl):
- Twee grote kleurvlakken (blauw/wit)
- Timer prominent in het midden
- Scores zeer groot en direct leesbaar

### 3. LCD link updaten in device-toegangen — OPEN

**Wat:** De "LCD Display" knop bij device-toegangen (Instellingen → Organisatie) linkt nog naar de oude lange URL.

**Te doen:**
- Zoek in de Blade views waar de LCD link wordt getoond
- Update naar `/tv/{eerste 4 tekens van code}` format
- Gebruik `$deviceToegang->getDisplayCode()` helper (bestaat al)

### ~~4. Backend: osaekomi_times doorlaten in ScoreboardController~~ ✅ NIET NODIG

Controller gebruikt `$request->all()` — alle data wordt automatisch doorgestuurd naar de broadcast. Geen wijziging nodig.

### 5. Backend: bij kleurbeurt match data broadcasten naar LCD — OPEN (PRIORITEIT)

**Probleem:** LCD krijgt namen pas als de app `match.start` stuurt. Dat is een vertraging
en als de app offline is, ziet de LCD helemaal niks.

**Wat moet gebeuren:** Wanneer een match groen wordt gezet (kleurbeurt toegewezen aan mat),
moet de backend DIRECT ook broadcasten op het `scoreboard-display` channel zodat de LCD
de judoka namen en wedstrijdinfo meteen toont.

**Te doen:**
1. Zoek waar `ScoreboardAssignment` event wordt gedispatcht (bij groen zetten van wedstrijd)
2. Voeg daar een EXTRA broadcast toe op `scoreboard-display.{toernooiId}.{matId}` channel
3. Event type: `match.assign` (nieuw event type)
4. Data meesturen:
   ```json
   {
     "event": "match.assign",
     "wedstrijd_id": 123,
     "judoka_wit": { "naam": "Jansen", "club": "Budokan" },
     "judoka_blauw": { "naam": "De Vries", "club": "Kenamju" },
     "poule_naam": "Poule A -66kg",
     "match_duration": 240
   }
   ```
5. In `scoreboard-live.blade.php`: handle `match.assign` event — toon namen, reset scores,
   zet timer op standby (niet starten). Vergelijkbaar met `match.start` maar zonder dat de
   wedstrijd als "gestart" wordt beschouwd.

**Waar te zoeken:**
- `ScoreboardAssignment` event class
- Controller/service die wedstrijden groen zet (waarschijnlijk in Mat of Wedstrijd controller)
- Check of het bestaande `ScoreboardAssignment` event al de juiste data bevat

### 6. Backend: bij verwijderen kleurbeurt → reset LCD en app — OPEN (PRIORITEIT)

**Probleem:** Als een kleurbeurt wordt verwijderd (wedstrijd van mat afgehaald),
weten de Android app en LCD daar niks van. De LCD blijft de oude namen tonen,
de app blijft op de oude wedstrijd staan.

**Wat moet gebeuren:** Bij verwijderen kleurbeurt broadcasten op BEIDE channels.

**Te doen:**
1. Zoek waar kleurbeurt wordt verwijderd (wedstrijd van mat afhalen, of mat leegmaken)
2. Broadcast op `scoreboard.{toernooiId}.{matId}` channel:
   ```json
   { "event": "match.unassign" }
   ```
   → Android app reageert: terug naar WaitingScreen
3. Broadcast op `scoreboard-display.{toernooiId}.{matId}` channel:
   ```json
   { "event": "match.unassign" }
   ```
   → LCD reageert: reset naar standby (namen weg, timer reset, scores leeg)

4. In `scoreboard-live.blade.php`: handle `match.unassign` event:
   - Namen terug naar "WIT" / "BLAUW"
   - Scores naar 0
   - Timer reset
   - Osaekomi clear
   - Evt. een subtiel "Wacht op wedstrijd..." bericht tonen

5. In JudoScoreBoard app: handle `match.unassign` op WebSocket channel:
   - Navigeer terug naar WaitingScreen
   - Toon melding "Wedstrijd is verwijderd van de mat"

**Waar te zoeken:**
- Dezelfde plek als waar kleurbeurt wordt toegewezen, maar dan de delete/remove actie
- Kan in een MatController, WedstrijdController, of via een service class

**Let op:** Taak 5 en 6 horen bij elkaar. De `match.assign` en `match.unassign` events
zijn nieuwe event types die NIET via de Android app gaan maar direct vanuit de backend.
Dit is anders dan de bestaande events die allemaal via `POST /api/scoreboard/event` lopen.
Deze events worden getriggerd door acties in de JudoToernooi webinterface.

## Event types

### Events vanuit Android app (via `POST /api/scoreboard/event`)

| Event | Extra data |
|-------|-----------|
| `match.start` | wedstrijd_id, judoka_wit, judoka_blauw, poule_naam, match_duration |
| `timer.start` | timestamp, remaining, golden_score |
| `timer.stop` | remaining |
| `timer.reset` | duration |
| `score.update` | scores (wit/blauw: yuko, wazaari, ippon, shido), **osaekomi_times** |
| `osaekomi.start` | judoka, timestamp, **osaekomi_times** |
| `osaekomi.stop` | judoka, time, **osaekomi_times** |
| `match.end` | winner, uitslag_type |

### Events vanuit backend (bij mat-wijzigingen in webinterface) — NIEUW

| Event | Trigger | Naar | Data |
|-------|---------|------|------|
| `match.assign` | Wedstrijd groen gezet | App + LCD | judoka namen, poule, duration |
| `match.unassign` | Kleurbeurt verwijderd | App + LCD | (leeg) |

## Wat is er veranderd (2026-03-29)

### JudoScoreBoard (Android app) — commits `e391a40` en `a60374c`
- `osaekomi.start` en `osaekomi.stop` sturen nu `osaekomi_times` mee
- `score.update` stuurt ook `osaekomi_times` mee (via ref voor actuele waarde)
- `awardOsaekomiScore` verwijdert tijd uit lijst VOOR score event, zodat display correcte state krijgt

### JudoToernooi (Blade view) — commit `7894866a`
- `osaekomiTimes` state + `getOsaekomiZone()` + `renderOsaekomiTimes()` functies toegevoegd
- `osaekomi.start`: clear vorige indicators, update times, render
- `osaekomi.stop`: update times, render
- `score.update`: update times, render (voor wanneer tijd wordt weggetikt)
- `match.start`: reset osaekomiTimes
- CSS: `.osaekomi-time-entry` met blink animatie, per kant in `#wit-osaekomi-times` / `#blauw-osaekomi-times`

## Nog te doen in JudoScoreBoard (Android app)

Na taak 5+6 in JudoToernooi moet de app ook `match.unassign` afhandelen op het WebSocket channel:
- Luisteren naar `match.unassign` event
- Terug navigeren naar WaitingScreen
- Gebruiker informeren dat de wedstrijd is verwijderd
