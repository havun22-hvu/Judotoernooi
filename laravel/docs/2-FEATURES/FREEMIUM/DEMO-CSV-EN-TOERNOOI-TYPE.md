---
title: Demo CSV's & toernooi-type zichtbaarheid
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Demo CSV's & toernooi-type zichtbaarheid

> Onderdeel van [Freemium Model](../FREEMIUM.md).

## Demo CSV's (Free Tier)

De free tier biedt **downloadbare demo CSV's** met fake judoka's, zodat de organisator direct een werkend toernooi kan ervaren.

### Beschikbare demo CSV's
| Bestand | Aantal | Plekken over | Gebruik |
|---------|--------|--------------|---------|
| `demo-30.csv` | 30 | 20 | Import + coach portal testen |
| `demo-40.csv` | 40 | 10 | Import + beperkt handmatig |
| `demo-50.csv` | 50 | 0 | Alleen import testen |

### Kenmerken demo judoka's
| Aspect | Waarde |
|--------|--------|
| **Gewicht** | 30-45 kg (realistische spreiding) |
| **Leeftijd** | 6-12 jaar (geboortejaren passend) |
| **Namen** | Mix Japanse/Nederlandse namen |
| **Geslacht** | Mix jongens/meisjes |
| **Band** | Mix wit t/m oranje (passend bij leeftijd) |
| **Club** | Demo Judoschool |

### Free tier import flow
1. **Demo CSV downloaden** → kies 30, 40 of 50 judoka's
2. **Demo CSV uploaden** → poules en schema's werken direct
3. **Eigen CSV uploaden** → max 20 judoka's, test of eigen bestand werkt
4. **Handmatig toevoegen** → max 20 via coach portal of handmatig invoeren
5. **Poules/schema's/wedstrijden** → volledig bruikbaar
6. **Print/noodplan** → pas na upgrade

### Technisch

```php
// Demo CSV's als statische bestanden
// storage/app/demo/demo-30.csv
// storage/app/demo/demo-40.csv
// storage/app/demo/demo-50.csv

// Download route
// GET /{org}/toernooi/{toernooi}/demo-csv/{variant}

// Import limiet free tier
// Eigen CSV: max 20 judoka's (FreemiumService::FREE_MAX_EIGEN_IMPORT)
// Handmatig: max 20 (FreemiumService::FREE_MAX_HANDMATIG)
```

---

## Toernooi Type (UI zichtbaarheid)

De instellingen pagina past zich aan op het toernooi type. Dit bepaalt **alleen de zichtbaarheid** van secties in de UI — niet de betalingslogica.

| Type | Zichtbaar in instellingen | Typisch gebruik |
|------|--------------------------|-----------------|
| **Intern** (default) | Basis instellingen (poules, matten, categorieën) | Kleine clubtoernooien, beginners |
| **Open** | Alles: + eliminatie, danpunten, coachkaarten | Grotere open toernooien |

**Wat verborgen wordt bij "intern":**
- Eliminatie systeem sectie
- Danpunten (JBN) sectie
- Coachkaarten sectie (in noodplan/admin)

**Belangrijk:** De betalings-gate blijft apart. Bij "open" worden secties zichtbaar maar de features vereisen nog steeds een betaald pakket om ingesteld/gebruikt te worden.

**Database:** `toernooi_type` enum (`intern`, `open`), default `intern`. Bestaande toernooien → `open`.

---

