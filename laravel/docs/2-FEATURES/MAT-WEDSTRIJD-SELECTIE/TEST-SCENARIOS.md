---
title: Test-scenarios
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Test-scenarios

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## Test Scenarios

### Selectie

| Test | Verwacht resultaat |
|------|-------------------|
| Klik wedstrijd (niets geselecteerd) | Wordt groen |
| Klik andere wedstrijd (alleen groen) | Wordt geel |
| Klik andere wedstrijd (groen + geel) | Wordt blauw |
| Klik andere wedstrijd (groen + geel + blauw) | Alert |

### Deselectie

| Test | Verwacht resultaat |
|------|-------------------|
| Klik groene wedstrijd + bevestig | Geel → groen, blauw → geel |
| Klik gele wedstrijd | Blauw → geel |
| Klik blauwe wedstrijd | Blauw = null |

### Doorschuiving na uitslag

| Test | Verwacht resultaat |
|------|-------------------|
| Registreer uitslag groene wedstrijd | Geel → groen, blauw → geel |

### Multi-poule

| Test | Verwacht resultaat |
|------|-------------------|
| 3 poules op mat, selecteer uit elke poule 1 | 1 groen, 1 geel, 1 blauw |
| Verplaats poule met gele wedstrijd | Blauw → geel, groen blijft |

### Eliminatie A/B Split

| Test | Verwacht resultaat |
|------|-------------------|
| B-chip naar mat 2 gesleept | Mat 1 toont alleen A-tab, mat 2 alleen B-tab |
| Groen/geel/blauw op mat 1 | Onafhankelijk van mat 2 selecties |
| A-finale gespeeld, B nog niet | "Afronden" knop verschijnt op mat 1, backend blokkeert tot B ook klaar |
| Alle A+B gespeeld, afronden | Broadcast naar beide mats, spreker_klaar gezet |

---

