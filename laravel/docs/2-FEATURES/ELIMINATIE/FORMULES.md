---
title: Eliminatie Formules
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

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

De B-start ronde heeft `a2` wedstrijden.

| Conditie | Plaatsing | B-structuur |
|----------|-----------|-------------|
| a1 = a2 | a1 → WIT, a2 → BLAUW, precies gevuld | **SAMEN** exact |
| a1 < a2 | a1 → WIT, extra (a2-a1) a2 ook → WIT, rest a2 → BLAUW | **SAMEN** met byes |
| a1 > a2 | a1 past NIET in a2 slots, extra ronde nodig | **DUBBEL** met (1)/(2) |

**UITZONDERING bij SAMEN (a1 < a2):**
Er zijn meer a2 dan a1 verliezers. De extra (a2-a1) a2-verliezers komen op WIT.
Dit is de ENIGE situatie dat a2 verliezers op WIT komen.
Vul-volgorde: zie Fairness Regel.

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

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [FORMULES-AFLEIDING.md](./FORMULES-AFLEIDING.md) | Je wilt de vul-volgorde van de B-start weten (SAMEN vs DUBBEL), op welk niveau B start, of de eerste A/B-ronde vooraf uit N berekenen |
| [FORMULES-BYES.md](./FORMULES-BYES.md) | Je rekent byes uit (A-groep, B-groep, DUBBEL-spreiding), zoekt de fairness-regel, of controleert het totaal aantal wedstrijden |
