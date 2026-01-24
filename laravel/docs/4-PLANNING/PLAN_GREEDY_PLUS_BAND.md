# Implementatieplan: Greedy++ & max_band_verschil

> **Status:** Klaar voor implementatie
> **Datum:** 24 jan 2026
> **Geschatte omvang:** Medium-groot

## Doel

1. **max_band_verschil** - Nieuw hard criterium zodat wit nooit tegen groen staat
2. **Greedy++** - Betere optimalisatie: meer poules van 5, minder orphans

## Huidige situatie

- Python solver (`scripts/poule_solver.py`) gebruikt "Sliding Window" algoritme
- Sorteert op band BINNEN gewichtsrange, maar pakt dan eerste 5
- Resultaat: wit kan in zelfde poule als groen terechtkomen
- Geen backtracking/swaps na initiële indeling

## Deel 1: max_band_verschil

### 1.1 Database/Config (geen migration nodig)

Het veld wordt opgeslagen in `gewichtsklassen` JSON kolom per categorie, net als `max_kg_verschil`.

**Locatie:** `toernooien.gewichtsklassen` JSON

```json
{
  "u7": {
    "label": "U7",
    "max_leeftijd": 6,
    "max_kg_verschil": 3,
    "max_leeftijd_verschil": 2,
    "max_band_verschil": 2,  // NIEUW
    ...
  }
}
```

### 1.2 UI Aanpassing

**Bestand:** `resources/views/pages/toernooi/edit.blade.php`

Voeg toe naast Δkg en Δlft velden (rond regel 1030-1040):

```html
<div class="flex items-center gap-2">
    <label class="text-gray-600 text-sm whitespace-nowrap">Δband:</label>
    <input type="number" name="gewichtsklassen_max_band[${key}]"
           value="${maxBand}"
           class="max-band-input w-12 border rounded px-1 py-1 text-center text-sm"
           min="0" max="6" step="1"
           title="0 = geen limiet, 1 = max 1 niveau (wit-geel), 2 = max 2 niveaus (wit-oranje)">
</div>
```

**Aanpassingen nodig in JavaScript functies:**
- `collectFormData()` - rond regel 855: voeg `max_band_verschil` toe
- `renderCategorieen()` - rond regel 963: lees `max_band_verschil` uit config
- `addCategorie()` - rond regel 1328: default waarde `max_band_verschil: 2`
- `collectConfiguratie()` - rond regel 1218: voeg toe aan verzamelde config

### 1.3 PHP Service Aanpassing

**Bestand:** `app/Services/DynamischeIndelingService.php`

Pas `callPythonSolver()` aan om `max_band_verschil` mee te sturen:

```php
$pythonInput = [
    'max_kg_verschil' => $maxKg,
    'max_leeftijd_verschil' => $maxLeeftijd,
    'max_band_verschil' => $config['max_band_verschil'] ?? 0,  // NIEUW
    'poule_grootte_voorkeur' => $this->config['poule_grootte_voorkeur'],
    'judokas' => [],
];
```

Pas ook `simpleFallback()` aan om band te checken.

### 1.4 Python Solver Aanpassing

**Bestand:** `scripts/poule_solver.py`

**A. Input parsing (solve functie, regel 436):**
```python
max_band = int(input_data.get('max_band_verschil', 0))  # 0 = geen limiet
```

**B. Sliding window aanpassen (maak_een_poule functie, regel 213-222):**

Huidige code:
```python
# Sorteer op band (laagste eerst)
in_range_sorted = sorted(in_range, key=lambda j: j.band)
# Pak max ideale_grootte judoka's
poule_judokas = in_range_sorted[:ideale_grootte]
```

Nieuwe code:
```python
# Als max_band_verschil > 0: ook sliding window op band
if max_band > 0:
    # Sorteer op band
    in_range_sorted = sorted(in_range, key=lambda j: j.band)
    # Bepaal laagste band
    laagste_band = in_range_sorted[0].band
    max_band_groep = laagste_band + max_band
    # Filter op band range
    in_band_range = [j for j in in_range_sorted if j.band <= max_band_groep]
    poule_judokas = in_band_range[:ideale_grootte]
else:
    # Geen band limiet, pak eerste 5 (gesorteerd op band)
    in_range_sorted = sorted(in_range, key=lambda j: j.band)
    poule_judokas = in_range_sorted[:ideale_grootte]
```

**C. Merge check aanpassen (merge_kleine_poules functie, regel 380-386):**

Voeg band check toe:
```python
# Check constraints
all_judokas = kleine_poule.judokas + andere.judokas
gewichten = [j.gewicht for j in all_judokas]
leeftijden = [j.leeftijd for j in all_judokas]
banden = [j.band for j in all_judokas]

if max(gewichten) - min(gewichten) > max_kg:
    continue
if max(leeftijden) - min(leeftijden) > max_lft:
    continue
if max_band > 0 and max(banden) - min(banden) > max_band:  # NIEUW
    continue
```

