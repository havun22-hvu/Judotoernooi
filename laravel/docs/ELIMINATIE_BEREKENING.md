# Eliminatie (K.O.) Systeem - Berekening

Dit document beschrijft de wiskundige logica voor het double elimination systeem.

## Overzicht

```
A-groep (Hoofdboom)     →  Goud + Zilver
B-groep (Herkansing)    →  Brons (aantal instelbaar: 1 of 2)

Iedereen start in A-groep.
Verlies in A = naar B-groep (herkansing).
Verlies in B = uitgeschakeld.
```

### Instelling: aantal_brons (toernooi configuratie)

| aantal_brons | B-einde | Medailles |
|--------------|---------|-----------|
| **2** (default) | 2x B-1/2(2) = 2x BRONS (geen finale) | 1 goud, 1 zilver, 2 brons |
| **1** | B-finale (winnaars B-1/2(2)) = 1x BRONS | 1 goud, 1 zilver, 1 brons |

**Bij 2 bronzen medailles:**
- Beide verliezers van A-1/2 finale krijgen brons via B-1/2(2)
- GEEN B-finale nodig

**Bij 1 bronzen medaille:**
- Winnaars van B-1/2(2) vechten tegen elkaar in B-finale
- Winnaar B-finale = brons

---

## A-GROEP (Winners Bracket)

### Ronde-indeling op basis van aantal judoka's

| Eerste A-ronde | Aantal judoka's | D (doel) |
|----------------|-----------------|----------|
| A-1/4 finale | 5-8 | 4 |
| A-1/8 finale | 9-16 | 8 |
| A-1/16 finale | 17-32 | 16 |
| A-1/32 finale | 33-64 | 32 |
| A-1/64 finale | 65-128 | 64 |

### Formules A-groep

```
N = aantal judoka's in de categorie
D = grootste macht van 2 die kleiner of gelijk is aan N
    (D = 4, 8, 16, 32, 64, ...)

Voorbeelden D:
- N=27 → D=16 (want 16 ≤ 27 < 32)
- N=35 → D=32 (want 32 ≤ 35 < 64)
- N=16 → D=16 (want 16 = 16)
- N=9  → D=8  (want 8 ≤ 9 < 16)

Eerste ronde wedstrijden = N - D
Byes in eerste ronde = 2D - N
Totaal A-wedstrijden = N - 1
```

### Voorbeeld: 27 judoka's

```
N = 27, D = 16

Eerste ronde: A-1/16 finale
- Wedstrijden: 27 - 16 = 11
- Byes: 32 - 27 = 5

Volgende rondes:
- A-1/8: 8 wedstrijden (16 judoka's)
- A-1/4: 4 wedstrijden
- A-1/2: 2 wedstrijden
- A-finale: 1 wedstrijd → Goud + Zilver

Totaal A: 11 + 8 + 4 + 2 + 1 = 26 wedstrijden (= N-1 ✓)
```

### Complete tabel A-groep (5-64 judoka's)

| N | D | 1e ronde | Wed 1e | Byes | A-1/8 | A-1/4 | A-1/2 | Finale | Totaal A |
|---|---|----------|--------|------|-------|-------|-------|--------|----------|
| 5 | 4 | A-1/4 | 1 | 3 | - | - | 2 | 1 | 4 |
| 6 | 4 | A-1/4 | 2 | 2 | - | - | 2 | 1 | 5 |
| 7 | 4 | A-1/4 | 3 | 1 | - | - | 2 | 1 | 6 |
| 8 | 8 | A-1/8 | 0 | - | 4 | - | 2 | 1 | 7 |
| 9 | 8 | A-1/8 | 1 | 7 | 4 | - | 2 | 1 | 8 |
| 10 | 8 | A-1/8 | 2 | 6 | 4 | - | 2 | 1 | 9 |
| 11 | 8 | A-1/8 | 3 | 5 | 4 | - | 2 | 1 | 10 |
| 12 | 8 | A-1/8 | 4 | 4 | 4 | - | 2 | 1 | 11 |
| 13 | 8 | A-1/8 | 5 | 3 | 4 | 4 | 2 | 1 | 12 |
| 14 | 8 | A-1/8 | 6 | 2 | 4 | 4 | 2 | 1 | 13 |
| 15 | 8 | A-1/8 | 7 | 1 | 4 | 4 | 2 | 1 | 14 |
| 16 | 16 | A-1/16 | 0 | - | 8 | 4 | 2 | 1 | 15 |
| 17-24 | 16 | A-1/16 | 1-8 | 15-8 | 8 | 4 | 2 | 1 | 16-23 |
| 25-32 | 16 | A-1/16 | 9-16 | 7-0 | 8 | 4 | 2 | 1 | 24-31 |
| 33-48 | 32 | A-1/32 | 1-16 | 31-16 | 16 | 8 | 4 | 2 | 1 | 32-47 |
| 49-64 | 32 | A-1/32 | 17-32 | 15-0 | 16 | 8 | 4 | 2 | 1 | 48-63 |

