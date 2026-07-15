---
title: Overpoelen (Wedstrijddag Poules)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Overpoelen (Wedstrijddag Poules)

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Overpoelen (Wedstrijddag Poules)

Na sluiten weegtijd moeten judoka's die buiten hun gewichtsklasse vallen worden verplaatst.

### Pagina: Wedstrijddag Poules

1. Ga naar **Wedstrijddag Poules** pagina
2. Per blok zie je alle categorieën met hun poules
3. **ⓘ icoon** in poule header = klik voor info over afwezige judoka's
4. **Afwijkende judoka's** zijn gemarkeerd in hun poule (rode stip/badge)

### Workflow Overpoelen

**Vaste gewichtsklassen:** Judoka weegt buiten klasse → gemarkeerd in poule
**Variabele gewichten:** Poule range te groot → poule gemarkeerd, lichtste/zwaarste gehighlight

1. Bekijk gemarkeerde judoka's / poules
2. Afwezige judoka's zijn automatisch uit de poule (zichtbaar bij ⓘ)
3. Sleep afwijkende judoka's naar juiste poule, of gebruik 🔍 Zoek Match
4. Let op: max 6 judoka's per poule
5. Statistieken updaten automatisch

### Judoka Afmelden (kan niet deelnemen)

Als een judoka niet kan deelnemen (blessure, te zwaar, te jong, etc.):

**Optie 1: Via weging**
- Bij weging gewicht **0** invoeren = afmelden
- Of in Weeglijst Live: klik "Wijzig" → "Afmelden" knop

**Optie 2: Via Wedstrijddag Poules**
- Hover over judoka in poule → klik **✕** (afmelden)
- Of klik 🔍 (zoek poule) → "Afmelden" knop in modal

**Wat gebeurt er:**
- Judoka krijgt status `afwezig`
- Judoka verdwijnt uit poule (wordt niet meer getoond)
- Poule statistieken worden bijgewerkt

### Naar Zaaloverzicht Sturen

Wanneer een poule klaar is (overgepouled):

1. Klik **"→"** knop bij de poule (in de poule header)
2. Knop wordt **"✓"** (groen)
3. In zaaloverzicht verschijnt de categorie chip als **wit** zodra minstens 1 poule doorgestuurd is

**Knop kleuren per poule:**
| Knop | Status |
|------|--------|
| **→** (blauw) | Nog niet doorgestuurd |
| **✓** (groen) | Doorgestuurd naar zaaloverzicht |

**Weging-check bij doorsturen:**

| Weging verplicht? | Alle judoka's gewogen? | Weging gesloten? | Resultaat |
|-------------------|------------------------|------------------|-----------|
| Nee | n.v.t. | n.v.t. | **OK** - direct doorsturen |
| Ja | Ja | n.v.t. | **OK** - poule mag alvast beginnen |
| Ja | Nee | Ja | **OK** - niet-gewogen zijn al afwezig |
| Ja | Nee | Nee | **BLOKKEER** - eerst weging sluiten of alle judoka's wegen |

Als de organisator toch verder wil zonder weging: wijzig `weging verplicht` in toernooi instellingen.

