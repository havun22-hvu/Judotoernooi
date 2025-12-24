# Eliminatie (K.O.) Systeem - Berekening

Dit document beschrijft de wiskundige logica voor het berekenen van het aantal wedstrijden in een double elimination bracket.

## Variabelen

| Symbool | Betekenis |
|---------|-----------|
| N | Aantal judoka's |
| D | Doel A = grootste macht van 2 ≤ N |
| V | Voorronde A = N - D |

## Voorbeeld: 29 judoka's

```
N = 29
D = 16  (want 16 ≤ 29 < 32)
V = 29 - 16 = 13
```

---

## A-Groep (Winners Bracket)

| Ronde | Wedstrijden | Verliezers → B |
|-------|-------------|----------------|
| A voorronde | V = 13 | 13 → B 1/8 |
| A 1/8 finale | D/2 = 8 | 8 → B voorronde* |
| A 1/4 finale | 4 | 4 → B 1/4 deel 2 |
| A 1/2 finale | 2 | 2 → Brons |
| A finale | 1 | - |
| **Totaal A** | **N - 1 = 28** | |

*Bye-verliezers uit A 1/8 (die geen voorronde speelden) gaan EERST naar B voorronde.

---

## B-Groep (Losers Bracket)

### Stap 1: Bereken B voorronde

```
Verliezers naar B 1/8 = V + D/2 = 13 + 8 = 21
B 1/8 capaciteit = 16 plekken (8 wedstrijden)
B voorronde nodig = max(0, 21 - 16) = 5 wedstrijden
```

### Stap 2: B-groep structuur

| Ronde | Wedstrijden | Instroom |
|-------|-------------|----------|
| B voorronde | 5 | Overflow + bye-verliezers uit A |
| B 1/8 finale | 8 | B voorronde winnaars + A voorronde verliezers |
| B 1/4 deel 1 | 4 | 8 winnaars uit B 1/8 |
| B 1/4 deel 2 | 4 | 4 winnaars deel 1 + **4 verliezers A 1/4** |
| B 1/2 deel 1 | 2 | 4 winnaars uit B 1/4 deel 2 |
| Brons (B 1/2 deel 2) | 2 | 2 B-winnaars + **2 verliezers A 1/2** |
| **Totaal B** | **25** | |

---

## Totaal Wedstrijden

```
Totaal = A + B = 28 + 25 = 53 wedstrijden
```

### Formules per bracket grootte

