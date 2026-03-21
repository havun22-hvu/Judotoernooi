# Redundantie & Veiligheidsplan

> **Status:** Planning
> **Doel:** Toernooien die ALTIJD werken, ongeacht internet
> **SLA:** 100% lokale uptime, max 5 seconden data loss bij failover

---

## 1. Kernprincipe

```
┌─────────────────────────────────────────────────────────────────────┐
│                                                                     │
│   LOKAAL = PRIMAIR                                                  │
│   CLOUD = SYNC (bonus, niet vereist)                               │
│                                                                     │
│   Een klant in China moet zonder internet een toernooi kunnen      │
│   draaien. Internet in sporthallen is onbetrouwbaar (uitval, 5G    │
│   bereik slecht, 500 telefoons op dezelfde wifi).                  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Overzicht

Dit document beschrijft de technische architectuur voor een fail-safe toernooi systeem dat:
- **Volledig offline werkt** - geen internet nodig tijdens wedstrijddag
- **Op elke laptop draait** - geen vooraf geïnstalleerde software nodig
- **Wereldwijd bruikbaar** - China, Australië, overal
- **Simpel op te zetten** - dubbelklik en klaar
- **Beschermd tegen piraterij** - license key + gecompileerde app

---

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

## 2. Architectuur Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│                        CLOUD LAYER (judotournament.org)                     │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                                                                       │  │
│  │   Production Server (Hetzner)                                        │  │
│  │   - MySQL Database (master)                                          │  │
│  │   - Laravel Application                                              │  │
│  │   - Publiek Dashboard (500+ smartphones)                             │  │
│  │   - Coach Portal (inschrijvingen)                                    │  │
│  │                                                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    ▲                                        │
│                                    │ Sync elke 30 sec                       │
│                                    │ (bidirectioneel)                       │
│                                    │                                        │
├────────────────────────────────────┼────────────────────────────────────────┤
│                                    │                                        │
│                        LOKAAL NETWERK (Deco M4 Mesh)                        │
│                                    │                                        │
│  ┌─────────────────────────────────┼─────────────────────────────────────┐  │
│  │                                 │                                     │  │
│  │   ┌─────────────────┐    heartbeat    ┌─────────────────┐            │  │
│  │   │                 │◄────(5 sec)────►│                 │            │  │
│  │   │   PRIMARY       │                 │   STANDBY       │            │  │
│  │   │   Laptop A      │                 │   Laptop B      │            │  │
│  │   │                 │   real-time     │                 │            │  │
│  │   │   SQLite DB     │◄──replication──►│   SQLite DB     │            │  │
│  │   │   Laravel       │                 │   (replica)     │            │  │
│  │   │   192.168.1.100 │                 │   192.168.1.101 │            │  │
│  │   │                 │                 │                 │            │  │
│  │   └────────┬────────┘                 └────────┬────────┘            │  │
│  │            │                                   │                      │  │
│  │            │      AUTOMATIC FAILOVER           │                      │  │
│  │            │      (< 10 sec recovery)          │                      │  │
│  │            └───────────────┬───────────────────┘                      │  │
│  │                            │                                          │  │
│  │            ┌───────────────┴───────────────┐                          │  │
│  │            │                               │                          │  │
│  │            │   TP-Link Deco M4 Mesh        │                          │  │
│  │            │   - 3 units = 400m² dekking   │                          │  │
│  │            │   - Self-healing netwerk      │                          │  │
│  │            │   - Geen internet vereist     │                          │  │
│  │            │   - ~75 devices               │                          │  │
│  │            │                               │                          │  │
│  │            └───────────────┬───────────────┘                          │  │
│  │                            │                                          │  │
│  │    ┌───────────────────────┼───────────────────────┐                  │  │
│  │    │                       │                       │                  │  │
│  │    ▼                       ▼                       ▼                  │  │
│  │  ┌──────────┐        ┌──────────┐           ┌──────────┐              │  │
│  │  │ Mat      │        │ Weging   │           │ Publiek  │              │  │
│  │  │ Tablets  │        │ Laptop   │           │ Schermen │              │  │
│  │  │ (4-6x)   │        │          │           │ (per mat)│              │  │
│  │  └──────────┘        └──────────┘           └──────────┘              │  │
│  │                                                                       │  │
│  │                       ┌──────────┐                                    │  │
│  │                       │ Coach    │                                    │  │
│  │                       │ PWA's    │                                    │  │
│  │                       │ (20-40x) │                                    │  │
│  │                       └──────────┘                                    │  │
│  │                                                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│                        FALLBACK LAYER (Papier)                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                                                                       │  │
│  │   - Geprinte wedstrijdschema's (matrix format)                       │  │
│  │   - Weeglijsten                                                       │  │
│  │   - Contactlijst coaches                                              │  │
│  │   - Lege schema templates                                             │  │
│  │                                                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Componenten

### 3.1 Cloud Server

| Aspect | Specificatie |
|--------|--------------|
| **Provider** | Hetzner (Duitsland) |
| **Uptime SLA** | 99.9% |
| **Database** | MySQL 8.0 |
| **Backup** | Dagelijks, 7 dagen retentie |
| **Functie** | Publiek dashboard, coach portal, centrale database |

### 3.2 Lokale Primary Server (Laptop A)

| Aspect | Specificatie |
|--------|--------------|
| **OS** | Windows 10/11 of macOS |
| **Software** | PHP 8.2+, Laravel, SQLite |
| **Netwerk** | Vast IP via Deco (192.168.1.100) |
| **Functie** | Lokale server voor mat interfaces, weging, displays |

### 3.3 Lokale Standby Server (Laptop B)

| Aspect | Specificatie |
|--------|--------------|
| **OS** | Windows 10/11 of macOS |
| **Software** | Identiek aan Laptop A |
| **Netwerk** | Vast IP via Deco (192.168.1.101) |
| **Functie** | Hot standby, neemt over bij crash Laptop A |

### 3.4 Mesh Netwerk (Deco M4)

| Aspect | Specificatie |
|--------|--------------|
| **Units** | 3 stuks (max 10 per netwerk) |
| **Dekking** | ~400m² (met 3 units) |
| **Max devices** | 75-100 stabiel |
| **Internet** | Niet vereist voor lokale werking |

**Schalen naar grotere locaties:**

| Aantal units | Dekking | Geschikt voor |
|--------------|---------|---------------|
| 2 | ~260m² | Kleine sporthal |
| 3 | ~400m² | Normale sporthal |
| 5 | ~650m² | Grote sporthal |
| 10 | ~1300m² | Evenementenhal |

**Fysieke setup:**
```
Stopcontact 1          Stopcontact 2          Stopcontact 3
     │                      │                      │
 ┌───┴───┐              ┌───┴───┐              ┌───┴───┐
 │ Deco 1│ ~~~ wifi ~~~ │ Deco 2│ ~~~ wifi ~~~ │ Deco 3│
 └───────┘    mesh      └───────┘    mesh      └───────┘
     │
     └── (optioneel: ethernet naar sporthal internet)