---

## B-GROEP (Losers Bracket / Herkansing)

### Kernprincipe

```
B-groep vangt verliezers op uit A-groep in BATCHES:

Batch 1: Verliezers uit A-1e ronde      → starten in B
Batch 2: Verliezers uit A-2e ronde      → voegen zich bij B
Batch 3: Verliezers uit A-1/4 finale    → voegen zich bij B
Batch 4: Verliezers uit A-1/2 finale    → BRONS wedstrijden
```

### B-start ronde bepalen

**De B-groep start één niveau lager dan de A-2e ronde:**

| A-1e ronde | A-2e ronde | Verliezers A-2e | B-start ronde |
|------------|------------|-----------------|---------------|
| A-1/4 | A-1/2 | 2 | B-1/4 |
| A-1/8 | A-1/4 | 4 | B-1/4 |
| A-1/16 | A-1/8 | 8 | B-1/8 |
| A-1/32 | A-1/16 | 16 | B-1/16 |
| A-1/64 | A-1/32 | 32 | B-1/32 |

### KRITIEKE REGEL: Enkele vs Dubbele rondes

**Wanneer zijn (1) en (2) rondes nodig?**

De verliezers uit A-2e ronde vullen ALTIJD de helft van de B-start ronde.
De verliezers uit A-1e ronde vullen de andere helft.

```
Als verliezers A-1e ronde ≤ verliezers A-2e ronde:
    → ENKELE B-ronde (alles past in één ronde)

Als verliezers A-1e ronde > verliezers A-2e ronde:
    → DUBBELE B-rondes: (1) en (2)
    → B-start(1): verliezers A-1e ronde onderling
    → B-start(2): winnaars (1) + verliezers A-2e ronde
```

### Grenswaarden per eerste A-ronde

| A-1e ronde | Verliezers A-2e | Grens | Enkele B | Dubbele B |
|------------|-----------------|-------|----------|-----------|
| A-1/8 | 4 | >4 | ≤4 verl (N=9-12) | 5-8 verl (N=13-16) |
| A-1/16 | 8 | >8 | ≤8 verl (N=17-24) | 9-16 verl (N=25-32) |
| A-1/32 | 16 | >16 | ≤16 verl (N=33-48) | 17-32 verl (N=49-64) |
| A-1/64 | 32 | >32 | ≤32 verl (N=65-96) | 33-64 verl (N=97-128) |

### Formule

```
D = grootste macht van 2 ≤ N
V1 = N - D                    (verliezers A-1e ronde)
V2 = D / 2                    (verliezers A-2e ronde, altijd vast!)

Als V1 ≤ V2:  ENKELE B-rondes
Als V1 > V2:  DUBBELE B-rondes met (1) en (2)
```

**Voorbeelden:**
```
N=20, D=16:  V1 = 20-16 = 4,  V2 = 16/2 = 8  → V1 ≤ V2 → ENKELE rondes
N=27, D=16:  V1 = 27-16 = 11, V2 = 16/2 = 8  → V1 > V2 → DUBBELE rondes
N=40, D=32:  V1 = 40-32 = 8,  V2 = 32/2 = 16 → V1 ≤ V2 → ENKELE rondes
N=50, D=32:  V1 = 50-32 = 18, V2 = 32/2 = 16 → V1 > V2 → DUBBELE rondes
```

