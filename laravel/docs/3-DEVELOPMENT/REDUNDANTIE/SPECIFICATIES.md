---
title: Technische specificaties & architectuurbeslissingen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Technische specificaties & architectuurbeslissingen

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