```

- Deco's worden **alleen op stroom** aangesloten
- Onderling verbinden ze draadloos (mesh)
- Laptops en tablets verbinden als wifi clients
- Zonder internet: puur lokaal netwerk (aanbevolen voor redundantie)

---

## 4. Sync Mechanisme

### 4.1 Cloud ↔ Lokaal

```
┌──────────────┐         ┌──────────────┐
│  Cloud       │◄───────►│  Laptop A    │
│  Server      │  30 sec │  (Primary)   │
└──────────────┘         └──────────────┘
```

**Richting: Lokaal → Cloud**
- Elke 30 seconden push van wedstrijdresultaten
- Queue systeem voor offline buffering
- Automatisch retry bij connection herstel

**Richting: Cloud → Lokaal**
- Initiële download bij toernooi start
- Delta sync voor wijzigingen (poule aanpassingen)

### 4.2 Primary ↔ Standby

```
┌──────────────┐         ┌──────────────┐
│  Laptop A    │◄───────►│  Laptop B    │
│  (Primary)   │   5 sec │  (Standby)   │
└──────────────┘         └──────────────┘
```

**Heartbeat**
- Elke 5 seconden ping
- Standby pollt data van Primary
- Bij 3 gemiste heartbeats → alert

**Replicatie**
- Standby haalt elke 5 sec sync-data op van Primary
- Schrijft naar eigen SQLite
- Max 5 sec data achterstand

---

## 5. Failover Scenario's

### 5.1 Internet Uitval

| Fase | Actie | Downtime |
|------|-------|----------|
| Detectie | Automatisch (timeout) | 0 sec |
| Reactie | Lokaal netwerk blijft werken | 0 sec |
| Publiek | Smartphones zien geen updates | N.v.t. |
| Herstel | Sync queue wordt weggewerkt | 0 sec |

**Impact:** Geen - lokaal werkt volledig autonoom

### 5.2 Primary Laptop Crash

| Fase | Actie | Downtime |
|------|-------|----------|
| Detectie | Heartbeat timeout (15 sec) | 15 sec |
| Reactie | Organisator start Standby server | ~60 sec |
| Devices | Deco IP switch: Standby krijgt IP van Primary | ~60 sec |
| Herstel | Standby wordt Primary | 0 sec |

**Impact:** Max 2-3 minuten, max 5 sec data verlies

### 5.3 Beide Laptops Crash

| Fase | Actie | Downtime |
|------|-------|----------|
| Detectie | Handmatig | Variabel |
| Reactie | Print fallback activeren | ~5 min |
| Werking | Papieren schema's, handmatig scoren | N.v.t. |
| Herstel | Data invoeren na toernooi | N.v.t. |

**Impact:** Toernooi gaat door op papier

---

## 6. Device Overzicht

### 6.1 Kritieke Devices (Lokaal Netwerk)

| Device | Aantal | Functie | Offline? |
|--------|--------|---------|----------|
| Laptop A (Primary) | 1 | Server | Ja |
| Laptop B (Standby) | 1 | Backup server | Ja |
| Mat tablets | 4-6 | Score invoer | Ja |
| Weging laptop | 1 | Gewicht registratie | Ja |
| Publiek schermen | 4-6 | Huidige/volgende wedstrijd | Ja |
| Coach PWA's | 20-40 | Judoka's volgen | Ja |

**Totaal kritieke devices:** ~35-55

### 6.2 Niet-kritieke Devices (Internet)

| Device | Aantal | Functie | Offline? |
|--------|--------|---------|----------|
| Publiek smartphones | 500+ | Favorieten volgen | Nee |

---

## 7. Pre-Flight Checklist

Voor elk toernooi moet de volgende checklist doorlopen worden:

### 7.1 Hardware Check (1 dag voor toernooi)

- [ ] Laptop A opgeladen en werkend
- [ ] Laptop B opgeladen en werkend
- [ ] Deco M4 units beschikbaar (3x)
- [ ] Mat tablets opgeladen (4-6x)
- [ ] Weging laptop beschikbaar
- [ ] Printer + papier beschikbaar
- [ ] Stroomkabels/stekkerdozen

### 7.2 Software Check (1 dag voor toernooi)

- [ ] Laravel draait op Laptop A
- [ ] Laravel draait op Laptop B
- [ ] Database sync werkt (A → B)
- [ ] Cloud sync werkt (A → Cloud)
- [ ] Print functie getest

### 7.3 Netwerk Check (ochtend toernooi)

- [ ] Deco M4 geïnstalleerd en werkend
- [ ] Laptop A verbonden (vast IP)
- [ ] Laptop B verbonden (vast IP)
- [ ] Mat tablets kunnen Primary bereiken
- [ ] Heartbeat actief tussen A en B

### 7.4 Data Check (ochtend toernooi)

- [ ] Toernooi data gedownload naar Laptop A
- [ ] Toernooi data gerepliceerd naar Laptop B
- [ ] Noodplan documenten geprint:
  - [ ] Weeglijsten (alle blokken)
  - [ ] Wedstrijdschema's (matrix, leeg)
  - [ ] Contactlijst coaches
  - [ ] Zaaloverzicht

---

## 8. Disaster Recovery Procedure

### 8.1 Bij Primary Crash

```
1. Organisator merkt storing (mat tablets reageren niet)
2. Check Laptop A → niet bereikbaar
3. Ga naar Laptop B
4. Dubbelklik "Start Server" shortcut
5. Wacht 30 seconden
6. Open Deco app → Wijzig IP reservering:
   - Geef Laptop B het IP van Laptop A (192.168.1.100)
   - Tablets hoeven NIKS te wijzigen
