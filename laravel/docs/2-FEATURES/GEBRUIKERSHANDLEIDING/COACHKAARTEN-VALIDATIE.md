---
title: Coachkaarten & Valideer Judoka's
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coachkaarten & Valideer Judoka's

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

### Coachkaarten (Wedstrijdcoaches)

Wedstrijdcoaches krijgen toegangskaarten voor de dojo. Het aantal kaarten wordt in **twee fasen** bepaald:

#### Fase 1: Tijdens Inschrijving

**Elke budoschool krijgt 1 coachkaart** - ongeacht aantal judoka's.

- Bij eerste judoka inschrijving → 1 coachkaart aangemaakt
- Oude/overtollige coachkaarten worden automatisch verwijderd
- Dit voorkomt verwarring tijdens de inschrijfperiode

#### Fase 2: Na Einde Voorbereiding

Organisator klikt **"Genereer Coachkaarten"** na de blokverdeling. Dan wordt het juiste aantal berekend op basis van het **grootste blok** per club.

**Formule:** `ceil(max_judokas_in_grootste_blok / judokas_per_coach)`

| Max judoka's in één blok | Kaarten (bij 5 per coach) |
|--------------------------|---------------------------|
| 1-5 | 1 kaart |
| 6-10 | 2 kaarten |
| 11-15 | 3 kaarten |
| 16-20 | 4 kaarten |

**Voorbeeld:**
- Club met 11 judoka's in grootste blok, 5 per coach
- `ceil(11/5) = 3` coachkaarten

**Waarom per blok?**
Een coach hoeft alleen aanwezig te zijn wanneer zijn judoka's wedstrijden hebben. Als een club 15 judoka's heeft verdeeld over 3 blokken (8, 4, 3), dan zijn er maximaal 8 judoka's tegelijk actief → 2 coachkaarten nodig.

**Coachkaart activatie:**
1. Coach scant QR-code of opent link
2. Vult naam in en maakt pasfoto
3. Kaart wordt gekoppeld aan dit device (device binding)
4. QR-code is alleen zichtbaar op het geactiveerde device

**Instelling wijzigen:**
- Ga naar **Toernooi** > **Bewerken** > **Coach instellingen**
- Pas `judokas_per_coach` aan (bijv. 10 voor grotere groepen)

## Valideer Judoka's (Einde Inschrijving)

Na de sluitingsdatum van de inschrijving:

1. Ga naar **Toernooi** > **Judoka's** > **Valideer**
2. Systeem controleert alle judoka's op:
   - Volledige gegevens (naam, geboortejaar, geslacht, band, gewicht)
   - Correcte gewichtsklasse
3. **QR-codes worden nu aangemaakt** (definitief)
   - Gebaseerd op definitieve naam, band, gewicht
   - `judoka_code` = LLGGBGVV (Leeftijd-Gewicht-Band-Geslacht-Volgnummer)
4. Na validatie kunnen gegevens niet meer door coaches gewijzigd worden

**Belangrijk:** Tot dit moment kunnen coaches nog wijzigingen doorvoeren. Na validatie zijn de gegevens definitief.

