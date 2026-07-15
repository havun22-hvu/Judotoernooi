---
title: Architectuurdiagram
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Architectuurdiagram

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