7. Test verbinding vanaf 1 mat tablet
8. Doorgaan met toernooi
```

### 8.2 Bij Beide Laptops Crash

```
1. Roep alle mat-vrijwilligers bij elkaar
2. Verdeel geprinte wedstrijdschema's (matrix format)
3. Instrueer: "Vul handmatig W en J kolommen in"
4. Organisator noteert uitslagen centraal
5. Na toernooi: invoeren in systeem
```

### 8.3 Bij Netwerkproblemen

```
1. Check Deco units (lampjes moeten wit zijn)
2. Herstart Deco units indien nodig
3. Check of laptops verbonden zijn
4. Test ping tussen devices
5. Als niets werkt: papieren fallback
```

---

## 9. Health Dashboard

Het systeem toont real-time status op het organisator scherm:

```
┌─────────────────────────────────────────────────────────────┐
│  SYSTEEM STATUS                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Cloud Sync:      ● ONLINE    (laatste: 14:32:15)          │
│  Standby Server:  ● ACTIEF    (heartbeat: OK)              │
│  Netwerk:         ● STABIEL   (42 devices verbonden)       │
│  Data Backup:     ● ACTUEEL   (156 wedstrijden)            │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Mat 1: ● OK    Mat 2: ● OK    Mat 3: ● OK           │   │
│  │ Mat 4: ● OK    Weging: ● OK   Displays: ● OK        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 10. Server Rol Configuratie

