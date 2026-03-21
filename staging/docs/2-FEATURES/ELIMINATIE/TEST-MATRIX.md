# Test Matrix

> Verificatietabellen voor eliminatie berekeningen.
> Gebruik deze tabellen om code te valideren.

## Quick Test: Samen of Dubbel?

| N | a1 | a2 | a1 > a2? | Type |
|---|----|----|----------|------|
| 12 | 4 | 4 | Nee | **SAMEN** (exact) |
| 16 | 8 | 4 | Ja | **DUBBEL** |
| 21 | 5 | 8 | Nee | **SAMEN** (3 byes WIT) |
| 24 | 8 | 8 | Nee | **SAMEN** (exact) |
| 32 | 16 | 8 | Ja | **DUBBEL** |
| 48 | 16 | 16 | Nee | **SAMEN** (exact) |
| 64 | 32 | 16 | Ja | **DUBBEL** |

**Vuistregels:**
- Exacte machten van 2 (8, 16, 32, 64) → ALTIJD DUBBEL
- N = 3D/2 (6, 12, 24, 48) → SAMEN exact (a1 = a2)
- Daartussen: a1 < a2 → SAMEN met byes, a1 > a2 → DUBBEL

## Complete Referentietabel (5-64 judoka's)

### N = 5-8 (D=4, start A-1/4)

| N | V1 | Byes | a1 | a2 | B-type | B-rondes |
|---|-----|------|----|----|--------|----------|
| 5 | 1 | 3 | 1 | 2 | Samen | B-1/2 |
| 6 | 2 | 2 | 2 | 2 | Samen | B-1/2 |
| 7 | 3 | 1 | 3 | 2 | Dubbel | B-1/2(1), B-1/2(2) |
| 8 | 0 | - | 4 | 2 | Dubbel | B-1/2(1), B-1/2(2) |

### N = 9-16 (D=8, start A-1/8)

| N | V1 | Byes | a1 | a2 | B-type | B-rondes |
|---|-----|------|----|----|--------|----------|
| 9 | 1 | 7 | 1 | 4 | Samen | B-1/4, B-1/2(2) |
| 10 | 2 | 6 | 2 | 4 | Samen | B-1/4, B-1/2(2) |
| 11 | 3 | 5 | 3 | 4 | Samen | B-1/4, B-1/2(2) |
| 12 | 4 | 4 | 4 | 4 | Samen | B-1/4, B-1/2(2) |
| 13 | 5 | 3 | 5 | 4 | Dubbel | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 14 | 6 | 2 | 6 | 4 | Dubbel | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 15 | 7 | 1 | 7 | 4 | Dubbel | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| **16** | 0 | - | **8** | **4** | **Dubbel** | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |

### N = 17-32 (D=16, start A-1/16)

| N | V1 | Byes | a1 | a2 | B-type | B-start | B WIT byes |
|---|-----|------|----|----|--------|---------|------------|
| 17 | 1 | 15 | 1 | 8 | Samen | B-1/8 | 7 |
| 20 | 4 | 12 | 4 | 8 | Samen | B-1/8 | 4 |
| **21** | **5** | **11** | **5** | **8** | **Samen** | **B-1/8** | **3** |
| 24 | 8 | 8 | 8 | 8 | Samen | B-1/8 | 0 |
| 25 | 9 | 7 | 9 | 8 | Dubbel | B-1/8(1) | - |
| 28 | 12 | 4 | 12 | 8 | Dubbel | B-1/8(1) | - |
| **32** | 0 | - | **16** | **8** | **Dubbel** | B-1/8(1) | - |

### N = 33-64 (D=32, start A-1/32)

| N | V1 | a1 | a2 | B-type | B-start |
|---|-----|----|----|--------|---------|
| 33-48 | 1-16 | 1-16 | 16 | Samen | B-1/16 |
| 48 | 16 | 16 | 16 | Samen | B-1/16 |
| 49-63 | 17-31 | 17-31 | 16 | Dubbel | B-1/16(1) |
| **64** | 0 | **32** | **16** | **Dubbel** | B-1/16(1) |

