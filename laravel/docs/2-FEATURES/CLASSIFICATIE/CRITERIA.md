---
title: Harde vs Zachte Criteria
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Harde vs Zachte Criteria

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Harde vs Zachte Criteria

### Harde Criteria voor CATEGORISEREN (Stap 1)

Deze criteria bepalen in WELKE CATEGORIE een judoka komt.
**Een judoka die qua leeftijd past maar niet qua geslacht/band = NIET GECATEGORISEERD!**

| Criterium | Voorbeeld | Toelichting |
|-----------|-----------|-------------|
| `max_leeftijd` | U7 = max 6 jaar | **HARDE GRENS** - 6-jarige in U7 komt NOOIT in U9 |
| `geslacht` | M / V / Gemengd | Moet matchen binnen de leeftijdscategorie |
| `band_filter` | t/m oranje, vanaf groen | Moet matchen binnen de leeftijdscategorie |

**U-terminologie (JBN standaard):**
```
U = Under (jonger dan)

U7  = max_leeftijd 6  (t/m 6 jaar)
U9  = max_leeftijd 8  (t/m 8 jaar)
U11 = max_leeftijd 10 (t/m 10 jaar)
U13 = max_leeftijd 12 (t/m 12 jaar)
U15 = max_leeftijd 14 (t/m 14 jaar)
U18 = max_leeftijd 17 (t/m 17 jaar)
U21 = max_leeftijd 20 (t/m 20 jaar)

Formule: max_leeftijd = U-nummer - 1
```

**Leeftijd in titel:**
- `max_leeftijd_verschil > 0` → Titel toont dynamische range (bijv. "7-9j")
- `max_leeftijd_verschil = 0` → Organisator zet leeftijd in label (bijv. "Dames U15")

```
⚠️ KRITIEK: Doorvallen naar andere leeftijdscategorie is VERBODEN!

   Voorbeeld:
   - Categorieën: U7 (max 6j, band_filter: vanaf_geel), U9 (max 8j)
   - 6-jarige met witte band
   - Past in U7 qua leeftijd ✓
   - Past NIET in U7 qua band ✗
   - Resultaat: NIET GECATEGORISEERD (melding!)
   - NOOIT naar U9 doorvallen!
```

### Harde Criteria voor POULE-INDELING (Stap 4)

Deze criteria bepalen hoe judoka's BINNEN een categorie in poules worden verdeeld.

| Criterium | Voorbeeld | Waar ingesteld |
|-----------|-----------|----------------|
| `gewichtsklassen` | -24kg, -27kg | Per categorie (bij vast) |
| `max_kg_verschil` | Max 3 kg in poule | Per categorie (bij variabel) |
| `max_leeftijd_verschil` | Max 2 jaar in poule | Per categorie (bij variabel) |
| `max_band_verschil` | Max 2 band niveaus in poule | Per categorie (0 = geen limiet) |
| `band_streng_beginners` | Wit/geel max 1 niveau verschil | Checkbox per categorie |

#### Band Streng Beginners

Wanneer deze optie aangevinkt is:
- Poules met **beginners** (wit of geel band) krijgen max **1** niveau bandverschil
- Poules met alleen **gevorderden** (oranje+) krijgen de normale `max_band_verschil`

**Voorbeeld met max_band_verschil=2 en streng_beginners=true:**
- Poule met wit(0) + geel(1) = OK (verschil 1)
- Poule met wit(0) + oranje(2) = **NIET OK** (verschil 2, maar beginner erin dus max 1)
- Poule met oranje(2) + groen(4) = OK (verschil 2, geen beginners)

### Zachte Criteria (sorteer niveau)

| Criterium | Volgorde | Effect |
|-----------|----------|--------|
| Leeftijd prioriteit | jong → oud | Jongste eerst in poule |
| Gewicht prioriteit | licht → zwaar | Lichtste eerst in poule |
| Band prioriteit | laag → hoog | Beginners eerst in poule |

### Apart Ingesteld

| Instelling | Waarde | Betekenis |
|------------|--------|-----------|
| `poule_grootte_voorkeur` | [5, 4, 6, 3] | Poule groottes (zie onder) |

#### poule_grootte_voorkeur - Twee functies

De lijst heeft **twee functies**:

1. **Prioriteit**: volgorde bepaalt voorkeur bij maken van poules
2. **Toegestaan**: groottes IN de lijst zijn acceptabel, NIET in lijst = problematisch

| Grootte | In lijst [5,4,6,3] | Status |
|---------|---------------------|--------|
| 5 | Positie 1 | ✅ Ideaal (0 punten) |
| 4 | Positie 2 | ✅ Goed (5 punten) |
| 6 | Positie 3 | ✅ Acceptabel (40 punten) |
| 3 | Positie 4 | ✅ Ongewenst maar OK (40 punten) |
| 7+ | Niet in lijst | ❌ **Kan niet** (solver maakt dit nooit) |
| 1-2 | Niet in lijst | 🔴 Orphan (100 punten) |

**Harde bovengrens:** `max(poule_grootte_voorkeur)` is de absolute maximum poulegrootte.
- Bij [5, 4, 6, 3] → max = 6, poule van 7 kan NIET
- Bij [5, 4, 3] → max = 5, poule van 6 kan NIET

**Voorbeeld prioriteit verschil:**
- [5, 4, **3**, 6]: 6 judoka's → 2×3 (want 3 staat vóór 6)
- [5, 4, **6**, 3]: 6 judoka's → 1×6 (want 6 staat vóór 3)

---

