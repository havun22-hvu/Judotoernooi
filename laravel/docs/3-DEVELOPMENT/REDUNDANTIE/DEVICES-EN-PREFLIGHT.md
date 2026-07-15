---
title: Device overzicht & pre-flight checklist
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Device overzicht & pre-flight checklist

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

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

