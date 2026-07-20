---
title: Organisator mobiel (responsive)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Organisator mobiel (responsive)

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Organisator Mobiel (Responsive Dashboard)

> **Concept:** Organisator loopt door de zaal en lost problemen op via smartphone. Gerealiseerd via responsive `WedstrijddagMobielController` + `toernooi/mobiel.blade.php`.

### Wat is het?

Geen aparte PWA — het bestaande dashboard met een **mobiele modus** die quick-action functies toont op kleine schermen. De organisator is al ingelogd via email+wachtwoord.

### Quick-actions (wedstrijddag)

| Functie | Beschrijving |
|---------|--------------|
| **Judoka Zoeken** | Zoek op naam/club → gewicht zien/invullen → poule bekijken → overpoulen |
| **Judoka Toevoegen** | Aan bestaande poule toevoegen (last-minute, vergeten). **Naam, geslacht, gewicht en poule zijn verplicht** — zie hieronder |
| **Mat Voortgang** | Resterende wedstrijden per mat + per poule op die mat |
| **Chat** | Berichten naar mat/weging/dojo (bestaand chat systeem) |

### Judoka toevoegen — naam, geslacht, gewicht en poule zijn verplicht

`wedstrijddag/nieuwe-judoka` koppelt de judoka **meteen** aan een poule (`poule_id` is required),
dus alles waar de indeling van afhangt moet er op dat moment zijn:

| Veld | Waarom verplicht |
|------|------------------|
| `geslacht` (`in:M,V`) | Gewichtsklasse-bepaling én poule-indeling zijn geslachtsafhankelijk |
| `gewicht` (`numeric\|min:10\|max:200`) | **Niet elk toernooi heeft een weging**, dus een leeg gewicht wordt nooit alsnog ingevuld |

Het endpoint weigert met **422** in plaats van de judoka half aan te maken. Beide formulieren
(`toernooi/mobiel.blade.php`, `wedstrijddag/poules.blade.php` → "Laatkomer") tonen de velden als
verplicht en blokkeren de submit.

> **Onvolledige judoka's mogen wél bestaan** — via import en het clubportaal, gemarkeerd met
> `is_onvolledig`. Die worden pas ingedeeld nadat ze zijn aangevuld. Een directe toevoeging op de
> mat is het enige pad waar de indeling onmiddellijk volgt, en daar is aanvullen-achteraf dus geen
> optie. Zie `Judoka::isVolledig()` / `getOntbrekendeVelden()` voor de volledige eis
> (naam, geboortejaar, geslacht, band, gewicht).

### UX: Verwijzing naar volledige app

Op de mobiele view wordt prominent getoond:
> "Volledige voorbereiding? Open de app op tablet of PC voor alle functies."

Dit voorkomt verwarring — de smartphone is voor quick-fixes op de wedstrijddag, niet voor volledige toernooi voorbereiding.

### Buiten scope

- Volledige poule-indeling (te complex voor telefoon → laptop)
- Toernooi instellingen (eenmalig, doe je vooraf)
- Eliminatie bracket beheer (te complex voor klein scherm)
- Spreker interface (bestaat al als aparte PWA)

### Route & Bestanden

```
Route:  /{organisator}/toernooi/{toernooi}/wedstrijddag/mobiel
View:   pages/toernooi/mobiel.blade.php
API's:  wedstrijddag/mat-voortgang (GET), wedstrijddag/poules-api (GET)
        + hergebruik: judoka.zoek, weging.registreer, wedstrijddag.verplaats-judoka, wedstrijddag.nieuwe-judoka
```

### Authenticatie

Bestaande organisator login — geen device binding, geen aparte auth.

---

