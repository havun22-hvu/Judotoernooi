---
title: Python Poule Solver
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Python Poule Solver

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Fase 3: Python Poule Solver

### Waarom een solver?

**Probleem met huidige greedy aanpak:**

```
Sortering: leeftijd → gewicht

Poule 1: 6j, 25-28kg (grootte=3, orphan!)
...veel judoka's verder in lijst...
Judoka X: 7j, 26kg  ← past qua gewicht, maar staat ver weg
```

- Greedy kijkt alleen "vooruit" in gesorteerde lijst
- Mist goede matches die verder weg staan (andere leeftijd, zelfde gewicht)
- Resulteert in veel orphans en ongelijke poules

**Solver voordelen:**
- Bekijkt ALLE judoka's in categorie
- Zoekt optimale combinaties (niet alleen buren)
- Minimaliseert orphans, maximaliseert poules van 5

### Architectuur

```
┌─────────────────────────────────────────────────────────────────┐
│ FLOW                                                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PHP: PouleIndelingService                                      │
│    │                                                            │
│    ├─► Stap 1: Categoriseren (harde grenzen)                    │
│    │                                                            │
│    ├─► Stap 2-3: Sorteren & Groeperen                           │
│    │                                                            │
│    └─► Stap 4: Poules maken                                     │
│          │                                                      │
│          ▼                                                      │
│        ┌─────────────────────────────────────────┐              │
│        │ Python: poule_solver.py                 │              │
│        │                                         │              │
│        │ Input:  JSON met judoka's per categorie │              │
│        │ Output: JSON met optimale poules        │              │
│        │                                         │              │
│        │ Algoritme:                              │              │
│        │ 1. Score functie (orphans, grootte)     │              │
│        │ 2. Zoek beste combinaties               │              │
│        │ 3. Return poule-toewijzingen            │              │
│        └─────────────────────────────────────────┘              │
│          │                                                      │
│          ▼                                                      │
│  PHP: Sla poules op in database                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Input/Output Format

**Input (PHP → Python):**
```json
{
  "categorie": "U7",
  "max_kg_verschil": 3.0,
  "max_leeftijd_verschil": 2,
  "poule_grootte_voorkeur": [5, 4, 6, 3],
  "judokas": [
    {"id": 1, "leeftijd": 6, "gewicht": 25.5, "band": 2, "club_id": 10},
    {"id": 2, "leeftijd": 6, "gewicht": 26.0, "band": 1, "club_id": 11},
    ...
  ]
}
```

**Output (Python → PHP):**
```json
{
  "success": true,
  "poules": [
    {"judoka_ids": [1, 2, 5, 8, 12], "gewicht_range": 2.8, "leeftijd_range": 1},
    {"judoka_ids": [3, 4, 6, 9, 10], "gewicht_range": 2.5, "leeftijd_range": 2},
    ...
  ],
  "orphans": [15],
  "stats": {
    "totaal_judokas": 50,
    "totaal_poules": 10,
    "poules_van_5": 8,
    "poules_van_4": 1,
    "poules_van_3": 1,
    "orphans": 1
  }
}
```

### Score Functie

**BELANGRIJK:** Scores zijn NIET hardcoded! Ze komen uit de config.

```python
def bereken_grootte_penalty(grootte, poule_grootte_voorkeur):
    """
    Score op basis van poule_grootte_voorkeur uit config.

    Voorbeeld: poule_grootte_voorkeur = [5, 4, 6, 3]
    - Index 0 (5) = beste   → penalty 0
    - Index 1 (4) = goed    → penalty 5
    - Index 2 (6) = minder  → penalty 40
    - Index 3 (3) = slecht  → penalty 40
    - Niet in lijst (1,2)   → orphan penalty 70
    - Orphan (0 of alleen)  → penalty 100
    """
    if grootte <= 1:
        return 100  # Orphan

    if grootte in poule_grootte_voorkeur:
        index = poule_grootte_voorkeur.index(grootte)
        # Eerste voorkeur = 0, tweede = 5, rest = 40
        if index == 0:
            return 0
        elif index == 1:
            return 5
        else:
            return 40
    else:
        return 70  # Niet in voorkeurlijst (poule van 2, 7, 8, etc.)


def score_indeling(poules, config):
    """
    Lagere score = betere indeling
    Config bevat: poule_grootte_voorkeur = [5, 4, 6, 3]
    """
    score = 0
    voorkeur = config.get('poule_grootte_voorkeur', [5, 4, 6, 3])

    for poule in poules:
        grootte = len(poule)
        score += bereken_grootte_penalty(grootte, voorkeur)

    return score
```

**Standaard penalties (bij voorkeur [5, 4, 6, 3]):**

| Grootte | Penalty | Reden |
|---------|---------|-------|
| 5 | 0 | Eerste voorkeur |
| 4 | 5 | Tweede voorkeur |
| 6 | 40 | Derde voorkeur |
| 3 | 40 | Vierde voorkeur |
| 2 | 70 | Niet in voorkeur |
| 1 | 100 | Orphan |

