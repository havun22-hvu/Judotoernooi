---
title: Eliminatie Formules: B-structuur en eerste ronde
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Eliminatie Formules: B-structuur en eerste ronde

> Onderdeel van [Eliminatie Formules](./FORMULES.md).

## B-Groep Structuur

### SAMEN (a1 ≤ a2)

Beide batches verliezers passen tegelijk in één B-ronde:

```
a1 = a2 (exact):
  a1 verliezers → WIT slots
  a2 verliezers → BLAUW slots

a1 < a2 (met byes), vul-volgorde:
  Stap 1: a1 verliezers → WIT bovenaan (slot 1, 3, 5, 7...)
  Stap 2: a2 verliezers → ALLE overige WIT slots (elke wed heeft nu minstens 1 judoka!)
  Stap 3: rest a2 → BLAUW van a2-wedstrijden (a2 vs a2, volle weds eerst)
  Stap 4: rest a2 → BLAUW van a1-wedstrijden (LAATST vullen)
  a1 wedstrijden zonder BLAUW tegenstander = bye

  BELANGRIJK: Geen enkele wedstrijd mag leeg zijn!
  Daarom EERST alle WIT vullen (stap 1+2), dan pas BLAUW (stap 3+4).
```

**Bepalen eerste A-ronde:**
- Eerste A-ronde = niveau van D
- D = 8 → eerste A-ronde = A-1/8
- D = 16 → eerste A-ronde = A-1/16

**Voorbeeld N=12 (a1=a2):**
1. D = 8
2. Eerste A-ronde = A-1/8, verliezers = 4
3. Tweede A-ronde = A-1/4, verliezers = 4
4. 4 == 4 → SAMEN exact
5. B-start = B-1/4
6. Plaatsing: a1 → WIT, a2 → BLAUW

**Voorbeeld N=21 (a1 < a2):**
1. D = 16
2. Eerste A-ronde = A-1/16, verliezers = 5 (a1)
3. Tweede A-ronde = A-1/8, verliezers = 8 (a2)
4. 5 < 8 → SAMEN met byes
5. B-start = B-1/8 (8 wedstrijden)
6. Plaatsing (vul-volgorde):
   - Stap 1: 5 a1 verliezers → WIT slots 1, 3, 5, 7, 9
   - Stap 2: 3 a2 verliezers → WIT slots 11, 13, 15 (elke wed heeft nu 1 judoka)
   - Stap 3: 3 a2 → BLAUW slots 12, 14, 16 (a2 vs a2, volle weds)
   - Stap 4: 2 a2 → BLAUW slots 2, 4 (tegenover a1, LAATST)
   - 3 a1 zonder BLAUW = 3 byes (slots 6, 8, 10 leeg)

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

## Eerste Ronde Berekenen (uit N, zonder wedstrijden te tellen)

De eerste ronde van A en B is **vooraf berekenbaar** uit N:

### A-bracket eerste ronde

```
D = 2^floor(log2(N))
V1 = N - D

V1 > 0: eerste ronde = getRondeNaam(2*D)
  → N=21, D=16 → getRondeNaam(32) = zestiende_finale
  → N=12, D=8  → getRondeNaam(16) = achtste_finale

V1 = 0: eerste ronde = getRondeNaam(D)
  → N=16, D=16 → getRondeNaam(16) = achtste_finale
  → N=32, D=32 → getRondeNaam(32) = zestiende_finale
```

### B-bracket eerste ronde

```
a1 = V1 > 0 ? V1 : D/2
a2 = V1 > 0 ? D/2 : D/4
dubbel = a1 > a2

Eerste B-ronde = getBRondeNaam(a2) + (dubbel ? '_1' : '')

Voorbeelden:
  N=12: a2=4, SAMEN  → b_kwartfinale
  N=16: a2=4, DUBBEL → b_kwartfinale_1
  N=21: a2=8, SAMEN  → b_achtste_finale
  N=32: a2=8, DUBBEL → b_achtste_finale_1
```

### Opmerking

Byes komen **alleen in de eerste ronde** voor. Daarom hoeft
`heeftOnverwerkteByes()` geen ronde-naam te berekenen — elke bye in de
groep is per definitie in de eerste ronde.

