---
title: Blokverdeling - Solver algoritme
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - Solver algoritme

> Onderdeel van [Blokverdeling](../BLOKVERDELING.md).


## Solver Algoritme

### Plaatsing Volgorde
1. **Grote leeftijden eerst** (volgorde uit preset config):
   - Categorieen worden geplaatst in volgorde van de preset
   - Eerste categorie start ALTIJD in blok 1
   - Elke volgende leeftijd sluit aan waar vorige eindigde

2. **Strikte aansluiting regels** per gewichtscategorie:
   - Zelfde blok (0) = 0 punten
   - Volgend blok (+1) = 10 punten
   - Vorig blok (-1) = 20 punten
   - 2 blokken later (+2) = 30 punten
   - Verder = 50+ punten (slecht)

3. **Kleinere categorieen als opvulling**:
   - Categorieen met minder judoka's (bijv. dames, specifieke bandgroepen)
   - Geplaatst in blokken met meeste ruimte

4. **Penalty aflopende leeftijdsklasse**:
   - Als laatste gewicht in lager blok zit dan eerste = +200 punten

### Scoring Formule
```
Verdeling Score = Σ absolute % afwijkingen per blok
Aansluiting Score = Σ punten per overgang (zie boven)
Totaal Score = (slider_X% × Verdeling) + (slider_Y% × Aansluiting)
```
**Lager = beter**

### Variatie Generatie
- 3 seconden rekentijd (10.000-30.000 berekeningen)
- 960.000+ mogelijke combinaties door:
  - 6 aansluiting strategieën
  - 100 random factors
  - 10 sorteer strategieën
  - 8 leeftijd shuffle opties
  - Slider gewicht variatie (±10%)
- Top 5 unieke varianten getoond

### Live Score Update
- Variant knop update direct bij handmatig verslepen
- Zelfde formule als backend berekening
- Slider beïnvloedt weging

### Auto-Apply
- Na Bereken/Herbereken wordt variant #1 automatisch toegepast
- Chips tonen direct de nieuwe posities

---
