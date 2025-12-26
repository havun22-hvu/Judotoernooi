# Eliminatie Systeem (Afvalsysteem)

Dit document beschrijft het double elimination systeem voor judotoernooien.

## Overzicht

Het eliminatiesysteem werkt met twee groepen:
- **Groep A (Hoofdboom)**: Winnaars bracket naar de finale
- **Groep B (Herkansing/B-poule)**: Verliezers krijgen een tweede kans voor brons

## Bracket Structuur

### Groep A - Hoofdboom

#### Voorronde (indien nodig)
Als het aantal judokas geen macht van 2 is, worden er **voorronde wedstrijden** gespeeld:

| Judokas | Doel (power of 2) | Voorronde weds | Voorronde judokas |
|---------|-------------------|----------------|-------------------|
| 23      | 16                | 7              | 14                |
| 18      | 16                | 2              | 4                 |
| 29      | 16                | 13             | 26                |
| 10      | 8                 | 2              | 4                 |

**Berekening:**
```
doel = grootste macht van 2 <= aantal judokas
voorronde_wedstrijden = aantal - doel
voorronde_judokas = voorronde_wedstrijden * 2
bye_judokas = doel - voorronde_wedstrijden (gaan direct naar 1/8)
```

#### Rondes na voorronde
```
Voorronde (indien nodig)
    ↓ winnaars
1/8 finale (bij doel=16)
    ↓ winnaars
Kwartfinale
    ↓ winnaars
Halve finale
    ↓ winnaars
Finale → 1e plaats (winnaar) en 2e plaats (verliezer)
```

### Groep B - Herkansing (B-poule)

De B-poule groeit elke ronde met verliezers uit de A-poule.

#### B-groep structuur per aantal spelers (8-40)

| N | D | V | Naar B | B-groep rondes (aantal wedstrijden) |
|---|---|---|--------|-------------------------------------|
| 8 | 8 | 0 | 4 | 1e 1/2 (2), 2e 1/2 (2) |
| 9 | 8 | 1 | 5 | 1 voorr (1), 1e 1/2 (2), 2e 1/2 (2) |
| 10 | 8 | 2 | 6 | 2 voorr (2), 1e 1/2 (2), 2e 1/2 (2) |
| 11 | 8 | 3 | 7 | 3 voorr (3), 1e 1/2 (2), 2e 1/2 (2) |
| **12** | 8 | 4 | 8 | **1/4 (4)**, 1e 1/2 (2), 2e 1/2 (2) |
| 13 | 8 | 5 | 9 | 1 voorr + 1/4 (5), 1e 1/2 (2), 2e 1/2 (2) |
| 14 | 8 | 6 | 10 | 2 voorr + 1/4 (6), 1e 1/2 (2), 2e 1/2 (2) |
| 15 | 8 | 7 | 11 | 3 voorr + 1/4 (7), 1e 1/2 (2), 2e 1/2 (2) |
| **16** | 16 | 0 | 8 | **1e 1/4 (4), 2e 1/4 (4)**, 1e 1/2 (2), 2e 1/2 (2) |
| 17 | 16 | 1 | 9 | 1 voorr (1), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 18 | 16 | 2 | 10 | 2 voorr (2), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 19 | 16 | 3 | 11 | 3 voorr (3), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| **20** | 16 | 4 | 12 | **1/8 (4)**, 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 21 | 16 | 5 | 13 | 1 voorr + 1/8 (5), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 22 | 16 | 6 | 14 | 2 voorr + 1/8 (6), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 23 | 16 | 7 | 15 | 3 voorr + 1/8 (7), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| **24** | 16 | 8 | 16 | **1/8 (8)**, 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 25 | 16 | 9 | 17 | 1 voorr + 1/8 (9), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 26 | 16 | 10 | 18 | 2 voorr + 1/8 (10), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 27 | 16 | 11 | 19 | 3 voorr + 1/8 (11), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 28 | 16 | 12 | 20 | 4 voorr + 1/8 (12), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 29 | 16 | 13 | 21 | 5 voorr + 1/8 (13), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 30 | 16 | 14 | 22 | 6 voorr + 1/8 (14), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 31 | 16 | 15 | 23 | 7 voorr + 1/8 (15), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| **32** | 32 | 0 | 16 | **1e 1/8 (8), 2e 1/8 (8)**, 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 33 | 32 | 1 | 17 | 1 voorr (1), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 34 | 32 | 2 | 18 | 2 voorr (2), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 35 | 32 | 3 | 19 | 3 voorr (3), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| **36** | 32 | 4 | 20 | **1/16 (4)**, 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 37 | 32 | 5 | 21 | 1 voorr + 1/16 (5), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 38 | 32 | 6 | 22 | 2 voorr + 1/16 (6), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| 39 | 32 | 7 | 23 | 3 voorr + 1/16 (7), 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |
| **40** | 32 | 8 | 24 | **1/16 (8)**, 1e 1/8 (8), 2e 1/8 (8), 1e 1/4 (4), 2e 1/4 (4), 1e 1/2 (2), 2e 1/2 (2) |

