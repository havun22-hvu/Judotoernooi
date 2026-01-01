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

## A-Groep Structuur

### Eerste Ronde Bepalen

De eerste A-ronde = **kleinste finale waar N in past**.

```
Bracket grootte = 2^ceil(log2(N))
Eerste ronde    = bracket grootte / 2 wedstrijden
Echte wedstrijden = N - (bracket grootte / 2)
Byes            = bracket grootte - N
```

| N | Past niet in | Start ronde | Echte wed | Byes |
|---|--------------|-------------|-----------|------|
| 5-8 | 1/2 (4 slots) | A-1/4 | N-4 | 8-N |
| 9-16 | 1/4 (8 slots) | A-1/8 | N-8 | 16-N |
| 17-32 | 1/8 (16 slots) | A-1/16 | N-16 | 32-N |
| 33-64 | 1/16 (32 slots) | A-1/32 | N-32 | 64-N |

### Voorbeeld N=24

```
Bracket grootte = 32 (want 2^5=32, 2^4=16 < 24)
Eerste ronde    = A-1/16 (16 wedstrijden)
Echte wedstrijden = 24 - 16 = 8
Byes            = 32 - 24 = 8
```

A-flow:
- A-1/16: 8 echte wedstrijden + 8 byes → **8 verliezers**
- A-1/8: 8 wedstrijden → **8 verliezers**
- A-1/4: 4 wedstrijden → **4 verliezers**
- A-1/2: 2 wedstrijden → **2 verliezers**
- A-finale: 1 wedstrijd

---

## Flow: A → B (Verliezers)

### B-Flow (van achteren naar voren)

Elke A-ronde produceert verliezers die naar B gaan:

| A-ronde | Verliezers | B-bestemming |
|---------|------------|--------------|
| A-1/2 | 2 | B-1/2(2) |
| A-1/4 | 4 | B-1/4(2) |
| A-1/8 | 8 | B-1/8(2) of samen met eerdere |
| A-1/16 | 16 | B-1/16(2) of samen met eerdere |
| A-1/32 | 32 | B-1/32(2) of samen met eerdere |

### Wanneer "samen" vs "(1)/(2)"?

**Samen (geen suffix):** Als verliezers uit twee A-rondes TEGELIJK in één B-ronde passen.
- A-ronde 1 verliezers → WIT slots
- A-ronde 2 verliezers → BLAUW slots
- Samen vullen ze de B-ronde

**(1)/(2) suffix:** Als verliezers NIET tegelijk passen en B-winnaars moeten wachten.
- Eerste golf → B-level(1) onderling
- B-winnaars → B-level(2) WIT
- Volgende A-verliezers → B-level(2) BLAUW

### Referentietabel A → B

| N | Eerste A | A-1 verl | Tweede A | A-2 verl | B-start | Samen/(1)(2) |
|---|----------|----------|----------|----------|---------|--------------|
| 12 | A-1/8 | 4 | A-1/4 | 4 | B-1/4 | Samen |
| 16 | A-1/8 | 8 | A-1/4 | 4 | B-1/4 | (1)/(2) |
| 24 | A-1/16 | 8 | A-1/8 | 8 | B-1/8 | Samen |
| 32 | A-1/16 | 16 | A-1/8 | 8 | B-1/8 | (1)/(2) |
| 48 | A-1/32 | 16 | A-1/16 | 16 | B-1/16 | Samen |
| 49 | A-1/32 | 17 | A-1/16 | 16 | B-1/32 + B-1/16 | Samen* |

*N=49: 17 verl in B-1/32 (1 wed + 15 byes) → 16 winnaars + 16 A-1/16 verl → B-1/16

### Formule: Samen of (1)/(2)?

```
Als (A-1 verliezers + A-2 verliezers) ≤ (2 × A-2 wedstrijden):
    → SAMEN (geen suffix)
Anders:
    → (1)/(2) suffixen nodig

Vereenvoudigd: Als A-1 verliezers > A-2 verliezers → (1)/(2) nodig
```

### Test Matrix

