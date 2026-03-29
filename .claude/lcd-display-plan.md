# Plan: LCD Display Verbeteren

> **Datum:** 2026-03-29
> **Context:** JudoScoreBoard app stuurt nu events naar backend, display moet verbeterd worden
> **Blade view:** `laravel/resources/views/pages/mat/scoreboard-live.blade.php`

## Achtergrond

De JudoScoreBoard Android app bedient het scorebord (timer, scores, shido's, osaekomi).
Het LCD display is een Blade webpagina die via Reverb WebSocket live meekijkt.
De app stuurt events bij elke state change → backend broadcast → display update.

**Spiegeling:** Display is gespiegeld t.o.v. bediening (blauw/wit omgedraaid).

## Taken

### 1. Osaekomi tijden tonen op display

**Wat:** De app stuurt nu `osaekomi_times` mee bij `osaekomi.start` en `osaekomi.stop` events.
Dit is een object: `{ blauw: [12, 8], wit: [15] }` (array van seconden per kant).

**Te doen:**
- In `handleEvent()` bij `osaekomi.stop` en `osaekomi.start`: lees `data.osaekomi_times`
- Toon opgeslagen tijden onder de osaekomi sectie (per kant)
- Tijden verdwijnen wanneer score.update binnenkomt (scheids heeft score toegekend)
- Format: `12s → WAZA-ARI` (tijd + zone label)
- Zones: Y=5s, W=10s, I=20s (zelfde drempels als app default)
- Tijden moeten knipperen (CSS animation) om aandacht te trekken

**Event data voorbeeld:**
```json
{
  "event": "osaekomi.stop",
  "judoka": "blauw",
  "time": 12,
  "osaekomi_times": {
    "blauw": [12],
    "wit": []
  }
}
```

### 2. LCD proporties vergroten voor TV

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

### 3. LCD link updaten in device-toegangen

**Wat:** De "LCD Display" knop bij device-toegangen (Instellingen → Organisatie) linkt nog naar de oude lange URL.

**Te doen:**
- Zoek in de Blade views waar de LCD link wordt getoond
- Update naar `/tv/{eerste 4 tekens van code}` format
- Gebruik `$deviceToegang->getDisplayCode()` helper (bestaat al)

### 4. Backend: osaekomi_times doorlaten in ScoreboardController

**Wat:** Controller valideert event data — check of `osaekomi_times` wordt doorgelaten.

**Te doen:**
- Check `ScoreboardController::event()` method
- Zorg dat `osaekomi_times` in de allowed/validated data zit
- Het wordt meegestuurd in de broadcast naar het display channel

## Volgorde

1. **Eerst taak 4** — backend moet data doorlaten
2. **Dan taak 1** — osaekomi tijden tonen
3. **Dan taak 2** — proporties vergroten
4. **Taak 3** — klein, kan tussendoor

## Event types die de app stuurt

| Event | Extra data |
|-------|-----------|
| `match.start` | wedstrijd_id, judoka_wit, judoka_blauw, poule_naam, match_duration |
| `timer.start` | timestamp, remaining, golden_score |
| `timer.stop` | remaining |
| `timer.reset` | duration |
| `score.update` | scores (wit/blauw: yuko, wazaari, ippon, shido) |
| `osaekomi.start` | judoka, timestamp, osaekomi_times |
| `osaekomi.stop` | judoka, time, osaekomi_times |
| `match.end` | winner, uitslag_type |
