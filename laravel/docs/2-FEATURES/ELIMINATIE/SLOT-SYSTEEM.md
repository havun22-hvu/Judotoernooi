---
title: Slot Systeem
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# Slot Systeem

> Definieert hoe judoka's door de bracket bewegen.

## BELANGRIJKSTE REGEL

**Slots worden ALTIJD van boven naar beneden genummerd, zonder spiegeling!**

```
1/16 finale (16 wedstrijden, 32 slots):
- Slot 1  = bovenste slot (wedstrijd 1, wit)
- Slot 2  = tweede slot (wedstrijd 1, blauw)
- Slot 3  = derde slot (wedstrijd 2, wit)
- ...
- Slot 31 = voorlaatste slot (wedstrijd 16, wit)
- Slot 32 = onderste slot (wedstrijd 16, blauw)
```

**GEEN gespiegelde weergave, GEEN complexe herberekening!**

## Definities

| Term | Betekenis |
|------|-----------|
| **Wedstrijd** | Een gevecht tussen 2 judoka's |
| **Slot** | Een plek in een wedstrijd (wit of blauw) |
| **Wit (shiro)** | Bovenste positie in wedstrijd (oneven slot) |
| **Blauw (ao)** | Onderste positie in wedstrijd (even slot) |

## Slot Nummering

Elke wedstrijd N heeft twee slots:

```
Slot WIT   = 2N - 1  (oneven)
Slot BLAUW = 2N      (even)
```

### Voorbeeld: 1/8 finale (8 wedstrijden, 16 slots)

```
Visueel (van boven naar beneden):

Slot 1  ┌─────────┐
Slot 2  │ Wed 1   │
        └─────────┘
Slot 3  ┌─────────┐
Slot 4  │ Wed 2   │
        └─────────┘
Slot 5  ┌─────────┐
Slot 6  │ Wed 3   │
        └─────────┘
Slot 7  ┌─────────┐
Slot 8  │ Wed 4   │
        └─────────┘
Slot 9  ┌─────────┐
Slot 10 │ Wed 5   │
        └─────────┘
Slot 11 ┌─────────┐
Slot 12 │ Wed 6   │
        └─────────┘
Slot 13 ┌─────────┐
Slot 14 │ Wed 7   │
        └─────────┘
Slot 15 ┌─────────┐
Slot 16 │ Wed 8   │
        └─────────┘
```

### Voorbeeld: 1/4 finale (4 wedstrijden, 8 slots)

```
Slot 1  ┌─────────┐
Slot 2  │ Wed 1   │
        └─────────┘
Slot 3  ┌─────────┐
Slot 4  │ Wed 2   │
        └─────────┘
Slot 5  ┌─────────┐
Slot 6  │ Wed 3   │
        └─────────┘
Slot 7  ┌─────────┐
Slot 8  │ Wed 4   │
        └─────────┘
```

## Unieke Slot ID Format

```
{Groep}{Ronde}-{Slot}
```

| Component | Waarden |
|-----------|---------|
| Groep | `A` (hoofdboom), `B` (herkansing) |
| Ronde | `1/16`, `1/8`, `1/4`, `1/2`, `F` |
| Suffix | `(1)`, `(2)` voor B-groep dubbele rondes |
| Slot | Slotnummer binnen ronde |

### Voorbeelden

| Slot ID | Betekenis |
|---------|-----------|
| `A1/8-7` | Slot 7 in A-groep 1/8 finale |
| `AF-1` | Slot 1 in A-finale (goud positie) |
| `B1/8(1)-5` | Slot 5 in B-groep 1/8 ronde 1 |
| `B1/4(2)-2` | Slot 2 in B-groep 1/4 ronde 2 |
| `BF-1` | B-finale slot 1 (brons positie) |

## Winnaar Doorschuiven

### Basisregel

```
Winnaar van slot S → slot ceil(S/2) in volgende ronde
```

### Formules

