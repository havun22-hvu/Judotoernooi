---
title: Disaster recovery & health dashboard
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Disaster recovery & health dashboard

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