Bij eerste start van de lokale server-modus moet de gebruiker expliciet kiezen welke rol dit apparaat heeft.

### Opstartscherm

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   SERVERROL CONFIGUREREN                                    │
│                                                             │
│   Welke rol heeft deze laptop?                              │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ○ PRIMARY SERVER (Laptop A)                        │   │
│   │                                                     │   │
│   │    → Dit is de hoofdserver                          │   │
│   │    → Alle tablets/devices verbinden hiermee         │   │
│   │    → IP wordt: 192.168.1.100                        │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ○ STANDBY SERVER (Laptop B)                        │   │
│   │                                                     │   │
│   │    → Dit is de backup server                        │   │
│   │    → Neemt automatisch over bij crash Primary       │   │
│   │    → IP wordt: 192.168.1.101                        │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   [ Bevestigen ]                                            │
│                                                             │
│   ⚠️  Let op: Kies elke rol maar op 1 laptop!              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Configuratie Opslag

Na keuze wordt de rol opgeslagen in `config/local-server.php`:

```php
return [
    'role' => 'primary',  // of 'standby'
    'ip' => '192.168.1.100',
    'configured_at' => '2026-01-31 10:00:00',
    'device_name' => 'Laptop A - Jurytafel',
];
```

### Validatie

Bij elke start controleert het systeem:
1. Is er al een config? → Gebruik opgeslagen rol
2. Komt IP overeen? → Waarschuwing als IP veranderd is
3. Is Primary al online? → Standby detecteert dit automatisch

### Rol Wijzigen

Via het instellingen menu kan de rol gewijzigd worden:
- **Noodplan → Server Instellingen → Rol Wijzigen**
- Vereist herstart van de server

---

## 11. Implementatie Roadmap

### Fase 1a: Database Download + Offline Print (MVP)

- [ ] Database download/export functie
- [ ] Offline matrix print vanuit localStorage
- [ ] Handleiding voor organisatoren

> **Voordeel:** Snel waarde, geen PHP installatie nodig op client

### Fase 1b: Lokale Server Launcher

- [ ] Laravel Herd installatie (Windows + Mac)
- [ ] Server rol configuratie scherm
- [ ] Dubbelklik-start launcher

### Fase 2: Hot Standby

- [ ] Sync API tussen Primary en Standby
- [ ] Heartbeat monitoring
- [ ] Standby status indicator
- [ ] Failover documentatie

