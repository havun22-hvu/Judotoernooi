---
title: Scorebord-app
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Scorebord-app

> De Android-app (apart project: `D:\GitHub\JudoScoreBoard`) bedient het scorebord; de backend en
> het LCD/TV-display zitten hier. Realtime via Reverb, geen polling.
> **Index-doc** — de details staan in [`SCOREBORD/`](SCOREBORD/). Zie de wegwijzer onderaan.

## Overzicht

Standalone Android app voor het bedienen van een judo scorebord (timer, scores, shido's, osaekomi).
Verbonden met JudoToernooi via API — ontvangt wedstrijden en stuurt uitslagen automatisch terug.

### Twee interfaces

| Interface | Device | Type | Functie |
|-----------|--------|------|---------|
| **Bediening** | Tablet, smartphone | Android APK | Alle knoppen: timer, score, shido, osaekomi |
| **Display** | TV, LCD, projector | Web (Blade + Reverb) | Alleen weergave, geen knoppen, **gespiegeld** |

### Spiegeling (IJF standaard)

De bediening en display zijn **gespiegeld** — de tafelofficial kijkt naar de mat,
de scheidsrechter kijkt naar de tafel. Wat links is voor de een, is rechts voor de ander.

| Interface | Links | Rechts |
|-----------|-------|--------|
| **Bediening** (tafelofficial) | Blauw | Wit |
| **Display** (scheidsrechter/publiek) | Wit | Blauw |

### Bediening: responsive (tablet + smartphone)

| Device | Layout |
|--------|--------|
| **Tablet** (10"+) | Volledig, ruim, alle info zichtbaar |
| **Smartphone** (5-7") | Compact, alles op 1 scherm, geen scrollen |

Beide landscape, zelfde functionaliteit, andere layout.

### Flow MET scorebord

```
Mat interface → "Groen" (actieve wedstrijd) → Scorebord app ontvangt wedstrijd
→ Scheidsrechter speelt wedstrijd op scorebord
→ Scorebord stuurt uitslag terug → Mat interface verwerkt automatisch
→ Groen vervalt, winnaar ingevuld
```

### Flow ZONDER scorebord (backward compatible)

```
Mat interface → Handmatig winnaar selecteren (wit/blauw)
→ Alles werkt zoals nu, niks verandert
```

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [SCORING-REGELS](SCOREBORD/SCORING-REGELS.md) | Wat telt als ippon/waza-ari/shido en wanneer een partij eindigt. |
| [ARCHITECTUUR](SCOREBORD/ARCHITECTUUR.md) | Hoe app, backend en display samenhangen — begin hier bij nieuw werk. |
| [DISPLAY-VIEW](SCOREBORD/DISPLAY-VIEW.md) | De publieke LCD-view zelf (Blade, CSP, spiegeling). |
| [REVERB-EVENTS](SCOREBORD/REVERB-EVENTS.md) | Welke events over de lijn gaan en hoe je erop bindt. |
| [AWASETE-IPPON](SCOREBORD/AWASETE-IPPON.md) | De 2e-waza-ari-waarschuwing tijdens osaekomi. |
| [BESTANDEN](SCOREBORD/BESTANDEN.md) | Welke Laravel-bestanden erbij horen + app-vereisten. |
| [TV-LCD-URLS](SCOREBORD/TV-LCD-URLS.md) | Korte vs volledige URL, de havun.nl/tv-redirect, en de mat-rij in Device Toegangen (waarom de QR alleen bij Mat staat). |

## Openstaand

- **LCD proporties voor TV (3-10m leesbaarheid):** timer ~15-20vw, grotere cijfers Y/W/I, namen op afstand leesbaar, shido-kaarten groter, vw/vh units voor 1920x1080.

