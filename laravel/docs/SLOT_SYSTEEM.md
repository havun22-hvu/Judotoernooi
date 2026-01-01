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
Dit is de normale **2:1 mapping**, maar met gespiegelde slot toewijzing:

**Bovenste helft** (wedstrijd 1 t/m halverwege):
- Oneven wedstrijd → WIT slot
- Even wedstrijd → BLAUW slot

**Onderste helft** (wedstrijd > halverwege, gespiegeld):
- Even wedstrijd → WIT slot (omgedraaid!)
- Oneven wedstrijd → BLAUW slot (omgedraaid!)

Dit zorgt ervoor dat de visuele flow correct is in de gespiegelde B-bracket layout.

Voorbeeld B-1/8(2) → B-1/4(1) (8 wedstrijden → 4 wedstrijden):
```
Bovenste helft (wed 1-4):
  Wed 1 → wed 1, wit
  Wed 2 → wed 1, blauw
  Wed 3 → wed 2, wit
  Wed 4 → wed 2, blauw

Onderste helft (wed 5-8, gespiegeld):
  Wed 5 → wed 3, blauw  (omgedraaid)
  Wed 6 → wed 3, wit    (omgedraaid)
  Wed 7 → wed 4, blauw  (omgedraaid)
  Wed 8 → wed 4, wit    (omgedraaid)
```

---

## Flow: A → B (Verliezers)

### A-Verliezers naar B-Groep

Bij **dubbele rondes** (V1 > V2):
```
A-voorronde verliezers → B-start(1) op eerste beschikbaar slot
A-1/16 verliezers      → B-1/16(2) op BLAUW slot
A-1/8 verliezers       → B-1/8(2) op BLAUW slot
A-1/4 verliezers       → B-1/4(2) op BLAUW slot
A-1/2 verliezers       → B-1/2(2) op BLAUW slot
```

Bij **enkele rondes** (V1 ≤ V2):
```
A-verliezers → B-ronde op ODD wedstrijden (1, 3, 5, 7...)
```

### Enkele Rondes: ODD Wedstrijden Regel

Bij enkele rondes (bv. N=16, V1=0, V2=8) komen alle verliezers tegelijk binnen.
Ze moeten op **ODD wedstrijden** geplaatst worden zodat winnaars naar WIT gaan.

**Voorbeeld N=16 (8 A-1/8 verliezers → B-1/8):**
```
B-1/8 wed 1 (slots 1,2):   verliezer A, verliezer B
B-1/8 wed 3 (slots 5,6):   verliezer C, verliezer D
B-1/8 wed 5 (slots 9,10):  verliezer E, verliezer F
B-1/8 wed 7 (slots 13,14): verliezer G, verliezer H

Wed 2, 4, 6, 8 blijven LEEG
```

**Waarom ODD wedstrijden?**
```
B-1/8 wed 1 winner → B-1/4 wed 1, WIT (ceil(1/2)=1, oneven=wit)
B-1/8 wed 3 winner → B-1/4 wed 2, WIT (ceil(3/2)=2, oneven=wit)
B-1/8 wed 5 winner → B-1/4 wed 3, WIT (ceil(5/2)=3, oneven=wit)
B-1/8 wed 7 winner → B-1/4 wed 4, WIT (ceil(7/2)=4, oneven=wit)
```

Zo kunnen A-1/4 verliezers op BLAUW in B-1/4 wed 1-4 geplaatst worden.

### Waarom BLAUW voor (2) rondes?
- B-winnaars van ronde (1) staan al op **WIT**
- A-verliezers komen op **BLAUW** (de tegenstander)
- Zo vechten B-winnaars tegen verse A-verliezers

### Visueel

```
A-GROEP                         B-GROEP

A-1/8 ─── winnaar ───────────► A-1/4
   │
   └── verliezer ────────────► B-1/8(2) BLAUW slot
                                    │
                                    ├── B-1/8(1) winnaar op WIT
                                    │
                                    └── A-1/8 verliezer op BLAUW
```

### Bye Fairness
Judoka's die al een **bye** hadden in de A-groep:
- Worden NIET opnieuw met bye geplaatst in B-groep
- Ze worden bij een tegenstander gezet indien mogelijk

