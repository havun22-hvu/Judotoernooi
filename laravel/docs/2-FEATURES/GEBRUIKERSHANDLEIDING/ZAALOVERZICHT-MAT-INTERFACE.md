---
title: Zaaloverzicht, Activatie & Mat Interface
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Zaaloverzicht, Activatie & Mat Interface

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Zaaloverzicht & Activatie

### Chip Kleuren in Zaaloverzicht

Per blok zie je chips voor elke categorie:

| Chip kleur | Betekenis | Actie |
|------------|-----------|-------|
| **Grijs** | Niet doorgestuurd | Ga naar Wedstrijddag Poules |
| **Wit** | Doorgestuurd, klaar voor activatie | Klik om te activeren |
| **Groen** ✓ | Geactiveerd (wedstrijden op mat) | Klik voor mat interface |

### Categorie Activeren

Klik op een **witte chip** om te activeren:

1. Wedstrijdschema wordt gegenereerd per poule (matten zijn al toegewezen in voorbereiding)
2. Alleen actieve judoka's komen in schema (afwezigen worden automatisch overgeslagen)
3. Chip wordt **groen** met ✓
4. Categorie is nu klaar voor de mat

**Let op:** Afwezige judoka's (niet gewogen na sluiting weegtijd) en judoka's buiten gewichtsklasse worden NIET meegenomen in het wedstrijdschema!

### Poules Verplaatsen

Na activatie kun je poules nog verplaatsen tussen matten:
1. Sleep poule naar andere mat
2. Wedstrijden tellen worden automatisch bijgewerkt

## Mat Interface

### Wedstrijden Beheren

1. Ga naar **Matten** > **Interface**
2. Selecteer blok en mat
3. Per poule zie je:
   - Lijst van judoka's met club
   - Wedstrijdschema in optimale volgorde
   - Status (gespeeld/niet gespeeld)

### Wedstrijd Selectie (3-Kleurensysteem)

Om te communiceren welke wedstrijden gespeeld worden, gebruikt het systeem een 3-kleurensysteem. Klik op een wedstrijd om deze te selecteren:

| Kleur | Betekenis | Actie voor judoka's |
|-------|-----------|---------------------|
| **Groen** | Wedstrijd speelt NU | Naar de mat komen |
| **Geel** | Judoka's staan KLAAR | Klaar staan naast de mat |
| **Blauw** | Judoka's moeten GEREEDMAKEN | Alvast opwarmen/voorbereiden |

**Hoe het werkt:**
- Klik op een ongeselecteerde wedstrijd → wordt eerst **groen**, dan **geel**, dan **blauw**
- Maximaal 3 wedstrijden tegelijk geselecteerd (1 groen, 1 geel, 1 blauw)
- Klik op een geselecteerde wedstrijd om te deselecteren
- Bij deselectie groen: geel schuift door naar groen, blauw naar geel
- Na uitslag registreren: automatisch doorschuiven (geel → groen, blauw → geel)

**Voor spreker/publiek:**
De kleuren zijn ook zichtbaar op het publieke scorebord en in de spreker interface, zodat de spreker de juiste judoka's kan oproepen.

### Uitslag Registreren (Puntensysteem)

Per wedstrijd voer je de score in via de matrix (WP en JP kolommen):

| JP invoer | Betekenis | WP resultaat |
|-----------|-----------|-------------|
| **Blanco** (leeg) | Niet gespeeld | Blanco (geen WP) |
| **0** | Gelijkspel | Beide WP = 1 |
| **5** (yuko) | Winnaar | Winnaar WP = 2, verliezer WP = 0 |
| **7** (waza-ari) | Winnaar | Winnaar WP = 2, verliezer WP = 0 |
| **10** (ippon) | Winnaar | Winnaar WP = 2, verliezer WP = 0 |

**Belangrijk:** Blanco JP ≠ 0 JP. Blanco = niet gespeeld, 0 = gelijkspel (geen judopunten behaald, wel 1 wedstrijdpunt).

**Tip:** JP invoer bepaalt automatisch de WP. WP kan ook handmatig aangepast worden, maar JP is leidend.

### Blessure / Uitval tijdens Toernooi

**Scenario 1: Blessure TIJDENS een wedstrijd**
- Tegenstander wint met ippon (10 JP)
- Resterende wedstrijden van de geblesseerde judoka: geef de tegenstander JP = 10 (ippon door opgave/fusen-gachi)
- Dit is handwerk per wedstrijd

**Scenario 2: Uitval VOOR de eerste wedstrijd (bv. blessure bij warming-up)**
1. Ga naar **Wedstrijddag Poules** → zet judoka op **afwezig**
2. Ga naar **Zaaloverzicht** → reset het wedstrijdschema voor die poule (chip terug naar wit)
3. Klik de **witte chip** opnieuw → nieuw wedstrijdschema wordt gegenereerd zonder de afwezige judoka
4. Poule speelt verder met overgebleven judoka's

**Let op:** De voorbereiding (poule-indeling) blijft gesloten. Je reset alleen het wedstrijdschema, niet de poule zelf.

### Stand Bijhouden

De poulestand wordt automatisch bijgewerkt:
- Hoogste winstpunten (WP)
- Hoogste judopunten (JP)
- Bij gelijke WP + JP: onderlinge wedstrijd

### Poule Afronden

Wanneer alle wedstrijden in een poule gespeeld zijn:
1. Klik **Poule Klaar**
2. Poule wordt naar spreker gestuurd voor prijsuitreiking

