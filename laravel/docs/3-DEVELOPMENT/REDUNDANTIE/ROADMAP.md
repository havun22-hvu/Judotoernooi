---
title: Implementatie roadmap
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Implementatie roadmap

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

