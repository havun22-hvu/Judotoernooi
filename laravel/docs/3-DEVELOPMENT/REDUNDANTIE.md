---
title: Redundantie & offline draaien
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Redundantie & offline draaien

> Hoe een toernooi blijft draaien zonder internet: lokale primary/standby servers,
> mesh netwerk, cloud-sync als bonus en het beschermde offline pakket.
> **Dit is een index-doc** — het kernprincipe staat hieronder, de details in de deeldocs.

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

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [OFFLINE-PAKKET.md](REDUNDANTIE/OFFLINE-PAKKET.md) | Je werkt aan de download-flow, license keys, de gecompileerde player app of anti-piraterij. |
| [ARCHITECTUUR.md](REDUNDANTIE/ARCHITECTUUR.md) | Je wilt het volledige plaatje zien: cloud layer, primary/standby laptops en het Deco M4 mesh netwerk. |
| [SYNC-EN-FAILOVER.md](REDUNDANTIE/SYNC-EN-FAILOVER.md) | Je raakt cloud↔lokaal of primary↔standby sync, of wilt weten wat er gebeurt bij internet-uitval of een crash. |
| [DEVICES-EN-PREFLIGHT.md](REDUNDANTIE/DEVICES-EN-PREFLIGHT.md) | Je moet weten welke devices kritiek zijn, of je loopt de checks van de dag vóór het toernooi af. |
| [DISASTER-RECOVERY.md](REDUNDANTIE/DISASTER-RECOVERY.md) | Er gaat iets stuk tijdens het toernooi, of je bouwt aan het health dashboard. |
| [SERVER-ROL.md](REDUNDANTIE/SERVER-ROL.md) | Je werkt aan het opstartscherm, de rol-keuze (primary/standby), de opslag daarvan of het wisselen van rol. |
| [ROADMAP.md](REDUNDANTIE/ROADMAP.md) | Je wilt weten wat er in fase 1a/1b, hot standby of enterprise zit en wat er nog niet gebouwd is. |
| [SPECIFICATIES.md](REDUNDANTIE/SPECIFICATIES.md) | Je moet hardware, software, netwerk of browser-eisen kennen, of het waarom achter een architectuurbeslissing. |
| [FAQ.md](REDUNDANTIE/FAQ.md) | Snelle antwoorden: alleen online werken, aantal devices, failover-snelheid, maximaal dataverlies. Plus contact. |
