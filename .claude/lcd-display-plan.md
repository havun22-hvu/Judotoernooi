# Plan: LCD Display Verbeteren

> **Datum:** 2026-03-29
> **Status:** Taak 1 en 4 zijn AFGEROND. Taak 2 en 3 staan open.
> **Blade view:** `laravel/resources/views/pages/mat/scoreboard-live.blade.php`

## Achtergrond

De JudoScoreBoard Android app bedient het scorebord (timer, scores, shido's, osaekomi).
Het LCD display is een Blade webpagina die via Reverb WebSocket live meekijkt.
De app stuurt events bij elke state change → backend broadcast → display update.

**Spiegeling:** Display is gespiegeld t.o.v. bediening (blauw/wit omgedraaid).

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

## Event types die de app stuurt

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
