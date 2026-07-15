---
title: Sync mechanisme & failover scenario's
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Sync mechanisme & failover scenario's

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

