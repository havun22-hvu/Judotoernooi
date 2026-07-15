---
title: Architectuur diagram & componenten
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Architectuur diagram & componenten

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