**D=16 (17-32 judoka's, geen A 1/16):**
```
B = B_voorronde + B_1/8 + B_1/4_deel1 + B_1/4_deel2 + B_1/2_deel1 + Brons
  = max(0, V+8-16) + 8 + 4 + 4 + 2 + 2
  = max(0, N-16+8-16) + 20
```

**D=32 (33-64 judoka's, met A 1/16):**
```
B = B_1/8_deel1 + B_1/8_deel2 + B_1/4_deel1 + B_1/4_deel2 + B_1/2_deel1 + Brons
  = 8 + 8 + 4 + 4 + 2 + 2 = 28 wedstrijden
```

### Voorbeelden

| N | D | V | A-weds | B-structuur | B-weds | Totaal |
|---|---|---|--------|-------------|--------|--------|
| 23 | 16 | 7 | 22 | 8+4+4+2+2 (1 bye) | 20 | 42 |
| 29 | 16 | 13 | 28 | 5+8+4+4+2+2 | 25 | 53 |
| 32 | 32 | 0 | 31 | 8+8+4+4+2+2 | 28 | 59 |

**Let op:** Bij N=23 is er 1 bye in B 1/8 (15 judoka's, 16 slots)

---

## Plaatsingsregels B-Groep

### Prioriteit voor bye-verliezers

Judoka's die een **bye** hadden in A (niet in voorronde speelden) gaan bij verlies in A 1/8 **eerst naar B voorronde**. Dit voorkomt dat ze een tweede bye krijgen in B 1/8.

### Volgorde van plaatsen

1. **WIT** plekken eerst vullen
2. Dan **BLAUW** plekken
3. Bye-verliezers → B voorronde (prioriteit)
4. Normale verliezers → target ronde volgens tabel

### Routing tabel

| Verlies in A | Gaat naar B |
|--------------|-------------|
| A voorronde | B 1/8 |
| A 1/8 (met bye) | B voorronde |
| A 1/8 (zonder bye) | B 1/8 |
| A 1/4 | B 1/4 deel 2 |
| A 1/2 | Brons (als WIT) |

---

## Visuele Structuur (29 judoka's, D=16)

```
A-GROEP                              B-GROEP
========                             ========

A Voorronde (13)
    ↓ winnaars
    ↓ verliezers ───────────────────────────────────→ B 1/8 (8)
                                                          ↓
A 1/8 (8)                                            B 1/4 deel 1 (4)
    ↓ winnaars                                            ↓
    ↓ verliezers (bye) ──────────────→ B voorronde (5) ──→ ↑

A 1/4 (4)                                            B 1/4 deel 2 (4)
    ↓ winnaars                                       ↗ B 1/4 deel 1 winnaars
    ↓ verliezers ────────────────────────────────────
                                                          ↓
A 1/2 (2)                                            B 1/2 deel 1 (2)
    ↓ winnaars                                            ↓
    ↓ verliezers ────────────────────────────────────→ BRONS (2)
                                                     ↗ B 1/2 deel 1 winnaars
A Finale (1)
    ↓
  GOUD + ZILVER
```

## Visuele Structuur (32 judoka's, D=32)

```
A-GROEP                              B-GROEP
========                             ========

A 1/16 (16)
    ↓ winnaars
    ↓ verliezers ───────────────────────────────────→ B 1/8 deel 1 (8)
                                                          ↓
A 1/8 (8)                                            B 1/8 deel 2 (8)
    ↓ winnaars                                       ↗ B 1/8 deel 1 winnaars
    ↓ verliezers ────────────────────────────────────
                                                          ↓
                                                     B 1/4 deel 1 (4)
                                                          ↓
A 1/4 (4)                                            B 1/4 deel 2 (4)
    ↓ winnaars                                       ↗ B 1/4 deel 1 winnaars
    ↓ verliezers ────────────────────────────────────
                                                          ↓
                                                     B 1/2 deel 1 (2)
                                                          ↓
A 1/2 (2)                                            BRONS (2)
    ↓ winnaars                                       ↗ B 1/2 deel 1 winnaars
    ↓ verliezers ────────────────────────────────────

A Finale (1)
    ↓
  GOUD + ZILVER
```

---

## Seeding en Bracket Locking

### Twee fasen

Het eliminatie systeem kent twee fasen:

| Fase | Status | Toegestane acties |
|------|--------|-------------------|
| **Seeding** | Geen wedstrijden gespeeld | Vrij verplaatsen van judoka's |
| **Wedstrijd** | ≥1 wedstrijd gespeeld | Alleen volgens schema |

### Seeding-fase

Vóór de eerste wedstrijd is gespeeld:

- **Vrij verplaatsen**: judoka's kunnen tussen alle slots worden gesleept
- **Clubgenoten scheiden**: zet judoka's van dezelfde club uit elkaar
- **Sterke spelers spreiden**: voorkom dat toppers elkaar vroeg treffen
- **Fouten corrigeren**: administratieve fouten in de loting herstellen

```
Voorbeeld seeding aanpassing:
- Club A heeft 3 judoka's in dezelfde bracket helft
- Verplaats 1 judoka naar de andere helft
- Zo treffen ze elkaar pas in de finale
```

### Bracket Locking

Zodra de **eerste wedstrijd is gespeeld**:

- Bracket is **definitief vergrendeld**
- Judoka's kunnen alleen naar het **correcte volgende vak**
- Positie (WIT/BLAUW) moet overeenkomen met `winnaar_naar_slot`
- Uitslagen worden automatisch geregistreerd bij doorschuiven

### Validatie (locked bracket)

Bij elke verplaatsing worden 3 checks uitgevoerd:

| Check | Fout | Actie |
|-------|------|-------|
| 1. Judoka in bronwedstrijd? | Judoka speelde niet in die wedstrijd | BLOKKEER |
| 2. Correcte volgende wedstrijd? | Verkeerde wedstrijd geselecteerd | BLOKKEER |
| 3. Correcte positie (wit/blauw)? | Verkeerde kant | BLOKKEER |

### Technische implementatie

```php
// Check of bracket locked is
$isLocked = Wedstrijd::where('poule_id', $pouleId)
    ->where('is_gespeeld', true)
    ->exists();

// Seeding-fase: vrij verplaatsen
// Wedstrijd-fase: strenge validatie
```

```javascript
// Frontend check
isBracketLocked(poule) {
    return poule.wedstrijden.some(w => w.is_gespeeld);
}
```
