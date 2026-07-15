---
title: Dynamische & Variabele Indeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Dynamische & Variabele Indeling

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

### DynamischeIndelingService

Roept Python solver aan voor dynamische categorieën:
- `berekenIndeling()` - Wrapper rond Python solver
- `getEffectiefGewicht()` - Fallback: gewicht_gewogen → gewicht → gewichtsklasse
- Filtert onvolledige judoka's (zonder gewicht/leeftijd) en rapporteert deze apart

#### Algoritme Python Solver (Greedy + Slimme Herverdeling)

```
INPUT:  Judoka's van 1 categorie + config (max_kg, max_lft, max_band, poule_grootte_voorkeur)
OUTPUT: Poules binnen constraints

============================================================================
KERNPRINCIPE: SIMPEL GREEDY + ACHTERAF FIXEN
============================================================================

Simpele aanpak die goed werkt:
1. Sorteer alle judoka's op prioriteit
2. Maak poules greedy (beste match zoeken)
3. Fix kleine poules achteraf als mogelijk
4. Accepteer orphans die nergens passen

============================================================================
STAP 1: SORTEER OP PRIORITEITEN
============================================================================

Sorteer alle judoka's op config prioriteiten (default: band → gewicht → leeftijd)

Resultaat:
  wit/22kg, wit/23kg, wit/25kg, geel/23kg, geel/26kg, oranje/27kg, oranje/30kg...

Lage banden en lage gewichten komen eerst = bij elkaar in poules.

============================================================================
STAP 2: SLIMME GREEDY VERDELING
============================================================================

Voor elke poule:
1. Start met eerste ongeplaatste judoka (anchor)
2. Zoek in ALLE overgebleven judoka's wie het beste past:
   - Moet voldoen aan: max_kg, max_lft, max_band t.o.v. IEDEREEN in poule
   - Score: zelfde band + dicht gewicht = beste match
3. Voeg beste match toe, herhaal tot poule vol (ideale grootte)
4. Geen match meer? Sluit poule, start nieuwe

Dit is NIET lineair door de lijst lopen, maar actief zoeken naar beste match!

============================================================================
STAP 3: HERVERDEEL KLEINE POULES
============================================================================

Na greedy verdeling kunnen er kleine poules (1-2 judoka's) overblijven.

STRATEGIE 1: Merge kleine poules
  - Als twee kleine poules samen passen (alle constraints OK)
  - Voeg ze samen tot één grotere poule

STRATEGIE 2: Steel van te grote poules
  - Als er poules zijn groter dan ideaal (bijv. 6 bij voorkeur 5)
  - Zoek judoka die past bij kleine poule
  - Verplaats alleen als die judoka nog niet eerder verplaatst is

============================================================================
STAP 4: ACCEPTEER ORPHANS
============================================================================

Judoka's die nergens passen blijven als orphan (poule van 1-2):
  - Te groot gewichtsverschil met anderen
  - Te groot bandverschil
  - Te groot leeftijdsverschil

Dit is CORRECT gedrag! Organisator kan handmatig oplossen of constraints aanpassen.

============================================================================
SAMENVATTING
============================================================================

1. SORTEER: Alle judoka's op prioriteit (laag → hoog)
2. GREEDY: Maak poules door beste match te zoeken
3. HERVERDEEL: Merge kleine poules, steel van grote
4. ACCEPTEER: Orphans die niet passen

Voordelen:
- Simpel en begrijpelijk
- Geen ingewikkelde cascading logica
- Resultaat is voorspelbaar
- Orphans zijn echt orphans (geen false positives)

```

### VariabeleBlokVerdelingService

Voor blokverdeling bij variabele categorieën:
- `genereerVarianten()` - Trial & error splits
- `groepeerInCategorieen()` - Dynamische headers

### Gemengde Blokverdeling (NIEUW)

Bij toernooien met ZOWEL vaste ALS variabele categorieën werkt `BlokMatVerdelingService` in twee fasen.

**Detectie:**
```php
// In BlokMatVerdelingService
private function isGemengdToernooi(Toernooi $toernooi): bool
{
    $config = $toernooi->getAlleGewichtsklassen();
    $heeftVast = false;
    $heeftVariabel = false;

    foreach ($config as $cat) {
        if (($cat['max_kg_verschil'] ?? 0) == 0) {
            $heeftVast = true;
        } else {
            $heeftVariabel = true;
        }
    }

    return $heeftVast && $heeftVariabel;
}
```

**Twee-Fasen Algoritme:**

```
genereerGemengdeVerdeling():

FASE 1: VASTE CATEGORIEËN
─────────────────────────
1. Filter categorieën waar max_kg_verschil = 0
2. Groepeer per leeftijdsklasse
3. Sorteer: jong → oud, dan licht → zwaar
4. Verdeel met bestaande aansluiting-logica (+1, -1, +2)
5. Update capaciteit per blok

FASE 2: VARIABELE POULES
─────────────────────────
1. Filter poules waar max_kg_verschil > 0
2. Sorteer op min_leeftijd → min_gewicht
3. Voor elk blok: vul resterende ruimte
4. Variabele poules passen flexibel in gaten
```

**Key methods:**

```php
// BlokMatVerdelingService
public function genereerGemengdeVerdeling(Toernooi $toernooi): array
{
    // Splits categorieën
    [$vaste, $variabele] = $this->splitsCategorieenOpType($toernooi);

    // Fase 1: Vaste eerst (ruggengraat)
    $capaciteit = $this->verdeelVasteCategorieen($vaste, $blokken);

    // Fase 2: Variabele als opvulling
    $this->vulMetVariabelePoules($variabele, $blokken, $capaciteit);

    return $this->berekenScores(...);
}
```

**Voordelen:**
- Grote groepen (vaste cat.) krijgen gegarandeerd plek
- Aansluiting gewichtsklassen blijft behouden
- Variabele poules vullen gaten flexibel
- Dag loopt logisch: jong → oud, licht → zwaar

---

