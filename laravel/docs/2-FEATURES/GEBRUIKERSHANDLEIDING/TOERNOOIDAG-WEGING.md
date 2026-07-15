---
title: Toernooidag & Weging
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Toernooidag & Weging

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Toernooidag

De toernooidag begint waar de voorbereiding eindigt. Nu worden judoka's daadwerkelijk gewogen en kunnen afwijkingen optreden.

### Dagflow per Blok

1. **Weging** - Judoka's wegen zich in
2. **Einde weegtijd** - Blok sluiten voor weging
3. **Overpoelen** - Te zware/lichte judoka's verplaatsen
4. **Poules controleren** - Afwezigen afhandelen
5. **Naar zaaloverzicht** - Categorie klaarzetten
6. **Wedstrijdschema genereren** - Per categorie
7. **Naar mat** - Wedstrijden kunnen beginnen

## Weging

### Gewichtsvelden: Ingeschreven vs Gewogen

Het systeem houdt **twee aparte gewichten** bij per judoka:

| Veld | Wanneer | Verandert |
|------|---------|-----------|
| `gewicht` | Ingeschreven gewicht (voorbereiding) | Alleen bij import/handmatige wijziging |
| `gewicht_gewogen` | Gemeten gewicht (wedstrijddag) | Bij elke weging |

**Waar wordt welk gewicht getoond?**

| Pagina | Toont | Reden |
|--------|-------|-------|
| **Poules** (voorbereiding) | `gewicht` (ingeschreven) | Voorbereiding = plan gebaseerd op inschrijving |
| **Blokverdeling** | `gewicht` (ingeschreven) | Idem |
| **Zaaloverzicht** | `gewicht` (ingeschreven) | Idem |
| **Weeglijst** | `gewicht` (ingeschreven) | Idem |
| **Wedstrijddag Poules** | `gewicht_gewogen` of `gewicht` | Toont actueel gewogen gewicht |
| **Afwijkend in poule** | `gewicht_gewogen` | Judoka's gemarkeerd die overgepouled moeten worden |

**Belangrijk:** De voorbereidingsviews veranderen NOOIT op basis van weging. Alleen de **Wedstrijddag Poules** pagina toont mutaties.

### Weging Interface (Admin/Hoofdjury)

1. Ga naar **Weging** > **Weging Interface**
2. Zie live overzicht van alle judoka's met weegstatus
3. Zoek op naam of club
4. Filter per blok of status (gewogen/niet gewogen)
5. Tabel toont: Naam, Club, Leeftijd, Gewicht, Blok, Gewogen, Tijd

### Weging Scanner (Vrijwilliger PWA)

1. Open de toegangs-URL (via Instellingen → Organisatie). Bij de eerste keer openen wordt het device automatisch gekoppeld — geen PIN nodig.
2. **Zoeken**: Typ naam of scan QR-code
3. **Registreren**: Vul gewicht in via numpad en bevestig

### Gewichtscontrole

Het systeem controleert automatisch:
- **Ondergrens**: Vorige gewichtsklasse + tolerantie
- **Bovengrens**: Eigen gewichtsklasse + tolerantie

Bij afwijking:
- Judoka **blijft in poule** maar wordt gemarkeerd (rode stip/badge "afwijkend gewicht")
- Organisator verplaatst judoka via drag & drop of 🔍 Zoek Match naar juiste gewichtsklasse

### Weging Sluiten

1. Ga naar **Weeglijst Live** > selecteer blok
2. Klik **Blok X: Einde weegtijd**
3. Bevestig

Na sluiten:
- Blok status wordt "Gesloten"
- **Bij verplichte weging:** niet-gewogen judoka's worden automatisch als afwezig gemarkeerd
- **Bij niet-verplichte weging:** geen automatische afmelding, organisator doet dit handmatig
- Overpoelen kan beginnen
- AFWEZIG badges worden nu zichtbaar in de weeglijst

### Automatische Aanwezigheidsbepaling

De aanwezigheidsstatus hangt af van of **weging verplicht** is (toernooi instelling):

**Bij weging verplicht (`weging_verplicht = true`):**

| Situatie | Aanwezigheid | Weergave |
|----------|--------------|----------|
| Judoka is gewogen | **Aanwezig** | Normaal |
| Niet gewogen, weegtijd nog open | Onbekend | Geen badge (neutraal) |
| Niet gewogen, weegtijd gesloten | **Afwezig** (automatisch) | Rode badge + doorgestreept |

Na sluiting weegtijd worden niet-gewogen judoka's **automatisch** als afwezig gemarkeerd.

**Bij weging niet verplicht (`weging_verplicht = false`):**

| Situatie | Aanwezigheid | Weergave |
|----------|--------------|----------|
| Judoka is gewogen | **Aanwezig** | Normaal |
| Niet gewogen | Onbekend | Geen badge (neutraal) |
| Handmatig afgemeld | **Afwezig** | Rode badge + doorgestreept |

Niet-gewogen judoka's worden **NIET** automatisch afwezig. De organisator meldt zelf judoka's af die er niet zijn.

**Belangrijk:**
- Gewogen = per definitie aanwezig (je kunt niet wegen zonder er te zijn)
- **AFWEZIG badge** wordt pas getoond nadat de weegtijd van het blok is gesloten
- Vóór sluiting weegtijd toont de weeglijst geen AFWEZIG status (ongeacht database waarde)
- Afwijkende judoka's worden gemarkeerd in hun poule (alleen aanwezigen)