---

## COMPLETE B-STRUCTUUR PER SCENARIO

### Scenario 1: A-1/8 als eerste ronde (9-16 judoka's)

**Case A: N = 9-12 (1-4 verliezers uit A-1/8) → ENKELE rondes**
```
A-1/8 verliezers (1-4) ──┬──► B-1/4 (max 4 wed) ──► B-1/2(1) ──► B-1/2(2) = BRONS
A-1/4 verliezers (4)  ──┘                              ↑
                                          A-1/2 verliezers (2)
```

**Case B: N = 13-16 (5-8 verliezers uit A-1/8) → DUBBELE rondes**
```
A-1/8 verliezers (5-8) ──► B-1/4(1) ──► B-1/4(2) ──► B-1/2(1) ──► B-1/2(2) = BRONS
                                ↑              ↑              ↑
                    A-1/4 verliezers (4)       │    A-1/2 verliezers (2)
```

### Scenario 2: A-1/16 als eerste ronde (17-32 judoka's)

**Case A: N = 17-24 (1-8 verliezers uit A-1/16) → ENKELE rondes**
```
A-1/16 verliezers (1-8) ──┬──► B-1/8 ──► B-1/4 ──► B-1/4(2) ──► B-1/2(1) ──► B-1/2(2) = BRONS
A-1/8 verliezers (8)   ──┘         ↑         ↑              ↑
                                   │    A-1/4 verl (4)   A-1/2 verl (2)
```

**Case B: N = 25-32 (9-16 verliezers uit A-1/16) → DUBBELE rondes**
```
A-1/16 verliezers (9-16) ──► B-1/8(1) ──► B-1/8(2) ──► B-1/4(1) ──► B-1/4(2) ──► B-1/2(1) ──► B-1/2(2)
                                    ↑              ↑              ↑              ↑
                          A-1/8 verl (8)           │    A-1/4 verl (4)    A-1/2 verl (2)
```

### Scenario 3: A-1/32 als eerste ronde (33-64 judoka's)

**Case A: N = 33-48 (1-16 verliezers uit A-1/32) → ENKELE rondes**
```
A-1/32 verliezers (1-16) ──┬──► B-1/16 ──► B-1/8 ──► ... ──► B-1/2(2) = BRONS
A-1/16 verliezers (16)  ──┘
```

**Case B: N = 49-64 (17-32 verliezers uit A-1/32) → DUBBELE rondes**
```
A-1/32 verliezers (17-32) ──► B-1/16(1) ──► B-1/16(2) ──► B-1/8(1) ──► B-1/8(2) ──► ...
                                      ↑
                            A-1/16 verl (16)
```

### Scenario 4: A-1/64 als eerste ronde (65-128 judoka's)

**Case A: N = 65-96 (1-32 verliezers uit A-1/64) → ENKELE rondes**
```
A-1/64 verliezers (1-32) ──┬──► B-1/32 ──► B-1/16 ──► ... ──► B-1/2(2) = BRONS
A-1/32 verliezers (32)  ──┘
```

**Case B: N = 97-128 (33-64 verliezers uit A-1/64) → DUBBELE rondes**
```
A-1/64 verliezers (33-64) ──► B-1/32(1) ──► B-1/32(2) ──► B-1/16(1) ──► ...
                                       ↑
                             A-1/32 verl (32)
```

---

## BYES BEREKENING

### Legenda

```
N  = aantal judoka's in de categorie
D  = grootste macht van 2 ≤ N (4, 8, 16, 32, 64...)
V1 = verliezers uit A-1e ronde = N - D
V2 = verliezers uit A-2e ronde = D / 2 (altijd vast!)
```

**Voorbeelden:**
| N | D | V1 (=N-D) | V2 (=D/2) |
|---|---|-----------|-----------|
| 20 | 16 | 4 | 8 |
| 27 | 16 | 11 | 8 |
| 40 | 32 | 8 | 16 |
| 50 | 32 | 18 | 16 |

### A-groep byes

```
A-byes = 2D - N
```

