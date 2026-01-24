# Implementatieplan: Greedy++ & max_band_verschil

> **Status:** Klaar voor implementatie
> **Datum:** 24 jan 2026
> **Geschatte omvang:** Medium-groot

## Doel

1. **max_band_verschil** - Nieuw hard criterium zodat wit nooit tegen groen staat
2. **Greedy++** - Betere optimalisatie: meer poules van 5, minder orphans
3. **Clubspreiding** - Zachte optimalisatie: judoka's van zelfde club liever niet samen

---

## Band Mapping (KRITIEK!)

**Judo kyu/dan systeem:**
- Wit = 6e kyu (beginner)
- Geel = 5e kyu
- Oranje = 4e kyu
- Groen = 3e kyu
- Blauw = 2e kyu
- Bruin = 1e kyu
- Zwart = 1e dan+ (gevorderd)

**Numerieke mapping (0-indexed, oplopend met niveau):**

| Band   | Waarde | Kyu/Dan |
|--------|--------|---------|
| wit    | 0      | 6e kyu  |
| geel   | 1      | 5e kyu  |
| oranje | 2      | 4e kyu  |
| groen  | 3      | 3e kyu  |
| blauw  | 4      | 2e kyu  |
| bruin  | 5      | 1e kyu  |
| zwart  | 6      | dan     |

**Dit is de mapping die naar Python gaat** (zie `DynamischeIndelingService.bandNaarNummer()`).

**Let op:** `BandHelper.php` gebruikt OMGEKEERDE waarden (wit=6, zwart=0) voor PHP sortering.
Dit is verwarrend maar werkt omdat ascending sort dan beginners eerst geeft.

**Regel:** Voor Python: lagere waarde = beginner, hogere waarde = gevorderd.

**Sortering:** `sorted(judokas, key=lambda j: j.band)` → wit(0) eerst, zwart(6) laatst.

**max_band_verschil voorbeeld:**
- `max_band_verschil = 2` → wit(0) mag bij geel(1) en oranje(2), NIET bij groen(3)
- `max_band_verschil = 0` → geen limiet

---

## Huidige situatie

- Python solver (`scripts/poule_solver.py`) gebruikt "Sliding Window" algoritme
- Sorteert op band BINNEN gewichtsrange, maar pakt dan eerste 5
- Resultaat: wit kan in zelfde poule als groen terechtkomen
- Geen backtracking/swaps na initiële indeling
- Geen clubspreiding

---

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
- `addCategorie()` - rond regel 1328: default waarde `max_band_verschil: 0` (geen limiet)
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

**Pas ook `simpleFallback()` aan:**

```php
private function simpleFallback(array $judokas, float $maxKg, int $maxLft, int $maxBand = 0): array
{
    // ... bestaande code ...

    // Bij toevoegen aan poule, check ook band:
    if ($maxBand > 0) {
        $banden = array_column($poule, 'band');
        $banden[] = $judoka['band'];
        if (max($banden) - min($banden) > $maxBand) {
            // Start nieuwe poule
            continue;
        }
    }
}
```

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
# Sorteer op band (1=wit/beginner eerst, 7=zwart/gevorderd laatst)
in_range_sorted = sorted(in_range, key=lambda j: j.band)

if max_band > 0:
    # Sliding window op band: start bij laagste
    laagste_band = in_range_sorted[0].band
    max_toegestane_band = laagste_band + max_band
    # Filter op band range
    in_band_range = [j for j in in_range_sorted if j.band <= max_toegestane_band]
    poule_judokas = in_band_range[:ideale_grootte]
else:
    # Geen band limiet
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
3. **Band swap** - Swap judoka's tussen poules om band spreiding te verbeteren
4. **Club spreiding** - Swap judoka's om clubs te spreiden (LAAGSTE prioriteit)

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

    Prioriteit (hoog → laag):
    1. Orphan rescue - plaats orphans in bestaande poules
    2. Small merge - voeg kleine poules samen
    3. Band swap - verbeter band spreiding
    4. Club swap - verbeter club spreiding (alleen als rest gelijk)
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
        orphans = [p for p in poules if len(p.judokas) == 1]
        for orphan_poule in orphans:
            orphan = orphan_poule.judokas[0]

            beste_poule = None
            beste_score_verbetering = 0

            for poule in poules:
                if poule is orphan_poule:
                    continue
                if len(poule.judokas) >= max_size:
                    continue

                if not kan_toevoegen(orphan, poule, max_kg, max_lft, max_band):
                    continue

                oude_score = (bereken_grootte_penalty(1, voorkeur) +
                              bereken_grootte_penalty(len(poule.judokas), voorkeur))
                nieuwe_score = bereken_grootte_penalty(len(poule.judokas) + 1, voorkeur)
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

        # === STAP 2: Small merge (al in merge_kleine_poules) ===
        # Skip

        # === STAP 3: Band swap ===
        for i, p1 in enumerate(poules):
            if verbeterd:
                break
            for p2 in poules[i+1:]:
                swap = vind_band_verbeterende_swap(p1, p2, max_kg, max_lft, max_band)
                if swap:
                    j1, j2 = swap
                    p1.judokas.remove(j1)
                    p2.judokas.remove(j2)
                    p1.judokas.append(j2)
                    p2.judokas.append(j1)
                    verbeterd = True
                    break

        if verbeterd:
            continue

        # === STAP 4: Club spreiding ===
        for i, p1 in enumerate(poules):
            if verbeterd:
                break
            for p2 in poules[i+1:]:
                swap = vind_club_verbeterende_swap(p1, p2, max_kg, max_lft, max_band)
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


