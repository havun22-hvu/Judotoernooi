# Slot Systeem

> Definieert hoe judoka's door de bracket bewegen.

## Definities

| Term | Betekenis |
|------|-----------|
| **Wedstrijd** | Een gevecht tussen 2 judoka's |
| **Slot** | Een plek in een wedstrijd (wit of blauw) |
| **Wit (shiro)** | Bovenste positie in wedstrijd |
| **Blauw (ao)** | Onderste positie in wedstrijd |

## Slot Nummering

Elke wedstrijd N heeft twee slots:

```
Slot WIT   = 2N - 1
Slot BLAUW = 2N
```

### Voorbeeld: 1/8 finale (8 wedstrijden)

```
Wedstrijd 1:  slot 1 (wit),  slot 2 (blauw)
Wedstrijd 2:  slot 3 (wit),  slot 4 (blauw)
Wedstrijd 3:  slot 5 (wit),  slot 6 (blauw)
Wedstrijd 4:  slot 7 (wit),  slot 8 (blauw)
Wedstrijd 5:  slot 9 (wit),  slot 10 (blauw)
Wedstrijd 6:  slot 11 (wit), slot 12 (blauw)
Wedstrijd 7:  slot 13 (wit), slot 14 (blauw)
Wedstrijd 8:  slot 15 (wit), slot 16 (blauw)
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

Dit is de normale **2:1 mapping** met spiegeling:

**Bovenste helft** (wed 1 t/m midden):
- Oneven wed → WIT
- Even wed → BLAUW

**Onderste helft** (gespiegeld):
- Even wed → WIT (omgedraaid!)
- Oneven wed → BLAUW (omgedraaid!)

### Voorbeeld B-1/8(2) → B-1/4(1)

```
Bovenste helft (wed 1-4):
  Wed 1 → wed 1, wit
  Wed 2 → wed 1, blauw
  Wed 3 → wed 2, wit
  Wed 4 → wed 2, blauw

Onderste helft (wed 5-8, gespiegeld):
  Wed 5 → wed 3, blauw  (omgedraaid!)
  Wed 6 → wed 3, wit    (omgedraaid!)
  Wed 7 → wed 4, blauw  (omgedraaid!)
  Wed 8 → wed 4, wit    (omgedraaid!)
```

## Visuele Layout

### A-Groep (normaal)

```
      1/8           1/4           1/2         Finale

┌─────────┐
│ Slot 1  │
├─────────┤ Wed 1
│ Slot 2  │──┐
└─────────┘  │     ┌─────────┐
             ├────►│ Slot 1  │
┌─────────┐  │     ├─────────┤ Wed 1
│ Slot 3  │──┘     │ Slot 2  │──┐
├─────────┤ Wed 2  └─────────┘  │
│ Slot 4  │                     │
└─────────┘                     │
                                ├────► Finale
```

### B-Groep (gespiegeld rond horizon)

```
B-1/8(1)      B-1/8(2)      B-1/4(1)      B-1/4(2)      B-1/2

   BOVENSTE HELFT
┌───┐
│   ├──►┌───┐
└───┘   │   ├──►┌───┐
        └───┘   │   ├──►┌───┐
                └───┘   │   ├──►┌───┐
                        └───┘   │   │
════════════════════════════════════════ HORIZON
                        ┌───┐   │   │
                ┌───┐   │   ├──►│   │──►  BRONS
        ┌───┐   │   ├──►└───┘   │   │
┌───┐   │   ├──►└───┘           │   │
│   ├──►└───┘                   └───┘
└───┘
   ONDERSTE HELFT (gespiegeld)
```

**Belangrijk:**
- Spiegeling is ALLEEN grafisch
- Slot nummers lopen altijd door: 1, 2, 3, ...
- WIT = altijd boven, BLAUW = altijd onder

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