---

## Deel 2: Greedy++ Algoritme

### 2.1 Concept

Na de initiële sliding window indeling:
1. **Orphan rescue** - Probeer orphans (poule van 1) toe te voegen aan bestaande poules
2. **Small poule merge** - Probeer kleine poules (< min voorkeur) samen te voegen
3. **Swap optimization** - Swap judoka's tussen poules om score te verbeteren

### 2.2 Python Implementatie

**Bestand:** `scripts/poule_solver.py`

Voeg nieuwe functie toe na `merge_kleine_poules`:

```python
def greedy_plus_plus(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Greedy++ optimalisatie na sliding window.

    1. Orphan rescue - plaats orphans in bestaande poules
    2. Small merge - voeg kleine poules samen
    3. Swap optimization - verbeter score door swaps
    """
    max_size = max(voorkeur) if voorkeur else 6
    min_size = min(voorkeur) if voorkeur else 3

    verbeterd = True
    max_iteraties = 100
    iteratie = 0

    while verbeterd and iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False

        # === STAP 1: Orphan rescue ===
        orphans = [p for p in poules if p.size == 1]
        for orphan_poule in orphans:
            orphan = orphan_poule.judokas[0]

            # Zoek beste poule om orphan aan toe te voegen
            beste_poule = None
            beste_score_verbetering = 0

            for poule in poules:
                if poule is orphan_poule:
                    continue
                if poule.size >= max_size:
                    continue

                # Check constraints
                if not kan_toevoegen(orphan, poule, max_kg, max_lft, max_band):
                    continue

                # Bereken score verbetering
                oude_score = (bereken_grootte_penalty(1, voorkeur) +
                              bereken_grootte_penalty(poule.size, voorkeur))
                nieuwe_score = bereken_grootte_penalty(poule.size + 1, voorkeur)
                verbetering = oude_score - nieuwe_score

                if verbetering > beste_score_verbetering:
                    beste_score_verbetering = verbetering
                    beste_poule = poule

            if beste_poule:
                beste_poule.judokas.append(orphan)
                poules.remove(orphan_poule)
                verbeterd = True
                break

        if verbeterd:
            continue

        # === STAP 2: Small merge (al geïmplementeerd in merge_kleine_poules) ===
        # Skip, wordt al aangeroepen

        # === STAP 3: Swap optimization ===
        # Probeer swaps tussen poules om score te verbeteren
        for i, p1 in enumerate(poules):
            if verbeterd:
                break
            for p2 in poules[i+1:]:
                swap = vind_verbeterende_swap(p1, p2, max_kg, max_lft, max_band, voorkeur)
                if swap:
                    j1, j2 = swap
                    p1.judokas.remove(j1)
                    p2.judokas.remove(j2)
                    p1.judokas.append(j2)
                    p2.judokas.append(j1)
                    verbeterd = True
                    break

    return poules


def kan_toevoegen(judoka: Judoka, poule: Poule, max_kg: float, max_lft: int, max_band: int) -> bool:
    """Check of judoka aan poule kan worden toegevoegd binnen constraints."""
    if not poule.judokas:
        return True

    gewichten = [j.gewicht for j in poule.judokas] + [judoka.gewicht]
    leeftijden = [j.leeftijd for j in poule.judokas] + [judoka.leeftijd]
    banden = [j.band for j in poule.judokas] + [judoka.band]

    if max(gewichten) - min(gewichten) > max_kg:
        return False
    if max(leeftijden) - min(leeftijden) > max_lft:
        return False
    if max_band > 0 and max(banden) - min(banden) > max_band:
        return False

    return True


def vind_verbeterende_swap(
    p1: Poule,
    p2: Poule,
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int]
) -> tuple:
    """
    Vind een swap tussen p1 en p2 die de totale score verbetert.
    Returns (judoka_uit_p1, judoka_uit_p2) of None.
    """
    huidige_score = (bereken_grootte_penalty(p1.size, voorkeur) +
                     bereken_grootte_penalty(p2.size, voorkeur))

    for j1 in p1.judokas:
        for j2 in p2.judokas:
            # Simuleer swap
            p1_zonder_j1 = [j for j in p1.judokas if j is not j1] + [j2]
            p2_zonder_j2 = [j for j in p2.judokas if j is not j2] + [j1]

            # Check constraints voor beide poules
            if not check_poule_constraints(p1_zonder_j1, max_kg, max_lft, max_band):
                continue
            if not check_poule_constraints(p2_zonder_j2, max_kg, max_lft, max_band):
                continue

            # Score blijft gelijk (zelfde groottes), maar check band spreiding
            # Een swap is nuttig als het de band spreiding vermindert
            oude_band_spread = (max(j.band for j in p1.judokas) - min(j.band for j in p1.judokas) +
                                max(j.band for j in p2.judokas) - min(j.band for j in p2.judokas))
            nieuwe_band_spread = (max(j.band for j in p1_zonder_j1) - min(j.band for j in p1_zonder_j1) +
                                  max(j.band for j in p2_zonder_j2) - min(j.band for j in p2_zonder_j2))

            if nieuwe_band_spread < oude_band_spread:
                return (j1, j2)

    return None


def check_poule_constraints(judokas: List[Judoka], max_kg: float, max_lft: int, max_band: int) -> bool:
    """Check of lijst judoka's binnen constraints valt."""
    if not judokas:
        return True

    gewichten = [j.gewicht for j in judokas]
    leeftijden = [j.leeftijd for j in judokas]
    banden = [j.band for j in judokas]

    if max(gewichten) - min(gewichten) > max_kg:
        return False
    if max(leeftijden) - min(leeftijden) > max_lft:
        return False
    if max_band > 0 and max(banden) - min(banden) > max_band:
        return False

    return True
```

