---
title: Classificatie & Poule Indeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Classificatie & Poule Indeling

> **Status:** werkend. Python solver (Greedy++ met sliding window) live, inclusief finetuning
> (orphan rescue, rebalance, band/club swap). Open: gemengde blokverdeling, UI-varianten, unit tests.
> **Index-doc** — de details staan in [`CLASSIFICATIE/`](CLASSIFICATIE/). Zie de wegwijzer onderaan.

## Kernbegrippen

### De 4 Stappen (BELANGRIJK!)

| Stap | Wat | Resultaat |
|------|-----|-----------|
| **1. Categoriseren** | Judoka → categorie (harde criteria) | Elke judoka heeft een categorie |
| **2. Sorteren** | Binnen categorie op prioriteiten | Gesorteerde lijst per categorie |
| **3. Groeperen** | Per categorie groeperen | Gesorteerde lijst PER categorie |
| **4. Poules maken** | Verdelen in poules (bv. 5) | Poules binnen kg/lft limieten |

**Stap 1: Categoriseren** = Welke groep?
- Judoka moet voldoen aan ALLE harde criteria
- Eerste leeftijdsmatch = zijn categorie (NOOIT doorvallen!)
- Harde criteria: max_leeftijd, geslacht, band_filter

**Stap 2-3: Sorteren & Groeperen** = Welke volgorde?
- Sorteer op prioriteiten (leeftijd/gewicht/band)
- Groepeer per categorie → gesorteerde lijst per categorie

**Stap 4: Poules maken** = Verdelen
- Binnen limieten: max_kg_verschil, max_leeftijd_verschil
- Poulegrootte voorkeur instelbaar (bv. [5, 4, 6, 3])

---

## Algoritme Overzicht

```
┌─────────────────────────────────────────────────────────────────┐
│ POULE INDELING ALGORITME (4 stappen)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ STAP 1: CATEGORISEREN                                           │
│   Per judoka → check welke categorie past                       │
│                                                                 │
│   A. Vind eerste categorie waar leeftijd ≤ max_leeftijd         │
│      (categorieën gesorteerd van jong → oud)                    │
│                                                                 │
│   B. Check ALLEEN categorieën met DIE max_leeftijd:             │
│      • geslacht = M/V/Gemengd                                   │
│      • band voldoet aan band_filter (als gezet)                 │
│                                                                 │
│   ⚠️ KRITIEK: Als geslacht/band niet past → NIET GECATEGORISEERD│
│      NOOIT doorvallen naar categorie met hogere max_leeftijd!   │
│      Een 6-jarige in U7 komt NOOIT in U9, ook niet als          │
│      band_filter niet matcht!                                   │
│                                                                 │
│   LET OP: max_kg_verschil is NIET voor categoriseren!           │
│   Dat is voor stap 4 (poules maken binnen de categorie).        │
│                                                                 │
│ STAP 2: SORTEREN                                                │
│   Sorteer ALLE judoka's volgens verdeling_prioriteiten:         │
│   • Leeftijd: jong → oud                                        │
│   • Gewicht: licht → zwaar                                      │
│   • Band: laag → hoog (wit → zwart)                             │
│                                                                 │
│ STAP 3: GROEPEREN                                               │
│   Groepeer per categorie (sortering blijft behouden)            │
│   → Gesorteerde lijst per categorie, klaar voor poule-indeling  │
│                                                                 │
│ STAP 4: POULES MAKEN (greedy, direct optimaal)                  │
│   Gesorteerde groep verdelen in poules van 5 (of 4/6/3):        │
│                                                                 │
│   Voor elke judoka (gesorteerd):                                │
│   1. Probeer toe te voegen aan huidige poule                    │
│   2. Check: gewicht_verschil ≤ max_kg_verschil (uit config)     │
│   3. Check: leeftijd_verschil ≤ max_leeftijd_verschil (config)  │
│   4. Check: poule_grootte < 5 (of voorkeur)                     │
│   5. Alle checks OK → toevoegen, anders → nieuwe poule          │
│                                                                 │
│   Aan einde: merge kleine poules (< 4) als binnen limieten      │
│                                                                 │
│   ⚠️ UITZONDERING: Eliminatie & Kruisfinale                      │
│   Bij eliminatie/kruisfinale: GEEN splitsen in poules van 5!    │
│   Hele gewichtsklasse = 1 poule (alle judoka's samen).          │
│   Vereist: Δkg=0, Δlft=0, gewichtsklassen ingevuld.            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [CRITERIA](CLASSIFICATIE/CRITERIA.md) | Wat bepaalt de categorie (hard) vs de volgorde (zacht). Begin hier bij "waarom valt deze judoka buiten de boot". |
| [INSTELLINGEN](CLASSIFICATIE/INSTELLINGEN.md) | De categorieën-UI en de presets (JBN e.d.). |
| [POULES](CLASSIFICATIE/POULES.md) | Poulegrootte-voorkeur ([5,4,6,3]) en hoe pouletitels ontstaan. |
| [DATABASE](CLASSIFICATIE/DATABASE.md) | Kolommen en configvelden: `categorie_key`, `sort_categorie`, `verdeling_prioriteiten`. |
| [SERVICES](CLASSIFICATIE/SERVICES.md) | `CategorieClassifier` en `PouleIndelingService` — de instap voor code. |
| [INDELING-SERVICES](CLASSIFICATIE/INDELING-SERVICES.md) | `DynamischeIndelingService`, variabele en gemengde blokverdeling. |
| [SOLVER](CLASSIFICATIE/SOLVER.md) | Waarom er een Python-solver is, de architectuur en de scorefunctie. |
| [SOLVER-ALGORITME](CLASSIFICATIE/SOLVER-ALGORITME.md) | Greedy++ zelf en de implementatiestappen. |
| [TECHNISCH](CLASSIFICATIE/TECHNISCH.md) | Randgevallen en implementatiedetails. |
| [OVERPOULEN](CLASSIFICATIE/OVERPOULEN.md) | Wedstrijddag: wanneer overpoulen nodig is en welke regels gelden. |
| [OVERPOULEN-AFWEZIGEN](CLASSIFICATIE/OVERPOULEN-AFWEZIGEN.md) | Afwezigen, lege poules en de zoek-match op de dag zelf. |
| [OVERPOULEN-UI](CLASSIFICATIE/OVERPOULEN-UI.md) | De schermen erbij: problematische poules, zoek-match, nieuwe poule. |
| [HANDMATIG](CLASSIFICATIE/HANDMATIG.md) | Poule met de hand aanmaken + legacy. |

## Implementatie Status
### Voltooid

- [x] Database & UI (Fase 1)
- [x] Indeling algoritme (Fase 2)
- [x] Eigen presets
- [x] Drag & drop categorieën
- [x] Variabele blokverdeling
- [x] Live titel update bij drag & drop
- [x] Hardcoded categorieën opgeruimd
- [x] **Python Poule Solver (Fase 3)** - Greedy++ met sliding window
- [x] **Finetuning** - orphan rescue, rebalance, band/club swap

### Gepland

- [ ] **Gemengde Blokverdeling** - Vast + variabel in één toernooi (twee-fasen algoritme)
- [ ] UI varianten weergave (Fase 4)
- [ ] Unit tests (Fase 5)

---

