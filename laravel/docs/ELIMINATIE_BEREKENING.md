# Eliminatie (K.O.) Systeem - Berekening

Dit document beschrijft de wiskundige logica voor het double elimination systeem.

## Basisformules

| Variabele | Formule | Betekenis |
|-----------|---------|-----------|
| N | - | Aantal judoka's |
| D | 2^floor(log₂N) | Doel: grootste macht van 2 ≤ N |
| A | N - 1 | Wedstrijden in A-groep |
| B | N - 4 | Wedstrijden in B-groep |
| Totaal | 2N - 5 | Totaal wedstrijden |

## A-Groep (Winners Bracket)

### Structuur
```
1/16 → 1/8 → 1/4 → 1/2 → Finale → 🥇 Goud + 🥈 Zilver
```

### Byes (geen aparte voorronde)

Bij niet-macht-van-2 worden **byes** gebruikt in de eerste ronde:

```
Echte wedstrijden = N - D
Byes = D - (N - D) = 2D - N  (alleen als N > D)
```

| N | D | Wedstrijden 1/16 | Byes |
|---|---|------------------|------|
| 29 | 16 | 13 | 3 |
| 24 | 16 | 8 | 8 |
| 20 | 16 | 4 | 12 |
| 16 | 16 | 0 (start bij 1/8) | - |

### A-groep wedstrijden per ronde

| Ronde | Wedstrijden |
|-------|-------------|
| 1/16 | N - D (of 0) |
| 1/8 | D/2 = 8 |
| 1/4 | 4 |
| 1/2 | 2 |
| Finale | 1 |
| **Totaal** | **N - 1** |

## B-Groep (Losers Bracket / Herkansing)

### Instroom vanuit A

| A-ronde | Verliezers | Naar B-ronde |
|---------|------------|--------------|
| 1/16 | N - D | B start / B 1/8 (1) |
| 1/8 | D/2 | B 1/8 (2) |
| 1/4 | 4 | B 1/4 (2) |
| 1/2 | 2 | B 1/2 (2) = Brons |

### Dubbele rondes

B-groep heeft **dubbele rondes** door de batch instroom:

```
B: Start → 1/8(1) → 1/8(2) → 1/4(1) → 1/4(2) → 1/2(1) → 1/2(2) → 2x 🥉
```

### B-groep wedstrijden berekening

**Totaal B = N - 4**

| Ronde | Wedstrijden | Toelichting |
|-------|-------------|-------------|
| B start | max(0, instroom - 16) | Om naar macht van 2 te komen |
| B 1/8 (1) | 8 | B onderling |
| B 1/8 (2) | 8 | + A 1/8 verliezers |
| B 1/4 (1) | 4 | B winnaars |
| B 1/4 (2) | 4 | + A 1/4 verliezers |
| B 1/2 (1) | 2 | B winnaars |
| B 1/2 (2) | 2 | + A 1/2 verliezers → **Brons** |

## Voorbeeldberekeningen

### 29 Judoka's

```
N = 29, D = 16

A-GROEP:
- 1/16: 29 - 16 = 13 wedstrijden (26 judoka's, 3 bye)
- 1/8: 8 wedstrijden
- 1/4: 4 wedstrijden
- 1/2: 2 wedstrijden
- Finale: 1 wedstrijd
Totaal A: 28 wedstrijden (= N - 1 ✓)

B-GROEP:
- Instroom 1: 13 (A 1/16 verliezers)
- Instroom 2: 8 (A 1/8 verliezers)
- Totaal eerste instroom: 21
- B start: 21 - 16 = 5 wedstrijden
- B 1/8 (1): 8 wedstrijden
- B 1/8 (2): 8 wedstrijden
- B 1/4 (1): 4 wedstrijden
- B 1/4 (2): 4 wedstrijden
- B 1/2 (1): 2 wedstrijden
- B 1/2 (2): 2 wedstrijden (Brons)
Totaal B: 25 wedstrijden (= N - 4 ✓)

TOTAAL: 53 wedstrijden (= 2N - 5 ✓)
```

### 16 Judoka's (precies macht van 2)

```
N = 16, D = 16

A-GROEP:
- 1/16: 0 wedstrijden (start direct bij 1/8)
- 1/8: 8 wedstrijden
- 1/4: 4 wedstrijden
- 1/2: 2 wedstrijden
- Finale: 1 wedstrijd
Totaal A: 15 wedstrijden (= N - 1 ✓)

B-GROEP:
- Instroom: 8 (A 1/8 verliezers)
- B 1/8 (1): 4 wedstrijden
- B 1/8 (2): 4 wedstrijden
- B 1/4 (1): 2 wedstrijden
- B 1/4 (2): 2 wedstrijden
- B 1/2 (2): 2 wedstrijden (Brons)
Totaal B: 12 wedstrijden (= N - 4 ✓)

TOTAAL: 27 wedstrijden (= 2N - 5 ✓)
```

## Batch Timing

Verliezers stromen in **batches** in naar B:

1. **Na A 1/16**: verliezers beschikbaar voor B start
2. **Na A 1/8**: verliezers combineren met B 1/8 (1) winnaars
3. **Na A 1/4**: verliezers combineren met B 1/4 (1) winnaars
4. **Na A 1/2**: verliezers naar B 1/2 (2) = Brons

Dit maakt **parallel spelen** mogelijk:
- B-groep kan beginnen zodra A 1/16 klaar is
- Niet wachten tot hele A-groep klaar is

## Quick Reference

```
29 judoka's:
├── A: 13 + 8 + 4 + 2 + 1 = 28 wedstrijden
├── B: 5 + 8 + 8 + 4 + 4 + 2 + 2 = 25 wedstrijden
└── Totaal: 53 wedstrijden, 4 medailles

Formules:
├── A = N - 1
├── B = N - 4
└── Totaal = 2N - 5
```