### Fase 3: Enterprise Features

- [ ] Automatic failover (optioneel)
- [ ] Health dashboard
- [ ] Pre-flight check wizard
- [ ] Audit logging

---

## 12. Technische Specificaties

### 12.1 Hardware Vereisten (Primary & Standby Laptops)

| Component | Minimum | Aanbevolen |
|-----------|---------|------------|
| **Besturingssysteem** | Windows 10 / macOS 10.15 | Windows 11 / macOS 13+ |
| **Processor** | Dual-core 2.0 GHz | Quad-core 2.5 GHz+ |
| **Werkgeheugen** | 4 GB RAM | 8 GB RAM |
| **Opslag** | 10 GB vrij (HDD) | 20 GB vrij (SSD) |
| **Wifi** | 2.4 GHz 802.11n | 5 GHz 802.11ac |
| **Accuduur** | 2 uur | 4+ uur |

**Praktisch:**
- Elke laptop van na 2018 voldoet
- Chromebooks werken NIET (geen PHP ondersteuning)
- Tablets werken NIET als server (alleen als client)

**Geschikte laptops (voorbeelden):**
- HP ProBook / EliteBook
- Lenovo ThinkPad
- Dell Latitude
- MacBook Air / Pro
- Acer Aspire (zakelijke versies)

### 12.2 Software Vereisten

```
- PHP 8.2 of hoger
- Composer
- Node.js 18+ (voor assets)
- SQLite 3
- Browser: Chrome 90+ (aanbevolen)
```

> **Let op:** Gebruik Laravel Herd voor lokale server - gratis, cross-platform, installeert PHP automatisch.

### 12.4 Netwerk Vereisten

```
- Deco M4 of vergelijkbaar mesh systeem
- Minimaal 2.4GHz wifi (5GHz optioneel)
- DHCP of statische IP configuratie
- Geen internet vereist voor lokale werking
```

### 12.5 Browser Compatibiliteit

| Browser | Minimum | Aanbevolen |
|---------|---------|------------|
| Chrome | 90+ | Latest |
| Safari | 14+ | Latest |
| Edge | 90+ | Latest |
| Firefox | 90+ | Latest |

> **Aanbeveling:** Gebruik Chrome voor beste compatibiliteit met print functies.

---

## 12. Architectuurbeslissingen

| Vraag | Beslissing | Reden |
|-------|------------|-------|
| **PHP bundelen** | Laravel Herd | Gratis, cross-platform, makkelijkste voor leken |
| **IP failover** | Deco IP reservation switch | Standby krijgt IP van Primary via Deco app, tablets hoeven niks te wijzigen |
| **Database** | SQLite lokaal, MySQL cloud | Code is al database-agnostic (Eloquent) |
| **Conflict resolution** | Last-write-wins | Wedstrijden worden lokaal ingevoerd, cloud sync is alleen backup |
| **MVP scope** | Splitsen in 1a en 1b | Fase 1a (download+print) geeft snel waarde zonder PHP op client |

---

## 13. FAQ

### Kan ik ook alleen online werken?

Ja, de lokale modus is een **uitbreiding**, geen vervanging. Je kunt gewoon via internet werken zoals altijd, met de lokale servers als automatische backup.

### Hoeveel devices kan het mesh netwerk aan?

De Deco M4 ondersteunt stabiel 75-100 devices. Voor een toernooi met ~50 kritieke devices is dit ruim voldoende.

### Wat als er 500 bezoekers zijn?

Bezoekers gebruiken hun eigen 4G/5G data om de publieke website te bezoeken. Zij gaan niet via het lokale mesh netwerk.

### Hoe snel is de failover?

- Automatisch: ~10 seconden (met heartbeat)
- Handmatig: ~2-3 minuten (organisator start backup)

### Hoeveel data kan verloren gaan?

Maximaal 5 seconden aan wedstrijdresultaten. Dit zijn typisch 0-1 wedstrijden.

---

## 14. Contact

Bij vragen over dit veiligheidsplan:
- **Technisch:** Havun (havun22@gmail.com)
- **Platform:** judotournament.org
