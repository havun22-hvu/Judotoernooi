---
title: Scenario's: welke setup past bij jouw sporthal
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Scenario's: welke setup past bij jouw sporthal

> Onderdeel van [Noodplan-handleiding](../NOODPLAN-HANDLEIDING.md).

## Scenario's — Welke setup past bij jouw sporthal?

### Altijd (bij elk scenario)
- **Laptops** voor hoofd-apps (mat interface, hoofdjury) — muis is preciezer
- **Tablets** voor vrijwilligers (wegen, dojo check-in, spreker)
- **Papieren backup** — schrijf uitslagen mee per mat
- **Hotspot** van tevoren klaarzetten op telefoon (naam + wachtwoord noteren)

### Scenario A: Goed internet bereik
| | |
|---|---|
| **Netwerk** | Sporthal WiFi |
| **Server** | judotournament.org (online) |
| **Publieke PWA** | Ja (live scores voor publiek) |
| **Vrijwilligers PWA** | Ja (mat, wegen, dojo, spreker) |
| **Eigen Deco** | Optioneel als backup netwerk |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Internet valt weg | Hotspot aan, alle tablets + laptops op hotspot |
| Internet + hotspot onmogelijk | Lokale server starten → scenario C |
| Online server crash | Lokale server starten → scenario C |

### Scenario B: Slecht WiFi bereik
| | |
|---|---|
| **Netwerk** | Eigen lokaal netwerk (Deco's ~60 devices / hubs / LAN-kabels) |
| **Internet** | Via LAN-aansluiting sporthal |
| **Server** | judotournament.org (online) |
| **Publieke PWA** | Nee (beperkte netwerkcapaciteit) |
| **Vrijwilligers PWA** | Ja (mat, wegen, dojo, spreker) |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Deco / eigen netwerk uitval | Herstarten Deco's, of overschakelen op LAN-kabels |
| Online server crash | Lokale server starten → scenario C |

### Scenario C: Geen internet / server crash
| | |
|---|---|
| **Netwerk** | Eigen lokaal netwerk (verplicht) |
| **Server** | Lokale server op primaire laptop |
| **Publieke PWA** | Nee |
| **Vrijwilligers PWA** | Ja, op lokaal IP (bijv. 192.168.1.100:8000) |

**Storingen:**

| Situatie | Actie |
|----------|-------|
| Primaire laptop crash | Standby laptop starten, tablets naar standby IP |
| Standby ook stuk | Papieren backup — verder op papier |

---

