---
title: Reverb-events
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Reverb-events

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

## Reverb Events

### ScoreboardAssignment (Server → Scorebord App)
**Channel:** `scoreboard.{toernooiId}.{matId}`
**Trigger:** Wanneer wedstrijd op groen gezet in mat interface
**Payload:**
```json
{
  "wedstrijd_id": 101,
  "judoka_wit": { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord" },
  "judoka_blauw": { "id": 43, "naam": "Piet Jansen", "club": "Sportclub Oost" },
  "poule_naam": "B-pupillen -34 kg",
  "ronde": null,
  "match_duration": 240
}
```

### ScoreboardEvent (Bediening → Display via Server)
**Channel:** `scoreboard-display.{toernooiId}.{matId}`
**Trigger:** Bediening POST naar `/api/scoreboard/event`
**Payload:** Verschilt per event type:

**timer.start:**
```json
{ "event": "timer.start", "timestamp": 1711025400.123, "remaining": 240, "golden_score": false }
```

**timer.stop:**
```json
{ "event": "timer.stop", "remaining": 134.5 }
```

**timer.reset:**
```json
{ "event": "timer.reset", "duration": 240 }
```

**score.update:**
```json
{
  "event": "score.update",
  "scores": {
    "wit": { "yuko": 1, "wazaari": 1, "ippon": false, "shido": 0 },
    "blauw": { "yuko": 0, "wazaari": 0, "ippon": false, "shido": 1 }
  }
}
```

**osaekomi.start:**
```json
{ "event": "osaekomi.start", "judoka": "blauw", "timestamp": 1711025400.123 }
```

**osaekomi.stop:**
```json
{ "event": "osaekomi.stop" }
```

**osaekomi.warning:** (awasete-ippon — 2e waza-ari tijdens osaekomi)
```json
{ "event": "osaekomi.warning", "judoka": "blauw", "active": true }
```
`active: true` = balk aan bij de W-drempel als de houder al ≥1 waza-ari heeft;
`active: false` = balk weg (toketa, overname, mate, timer.reset, match.end).

**match.start:**
```json
{
  "event": "match.start",
  "wedstrijd_id": 101,
  "judoka_wit": { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord" },
  "judoka_blauw": { "id": 43, "naam": "Piet Jansen", "club": "Sportclub Oost" },
  "poule_naam": "B-pupillen -34 kg",
  "match_duration": 240
}
```

**match.end:**
```json
{ "event": "match.end", "winner": "wit", "uitslag_type": "wazaari" }
```

---

