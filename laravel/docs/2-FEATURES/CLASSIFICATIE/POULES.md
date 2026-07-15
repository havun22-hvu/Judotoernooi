---
title: Poulegrootte & Poule Titels
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Poulegrootte & Poule Titels

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Poulegrootte Verdeling

### Voorkeur Algoritme

Gegeven `poule_grootte_voorkeur = [5, 4, 6, 3]`:

| Aantal | Verdeling | Uitleg |
|--------|-----------|--------|
| 8 | [4, 4] | Twee gelijke (niet 5+3) |
| 9 | [5, 4] | Ideaal + goed |
| 10 | [5, 5] | Twee ideale |
| 11 | [6, 5] of [4, 4, 3] | Afhankelijk van 6 vs 3 voorkeur |
| 12 | [4, 4, 4] | Drie gelijke |
| 15 | [5, 5, 5] | Drie ideale |
| 20 | [5, 5, 5, 5] | Vier ideale |

### Harde Constraints

| Constraint | Breekbaar? |
|------------|------------|
| max_kg_verschil | Nee, nooit |
| max_leeftijd_verschil | Nee, nooit |
| Poulegrootte 3-6 | **Ja, poule van 1-2 toegestaan** |
| Geslacht (indien apart) | Nee, nooit |

### Verificatie Poule Grootte per Type

De "Verifieer poules" knop controleert grootte per poule type:

| Poule Type | Min | Max | Foutmelding |
|------------|-----|-----|-------------|
| **Normaal** | 3 | 6 | "X judoka's (min. 3)" of "X judoka's (max. 6)" |
| **Eliminatie** | 8 | ∞ | "X judoka's (min. 8 voor eliminatie)" |
| **Kruisfinale** | - | - | Geen grootte validatie |

**Code:** `PouleController::verifieer()` - regels 254-284

### Orphan Judoka's (poule van 1)

**Belangrijk:** Een judoka die geen gewichtsmatch heeft met anderen wordt
WEL ingedeeld in de juiste categorie, maar dan in een poule van 1.

Voorbeeld:
- Fleur (11j, 24.7kg) past in categorie "Jeugd" (t/m 14 jaar)
- Geen andere judoka binnen 3kg verschil
- → Fleur komt in poule van 1 binnen categorie "Jeugd"
- → Organisator kan haar handmatig verplaatsen of constraint aanpassen

Dit voorkomt "niet ingedeeld" meldingen voor judoka's die WEL in een
categorie passen maar geen gewichtsmatch hebben.

### Niet-Gecategoriseerde Judoka's (configuratie probleem)

**Belangrijk:** Dit is iets ANDERS dan orphan judoka's!

| Type | Oorzaak | Oplossing |
|------|---------|-----------|
| **Niet gecategoriseerd** | Geen categorie past (leeftijd/geslacht/band) | Config aanpassen |
| **Orphan (poule van 1)** | Wel categorie, geen gewichtsmatch | Handmatig of max_kg aanpassen |

**Melding "Niet gecategoriseerd":**
- Locatie: **Bovenaan Instellingen pagina** (niet bij Poules!)
- Stijl: Knipperende rode melding (10 sec)
- Triggers:
  1. Na opslaan instellingen (categorie config gewijzigd)
  2. Na import/validatie judoka's
  3. Bij laden instellingen pagina (als er niet-gecategoriseerde zijn)
- Inhoud: Aantal + link naar lijst

---

## Poule Titels

### Opbouw (algemene regel)

```
#nummer Label / Leeftijd / Gewicht
```

| # | Component | Tonen wanneer | Voorbeeld |
|---|-----------|---------------|-----------|
| 1 | **Poule #** | Altijd (in UI prefix) | `#1`, `#5` |
| 2 | **Label** | `toon_label_in_titel = true` | `Mini's`, `Jeugd` |
| 3 | **Leeftijd** | `max_leeftijd_verschil > 0` | `4j`, `9-10j` |
| 4a | **Gewichtsklasse** | `max_kg_verschil = 0` (vaste klassen) | `-26kg` |
| 4b | **Gewichtsrange** | `max_kg_verschil > 0` (variabel) | `25-27kg` |

### Voorbeelden

```
#1 Mini's / 4j / -26kg     ← label aan, lft_verschil>0, vaste kg klasse
#2 Mini's / -26kg          ← label aan, lft_verschil=0 (geen lft), vaste kg
#3 Jeugd / 9-10j / 28-32kg ← label aan, beide variabel (ranges)
#4 9-10j / 28-32kg         ← label uit, beide variabel
#5 Mini's                  ← label aan, lft_verschil=0, geen gewichtsklassen
```

### Waar wordt de titel gebruikt?

| Locatie | Format | Voorbeeld |
|---------|--------|-----------|
| **Poule pagina** | `#nummer Titel` | `#1 Mini's / 4j / -26kg` |
| **Wedstrijddag poules** | `#nummer Titel` | `#1 Mini's / 4j / -26kg` |
| **Blokverdeling chips** | `#nummer Afkorting` | `#1 M` (1e letter van label) |
| **Zaaloverzicht** | `#nummer Afkorting` | `#1 M` (1e letter van label) |
| **Wedstrijdschema's** | Volledige titel | `Mini's / 4j / -26kg` |
| **Publieke app** | Volledige titel | `Mini's / 4j / -26kg` |
| **Spreker interface** | Volledige titel | `Mini's / 4j / -26kg` |
| **Mat interface** | Volledige titel | `Mini's / 4j / -26kg` |

### Regels

- Als `max_leeftijd_verschil = 0`: leeftijd niet tonen (organisator zet het in label)
- Als `max_kg_verschil = 0` MET vaste gewichtsklassen: toon de klasse (bijv. `-26kg`)
- Als `max_kg_verschil = 0` ZONDER gewichtsklassen: geen gewicht tonen
- Als `max_kg_verschil > 0`: toon live berekende range uit judoka's

### Code locatie

- `Poule::getDisplayTitel()` - centrale methode voor titel generatie
- Gebruik altijd deze methode, niet zelf titel samenstellen

---

