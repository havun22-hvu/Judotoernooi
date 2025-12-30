# Slot Systeem - Eliminatie Brackets

## Definities

### Wedstrijd vs Slot
- **Wedstrijd** = een gevecht tussen 2 judoka's
- **Slot** = een plek in een wedstrijd (wit of blauw)
- Elke wedstrijd heeft **2 slots**: wit (boven) en blauw (onder)

### Slot Nummering per Ronde
Wedstrijd N heeft:
- **Slot (2N-1)** = wit (boven)
- **Slot (2N)** = blauw (onder)

Voorbeeld 1/8 finale (8 wedstrijden, 16 slots):
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

Voorbeeld 1/16 finale (16 wedstrijden, 32 slots):
```
Wedstrijd 10: slot 19 (wit), slot 20 (blauw)
```

### Unieke Slot ID
Format: `{Groep}{Ronde}-{Slot}`

Componenten:
- **Groep**: `A` (hoofdboom) of `B` (herkansing)
- **Ronde**: `1/16`, `1/8`, `1/4`, `1/2`, `F` (finale)
- **Voor B-groep dubbele rondes**: `1/8(1)`, `1/8(2)`, etc.
- **Slot**: slotnummer binnen die ronde (1, 2, 3, ...)

Voorbeelden:
- `A1/8-7` = slot 7 in A-groep 1/8 finale
- `A1/2-3` = slot 3 in A-groep halve finale
- `AF-1` = slot 1 in A-groep finale (goud positie)
- `AF-2` = slot 2 in A-groep finale (zilver positie)
- `B1/8(1)-5` = slot 5 in B-groep 1/8 ronde 1
- `B1/4(2)-2` = slot 2 in B-groep 1/4 ronde 2
- `BF-1` = slot 1 in B-groep finale (brons positie)

---

## Flow: Winnaar Doorschuiven

### Basis Regel
De winnaar van **slot S** gaat naar **slot ceil(S/2)** in de volgende ronde.

### Voorbeelden

**1/16 → 1/8:**
```
Slot 19 (wed 10 wit)  ─┬─► Slot 10 (wed 5 blauw)
Slot 20 (wed 10 blauw)─┘

Slot 21 (wed 11 wit)  ─┬─► Slot 11 (wed 6 wit)
Slot 22 (wed 11 blauw)─┘
```

**1/8 → 1/4:**
```
Slot 1 (wed 1 wit)  ─┬─► Slot 1 (wed 1 wit)
Slot 2 (wed 1 blauw)─┘

Slot 3 (wed 2 wit)  ─┬─► Slot 2 (wed 1 blauw)
Slot 4 (wed 2 blauw)─┘
```

**1/2 → Finale:**
```
Slot 1 (wed 1 wit)  ─┬─► Slot 1 (finale wit = GOUD)
Slot 2 (wed 1 blauw)─┘

Slot 3 (wed 2 wit)  ─┬─► Slot 2 (finale blauw = ZILVER)
Slot 4 (wed 2 blauw)─┘
```

### Formules

```php
// Bereken slots voor wedstrijd N
$slot_wit = (N - 1) * 2 + 1;   // = 2N - 1
$slot_blauw = (N - 1) * 2 + 2; // = 2N

// Bereken doel-slot voor winnaar van slot S
$doel_slot = ceil(S / 2);

// Bereken doel-wedstrijd voor winnaar van slot S
$doel_wedstrijd = ceil(S / 4);

// Bepaal of winnaar naar wit of blauw gaat
// Oneven slot → wit, even slot → blauw
$doel_positie = (S % 2 === 1) ? 'wit' : 'blauw';
```

---

## B-Groep: Dubbele Rondes

In de B-groep (herkansing) zijn er dubbele rondes: (1) en (2).

### Structuur
- **Ronde (1)**: B-winnaars onderling (alleen WIT slots gevuld)
- **Ronde (2)**: Winnaars van (1) op WIT + A-verliezers op BLAUW

### Flow (1) → (2)
Dit is een **1:1 mapping** (geen 2:1):
- Winnaar van B1/8(1) wed N → B1/8(2) wed N, **altijd WIT slot**
- A-verliezer komt op **BLAUW slot**

### Flow (2) → volgende (1)
Dit is de normale **2:1 mapping**:
- Slot S van B1/8(2) → Slot ceil(S/2) van B1/4(1)

---

## Database Velden

In de `wedstrijden` tabel:
- `bracket_positie`: wedstrijdnummer binnen de ronde (1, 2, 3, ...)
- `locatie_wit`: slotnummer van de wit positie
- `locatie_blauw`: slotnummer van de blauw positie
- `volgende_wedstrijd_id`: ID van de wedstrijd waar de winnaar naartoe gaat
- `winnaar_naar_slot`: 'wit' of 'blauw' - welke positie in de volgende wedstrijd

---

## Visuele Layout

```
         1/8                    1/4                 1/2              Finale

┌─────────────┐           ┌─────────────┐     ┌─────────────┐    ┌─────────────┐
│ Slot 1 (wit)│           │             │     │             │    │             │
├─────────────┤ Wed 1     │ Slot 1 (wit)│     │             │    │ Slot 1 GOUD │
│ Slot 2(blau)│───┐       ├─────────────┤Wed 1│ Slot 1 (wit)│    ├─────────────┤
└─────────────┘   ├──────►│ Slot 2(blau)│──┐  ├─────────────┤Wed1│ Slot 2ZILVER│
┌─────────────┐   │       └─────────────┘  ├─►│ Slot 2(blau)│───►└─────────────┘
│ Slot 3 (wit)│───┘                        │  └─────────────┘
├─────────────┤ Wed 2     ┌─────────────┐  │
│ Slot 4(blau)│           │ Slot 3 (wit)│  │
└─────────────┘           ├─────────────┤Wed2
                    ┌────►│ Slot 4(blau)│──┘
┌─────────────┐     │     └─────────────┘
│ Slot 5 (wit)│─────┘
├─────────────┤ Wed 3
│ Slot 6(blau)│───┐
└─────────────┘   │
┌─────────────┐   │
│ Slot 7 (wit)│───┘
├─────────────┤ Wed 4
│ Slot 8(blau)│
└─────────────┘
        │
       ...
```