**Voorbeelden:**
| N | D | A-byes (=2D-N) | Betekenis |
|---|---|----------------|-----------|
| 27 | 16 | 5 | 5 judoka's direct naar A-1/8 |
| 20 | 16 | 12 | 12 judoka's direct naar A-1/8 |
| 16 | 16 | 16 | Geen voorronde, start bij A-1/8 |

### B-groep byes

**Stap 1: Bepaal B-start capaciteit**
```
B-start capaciteit = 2 × V2

Waarom? De B-start ronde heeft evenveel wedstrijden als V2.
V2 wedstrijden = V2 × 2 plekken = 2 × V2 capaciteit.
```

**Stap 2: Bereken B-byes**

| Situatie | Instroom B-start | B-byes formule |
|----------|------------------|----------------|
| ENKELE rondes (V1 ≤ V2) | V1 + V2 | 2×V2 - (V1+V2) = **V2 - V1** |
| DUBBELE rondes (V1 > V2) | alleen V1 | 2×V2 - V1 = **2×V2 - V1** |

**Voorbeelden:**
```
N=20, D=16 (ENKELE rondes):
- V1=4, V2=8 → V1 ≤ V2
- B-1/8 capaciteit = 2×8 = 16
- Instroom = V1 + V2 = 4 + 8 = 12
- B-byes = 16 - 12 = 4

N=27, D=16 (DUBBELE rondes):
- V1=11, V2=8 → V1 > V2
- B-1/8(1) capaciteit = 2×8 = 16
- Instroom B-1/8(1) = V1 = 11
- B-byes = 16 - 11 = 5
```

### FAIRNESS REGEL: B-byes verdeling

```
REGEL: B-byes NIET aan A-bye judoka's geven (tenzij niet anders kan)

Reden: Judoka's die al een bye hadden in A-groep, moeten niet
       nóg een bye krijgen in B-groep. Anders krijgen zij oneerlijk
       voordeel (minder wedstrijden, meer rust).

Implementatie:
1. Markeer welke judoka's A-bye hadden
2. Bij B-groep plaatsing: geef byes aan judoka's ZONDER A-bye
3. Alleen als alle judoka's A-bye hadden, mag B-bye aan A-bye
```

---

## COMPLETE TABEL (6-64 judoka's)

| N | D | A-1e ronde | V1 | V2 | V1>V2? | B-structuur |
|---|---|------------|----|----|--------|-------------|
| 6 | 4 | A-1/4 | 2 | 2 | Nee | B-1/4, B-1/2(2) |
| 7 | 4 | A-1/4 | 3 | 2 | Ja | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 8 | 8 | A-1/8 | 0 | 4 | Nee | B-1/4, B-1/2(2) |
| 9 | 8 | A-1/8 | 1 | 4 | Nee | B-1/4, B-1/2(2) |
| 10 | 8 | A-1/8 | 2 | 4 | Nee | B-1/4, B-1/2(2) |
| 11 | 8 | A-1/8 | 3 | 4 | Nee | B-1/4, B-1/2(2) |
| 12 | 8 | A-1/8 | 4 | 4 | Nee | B-1/4, B-1/2(2) |
| 13 | 8 | A-1/8 | 5 | 4 | Ja | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 14 | 8 | A-1/8 | 6 | 4 | Ja | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 15 | 8 | A-1/8 | 7 | 4 | Ja | B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 16 | 16 | A-1/16 | 0 | 8 | Nee | B-1/8, B-1/4, B-1/2(2) |
| 17 | 16 | A-1/16 | 1 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 18 | 16 | A-1/16 | 2 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 19 | 16 | A-1/16 | 3 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 20 | 16 | A-1/16 | 4 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 21 | 16 | A-1/16 | 5 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 22 | 16 | A-1/16 | 6 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 23 | 16 | A-1/16 | 7 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 24 | 16 | A-1/16 | 8 | 8 | Nee | B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 25 | 16 | A-1/16 | 9 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 26 | 16 | A-1/16 | 10 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 27 | 16 | A-1/16 | 11 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 28 | 16 | A-1/16 | 12 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 29 | 16 | A-1/16 | 13 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 30 | 16 | A-1/16 | 14 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 31 | 16 | A-1/16 | 15 | 8 | Ja | B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 32 | 32 | A-1/32 | 0 | 16 | Nee | B-1/16, B-1/8, B-1/4, B-1/4(2), B-1/2(1), B-1/2(2) |
| 33-48 | 32 | A-1/32 | 1-16 | 16 | Nee | B-1/16, B-1/8, B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |
| 49-64 | 32 | A-1/32 | 17-32 | 16 | Ja | B-1/16(1), B-1/16(2), B-1/8(1), B-1/8(2), B-1/4(1), B-1/4(2), B-1/2(1), B-1/2(2) |