def vind_band_verbeterende_swap(
    p1: Poule,
    p2: Poule,
    max_kg: float,
    max_lft: int,
    max_band: int
) -> tuple:
    """
    Vind een swap die de totale band spreiding vermindert.
    Returns (judoka_uit_p1, judoka_uit_p2) of None.
    """
    oude_band_spread = (
        max(j.band for j in p1.judokas) - min(j.band for j in p1.judokas) +
        max(j.band for j in p2.judokas) - min(j.band for j in p2.judokas)
    )

    for j1 in p1.judokas:
        for j2 in p2.judokas:
            # Simuleer swap
            p1_na = [j for j in p1.judokas if j is not j1] + [j2]
            p2_na = [j for j in p2.judokas if j is not j2] + [j1]

            # Check harde constraints
            if not check_poule_constraints(p1_na, max_kg, max_lft, max_band):
                continue
            if not check_poule_constraints(p2_na, max_kg, max_lft, max_band):
                continue

            # Check verbetering
            nieuwe_band_spread = (
                max(j.band for j in p1_na) - min(j.band for j in p1_na) +
                max(j.band for j in p2_na) - min(j.band for j in p2_na)
            )

            if nieuwe_band_spread < oude_band_spread:
                return (j1, j2)

    return None


def club_penalty(poule: Poule) -> int:
    """
    Bereken club penalty voor een poule.
    Hoger = slechter (meer judoka's van zelfde club).
    """
    clubs = [j.club_id for j in poule.judokas if j.club_id]
    if not clubs:
        return 0
    # Tel dubbele clubs
    from collections import Counter
    counts = Counter(clubs)
    # Penalty = aantal extra judoka's per club (boven 1)
    penalty = sum(count - 1 for count in counts.values())
    return penalty


def vind_club_verbeterende_swap(
    p1: Poule,
    p2: Poule,
    max_kg: float,
    max_lft: int,
    max_band: int
) -> tuple:
    """
    Vind een swap die de totale club spreiding verbetert.
    Alleen swappen als harde constraints (kg, lft, band) behouden blijven.
    Returns (judoka_uit_p1, judoka_uit_p2) of None.
    """
    oude_club_penalty = club_penalty(p1) + club_penalty(p2)

    # Geen penalty = geen verbetering mogelijk
    if oude_club_penalty == 0:
        return None

    for j1 in p1.judokas:
        for j2 in p2.judokas:
            # Skip als zelfde club (swap helpt niet)
            if j1.club_id == j2.club_id:
                continue

            # Simuleer swap
            p1_na = [j for j in p1.judokas if j is not j1] + [j2]
            p2_na = [j for j in p2.judokas if j is not j2] + [j1]

            # Check harde constraints
            if not check_poule_constraints(p1_na, max_kg, max_lft, max_band):
                continue
            if not check_poule_constraints(p2_na, max_kg, max_lft, max_band):
                continue

            # Check verbetering
            nieuwe_club_penalty = club_penalty_list(p1_na) + club_penalty_list(p2_na)

            if nieuwe_club_penalty < oude_club_penalty:
                return (j1, j2)

    return None


def club_penalty_list(judokas: List[Judoka]) -> int:
    """Bereken club penalty voor een lijst judoka's."""
    clubs = [j.club_id for j in judokas if j.club_id]
    if not clubs:
        return 0
    from collections import Counter
    counts = Counter(clubs)
    return sum(count - 1 for count in counts.values())
```

### 2.3 Integratie in solve()

Pas de `solve()` functie aan (rond regel 459):

```python
# Sliding window basis
poules = sliding_window(judokas, max_kg, max_lft, max_band, voorkeur)

# Merge kleine poules
poules = merge_kleine_poules(poules, max_kg, max_lft, max_band, voorkeur)

# Greedy++ optimalisatie (NIEUW)
poules = greedy_plus_plus(poules, max_kg, max_lft, max_band, voorkeur)
```

---

## Deel 3: Testen

### 3.1 Unit Tests Python

**Bestand:** `scripts/test_poule_solver.py` (nieuw)

```python
import unittest
from poule_solver import Judoka, Poule, solve, kan_toevoegen, club_penalty

