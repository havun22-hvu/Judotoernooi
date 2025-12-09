# Eliminatie Systeem (Afvalsysteem)

Dit document beschrijft het double elimination systeem voor judotoernooien.

## Overzicht

Het eliminatiesysteem werkt met twee groepen:
- **Groep A (Hoofdboom)**: Winnaars bracket naar de finale
- **Groep B (Herkansing)**: Verliezers krijgen een tweede kans voor brons

## Groep A - Hoofdboom

### Structuur
```
Ronde 1 (indien nodig voor byes)
    ↓
1/8 finale (bij 9-16 judoka's)
    ↓
1/4 finale (kwartfinale)
    ↓
1/2 finale (halve finale)
    ↓
Finale → 1e plaats (winnaar) en 2e plaats (verliezer)
```

### Byes (Vrijstellingen)
Als het aantal judoka's geen macht van 2 is (2, 4, 8, 16, 32), krijgen sommige judoka's een bye:

| Judoka's | Bracket grootte | Byes | Ronde 1 wedstrijden |
|----------|-----------------|------|---------------------|
| 3        | 4               | 1    | 2                   |
| 5-6      | 8               | 3-2  | 2-3                 |
| 7        | 8               | 1    | 3                   |
| 9-12     | 16              | 7-4  | 4-6                 |
| 13-15    | 16              | 3-1  | 6-7                 |

**Berekening:**
- Bracket grootte = kleinste macht van 2 >= aantal judoka's
- Aantal byes = bracket grootte - aantal judoka's
- Byes worden verdeeld over de hoogst geplaatste/gelote judoka's

### Seeding
Judoka's worden geplaatst op basis van:
1. Band (hoogste band = hoogste seed)
2. Gewicht (indien relevant)
3. Loting voor gelijke posities

## Groep B - Herkansing

### Instroom
Verliezers uit Groep A stromen in bij de herkansing:
- Verliezers ronde 1 → Start herkansing ronde 1
- Verliezers 1/4 finale → Instroom bij herkansing ronde 2
- etc.

### Structuur Herkansing
```
Herkansing R1: Verliezers R1 Groep A
    ↓
Herkansing R2: Winnaars H-R1 + Verliezers 1/4 finale
    ↓
Herkansing R3: Winnaars H-R2 + Verliezers 1/2 finale (optioneel)
    ↓
Herkansing finale
```

## Strijd om Brons (Gedeelde 3e plaats)

### Belangrijk principe
De winnaars van de herkansing vechten NIET tegen elkaar voor één bronzen medaille.

### Wedstrijden om brons
Er zijn **twee aparte wedstrijden** om de 3e plaats:

```
Brons wedstrijd 1:
  Verliezer halve finale A  vs  Winnaar herkansing tak A

Brons wedstrijd 2:
  Verliezer halve finale B  vs  Winnaar herkansing tak B
```

### Resultaat
- **2 bronzen medailles** (gedeelde 3e plaats)
- Winnaars van beide brons-wedstrijden krijgen brons
- Verliezers eindigen op 5e plaats

## Voorbeeld: 8 Judoka's

### Groep A (Hoofdboom)
```
        1/4 finale              1/2 finale           Finale

Judoka 1 ─┐
          ├─ Winnaar A ─┐
Judoka 8 ─┘             │
                        ├─ Winnaar E ─┐
Judoka 4 ─┐             │             │
          ├─ Winnaar B ─┘             │
Judoka 5 ─┘                           │
                                      ├─ FINALE → 1e/2e
Judoka 3 ─┐                           │
          ├─ Winnaar C ─┐             │
Judoka 6 ─┘             │             │
                        ├─ Winnaar F ─┘
Judoka 2 ─┐             │
          ├─ Winnaar D ─┘
Judoka 7 ─┘
```

### Groep B (Herkansing)
```
Verliezer 1/4 A ─┐
                 ├─ Winnaar H1 ─┐
Verliezer 1/4 D ─┘              │
                                ├─ Winnaar H3 ─┐
Verliezer 1/4 B ─┐              │              │
                 ├─ Winnaar H2 ─┘              │
Verliezer 1/4 C ─┘                             │
                                               ├→ vs Verliezer 1/2 E → BRONS 1
                                               │
                    (zelfde structuur)         ├→ vs Verliezer 1/2 F → BRONS 2
```

## Database Model

### Wedstrijd velden voor eliminatie
```php
'type' => 'eliminatie',           // Type wedstrijd
'ronde' => 'kwartfinale',         // finale, halve_finale, kwartfinale, achtste_finale, etc.
'groep' => 'A',                   // A = hoofdboom, B = herkansing
'bracket_positie' => 1,           // Positie in de bracket (1-based)
'volgende_wedstrijd_id' => null,  // Winnaar gaat naar deze wedstrijd
'herkansing_wedstrijd_id' => null,// Verliezer gaat naar deze wedstrijd (herkansing)
```

### Ronde namen
| Ronde code        | Nederlandse naam    |
|-------------------|---------------------|
| `finale`          | Finale              |
| `halve_finale`    | Halve finale        |
| `kwartfinale`     | Kwartfinale         |
| `achtste_finale`  | Achtste finale      |
| `zestiende_finale`| Zestiende finale    |
| `brons`           | Strijd om brons     |
| `herkansing_r1`   | Herkansing ronde 1  |
| `herkansing_r2`   | Herkansing ronde 2  |

## Wedstrijd Volgorde

De wedstrijden worden gespeeld in deze volgorde:
1. Eerste rondes Groep A
2. Herkansing rondes (gelijk met volgende Groep A rondes)
3. Kwartfinales Groep A
4. Herkansing rondes
5. Halve finales Groep A
6. Herkansing finales
7. Strijd om brons (2 wedstrijden)
8. Finale

## Speciale situaties

### 3 Judoka's
Bij 3 judoka's is eliminatie niet ideaal. Overweeg:
- Poule met dubbele ronde (6 wedstrijden)
- Of: 1 bye, 1 wedstrijd ronde 1, dan finale

### 2 Judoka's
Directe finale (1 wedstrijd).

### Walk-over / Opgave
- Bij walk-over in Groep A: tegenstander naar volgende ronde
- Verliezer (walk-over) gaat NIET naar herkansing

## Implementatie notities

### Bracket generatie algoritme
1. Bepaal bracket grootte (kleinste macht van 2 >= n)
2. Bereken aantal byes
3. Seed judoka's (band, gewicht, loting)
4. Plaats byes bij hoogste seeds
5. Genereer wedstrijden voor ronde 1
6. Koppel wedstrijden aan volgende rondes
7. Genereer herkansing structuur

### Judoka plaatsing bij winst/verlies
```php
// Bij resultaat invoer:
if ($wedstrijd->groep === 'A') {
    // Winnaar naar volgende wedstrijd in Groep A
    $winnaar->plaatsIn($wedstrijd->volgende_wedstrijd);

    // Verliezer naar herkansing (Groep B)
    if ($wedstrijd->herkansing_wedstrijd) {
        $verliezer->plaatsIn($wedstrijd->herkansing_wedstrijd);
    }
}
```
