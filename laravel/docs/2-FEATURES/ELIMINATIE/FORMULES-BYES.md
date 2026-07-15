---
title: Eliminatie Formules: byes en totaal wedstrijden
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Eliminatie Formules: byes en totaal wedstrijden

> Onderdeel van [Eliminatie Formules](./FORMULES.md).

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
REGEL: Geen enkele wedstrijd mag helemaal leeg zijn!

Implementatie (SAMEN, a1 < a2):
1. Zet a1 verliezers op WIT bovenaan (slot 1, 3, 5...)
2. Zet a2 op ALLE overige WIT slots (elke wed heeft nu minstens 1 judoka)
3. Zet rest a2 op BLAUW van a2-wedstrijden (a2 vs a2, volle weds)
4. Zet rest a2 op BLAUW van a1-wedstrijden (LAATST vullen)
5. a1 zonder BLAUW tegenstander = bye

Waarom EERST alle WIT vullen:
- Elke wedstrijd moet minstens 1 judoka hebben
- Anders heb je in de volgende ronde lege slots

Waarom BLAUW van a1 LAATST:
- a1 verliezers hebben al gevochten in de eerste A-ronde
- a2 verliezers hadden mogelijk een A-bye
- Door BLAUW van a1 als laatst te vullen krijgen a1 de byes
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
