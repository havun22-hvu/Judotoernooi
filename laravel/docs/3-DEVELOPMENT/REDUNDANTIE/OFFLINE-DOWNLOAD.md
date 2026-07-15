---
title: Offline pakket: player, download & beveiliging
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Offline pakket: player, download & beveiliging

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