---

## GESPIEGELDE LAYOUT

B-groep is **horizontaal gespiegeld** rond de B-1/2 finale horizon:

```
                                                    BRONS
B-1/8(1)    B-1/8(2)    B-1/4(1)    B-1/4(2)    B-1/2(1)    B-1/2(2)
┌──┐
│  ├──┐
└──┘  │
      ├──┐
┌──┐  │  │
│  ├──┘  │
└──┘     ├──┐
         │  │
┌──┐     │  │
│  ├──┐  │  │
└──┘  │  │  │
      ├──┘  ├──┐
┌──┐  │     │  │
│  ├──┘     │  ├──┐
└──┘        │  │  ├──── 🥉 Brons 1
════════════════════════════════ horizon
┌──┐        │  │  ├──── 🥉 Brons 2
│  ├──┐     │  ├──┘
└──┘  │     │  │
      ├──┐  ├──┘
┌──┐  │  │  │
│  ├──┘  │  │
└──┘     ├──┘
         │
┌──┐     │
│  ├──┐  │
└──┘  │  │
      ├──┘
┌──┐  │
│  ├──┘
└──┘
```

**BELANGRIJK:**
- Bij `aantal_brons = 2`: 2x B-1/2(2) = 2x BRONS, **GEEN B-finale!**
- Bij `aantal_brons = 1`: B-1/2(2) winnaars → B-finale = 1x BRONS
- B-1/2(1) winnaars (wit) vs A-1/2 verliezers (blauw) in B-1/2(2)

---

## TOTAAL WEDSTRIJDEN

```
A-groep wedstrijden = N - 1
B-groep wedstrijden = N - 4 (bij 2 brons) of N - 3 (bij 1 brons)
Totaal wedstrijden  = 2N - 5 (bij 2 brons) of 2N - 4 (bij 1 brons)
```

| aantal_brons | B-wedstrijden | Totaal | Medailles |
|--------------|---------------|--------|-----------|
| 2 | N - 4 | 2N - 5 | 4 (1 goud, 1 zilver, 2 brons) |
| 1 | N - 3 | 2N - 4 | 3 (1 goud, 1 zilver, 1 brons) |

### Verificatie voorbeelden

| N | A-wed | B-wed | Totaal | Formule check |
|---|-------|-------|--------|---------------|
| 16 | 15 | 12 | 27 | 2×16-5=27 ✓ |
| 24 | 23 | 20 | 43 | 2×24-5=43 ✓ |
| 27 | 26 | 23 | 49 | 2×27-5=49 ✓ |
| 32 | 31 | 28 | 59 | 2×32-5=59 ✓ |

---

## SAMENVATTING ALGORITME

```
INPUT: N = aantal judoka's

STAP 1: Bepaal D (grootste macht van 2 ≤ N)

STAP 2: Bepaal A-1e ronde
- A-1/4 als D=4 (N=5-8)
- A-1/8 als D=8 (N=9-16)
- A-1/16 als D=16 (N=17-32)
- A-1/32 als D=32 (N=33-64)
- A-1/64 als D=64 (N=65-128)

STAP 3: Bereken verliezers
- V1 = N - D (verliezers A-1e ronde)
- V2 = D / 2 (verliezers A-2e ronde)

STAP 4: Bepaal B-structuur
- Als V1 ≤ V2: ENKELE B-rondes
- Als V1 > V2: DUBBELE B-rondes met (1) en (2)

STAP 5: Genereer brackets
- A-groep: standaard knockout
- B-groep: volgens structuur uit stap 4
```