| N | D | Eerste A | A-1 verl | Tweede A | A-2 verl | A-1 > A-2? | Verwacht |
|---|---|----------|----------|----------|----------|------------|----------|
| 12 | 8 | A-1/8 | 4 | A-1/4 | 4 | Nee | Samen |
| 16 | 16 | A-1/8 | 8 | A-1/4 | 4 | Ja | (1)/(2) |
| 24 | 16 | A-1/16 | 8 | A-1/8 | 8 | Nee | Samen |
| 32 | 32 | A-1/16 | 16 | A-1/8 | 8 | Ja | (1)/(2) |
| 48 | 32 | A-1/32 | 16 | A-1/16 | 16 | Nee | Samen |

**LET OP:** Huidige code gebruikt `$dubbelRondes = ($v1 > $v2)` waar:
- V1 = N - D (extra wedstrijden met byes, NIET eerste ronde verliezers)
- V2 = D / 2 (NIET tweede ronde verliezers)

Dit geeft verkeerde resultaten voor N = exacte macht van 2 (16, 32, 64):
- N=32: V1=0, V2=16, code zegt ENKELE maar zou (1)/(2) moeten zijn!

**Correcte formule:**
```php
// Eerste ronde verliezers = echte wedstrijden in eerste ronde
$a1Verliezers = max($v1, $d / 2);  // V1 als er byes zijn, anders D/2

// Tweede ronde verliezers = D/4 (helft van eerste "volle" ronde)
$a2Verliezers = $d / 4;

// Dubbel als eerste golf niet past met tweede golf
$dubbelRondes = ($a1Verliezers > $a2Verliezers);
```

### ODD Wedstrijden Regel

Bij enkele rondes komen alle verliezers tegelijk binnen.
Ze moeten op **ODD wedstrijden** geplaatst worden zodat winnaars naar WIT gaan.

**Waarom ODD wedstrijden?**
```
B-1/8 wed 1 winner → B-1/4 wed 1, WIT (ceil(1/2)=1, oneven=wit)
B-1/8 wed 3 winner → B-1/4 wed 2, WIT (ceil(3/2)=2, oneven=wit)
B-1/8 wed 5 winner → B-1/4 wed 3, WIT (ceil(5/2)=3, oneven=wit)
B-1/8 wed 7 winner → B-1/4 wed 4, WIT (ceil(7/2)=4, oneven=wit)
```

Zo kunnen A-verliezers van de volgende ronde op BLAUW geplaatst worden.

### Berekening Volle Wedstrijden vs Byes

Voor W benodigde wedstrijden en X verliezers:
```
Volle wedstrijden (2 judoka's) = X - W
Bye wedstrijden (1 judoka)     = 2W - X
```

**Voorbeeld B-1/8 → B-1/4 (W=4 ODD wedstrijden: 1, 3, 5, 7):**

| X verliezers | Volle wed | Byes | Verdeling voorbeeld |
|--------------|-----------|------|---------------------|
| 8 | 4 | 0 | wed 1,3,5,7 elk 2 judoka's |
| 7 | 3 | 1 | wed 1,3,5: vol; wed 7: bye |
| 6 | 2 | 2 | wed 1,3: vol; wed 5,7: bye |
| 5 | 1 | 3 | wed 1: vol; wed 3,5,7: bye |
| 4 | 0 | 4 | wed 1,3,5,7 elk 1 judoka (bye) |

### Plaatsing Algoritme (RANDOM)

De verdeling moet RANDOM zijn om te voorkomen dat wedstrijdvolgorde in A-groep
de tegenstanders in B-groep bepaalt.

**Stappen:**
1. Bereken aantal volle wedstrijden en byes
2. RANDOM selecteren welke ODD wedstrijden vol worden vs bye
3. RANDOM verdelen van verliezers over de geselecteerde wedstrijden

**Voorbeeld 5 verliezers (1 vol, 3 byes):**
```
Stap 1: 1 volle wedstrijd, 3 bye wedstrijden
Stap 2: Random selectie → wed 5 wordt vol, wed 1,3,7 worden bye
Stap 3: Random verdeling:
        - wed 1 (bye): verliezer D → B1/8-1
        - wed 3 (bye): verliezer A → B1/8-5
        - wed 5 (vol): verliezer C, verliezer E → B1/8-9, B1/8-10
        - wed 7 (bye): verliezer B → B1/8-13
```

**Resultaat:** B1/8-1, B1/8-5, B1/8-9, B1/8-10, B1/8-13 (random verdeeld)

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
