# Eliminatie Systeem (K.O. / Double Elimination)

Dit document beschrijft het double elimination systeem voor judotoernooien.

## Overzicht

Het eliminatiesysteem werkt met twee groepen:
- **A-groep (Hoofdboom)**: Winnaars bracket → Goud en Zilver
- **B-groep (Herkansing)**: Verliezers krijgen tweede kans → 2x Brons

## Principe

1. **Iedereen begint in A-groep**
2. **Verlies in A → naar B-groep** (herkansing)
3. **Verlies in B → uitgeschakeld** (naar huis)
4. **Resultaat: 4 medailles** (1x goud, 1x zilver, 2x brons)

## Formules

| Formule | Betekenis |
|---------|-----------|
| **A = N - 1** | Aantal A-groep wedstrijden |
| **B = N - 4** | Aantal B-groep wedstrijden |
| **Totaal = 2N - 5** | Totaal aantal wedstrijden |
| **D = 2^floor(log₂N)** | Doel: grootste macht van 2 ≤ N |

### Voorbeeld: 29 judoka's
```
N = 29
D = 16 (want 16 ≤ 29 < 32)

A-groep: 29 - 1 = 28 wedstrijden
B-groep: 29 - 4 = 25 wedstrijden
Totaal:  2×29 - 5 = 53 wedstrijden
```

## A-Groep Structuur

### Rondes (rechts → links = klein → groot)
```
1/16 → 1/8 → 1/4 → 1/2 → Finale
```

### Byes (geen aparte voorronde!)

Als N geen macht van 2 is, worden **byes** gebruikt:

| N judoka's | D (doel) | Echte wedstrijden 1/16 | Byes |
|------------|----------|------------------------|------|
| 29 | 16 | 13 (26 spelen) | 3 (direct naar 1/8) |
| 24 | 16 | 8 (16 spelen) | 8 (direct naar 1/8) |
| 20 | 16 | 4 (8 spelen) | 12 (direct naar 1/8) |
| 16 | 16 | 0 | Start bij 1/8 |

**Byes worden automatisch berekend:**
- A-byes = 2D - N
- B-byes niet aan A-bye judoka's geven (fairness regel)

Zie [ELIMINATIE_BEREKENING.md](../ELIMINATIE_BEREKENING.md) voor details.

### Layout (horizontaal gespiegeld)

```
1/16      1/8       1/4       1/2       Finale
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
      ├──┘  │
┌──┐  │     ├──┐
│  ├──┘     │  │
└──┘        │  ├──── 🏆 Goud
════════════════════════ horizon (midden)
┌──┐        │  ├──── 🥈 Zilver
│  ├──┐     │  │
└──┘  │     ├──┘
      ├──┐  │
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

## B-Groep Structuur

### Instroom (batches)

Verliezers uit A stromen **in batches** in naar B:

| A-ronde verliezers | Gaan naar B-ronde |
|-------------------|-------------------|
| A 1/16 | B 1/8 (1) |
| A 1/8 | B 1/8 (2) |
| A 1/4 | B 1/4 (2) |
| A 1/2 | B 1/2 (2) = Brons |

### Dubbele rondes

Door de batch instroom heeft B-groep **dubbele rondes**:

```
B-groep: Start → 1/8(1) → 1/8(2) → 1/4(1) → 1/4(2) → 1/2(1) → 1/2(2)
                                                              ↓
                                                          2x 🥉 Brons
```

| Ronde | Wedstrijden | Samenstelling |
|-------|-------------|---------------|
| B 1/8 (1) | 8 | B onderling |
| B 1/8 (2) | 8 | B 1/8(1) winnaars + A 1/8 verliezers |
| B 1/4 (1) | 4 | B 1/8(2) winnaars |
| B 1/4 (2) | 4 | B 1/4(1) winnaars + A 1/4 verliezers |
| B 1/2 (1) | 2 | B 1/4(2) winnaars |
| B 1/2 (2) | 2 | B 1/2(1) winnaars + A 1/2 verliezers → **Brons** |

### Voorbeeld: 29 judoka's

```
A-GROEP                              B-GROEP
========                             ========

A 1/16 (13 weds)
    ↓ 13 verliezers ─────────────────→ B start (5 weds, 21→16)
                                           ↓
A 1/8 (8 weds)                        B 1/8 (1) (8 weds)
    ↓ 8 verliezers ──────────────────→ B 1/8 (2) (8 weds)
                                           ↓
A 1/4 (4 weds)                        B 1/4 (1) (4 weds)
    ↓ 4 verliezers ──────────────────→ B 1/4 (2) (4 weds)
                                           ↓
A 1/2 (2 weds)                        B 1/2 (1) (2 weds)
    ↓ 2 verliezers ──────────────────→ B 1/2 (2) = BRONS (2 weds)
    ↓
A Finale (1 wed)
    ↓
  🥇 + 🥈
```

## Wedstrijd Weergave

### Wedstrijd = 1 potje
- **Bovenste vak**: Wit (shiro)
- **Onderste vak**: Blauw (ao)
- **Groene stip**: Winnaar markering

### Winnaar doorschuiven
1. Sleep winnaar naar volgende ronde
2. Systeem registreert automatisch uitslag
3. Verliezer gaat naar B-groep (bij A-wedstrijd)

## Database Model

### Wedstrijd velden
```php
'ronde' => 'kwartfinale',           // zestiende_finale, achtste_finale, etc.
'groep' => 'A',                     // A = hoofdboom, B = herkansing
'bracket_positie' => 1,             // Positie in de ronde (1-based)
'volgende_wedstrijd_id' => null,    // Winnaar gaat naar deze wedstrijd
'winnaar_naar_slot' => 'wit',       // Winnaar wordt wit of blauw
```

### Ronde namen

| A-groep | B-groep |
|---------|---------|
| `zestiende_finale` | `b_start` |
| `achtste_finale` | `b_achtste_finale`, `b_achtste_finale_2` |
| `kwartfinale` | `b_kwartfinale_1`, `b_kwartfinale_2` |
| `halve_finale` | `b_halve_finale_1` |
| `finale` | `b_brons` |

## Implementatie

### EliminatieService

```php
// Genereer complete bracket
$service->genereerBracket($poule, $judokaIds);

// Verwerk uitslag
$service->verwerkUitslag($wedstrijd, $winnaarId);

// Bereken statistieken
$stats = $service->berekenStatistieken($n);
// Returns: a_wedstrijden, b_wedstrijden, totaal_wedstrijden, etc.
```

### Test script

```bash
cd laravel
php test_eliminatie.php
```
