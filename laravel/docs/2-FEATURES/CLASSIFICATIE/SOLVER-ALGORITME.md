---
title: Solver: Algoritme & Implementatie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Solver: Algoritme & Implementatie

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

### Algoritme Opties

| Optie | Beschrijving | Snelheid | Kwaliteit |
|-------|--------------|----------|-----------|
| **Greedy++** | Greedy + backtrack voor orphans | Snel | Goed |
| **Simulated Annealing** | Random swaps, accepteer soms slechter | Medium | Zeer goed |
| **OR-Tools CP** | Constraint Programming solver | Langzaam | Optimaal |

**Aanbeveling:** Start met Greedy++ (PHP vervanging), upgrade naar SA als nodig.

### Greedy++ Algoritme

```python
def greedy_plus_plus(judokas, max_kg, max_lft):
    """
    1. Sorteer op leeftijd → gewicht
    2. Maak poules greedy (zoals nu)
    3. NIEUW: Voor elke orphan/kleine poule:
       - Zoek in ALLE poules of orphan erbij past
       - Zoek in ALLE judoka's of er een swap mogelijk is
    """

    # Stap 1-2: Greedy basis
    poules = maak_poules_greedy(judokas, max_kg, max_lft)

    # Stap 3: Fix orphans
    for _ in range(MAX_ITERATIES):
        verbeterd = False

        # Probeer orphans toe te voegen aan bestaande poules
        for orphan in get_orphans(poules):
            for poule in poules:
                if kan_toevoegen(orphan, poule, max_kg, max_lft):
                    poule.append(orphan)
                    verbeterd = True
                    break

        # Probeer kleine poules samen te voegen
        for p1, p2 in combinaties(kleine_poules(poules)):
            if kan_samenvoegen(p1, p2, max_kg, max_lft):
                merge(p1, p2)
                verbeterd = True

        # Probeer swaps tussen poules
        for p1, p2 in combinaties(poules):
            if swap_verbetert(p1, p2, max_kg, max_lft):
                doe_swap(p1, p2)
                verbeterd = True

        if not verbeterd:
            break

    return poules
```

### Implementatie Stappen

1. **Python solver script** (`laravel/scripts/poule_solver.py`)
   - Input: JSON van stdin
   - Output: JSON naar stdout
   - Greedy++ algoritme

2. **PHP integratie** (`DynamischeIndelingService.php`)
   - `callPythonSolver($judokas, $config): array`
   - Fallback naar PHP greedy als Python faalt

3. **Tests**
   - Unit tests Python solver
   - Integratie test PHP ↔ Python

### Bestaand Experiment

Er is al een experiment: `Scripts/python/poule_solver_experiment.py`
- Test 3 algoritmes: GEWICHT>BAND, BAND>GEWICHT, LEEFTIJD>GEWICHT>BAND
- Kan als basis dienen voor productie solver

---