```php
// Doel-slot voor winnaar van slot S
$doelSlot = ceil($s / 2);

// Doel-wedstrijd
$doelWedstrijd = ceil($s / 4);

// Wit of blauw in volgende ronde?
$doelPositie = ($s % 2 === 1) ? 'wit' : 'blauw';
```

### Visueel Voorbeeld

```
1/16                    1/8                     1/4

Slot 19 (wed 10 wit)  ─┬─► Slot 10 (wed 5 blauw)
Slot 20 (wed 10 blauw)─┘
                              │
Slot 21 (wed 11 wit)  ─┬─► Slot 11 (wed 6 wit)   ─┬─► Slot 6 (wed 3 blauw)
Slot 22 (wed 11 blauw)─┘                         │
                                                  │
Slot 23 (wed 12 wit)  ─┬─► Slot 12 (wed 6 blauw)─┘
Slot 24 (wed 12 blauw)─┘
```

## B-Groep Specifiek

### (1) Rondes: Alleen WIT Gevuld

In ronde (1) staan alleen B-winnaars onderling:
- Slots zijn alleen WIT (oneven)
- BLAUW slots blijven leeg tot winnaar bekend

### (2) Rondes: WIT + BLAUW

- **WIT**: Winnaars uit (1) ronde
- **BLAUW**: Verse A-verliezers

### Flow (1) → (2)

Dit is een **1:1 mapping**:

```
B-1/8(1) wed N winnaar → B-1/8(2) wed N, WIT slot
A-1/8 verliezer        → B-1/8(2) wed N, BLAUW slot
```

### Flow (2) → Volgende (1)

Dit is de normale **2:1 mapping** (GEEN spiegeling):

```
Winnaar wed N → wed ceil(N/2) in volgende ronde
- Oneven wed (1,3,5,7) → WIT slot
- Even wed (2,4,6,8)   → BLAUW slot
```

### Voorbeeld B-1/8(2) → B-1/4(1)

```
Wed 1 → wed 1, wit
Wed 2 → wed 1, blauw
Wed 3 → wed 2, wit
Wed 4 → wed 2, blauw
Wed 5 → wed 3, wit
Wed 6 → wed 3, blauw
Wed 7 → wed 4, wit
Wed 8 → wed 4, blauw
```

## Visuele Layout

### A-Groep en B-Groep (identiek, van boven naar beneden)

```
      1/8           1/4           1/2         Finale

┌─────────┐
│ Slot 1  │ Wed 1
│ Slot 2  │──┐
└─────────┘  │
             ├────►┌─────────┐
┌─────────┐  │     │ Slot 1  │ Wed 1
│ Slot 3  │──┘     │ Slot 2  │──┐
│ Slot 4  │ Wed 2  └─────────┘  │
└─────────┘                     │
                                ├────► Finale
┌─────────┐                     │
│ Slot 5  │──┐                  │
│ Slot 6  │  │     ┌─────────┐  │
└─────────┘  ├────►│ Slot 3  │──┘
             │     │ Slot 4  │ Wed 2
┌─────────┐  │     └─────────┘
│ Slot 7  │──┘
│ Slot 8  │ Wed 4
└─────────┘
```

**GEEN spiegeling!** Slots lopen simpelweg van boven (1) naar beneden (N).

## Database Velden

| Veld | Beschrijving |
|------|--------------|
| `bracket_positie` | Wedstrijdnummer binnen ronde (1-based) |
| `locatie_wit` | Slotnummer wit (= 2N-1) |
| `locatie_blauw` | Slotnummer blauw (= 2N) |
| `volgende_wedstrijd_id` | Waar winnaar naartoe gaat |
| `winnaar_naar_slot` | 'wit' of 'blauw' in volgende wedstrijd |

## Debug Toggle

In de mat interface is een **"Slots AAN/UIT"** knop:

- Toont `[slotnummer]` voor elke judoka
- Handig voor verificatie
- Property: `debugSlots` in interface.blade.php
