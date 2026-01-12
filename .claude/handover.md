# Handover - Laatste Sessie

## Datum: 12 januari 2026

### Wat is gedaan:

1. **Validatie voor niet-ingedeelde judoka's** ✅
   - `vindNietIngedeeldeJudokas()` - vindt judoka's die niet in een poule zitten
   - `bepaalRedenNietIngedeeld()` - bepaalt reden (leeftijd/band/gewicht mismatch)
   - `bandPastInFilter()` - helper voor band filter validatie
   - Wordt aangeroepen na poule generatie (STAP 4: VALIDATIE)

2. **Clubspreiding in DynamischeIndelingService** ✅
   - `pasClubspreidingToe()` - verdeelt judoka's van zelfde club over poules
   - `clubInPoule()` - helper om club aanwezigheid te checken
   - Respecteert max kg verschil als HARD constraint bij swaps

3. **Max kg verschil is nu HARD constraint** ✅
   - Poules worden gesplitst als limiet overschreden zou worden
   - Geen exceptions - gewichtsverschil wordt NOOIT overschreden
   - Sortering op prioriteit (gewicht of band eerst), dan verdelen van boven naar beneden

### Algoritme samenvatting (zie PLANNING_DYNAMISCHE_INDELING.md):

```
STAP 1: HARDE SELECTIE
  - Max leeftijd (U9 = max 8 jaar, U11 = max 10 jaar)
  - Geslacht, Band filter, Gewichtsklasse
  - Max kg verschil per poule (HARD)

STAP 2: SORTEREN
  - Gewicht eerst → sorteer gewicht, dan band
  - Band eerst → sorteer band, dan gewicht

STAP 3: VERDELEN IN POULES
  - Van boven naar beneden
  - Nieuwe poule als max kg overschreden zou worden

STAP 4: VALIDATIE
  - Check: zijn alle judoka's ingedeeld?
  - Toon niet-ingedeelde met reden
```

### Openstaande items:

- [ ] UI voor varianten selectie (Fase 3)
- [ ] Score visualisatie (Fase 3)
- [ ] Unit tests voor dynamische indeling (Fase 4)

### Gewijzigde bestanden:

```
laravel/app/Services/PouleIndelingService.php
  - vindNietIngedeeldeJudokas()
  - bepaalRedenNietIngedeeld()
  - bandPastInFilter()
  - STAP 4 validatie aanroep

laravel/app/Services/DynamischeIndelingService.php
  - maakPoules() - nieuwe implementatie met HARD constraint
  - pasClubspreidingToe()
  - clubInPoule()
  - probeerToeTeVoegenAanLaatstePoule()
```

### Branch:

`main` - commits gepusht:
- `feat: Implement validation for unassigned judokas in pool generation`
- `fix: Correct pivot table name from judoka_poule to poule_judoka`
