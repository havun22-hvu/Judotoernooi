# Eliminatie Formules

> **Authoritative bron** voor alle eliminatie berekeningen.
> Bij twijfel: dit document is leidend.

## Basis Definities

```
N  = aantal judoka's in de poule
D  = 2^floor(log2(N))    # grootste macht van 2 die <= N
```

### D Bepalen

| N range | D |
|---------|---|
| 5-7 | 4 |
| 8-15 | 8 |
| 16-31 | 16 |
| 32-63 | 32 |
| 64-127 | 64 |

### Eerste A-ronde

V1 = N - D (aantal wedstrijden in eerste ronde)

| Conditie | Eerste A-ronde | Voorbeeld |
|----------|---------------|-----------|
| V1 > 0 | A-1/D fractie (V1 wedstrijden + byes) | N=12, D=8 → A-1/8 (4 wed + 4 byes) |
| V1 = 0 (exacte macht van 2) | A-1/(D/2) fractie (geen seeding ronde) | N=8, D=8 → A-1/4 (4 wedstrijden) |

## A-Groep Formules

```
Eerste ronde wedstrijden = N - D
Byes in eerste ronde     = 2D - N
Totaal A-wedstrijden     = N - 1
```

### Voorbeeld N=24

```
D = 16 (want 16 <= 24 < 32)

Eerste A-ronde: A-1/16
- Echte wedstrijden: 24 - 16 = 8
- Byes: 32 - 24 = 8
- Na A-1/16: 16 judoka's door

Volgende rondes:
- A-1/8: 8 wedstrijden → 8 verliezers
- A-1/4: 4 wedstrijden → 4 verliezers
- A-1/2: 2 wedstrijden → 2 verliezers
- A-finale: 1 wedstrijd → Goud + Zilver

Totaal A: 8 + 8 + 4 + 2 + 1 = 23 = N - 1 ✓
```

## B-Groep: Verliezers Bepalen

### a1 en a2: Verliezers per A-ronde

```
a1 = verliezers uit de EERSTE A-ronde (gaan als eerste naar B)
a2 = verliezers uit de TWEEDE A-ronde (gaan als tweede naar B)
```

**Berekening (exact zoals EliminatieService::berekenBracketParams):**

```php
$d = pow(2, floor(log($n, 2)));  // Grootste macht van 2 ≤ N
$v1 = $n - $d;

if ($v1 > 0) {
    // Niet-exacte macht van 2 (N=9,12,21,24,etc.)
    $a1 = $v1;           // = N - D
    $a2 = (int)($d / 2);
} else {
    // Exacte macht van 2 (N=8,16,32,64)
    $a1 = (int)($d / 2);
    $a2 = (int)($d / 4);
}
```

### SAMEN of DUBBEL?

De B-start ronde heeft `a2` wedstrijden. De `a1` verliezers komen op WIT, de `a2` verliezers op BLAUW.

| Conditie | Gevolg | B-structuur |
|----------|--------|-------------|
| a1 < a2 | a1 past in a2 slots, (a2 - a1) byes op WIT | **SAMEN** met byes |
| a1 = a2 | Precies gevuld, geen byes | **SAMEN** exact |
| a1 > a2 | a1 past NIET in a2 slots, extra ronde nodig | **DUBBEL** met (1)/(2) |

```php
$dubbelRondes = $a1 > $a2;  // NIET !==, want a1 < a2 = SAMEN met byes
```

### Verificatie

| N | D | a1 | a2 | a1 > a2? | Type |
|---|---|----|----|----------|------|
| 12 | 8 | 4 | 4 | Nee | SAMEN (exact) |
| 16 | 16 | 8 | 4 | Ja | DUBBEL |
| 21 | 16 | 5 | 8 | Nee | SAMEN (3 byes WIT) |
| 24 | 16 | 8 | 8 | Nee | SAMEN (exact) |
| 32 | 32 | 16 | 8 | Ja | DUBBEL |

## B-Groep Structuur

### SAMEN (a1 ≤ a2)

Beide batches verliezers passen tegelijk in één B-ronde:

```
Eerste A-ronde verliezers → B-start WIT slots
Tweede A-ronde verliezers → B-start BLAUW slots
```

**Bepalen eerste A-ronde:**
- Eerste A-ronde = niveau van D
- D = 8 → eerste A-ronde = A-1/8
- D = 16 → eerste A-ronde = A-1/16

**Voorbeeld N=12:**
1. D = 8
2. Eerste A-ronde = A-1/8, verliezers = 4
3. Tweede A-ronde = A-1/4, verliezers = 4
4. 4 == 4 → SAMEN
5. B-start = B-1/4
6. Plaatsing: A-1/8 verliezers → WIT, A-1/4 verliezers → BLAUW

### DUBBEL (a1 > a2)

Eerste batch moet eerst onderling uitvechten:

```
Eerste A-ronde verliezers → B-start(1) onderling
B-start(1) winnaars       → B-start(2) WIT slots
Tweede A-ronde verliezers → B-start(2) BLAUW slots
```

**Voorbeeld N=31:**
1. D = 16
2. Eerste A-ronde = A-1/16, verliezers = 15
3. Tweede A-ronde = A-1/8, verliezers = 8
4. 15 > 8 → DUBBEL
5. B-start = B-1/8(1)
6. Plaatsing: A-1/16 verliezers → B-1/8(1), A-1/8 verliezers → B-1/8(2) BLAUW

## B-Start Ronde Bepalen

De B-groep start op **hetzelfde niveau** als de tweede batch A-verliezers:

| Tweede A-ronde | Verliezers | B-start |
|----------------|------------|---------|
| A-1/2 | 2 | B-1/2 |
| A-1/4 | 4 | B-1/4 |
| A-1/8 | 8 | B-1/8 |
| A-1/16 | 16 | B-1/16 |
| A-1/32 | 32 | B-1/32 |

## Byes Berekening

### A-Groep Byes

```
A-byes = 2D - N
```

### B-Groep Byes

| Situatie | Instroom | B-byes |
|----------|----------|--------|
| SAMEN | A1 + A2 | 2×A2 - (A1+A2) |
| DUBBEL | alleen A1 | 2×B-cap - A1 |

**DUBBEL spreiding (`koppelARondeAanBRonde` type 'eerste'):**

```
B-capaciteit = berekenMinimaleBWedstrijden(A1)  // Kleinste 2^x >= A1/2
Volle weds   = A1 - B-capaciteit
Bye weds     = 2 × B-cap - A1

Eerste (volle × 2) verliezers → 2:1 mapping (wit + blauw)
Resterende verliezers         → 1:1 op WIT (bye, blauw=null)
```

Bye wedstrijden worden handmatig door de hoofdjury geregistreerd.

### Fairness Regel

```
REGEL: Judoka's met A-bye krijgen GEEN B-bye (indien mogelijk)
```

## Totaal Wedstrijden

| Instelling | A | B | Totaal | Medailles |
|------------|---|---|--------|-----------|
| 2 brons | N-1 | N-4 | 2N-5 | 1G, 1Z, 2B |
| 1 brons | N-1 | N-3 | 2N-4 | 1G, 1Z, 1B |

### Verificatie

| N | A | B (2 brons) | Totaal | Check |
|---|---|-------------|--------|-------|
| 12 | 11 | 8 | 19 | 2×12-5=19 ✓ |
| 16 | 15 | 12 | 27 | 2×16-5=27 ✓ |
| 24 | 23 | 20 | 43 | 2×24-5=43 ✓ |
| 32 | 31 | 28 | 59 | 2×32-5=59 ✓ |