## Wedstrijden Verificatie

### Totaal per N (2 brons instelling)

| N | A-wed | B-wed | Totaal | Formule |
|---|-------|-------|--------|---------|
| 12 | 11 | 8 | 19 | 2×12-5=19 ✓ |
| 16 | 15 | 12 | 27 | 2×16-5=27 ✓ |
| 24 | 23 | 20 | 43 | 2×24-5=43 ✓ |
| 32 | 31 | 28 | 59 | 2×32-5=59 ✓ |
| 48 | 47 | 44 | 91 | 2×48-5=91 ✓ |
| 64 | 63 | 60 | 123 | 2×64-5=123 ✓ |

## Test Scenario's

### Test 1: N=12 (SAMEN)

```
Verwacht:
- A-1/8: 4 wedstrijden (4 verliezers)
- A-1/4: 4 wedstrijden (4 verliezers)
- A-1/2: 2 wedstrijden
- A-finale: 1 wedstrijd

- B-1/4: 4 wedstrijden (A-1/8 + A-1/4 verliezers SAMEN)
- B-1/2(2): 2 wedstrijden (A-1/2 verliezers op BLAUW)

Totaal: 11 + 8 = 19 wedstrijden
```

### Test 2: N=16 (DUBBEL)

```
Verwacht:
- A-1/8: 8 wedstrijden (8 verliezers)
- A-1/4: 4 wedstrijden (4 verliezers)
- A-1/2: 2 wedstrijden
- A-finale: 1 wedstrijd

- B-1/4(1): 4 wedstrijden (A-1/8 verliezers onderling)
- B-1/4(2): 4 wedstrijden (B-1/4(1) winnaars + A-1/4 verliezers)
- B-1/2(1): 2 wedstrijden
- B-1/2(2): 2 wedstrijden

Totaal: 15 + 12 = 27 wedstrijden
```

### Test 3: N=32 (DUBBEL)

```
Verwacht:
- A-1/16: 16 wedstrijden (16 verliezers)
- A-1/8: 8 wedstrijden (8 verliezers)
- A-1/4: 4 wedstrijden
- A-1/2: 2 wedstrijden
- A-finale: 1 wedstrijd

- B-1/8(1): 8 wedstrijden (A-1/16 verliezers onderling)
- B-1/8(2): 8 wedstrijden (B-1/8(1) winnaars + A-1/8 verliezers)
- B-1/4(1): 4 wedstrijden
- B-1/4(2): 4 wedstrijden
- B-1/2(1): 2 wedstrijden
- B-1/2(2): 2 wedstrijden

Totaal: 31 + 28 = 59 wedstrijden
```

## Code Verificatie

```php
// Test in tinker
$service = app(\App\Services\EliminatieService::class);

foreach ([12, 16, 24, 32, 48] as $n) {
    $stats = $service->berekenStatistieken($n);

    $verwachtTotaal = 2 * $n - 5;
    $actueel = $stats['a_wedstrijden'] + $stats['b_wedstrijden'];

    $status = ($actueel === $verwachtTotaal) ? '✓' : '✗';

    echo "N=$n: A={$stats['a_wedstrijden']}, B={$stats['b_wedstrijden']}, ";
    echo "Totaal=$actueel (verwacht: $verwachtTotaal) $status\n";
}
```

## Bekende Edge Cases

### N = exacte macht van 2

Bij N=16, 32, 64, etc:
- V1 = 0 (geen extra wedstrijden in eerste ronde)
- Maar A1 verliezers > A2 verliezers!
- **ALTIJD DUBBEL**, niet SAMEN

### N = D + 1 (bijv. N=17, 33, 65)

- Slechts 1 wedstrijd in eerste ronde
- 2D-N-1 judoka's krijgen bye
- Die ene verliezer gaat naar B met veel byes

### Byes Fairness

Judoka's met A-bye mogen geen B-bye krijgen (indien mogelijk).
