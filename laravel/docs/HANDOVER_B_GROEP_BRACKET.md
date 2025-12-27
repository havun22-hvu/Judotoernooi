# Handover: B-groep Bracket Fix

## Wat is gedaan
- Voorronde toont nu alleen bestaande wedstrijden, niet 16 lege potjes
- Positie gebaseerd op `bracket_positie` (waar de voorronde hoort bij de 1/8)

## Wat nog moet (B-groep symmetrie)

### Gewenste layout (zie screenshot in chat)
```
Voorronde (5) | 1/8 (8) | 1/4 (1) | 1/4 (2) | 1/2 (1) | Brons | →🥉
```

### Belangrijke punten
1. **Aparte kolommen** - NIET combineren! Elke ronde eigen kolom
2. **Verticale posities**:
   - 1/4(2) op DEZELFDE hoogte als 1/4(1)
   - Brons op DEZELFDE hoogte als 1/2(1)
3. **Symmetrie = SPIEGELING naar het MIDDEN**
   - Bovenste helft spiegelt naar beneden
   - Onderste helft spiegelt naar boven
   - Finales komen uit in het midden
4. **Voorronde = alleen bestaande wedstrijden**
   - Niet complete 1/16 finale tekenen
   - Alleen de X voorrondes op hun bracket_positie

### Niveau systeem (al geimplementeerd)
```javascript
rondeNiveauMap = {
    'b_kwartfinale_1': 1,
    'b_kwartfinale_2': 1,  // Zelfde niveau als 1/4-1
    'b_halve_finale_1': 2,
    'b_brons': 2,  // Zelfde niveau als 1/2-1
}
```

### Wat NIET doen
- NOOIT rondes combineren in één kolom
- NOOIT lege potjes tekenen
- NOOIT de dubbele finales (1/4-2, brons) verwijderen

## Bestanden
- `laravel/resources/views/pages/mat/interface.blade.php` - renderBracket functie (~regel 928)
- `laravel/app/Services/EliminatieService.php` - backend bracket generatie

## Test
29 judoka's → B-groep krijgt 27 → Voorronde (5), 1/8 (8), etc.
