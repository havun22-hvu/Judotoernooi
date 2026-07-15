---
title: Database, interactie-logica en legenda
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Database, interactie-logica en legenda

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## Database

**Tabel: `matten`**

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `actieve_wedstrijd_id` | bigint NULL | FK → wedstrijden (groene wedstrijd) |
| `volgende_wedstrijd_id` | bigint NULL | FK → wedstrijden (gele wedstrijd) |
| `gereedmaken_wedstrijd_id` | bigint NULL | FK → wedstrijden (blauwe wedstrijd) |

---

## Interactie Logica

### Selecteren (klik op ongeselecteerde wedstrijd)

| Huidige situatie | Nieuwe wedstrijd wordt |
|------------------|----------------------|
| Geen groen | **Groen** |
| Wel groen, geen geel | **Geel** |
| Wel groen, wel geel, geen blauw | **Blauw** |
| Groen + geel + blauw aanwezig | Alert: "Deselecteer eerst een wedstrijd" |

### Deselecteren (klik op geselecteerde wedstrijd)

| Klik op | Actie | Doorschuiving |
|---------|-------|---------------|
| **Groen** | Vraag bevestiging: "Wedstrijd stoppen?" | Geel → Groen, Blauw → Geel |
| **Geel** | Direct deselecteren | Blauw → Geel |
| **Blauw** | Direct deselecteren | Geen doorschuiving |

**Belangrijk:** Deselectie alleen bij klik op eigen kleur!

### Wedstrijd afgerond (uitslag geregistreerd)

1. Groene wedstrijd wordt gemarkeerd als gespeeld
2. Automatische doorschuiving:
   - Geel → Groen
   - Blauw → Geel
   - Blauw wordt null

---

## Legenda

Bovenaan elke mat interface wordt een legenda getoond:

```
┌─────────────────────────────────────────────────────┐
│ ● Speelt nu   ● Staat klaar   ● Gereed maken        │
│   (groen)       (geel)          (blauw)             │
└─────────────────────────────────────────────────────┘
```

---