class TestBandMapping(unittest.TestCase):
    def test_band_sortering(self):
        """Band 0 (wit) moet voor band 6 (zwart) komen"""
        judokas = [
            Judoka(id=1, leeftijd=8, gewicht=30, band=6),  # zwart
            Judoka(id=2, leeftijd=8, gewicht=30, band=0),  # wit
            Judoka(id=3, leeftijd=8, gewicht=30, band=3),  # groen
        ]
        gesorteerd = sorted(judokas, key=lambda j: j.band)
        self.assertEqual(gesorteerd[0].band, 0)  # wit eerst
        self.assertEqual(gesorteerd[-1].band, 6)  # zwart laatst

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

        # Check dat wit en groen NIET in zelfde poule zitten
        for poule in result['poules']:
            ids = poule['judoka_ids']
            if 1 in ids and 3 in ids:
                self.fail("Wit (0) en groen (3) zitten in zelfde poule met max_band=2!")

class TestClubSpreiding(unittest.TestCase):
    def test_club_penalty_berekening(self):
        """Club penalty moet dubbele clubs tellen"""
        # 3 judoka's van club A = penalty 2 (3-1)
        judokas = [
            Judoka(id=1, leeftijd=8, gewicht=30, band=0, club_id=1),
            Judoka(id=2, leeftijd=8, gewicht=30, band=0, club_id=1),
            Judoka(id=3, leeftijd=8, gewicht=30, band=0, club_id=1),
        ]
        poule = Poule(judokas=judokas)
        self.assertEqual(club_penalty(poule), 2)

    def test_club_penalty_gemengd(self):
        """Gemengde clubs = lagere penalty"""
        # 2x club A, 2x club B, 1x club C = penalty 2
        judokas = [
            Judoka(id=1, leeftijd=8, gewicht=30, band=0, club_id=1),
            Judoka(id=2, leeftijd=8, gewicht=30, band=0, club_id=1),
            Judoka(id=3, leeftijd=8, gewicht=30, band=0, club_id=2),
            Judoka(id=4, leeftijd=8, gewicht=30, band=0, club_id=2),
            Judoka(id=5, leeftijd=8, gewicht=30, band=0, club_id=3),
        ]
        poule = Poule(judokas=judokas)
        self.assertEqual(club_penalty(poule), 2)  # (2-1) + (2-1) + (1-1) = 2

class TestGreedyPlusPlus(unittest.TestCase):
    def test_orphan_rescue(self):
        """Orphan moet aan passende poule worden toegevoegd"""
        # TODO: implementeer
        pass

if __name__ == '__main__':
    unittest.main()
```

### 3.2 Handmatige Test

**Test 1: Band limiet**
1. Maak testtoernooi met categorie `max_band_verschil: 2`
2. Voeg judoka's toe: 3x wit(0), 2x geel(1), 2x groen(3), 2x blauw(4)
3. Genereer poule-indeling
4. Verwacht: wit+geel samen, groen+blauw samen (niet gemengd)

**Test 2: Club spreiding**
1. Maak testtoernooi met 10 judoka's van 2 clubs (5 per club)
2. Zelfde gewicht/leeftijd/band
3. Genereer poule-indeling
4. Verwacht: 2 poules van 5, elke poule ~2-3 judoka's per club (niet 5+0)

---

## Implementatie Volgorde

### Stap 1: Band mapping documenteren
- [x] Band niveaus vastleggen (1=wit → 7=zwart)
- [ ] CLASSIFICATIE.md updaten (check consistentie)
- [ ] context.md updaten (verwijder tegenstrijdige info)

### Stap 2: max_band_verschil basis
- [ ] UI veld toevoegen in edit.blade.php
- [ ] JavaScript collectFormData/renderCategorieen aanpassen
- [ ] PHP service aanpassen (callPythonSolver + simpleFallback)

### Stap 3: Python solver max_band
- [ ] Input parsing uitbreiden
- [ ] Sliding window op band toevoegen
- [ ] Merge check uitbreiden met band

### Stap 4: Greedy++ implementatie
- [ ] `kan_toevoegen()` helper
- [ ] `check_poule_constraints()` helper
- [ ] `vind_band_verbeterende_swap()`
- [ ] `club_penalty()` + `vind_club_verbeterende_swap()`
- [ ] `greedy_plus_plus()` hoofdfunctie
- [ ] Integratie in `solve()`

### Stap 5: Testen & Documentatie
- [ ] Unit tests Python
- [ ] Handmatige test op staging
- [ ] CLASSIFICATIE.md updaten

---

## Prioriteit Hiërarchie (samenvatting)

```
HARDE CRITERIA (mogen NOOIT overschreden):
1. max_kg_verschil
2. max_leeftijd_verschil
3. max_band_verschil

ZACHTE OPTIMALISATIE (verbeteren waar mogelijk):
4. Poulegrootte (voorkeur [5,4,6,3])
5. Band spreiding (minder spreiding = beter)
6. Club spreiding (meer spreiding = beter) ← LAAGSTE prioriteit
```

---

## Referenties

- Huidige solver: `scripts/poule_solver.py`
- PHP service: `app/Services/DynamischeIndelingService.php`
- UI: `resources/views/pages/toernooi/edit.blade.php`
- Documentatie: `docs/2-FEATURES/CLASSIFICATIE.md`
