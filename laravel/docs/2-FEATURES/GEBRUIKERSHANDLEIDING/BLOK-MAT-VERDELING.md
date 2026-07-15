---
title: Blok/Mat Verdeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blok/Mat Verdeling

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Blok/Mat Verdeling

### Doel van de Blokverdeling

De blokkenpagina heeft twee hoofddoelen:

1. **Gelijkmatige verdeling** - Elk tijdsblok krijgt ongeveer evenveel wedstrijden
2. **Aansluiting gewichtscategorieën** - Opeenvolgende gewichtsklassen (-27, -30, -34, -38) in dezelfde of aansluitende blokken plaatsen

**Waarom aansluiting belangrijk is:**
Judoka's die te zwaar wegen worden overgepouled naar een zwaardere gewichtsklasse. Als die klasse in hetzelfde of volgend blok zit, hoeven ze niet lang te wachten.

**Aansluiting regels:**
| Overgang | Beoordeling |
|----------|-------------|
| Zelfde blok | Perfect |
| +1 blok (volgend) | Perfect |
| +2 blokken | Acceptabel |
| -1 blok (terug) | Slecht - blok is al geweest! |
| +3+ blokken | Slecht - te lang wachten |

### Interface Layout

De blokkenpagina bestaat uit twee delen:

**Links - Blokken:**
- Sleepvak met niet-verdeelde categorieën
- Blok 1-6 met toegewezen categorieën (chips)
- Per blok: actueel aantal wedstrijden en afwijking van gewenst

**Rechts - Overzicht Panel:**
- Per leeftijdsklasse alle gewichtscategorieën
- Bloknummer per categorie (voor beoordeling aansluiting)
- Kleurcodes: groen=vast, blauw=berekend, rood=niet verdeeld

### Varianten Berekenen

1. Klik **Bereken** - systeem genereert 5 varianten
2. Varianten verschijnen onder de blokken
3. Elke variant toont: `#1 ±14 / 3b`
   - ±14 = maximale afwijking van gemiddelde
   - 3b = aantal "breaks" (slechte overgangen: +2, -1, of +3+ blokken)
4. Klik op variant om preview te zien
5. **Overzicht panel update direct** - check aansluiting gewichten
6. Klik **Zet op Mat** om gekozen variant toe te passen

**Balans slider:**
```
Verdeling blokken |--------------------| Aansluiting gewichten
```
- **Links (0)**: Focus op gelijke verdeling wedstrijden over blokken
- **Rechts (100)**: Focus op aansluiting gewichtscategorieën (voor overpoelen)
- **Midden (50)**: Gebalanceerd
- Pas slider aan en klik opnieuw op **Bereken** voor nieuwe varianten

### Handmatig Aanpassen

- **Drag & drop** - Sleep categorie naar ander blok
- **Vastzetten** - Klik 📌 om categorie vast te zetten (wordt niet door berekening verplaatst)
- Bij elke wijziging updaten:
  - Blok totalen en afwijkingen
  - Overzicht panel bloktoewijzingen

### Zaaloverzicht & Mat Toewijzing

Na "Verdeel over matten" gaat de organisator naar het **Zaaloverzicht**:

1. **Automatische verdeling** - Poules zijn automatisch over matten verdeeld
2. **Aanpassen** - Sleep poules naar andere mat indien gewenst
3. **Balanceren** - Check dat elke mat ongeveer evenveel wedstrijden heeft
4. **Controleren** - Bekijk de verdeling voor elk blok

**Let op:** Poules kunnen altijd verplaatst worden naar andere matten via drag & drop in Zaaloverzicht.

### Einde Voorbereiding

Na het verdelen van poules over matten kun je de knop **"Einde Voorbereiding"** klikken in het Zaaloverzicht.

**Wat doet deze knop?**

1. **Controleert** of alle judoka's een poule, blok en mat hebben
2. **Herberekent coachkaarten** per club (op basis van grootste blok)
3. **Publiceert blok/mat info** op weegkaarten in coach portals

**Belangrijk over weegkaarten:**
- **Vóór "Einde Voorbereiding"**: Weegkaarten tonen "Indeling wordt later bekendgemaakt"
- **Na "Einde Voorbereiding"**: Weegkaarten tonen blok (als >1 blok) + mat + weegtijden

Dit voorkomt verwarring bij coaches over voorlopige indelingen. Je kunt de indeling altijd nog wijzigen na het afronden - de knop blokkeert geen functionaliteit.

**Na afronden kun je nog steeds:**
- Poules verplaatsen naar andere matten (drag & drop)
- Terug naar blokkenverdeling
- Alle andere voorbereidingsacties

### Weegkaarten

Weegkaarten zijn **dynamisch** en tonen altijd de actuele stand:
- Naam en club
- QR-code (voor scanner bij weging)
- **Blok nummer** + weegtijden (alleen na "Einde Voorbereiding", en alleen als >1 blok)
- **Mat nummer** (alleen na "Einde Voorbereiding")
- Gewichtsklasse, geboortejaar, band, geslacht

**Vereisten voor weegkaarten:**
1. "Valideer judoka's" uitgevoerd → QR-codes aangemaakt
2. Blokverdeling gedaan → blokken toegewezen
3. "Verdeel over matten" gedaan → matten toegewezen
4. **"Einde Voorbereiding"** → blok/mat info zichtbaar voor coaches

**Belangrijk:** Weegkaarten worden NIET als bestanden opgeslagen. Ze worden live gegenereerd bij openen. Wijzigingen in blok/mat zijn direct zichtbaar (na "Einde Voorbereiding").

### Zaaloverzicht

Het Zaaloverzicht toont per blok en mat:
- Welke poules waar staan (automatisch verdeeld, kan aangepast worden)
- Aantal wedstrijden per mat
- Status van de weging

Sleep poules naar andere matten om te verplaatsen.

