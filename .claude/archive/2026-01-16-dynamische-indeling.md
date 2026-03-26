# Session Handover: 16 januari 2026

## Wat is gedaan

### 1. Classificatie fix (hardcoded → config)
- **Probleem:** `Leeftijdsklasse` enum had hardcoded categorieën die toernooi config negeerden
- **Oplossing:**
  - `Toernooi::bepaalLeeftijdsklasse()` en `bepaalGewichtsklasse()` gebruiken nu config
  - `Toernooi::getLeeftijdsklasseSortValue()` voor sortering (jong → oud)
  - Alle `Leeftijdsklasse::` calls verwijderd uit controllers
  - `JudokaController::valideer()` hercategoriseert nu ook leeftijdsklasse

### 2. DynamischeIndelingService herschreven
- **Oud:** 1374 regels, complex multi-pass algoritme
- **Nieuw:** ~300 regels, simpel greedy algoritme

**Algoritme (4 stappen):**
1. **Greedy:** Loop door gesorteerde judoka's, maak poules van max 5
2. **Merge buren:** Kleine poules (< 4) samenvoegen met aangrenzende
3. **Globale merge:** Kleine poules samenvoegen met ALLE andere kleine poules (niet alleen buren)
4. **Balanceer:** Steel judoka's van poules met 6+ naar kleine poules (5 niet verkleinen!)

**Resultaat:** 335 judoka's → 71 poules, slechts 5 kleine (echte orphans)

### 3. Docs bijgewerkt
- `docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md` - algoritme beschrijving
- `docs/1-GETTING-STARTED/CONFIGURATIE.md` - dynamische categorieën

## Wat nog te doen (morgen)

### Verdere optimalisatie kleine poules
De 5 overblijvende kleine poules zijn "orphans" die qua gewicht/leeftijd niet passen:
- Poule 7: 3 judoka's (7jr, 28.8-31.3kg)
- Poule 8: 1 judoka (12jr, 35.1kg) - Mawin
- Poule 18: 2 judoka's (8jr, 35.3-37.4kg)
- Poule 33: 3 judoka's (9-11jr, 37.5-40.3kg)
- Poule 71: 2 judoka's (12jr, 59.5-61.5kg) - zwaarste

**Mogelijke verbeteringen:**
1. **Swap tussen poules:** Ruil judoka tussen poule van 5 en kleine poule als beide valid blijven
2. **Relaxeer limieten:** Bij orphans, check of 3.5kg of 3jr verschil acceptabel is
3. **Handmatige suggesties:** Toon welke judoka's dichtbij de limiet zitten

### Test in productie
- Genereer poule indeling met nieuwe code
- Vergelijk met oude indeling
- Check of alle judoka's correct gecategoriseerd zijn

## Belangrijke bestanden

| Bestand | Functie |
|---------|---------|
| `app/Services/DynamischeIndelingService.php` | Nieuw simpel algoritme |
| `app/Services/PouleIndelingService.php` | Roept DynamischeIndelingService aan |
| `app/Models/Toernooi.php` | `bepaalLeeftijdsklasse()`, `getLeeftijdsklasseSortValue()` |
| `docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md` | Algoritme documentatie |

## Config waardes (test-toernooi-2026)

```
Jeugd: max_kg_verschil = 3, max_leeftijd_verschil = 2
Mini's: max_kg_verschil = 3
Dames: max_kg_verschil = 4
Heren: max_kg_verschil = 3
```

## Commits vandaag

1. `fix: Add leeftijdsklasse recategorization to valideer function`
2. `fix: Sort age categories by max_leeftijd from config`
3. `fix: Sort dashboard statistics by max_leeftijd`
4. `docs: Update CONFIGURATIE.md - dynamic age categories`
5. `fix: Group all judokas by age class for dynamic weight grouping`
6. `refactor: Simplify DynamischeIndelingService with greedy algorithm`
7. `feat: Add balancing and global merge to reduce small pools`
8. `fix: Don't shrink pools of 5 - only steal from pools of 6+`
