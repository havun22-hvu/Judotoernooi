---
title: Blokverdeling - Functionele Specificatie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - Functionele Specificatie

> Verdeling van categorieen over blokken: doel, pagina-layout en de wegwijzer naar de deeldocs.
> De feature is live; solver, drag & drop en variabele categorieen zijn in gebruik.
> Dit is een index-doc — de details staan in `BLOKVERDELING/`.

## Doel
Verdeel alle categorieën (leeftijd + gewicht combinaties) over de beschikbare blokken, zodat:
1. Elk blok ongeveer evenveel wedstrijden heeft
2. Aansluitende gewichtscategorieën zoveel mogelijk in hetzelfde of opvolgende blok zitten

---

## Pagina Layout

```
+------------------------------------------------------------------+
| HEADER                                                            |
| Blokverdeling    6 blokken | 821 wed. | gem 137/blok              |
|                              [Bereken] [Opnieuw] [Naar Zaaloverzicht →] |
+------------------------------------------------------------------+

+------------------+----------------------------------------+----------+
| SLEEPVAK         | BLOKKEN                                | OVERZICHT|
| (niet verdeeld)  |                                        |          |
+------------------+----------------------------------------+----------+
| ▼ Mini's (45)    | BLOK 1         Gewenst: [137] Act: 132 |          |
|   -18 (6)        | [Mini -18][Mini -21][📍A-pup -24]...   | Mini's   |
|   -21 (8)        |                                        |  -18  1  |
|   -24 (10)       +----------------------------------------+  -21  1  |
|                  | BLOK 2         Gewenst: [137] Act: 151 |  -24  2  |
| ▼ A-pupillen     | [B-pup -30][B-pup -34]...              |          |
|   -21 (5)        |                                        | A-pup    |
|   -24 (12)       +----------------------------------------+  -21  -  |
|   ...            | ...                                    |  -24  -  |
+------------------+----------------------------------------+----------+

+------------------------------------------------------------------+
| Varianten: [#1 ±14/7] [#2 ±27/9] [#3] [#4] [#5]  [✕ Annuleer]   |
+------------------------------------------------------------------+
```

---
## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [UI-EN-WORKFLOW.md](BLOKVERDELING/UI-EN-WORKFLOW.md) | Je werkt aan het sleepvak, de blokken, het overzicht-panel, een knop, drag & drop, pins of de volgorde voorbereiding → toernooidag. |
| [SOLVER.md](BLOKVERDELING/SOLVER.md) | De berekende verdeling klopt niet, of je past de plaatsingsvolgorde, scoringsformule, variatie-generatie of auto-apply aan. |
| [VARIABELE-CATEGORIEEN.md](BLOKVERDELING/VARIABELE-CATEGORIEEN.md) | Het toernooi heeft geen vaste gewichtsklassen: knip op gewicht, chip-weergave, overpoulen of gewichtsmutaties op de wedstrijddag. |
| [GEMENGDE-TOERNOOIEN.md](BLOKVERDELING/GEMENGDE-TOERNOOIEN.md) | Een toernooi combineert vaste en variabele categorieen en je hebt het twee-fasen-algoritme of het bijbehorende UI-gedrag nodig. |
| [DATABASE.md](BLOKVERDELING/DATABASE.md) | Je zoekt welke kolommen op `poules` en `blokken` de verdeling vastleggen. |
