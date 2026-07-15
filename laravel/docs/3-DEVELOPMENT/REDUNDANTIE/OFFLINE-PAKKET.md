---
title: Offline pakket: anti-piraterij, inhoud & licentie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Offline pakket: anti-piraterij, inhoud & licentie

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

## 3. Offline Pakket Architectuur

### 3.1 Anti-piraterij Strategie

```
┌─────────────────────────────────────────────────────────────────┐
│  PROBLEEM:                                                      │
│  Als de app downloadbaar is, kan iemand het kraken en zonder   │
│  betaling gebruiken.                                            │
│                                                                 │
│  OPLOSSING:                                                     │
│  Offline pakket = DATA + LICENSE + GECOMPILEERDE PLAYER        │
│  (niet de volledige Laravel app)                               │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Wat zit in het Offline Pakket?

| Component | Beschrijving | Bescherming |
|-----------|--------------|-------------|
| **License key** | Toernooi ID + organisator + geldigheid | Signed, encrypted |
| **Toernooi data** | JSON met deelnemers, poules, etc. | Alleen dit toernooi |
| **Player app** | Gecompileerde applicatie | Obfuscated code |

### 3.3 License Key Specificaties

```
License key bevat (encrypted):
- toernooi_id: 12345
- organisator_id: 67
- organisator_naam: "Judoschool Cees Veen"  (watermark)
- geldig_van: 2026-01-31  (dag voor toernooi)
- geldig_tot: 2026-02-03  (2 dagen na toernooi)
- signature: HMAC-SHA256 met server secret
```

**Validatie bij start:**
1. Player app leest license key
2. Checkt signature (is key niet gemanipuleerd?)
3. Checkt datum (zijn we binnen geldigheidsperiode?)
4. Bij falen: app start niet, toont "License verlopen"

### 3.4 Player App Technologie

**Gekozen: Electron of Tauri (Rust)**

| Optie | Voordelen | Nadelen |
|-------|-----------|---------|
| **Electron** | Makkelijker te bouwen, zelfde web tech | Groter (~150MB), JS is te reverse-engineeren |
| **Tauri (Rust)** | Klein (~10MB), Rust moeilijker te kraken | Complexer om te bouwen |

**Aanbeveling:** Start met Electron, migreer later naar Tauri indien nodig.