---

## Database Velden

In de `wedstrijden` tabel:
- `bracket_positie`: wedstrijdnummer binnen de ronde (1, 2, 3, ...)
- `locatie_wit`: slotnummer van de wit plek (= 2N-1 voor wedstrijd N)
- `locatie_blauw`: slotnummer van de blauw plek (= 2N voor wedstrijd N)
- `volgende_wedstrijd_id`: ID van de wedstrijd waar de winnaar naartoe gaat
- `winnaar_naar_slot`: 'wit' of 'blauw' - welke plek in de volgende wedstrijd

---

## Visuele Layout

### A-Groep (normale layout)
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

### B-Groep (gespiegelde layout)

De B-groep wordt visueel gespiegeld weergegeven met een **horizon lijn** in het midden.
Dit is ALLEEN een grafische weergave - de slot nummers lopen gewoon door van boven naar beneden.

```
         1/8(1)              1/8(2)              1/4(1)           ...

         BOVENSTE HELFT (wedstrijden 1-4)
┌─────────────┐           ┌─────────────┐
│ Slot 1 (wit)│           │ Slot 1 (wit)│
├─────────────┤ Wed 1     ├─────────────┤ Wed 1
│ Slot 2(blau)│           │ Slot 2(blau)│ ← uit A-1/8
└─────────────┘           └─────────────┘
┌─────────────┐           ┌─────────────┐
│ Slot 3 (wit)│           │ Slot 3 (wit)│
├─────────────┤ Wed 2     ├─────────────┤ Wed 2
│ Slot 4(blau)│           │ Slot 4(blau)│ ← uit A-1/8
└─────────────┘           └─────────────┘
        ...                     ...

═══════════════════════ HORIZON LIJN ═══════════════════════

         ONDERSTE HELFT (wedstrijden 5-8, visueel gespiegeld)
┌─────────────┐           ┌─────────────┐
│ Slot 9 (wit)│           │ Slot 9 (wit)│
├─────────────┤ Wed 8*    ├─────────────┤ Wed 8*
│ Slot 10(bla)│           │ Slot 10(bla)│ ← uit A-1/8
└─────────────┘           └─────────────┘
┌─────────────┐           ┌─────────────┐
│ Slot 11(wit)│           │ Slot 11(wit)│
├─────────────┤ Wed 7*    ├─────────────┤ Wed 7*
│ Slot 12(bla)│           │ Slot 12(bla)│ ← uit A-1/8
└─────────────┘           └─────────────┘
        ...                     ...

* = wedstrijden worden visueel in omgekeerde volgorde getoond,
    maar slot nummers lopen gewoon door (9, 10, 11, 12, ...)
```

**BELANGRIJK:**
- De spiegeling is ALLEEN grafisch (voor symmetrische bracket layout)
- Slot nummers lopen ALTIJD door van boven naar beneden: 1, 2, 3, ... 15, 16
- WIT = altijd boven, BLAUW = altijd onder (ook in gespiegelde helft)
- De wedstrijden in de onderste helft worden in omgekeerde volgorde gerenderd

---

## Debug Slots Toggle

In de mat interface is een **"🔢 Slots AAN/UIT"** knop beschikbaar (in zowel A-groep als B-groep).

Wanneer ingeschakeld:
- Toont `[slotnummer]` voor elke judoka naam
- Lege slots tonen ook hun nummer
- Handig voor debugging en verificatie van slot nummering

**Locatie in code:** `interface.blade.php`
- Property: `debugSlots: false`
- Toggle knop in A-groep en B-groep headers
- Visuele slot nummers worden berekend in render loops (niet uit database)

### Visuele vs Database Slot Nummers

| Type | Beschrijving | Gebruik |
|------|-------------|---------|
| **Visuele slots** | Van boven naar beneden doorlopend (1, 2, 3, ...) | Debug weergave |
| **Database slots** | `locatie_wit`, `locatie_blauw` op wedstrijd | Backend logica |

In de B-groep gespiegelde layout zijn deze ANDERS:
- Visuele slot 9 kan bij wedstrijd 8 (database slot 15) horen
- De debug toggle toont de VISUELE nummers (doorlopend)
