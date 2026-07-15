---
title: Poule verplaatsen, toevoegen en verwijderen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Poule verplaatsen, toevoegen en verwijderen

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## Poule Verplaatsing / Toevoegen

### Poule verplaatst naar andere mat

De hoofdjury verplaatst een poule/groep vanuit het zaaloverzicht (bv. als de mat
ernaast leeg valt). Gedrag van de kleurbeurten op de **oude** mat:

| Kleur | Gedrag |
|-------|--------|
| **Groen** | **Blijft staan** — de lopende/geselecteerde partij maakt af op de oude mat; scorebord + LCD blijven 'm tonen |
| **Geel** | Vervalt (gereset), blauw → geel |
| **Blauw** | Vervalt (gereset) |

**Waarom groen blijft staan:** het systeem weet niet of de partij al fysiek
gestart is (het `match.start`-event wordt alleen gebroadcast, niet opgeslagen).
Een gestarte partij mag je niet van de mat trekken. De uitslag landt sowieso op
het juiste `wedstrijd_id` (de scorebord-app houdt dat vast), ongeacht op welke mat
de groep nu staat. Was de partij **nog niet gestart**? Dan zet de jury groen
handmatig uit — die knop vraagt bevestiging (*"Groene wedstrijd stoppen?"*) én
notificeert app + LCD correct.

**Groep-bewust (eliminatie A/B-split):** verplaats je alleen **groep B**, dan
blijven de kleurbeurten van **groep A** ongemoeid — ook al delen ze hetzelfde
`poule_id`. `Mat::resetWedstrijdSelectieVoorPoule($pouleId, $groep)` filtert op de
`groep` van de wedstrijd. De nieuwe mat krijgt geen automatische selectie; die
tafeljury wijst zelf aan.

> **Code:** `BlokSprekerController::verplaatsPoule` → `Mat::resetWedstrijdSelectieVoorPoule`.
> Groen wordt nooit door deze methode gereset.

### Poule toegevoegd aan mat

- Geen automatische selectie
- Mat-jury klikt handmatig op gewenste wedstrijden

### Poule verwijderd van mat

- Als groene wedstrijd van deze poule was: groen = null, geel → groen, blauw → geel
- Als gele wedstrijd van deze poule was: geel = null, blauw → geel
- Als blauwe wedstrijd van deze poule was: blauw = null

---