#### Formules

```
D = grootste macht van 2 ≤ N (doel A-bracket)
V = N - D (aantal A-voorrondes)
Naar_B = V + D/2 (totaal naar B eerste ronde)
B_voorrondes = V (overflow t.o.v. capaciteit D/2)
```

#### Grenzen voor rondes in B-groep

| Milestone | Vanaf N | Reden |
|-----------|---------|-------|
| Dubbele 1/2 finale | 8 | A 1/2 verliezers stromen in bij 2e 1/2 |
| Enkele 1/4 finale | 12 | 8 B-deelnemers = 4 wedstrijden |
| **Dubbele 1/4 finale** | **16** | A 1/8 → B 1e 1/4, A 1/4 → B 2e 1/4 |
| Enkele 1/8 finale | 20 | 12 B-deelnemers, 4 voorrondes = 1/8 |
| Volle 1/8 finale | 24 | 16 B-deelnemers = 8 wedstrijden |
| **Dubbele 1/8 finale** | **32** | A 1/16 → B 1e 1/8, A 1/8 → B 2e 1/8 |
| Enkele 1/16 finale | 36 | 20 B-deelnemers, 4 voorrondes = 1/16 |
| Volle 1/16 finale | 40 | 24 B-deelnemers = 8 wedstrijden |

#### Voorbeeld: 16 spelers (D=16, V=0)

```
A-GROEP                              B-GROEP
========                             ========
A 1/8 finale (8 weds)
    ↓ 8 verliezers ─────────────────→ B 1e 1/4 finale (4 weds)
                                          ↓ 4 winnaars
A 1/4 finale (4 weds)                     ↓
    ↓ 4 verliezers ─────────────────→ B 2e 1/4 finale (4 weds)
                                          ↓ 4 winnaars
A 1/2 finale (2 weds)                B 1e 1/2 finale (2 weds)
    ↓ 2 verliezers ─────────────────→     ↓ 2 winnaars
                                     B 2e 1/2 finale = BRONS (2 weds)
A finale (1 wed)
    ↓
  GOUD + ZILVER
```

#### Voorbeeld: 21 spelers (D=16, V=5)

```
A-GROEP                              B-GROEP
========                             ========
A voorronde (5 weds)
    ↓ 5 verliezers ──────────────────┐
                                     ↓
A 1/8 finale (8 weds)           B voorronde (5 weds, 13→8)
    ↓ 8 verliezers ─────────────────→ B 1e 1/4 finale (4 weds)
                                          ↓ 4 winnaars
A 1/4 finale (4 weds)                     ↓
    ↓ 4 verliezers ─────────────────→ B 2e 1/4 finale (4 weds)
                                          ↓ 4 winnaars
A 1/2 finale (2 weds)                B 1e 1/2 finale (2 weds)
    ↓ 2 verliezers ─────────────────→     ↓ 2 winnaars
                                     B 2e 1/2 finale = BRONS (2 weds)
A finale (1 wed)
    ↓
  GOUD + ZILVER
```

#### Structuur bij 23 judokas (doel=16, voorronde=7)

| B-ronde   | Deelnemers                                      | Wedstrijden |
|-----------|-------------------------------------------------|-------------|
| b_ronde_1 | Bye-judokas die 1/8 verliezen                   | 4           |
| b_ronde_2 | R1 winnaars + voorronde verliezers + rest 1/8   | ~8          |
| b_ronde_3 | R2 winnaars + kwartfinale verliezers            | ~6          |
| b_ronde_4 | (extra rondes tot 2 overblijven)                | ~3          |
| b_brons   | 2 B-winnaars + 2 halve finale verliezers uit A  | 2           |

#### Fairness Regel

**Wie al gespeeld heeft krijgt een bye in de B-poule:**

1. **Voorronde verliezers** → worden gespaard, gaan naar `b_ronde_2` (niet b_ronde_1)
2. **Bye-judokas die 1/8 verliezen** → gaan naar `b_ronde_1` (moeten daar hun eerste extra wedstrijd spelen)

Dit zorgt ervoor dat iedereen in de B-poule ongeveer evenveel wedstrijden speelt.

#### Batch-indeling

Verliezers worden **niet direct** ingedeeld bij elke uitslag, maar:

1. **Wacht** tot een complete A-ronde klaar is (bijv. alle 1/8 finales gespeeld)
2. **Sorteer** verliezers: bye-judokas eerst
3. **Shuffle** binnen elke groep (at random)
4. **Vul** B-poule: bye-judokas naar b_ronde_1, voorronde-spelers naar b_ronde_2
5. Als een ronde vol is → fallback naar andere ronde

Dit zorgt voor eerlijke verdeling wanneer er niet precies genoeg plekken zijn.