### 2.3 Integratie in solve()

Pas de `solve()` functie aan (rond regel 459):

```python
# Sliding window basis
poules = sliding_window(judokas, max_kg, max_lft, voorkeur)

# Merge kleine poules
poules = merge_kleine_poules(poules, max_kg, max_lft, voorkeur)

# Greedy++ optimalisatie (NIEUW)
poules = greedy_plus_plus(poules, max_kg, max_lft, max_band, voorkeur)
```

---

## Deel 3: Testen

### 3.1 Unit Tests Python

**Bestand:** `scripts/test_poule_solver.py` (nieuw)

```python
import unittest
from poule_solver import Judoka, Poule, solve, kan_toevoegen

class TestMaxBandVerschil(unittest.TestCase):
    def test_wit_niet_bij_groen(self):
        """Wit (0) mag niet bij groen (3) als max_band=2"""
        input_data = {
            'max_kg_verschil': 10,
            'max_leeftijd_verschil': 5,
            'max_band_verschil': 2,
            'poule_grootte_voorkeur': [5, 4, 6, 3],
            'judokas': [
                {'id': 1, 'leeftijd': 8, 'gewicht': 30, 'band': 0},  # wit
                {'id': 2, 'leeftijd': 8, 'gewicht': 30, 'band': 1},  # geel
                {'id': 3, 'leeftijd': 8, 'gewicht': 30, 'band': 3},  # groen
            ]
        }
        result = solve(input_data)

        # Check dat wit+geel in andere poule zit dan groen
        for poule in result['poules']:
            bands = [j for j in poule['judoka_ids']]
            # Specifieke check: id 1 (wit) en id 3 (groen) mogen niet samen
            if 1 in bands and 3 in bands:
                self.fail("Wit en groen zitten in zelfde poule!")

class TestGreedyPlusPlus(unittest.TestCase):
    def test_orphan_rescue(self):
        """Orphan moet aan passende poule worden toegevoegd"""
        # Test case met 6 judoka's waar 1 orphan wordt
        pass  # TODO: implementeer

if __name__ == '__main__':
    unittest.main()
```

### 3.2 Handmatige Test

1. Maak testtoernooi met categorie `max_band_verschil: 2`
2. Voeg judoka's toe: 3x wit, 2x geel, 2x groen, 2x blauw (zelfde gewicht/leeftijd)
3. Genereer poule-indeling
4. Verwacht: wit+geel in poule 1, groen+blauw in poule 2

---

## Implementatie Volgorde

### Stap 1: max_band_verschil basis
- [ ] UI veld toevoegen in edit.blade.php
- [ ] JavaScript collectFormData/renderCategorieen aanpassen
- [ ] PHP service aanpassen om waarde door te geven

### Stap 2: Python solver max_band
- [ ] Input parsing uitbreiden
- [ ] Sliding window op band toevoegen
- [ ] Merge check uitbreiden met band

### Stap 3: Greedy++ implementatie
- [ ] `kan_toevoegen()` helper
- [ ] `greedy_plus_plus()` hoofdfunctie
- [ ] `vind_verbeterende_swap()` voor band optimalisatie
- [ ] Integratie in `solve()`

### Stap 4: Testen & Documentatie
- [ ] Unit tests Python
- [ ] Handmatige test op staging
- [ ] CLASSIFICATIE.md updaten

---

## Referenties

- Huidige solver: `scripts/poule_solver.py`
- PHP service: `app/Services/DynamischeIndelingService.php`
- UI: `resources/views/pages/toernooi/edit.blade.php`
- Documentatie: `docs/2-FEATURES/CLASSIFICATIE.md`
