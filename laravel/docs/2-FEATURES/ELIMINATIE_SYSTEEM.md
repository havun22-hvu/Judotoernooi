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