## Bronswedstrijden

De bronswedstrijden maken onderdeel uit van de B-poule (ronde `b_brons`):

```
B-poule halve finale winnaar 1  vs  A halve finale verliezer 1  → BRONS 1
B-poule halve finale winnaar 2  vs  A halve finale verliezer 2  → BRONS 2
```

**Resultaat:** 2 bronzen medailles (gedeelde 3e plaats)

## Voorbeeld: 23 Judokas

### A-bracket generatie
```
23 judokas
├── doel = 16 (grootste macht van 2 <= 23)
├── voorronde = 23 - 16 = 7 wedstrijden (14 judokas)
├── bye judokas = 16 - 7 = 9 (gaan direct naar 1/8)
└── A-wedstrijden: 7 + 8 + 4 + 2 + 1 = 22 wedstrijden
```

### B-poule generatie
```
B-poule
├── b_ronde_1: 4 weds (max 8 bye-verliezers)
├── b_ronde_2: ~8 weds (R1 winnaars + 7 voorronde verliezers + rest 1/8)
├── b_ronde_3: ~6 weds (R2 winnaars + 4 kwartfinale verliezers)
├── extra rondes tot 2 B-winnaars overblijven
└── b_brons: 2 weds (2 B-winnaars + 2 A halve finale verliezers)
```

## Automatische Doorschuiving

### Bij uitslag registratie

1. **Winnaar in A-poule** → direct doorgeschoven naar volgende A-wedstrijd
2. **Verliezer in A-poule** → wacht tot ronde compleet, dan batch-indeling in B-poule
3. **Winnaar in B-poule** → direct doorgeschoven naar volgende B-ronde

### Judokas verplaatsbaar

Na automatische indeling kunnen tafelleiding judokas nog **handmatig verplaatsen** via drag & drop. Dit is nodig voor:
- Blessures (volgorde aanpassen)
- Correcties (verkeerde indeling)
- Speciale situaties

## Database Model

### Wedstrijd velden
```php
'ronde' => 'kwartfinale',         // voorronde, achtste_finale, kwartfinale, halve_finale, finale
'groep' => 'A',                   // A = hoofdboom, B = herkansing
'bracket_positie' => 1,           // Positie in de ronde (1-based)
'volgende_wedstrijd_id' => null,  // Winnaar gaat naar deze wedstrijd (A-poule)
'winnaar_naar_slot' => 'wit',     // Winnaar wordt wit of blauw in volgende wedstrijd
```

### Ronde namen

| Groep A             | Groep B           |
|---------------------|-------------------|
| `voorronde`         | `b_ronde_1`       |
| `achtste_finale`    | `b_ronde_2`       |
| `kwartfinale`       | `b_ronde_3`       |
| `halve_finale`      | `b_ronde_4` (etc) |
| `finale`            | `b_brons`         |

## EliminatieService

### Belangrijke methodes

```php
// Genereer complete bracket (A + B)
$service->genereerBracket($poule, $judokas);

// Verwerk uitslag (winnaar door, verliezer naar B bij ronde compleet)
$service->verwerkUitslag($wedstrijd, $winnaarId);
```

### Flow bij verwerkUitslag

1. Winnaar direct doorschuiven naar `volgende_wedstrijd_id`
2. Check of huidige A-ronde compleet is (alle wedstrijden gespeeld)
3. Zo ja: batch-indeling van alle verliezers in B-poule
   - Bye-judokas eerst → b_ronde_1
   - Voorronde-spelers → b_ronde_2
   - At random binnen elke groep
4. B-winnaar direct doorschuiven naar volgende B-ronde

## Mapping A-ronde → B-ronde

| A-ronde verliezers  | Gaan naar B-ronde |
|---------------------|-------------------|
| voorronde           | b_ronde_2 (gespaard) |
| achtste_finale (bye)| b_ronde_1         |
| achtste_finale (voorronde gespeeld) | b_ronde_2 |
| kwartfinale         | b_ronde_3         |
| halve_finale        | b_brons           |

## Implementatie Status

### Gereed
- [x] EliminatieService: bracket generatie (A + B met correcte structuur)
- [x] EliminatieService: batch-indeling verliezers na complete ronde
- [x] EliminatieService: fairness regel (bye-judokas eerst)
- [x] EliminatieService: winnaar doorschuiven (A en B)
- [x] MatController: auto-advance bij uitslag registratie
- [x] Database velden: groep, ronde, bracket_positie, volgende_wedstrijd_id

### Nog te testen
- [ ] Interface weergave met nieuwe ronde-namen
- [ ] Batch-indeling bij complete rondes
- [ ] Drag & drop verplaatsing in B-poule
- [ ] Bronswedstrijden flow

## Test Script

Voor het testen van bracket generatie:

```bash
cd laravel
php test_regen.php
```

Dit genereert brackets voor alle eliminatie-poules en toont de structuur.
