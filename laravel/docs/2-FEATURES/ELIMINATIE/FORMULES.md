# Eliminatie Formules

> **Authoritative bron** voor alle eliminatie berekeningen.
> Bij twijfel: dit document is leidend.

## Basis Definities

```
N  = aantal judoka's in de poule
D  = 2^floor(log2(N))    # grootste macht van 2 die <= N
```

### D Bepalen

| N range | D | Eerste A-ronde |
|---------|---|----------------|
| 5-8 | 4 | A-1/4 |
| 9-16 | 8 | A-1/8 |
| 17-32 | 16 | A-1/16 |
| 33-64 | 32 | A-1/32 |
| 65-128 | 64 | A-1/64 |

**Speciale gevallen (exacte machten van 2):**
- N=8: D=8, eerste ronde = A-1/4 (NIET A-1/8)
- N=16: D=16, eerste ronde = A-1/8 (NIET A-1/16)
- N=32: D=32, eerste ronde = A-1/16 (NIET A-1/32)

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

### KRITIEK: Echte Verliezers per Ronde

De formule `V1 = N - D` geeft het aantal **extra wedstrijden** in de eerste ronde, maar dit is **NIET** altijd gelijk aan het aantal verliezers!

**Correcte berekening:**

```php
// Stap 1: Bepaal bracket grootte
$bracketGrootte = pow(2, ceil(log2($n)));  // Kleinste 2^x >= N

// Stap 2: Bepaal eerste VOLLE ronde (zonder byes)
$eersteVolleRonde = $bracketGrootte / 2;

// Stap 3: Bereken echte verliezers
$a1Verliezers = $eersteVolleRonde;         // Eerste volle ronde
$a2Verliezers = $eersteVolleRonde / 2;     // Tweede volle ronde

// Stap 4: Bepaal SAMEN of DUBBEL
$dubbelRondes = ($a1Verliezers > $a2Verliezers);  // Altijd true behalve edge cases
```

### Waarom V1 > V2 Niet Werkt

| N | D | V1 (N-D) | V2 (D/2) | V1 > V2? | Werkelijk |
|---|---|----------|----------|----------|-----------|
| 12 | 8 | 4 | 4 | Nee | A1=4, A2=4 → SAMEN ✓ |
| 16 | 16 | 0 | 8 | Nee | A1=8, A2=4 → **DUBBEL** ✗ |
| 24 | 16 | 8 | 8 | Nee | A1=8, A2=8 → SAMEN ✓ |
| 32 | 32 | 0 | 16 | Nee | A1=16, A2=8 → **DUBBEL** ✗ |

**Probleem:** Bij exacte machten van 2 (N=16, 32, 64) is V1=0, maar er zijn WEL verliezers!

### Correcte Formule

```php
/**
 * Bepaal of B-groep dubbele rondes nodig heeft
 */
public function heeftDubbeleRondes(int $n): bool
{
    // Kleinste bracket waar N in past
    $bracketGrootte = pow(2, ceil(log2($n)));

    // Eerste volle ronde = bracket / 2
    $eersteVolleRonde = $bracketGrootte / 2;

    // Verliezers per ronde
    $a1Verliezers = $eersteVolleRonde;
    $a2Verliezers = $eersteVolleRonde / 2;

    return $a1Verliezers > $a2Verliezers;
}
```

## B-Groep Structuur

### SAMEN (verliezers eerste ronde == verliezers tweede ronde)

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

### DUBBEL (verliezers eerste ronde > verliezers tweede ronde)

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

De B-groep start **één niveau lager** dan waar de tweede batch verliezers vandaan komt:

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
