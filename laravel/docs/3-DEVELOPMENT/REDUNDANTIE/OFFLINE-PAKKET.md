---
title: Offline pakket architectuur
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Offline pakket architectuur

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

### 3.5 Download Flow

```
VOORBEREIDING (met internet):
┌─────────────────────────────────────────────────────────────────┐
│ 1. Organisator werkt online via judotournament.org             │
│ 2. Configureert toernooi, importeert deelnemers                │
│ 3. Avond voor toernooi: klikt "Download Offline Pakket"        │
│                                                                 │
│ Server genereert:                                               │
│ ├── license.key      (signed, geldig 3 dagen)                  │
│ ├── toernooi.json    (alle data voor dit toernooi)             │
│ └── Bundelt met player app                                     │
│                                                                 │
│ → Download: WestfriesOpen2026_offline.exe (.dmg voor Mac)      │
└─────────────────────────────────────────────────────────────────┘

WEDSTRIJDDAG (zonder internet):
┌─────────────────────────────────────────────────────────────────┐
│ 1. Dubbelklik op .exe/.dmg                                     │
│ 2. App checkt license key → OK                                 │
│ 3. App start lokale server                                     │
│ 4. Tablets/telefoons verbinden via lokaal wifi                 │
│ 5. Toernooi draait volledig offline                            │
└─────────────────────────────────────────────────────────────────┘

NA TOERNOOI (met internet):
┌─────────────────────────────────────────────────────────────────┐
│ 1. "Upload resultaten" knop in app                             │
│ 2. Sync naar judotournament.org                                │
│ 3. Na 3 dagen: license verloopt, app werkt niet meer          │
└─────────────────────────────────────────────────────────────────┘
```

### 3.6 Beveiligingslagen (Dubbele Bescherming)

```
┌─────────────────────────────────────────────────────────────────┐
│  LAAG 1: BEPERKTE FUNCTIONALITEIT                              │
│  ════════════════════════════════                              │
│  Offline app = alleen "wedstrijddag player"                    │
│                                                                 │
│  WEL in offline app:          NIET in offline app:             │
│  ✓ Weeglijst (invoeren)       ✗ Toernooi aanmaken              │
│  ✓ Zaaloverzicht (read-only)  ✗ Toernooi instellingen          │
│  ✓ Mat interface (scores)     ✗ Organisatie instellingen       │
│  ✓ Publiek scherm             ✗ Judoka's importeren            │
│  ✓ Coach PWA                  ✗ Poules verdelen (algoritme)    │
│  ✓ Uitslagen/ranglijst        ✗ Blokken indelen                │
│  ✓ Noodplan prints            ✗ Categorieën configureren       │
│                               ✗ Coach portal beheer            │
│                               ✗ Betalingen                     │
│                                                                 │
│  → Kraken heeft weinig zin: zonder voorbereiding kun je niks   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│  LAAG 2: TECHNISCHE BESCHERMING                                │
│  ══════════════════════════════                                │
│  ✓ Gecompileerd/encrypted (Electron/Tauri)                     │
│  ✓ License key: toernooi ID + organisator + 3 dagen geldig    │
│  ✓ Watermark: organisator naam in alle exports                 │
│  ✓ Signed key: niet te manipuleren                             │
│                                                                 │
│  → Zelfs proberen te kraken is moeilijk                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

| Laag | Wat | Beschermt tegen |
|------|-----|-----------------|
| **1. Beperkte functionaliteit** | Alleen wedstrijddag features | App stelen voor volledige use |
| **2. License key** | Geldig 3 dagen, signed | Hergebruik oude downloads |
| **3. Gecompileerde app** | Geen leesbare broncode | Code kopiëren/wijzigen |
| **4. Watermark** | Organisator naam in exports | Anoniem delen |

**Kraker scenario:**
```
Kraker: "Ik heb de offline app gekraakt!"
Realiteit: "...maar ik kan geen toernooi voorbereiden,
           geen judoka's importeren, geen poules maken..."
         → WAARDELOOS
```

**Resultaat:** Dubbele bescherming maakt kraken zinloos. Alle waarde (algoritmes, import, configuratie) blijft op de server.

---

