---
title: Blokverdeling - Variabele categorieen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - Variabele categorieen

> Onderdeel van [Blokverdeling](../BLOKVERDELING.md).


## Variabele Categorieën (Dynamische Blokverdeling)

Bij toernooien met **variabele categorieën** (bijv. "Jeugd t/m 14j" met Δkg=3 en Δlft=1) werkt de blokverdeling anders dan bij vaste categorieën.

### Het Probleem

Bij variabele indeling krijg je poules zoals:
- 9-10j, 22-25kg
- 10-11j, 25-28kg
- 11-12j, 28-31kg
- 12-13j, 32-35kg

De leeftijdsranges **overlappen**: een 11-jarige kan in zowel "10-11j" als "11-12j" poules zitten (afhankelijk van gewicht).

Je kunt dus NIET clean zeggen "alle 11-jarigen in blok 2" - want sommige zitten in poule "10-11j" en andere in "11-12j".

### De Oplossing: Knip op Gewicht

De verdeling werkt als volgt:

1. **Sorteer alle poules** op: min_leeftijd → min_gewicht
2. **Vul blok** totdat max wedstrijden bereikt is
3. **Knip waar je bent** - dit kan midden in een leeftijdsgroep vallen
4. **Genereer leesbare labels** per blok

**Voorbeeld:** Max 133 wedstrijden per blok

```
Blok 2 (132 wedstrijden):
├── 9-10j alle gewichten      (30 wed)
├── 10-11j alle gewichten     (45 wed)
├── 11-12j t/m 28kg           (57 wed)  ← knip hier!

Blok 3 (128 wedstrijden):
├── 11-12j vanaf 28kg         (35 wed)  ← zwaardere 11-12j
├── 12-13j alle gewichten     (48 wed)
├── 13-14j alle gewichten     (45 wed)
```

### Communicatie naar Ouders

De bloklabels worden automatisch gegenereerd:

```
Blok 2: "Jeugd 9-12j t/m 28kg"    - weging 10:00-10:30
Blok 3: "Jeugd 11-14j vanaf 28kg" - weging 11:00-11:30
```

**Let op de overlap in leeftijd!** Dit is correct:
- 11-12j **licht** (t/m 28kg) → Blok 2
- 11-12j **zwaar** (vanaf 28kg) → Blok 3

### Voordeel: Overpoulen

Als een 11-jarige te zwaar is voor zijn poule en overpouled wordt naar een zwaardere groep, zit hij automatisch al in het juiste blok (de zwaardere groep zit in het latere blok).

### Algoritme Details

```
voor elke poule in gesorteerde volgorde:
    als (actueel + poule.wedstrijden) > max_per_blok:
        start nieuw blok
    voeg poule toe aan huidig blok
    actueel += poule.wedstrijden

genereer label per blok:
    min_leeftijd = MIN(poules.min_leeftijd)
    max_leeftijd = MAX(poules.max_leeftijd)
    min_gewicht = MIN(poules.min_gewicht)
    max_gewicht = MAX(poules.max_gewicht)

    als eerste blok met deze leeftijdsrange:
        label = "{min_lft}-{max_lft}j t/m {max_gewicht}kg"
    anders:
        label = "{min_lft}-{max_lft}j vanaf {min_gewicht}kg"
```

### UI Aanpassingen

Bij variabele categorieën:
- Geen drag & drop per categorie (categorieën bestaan niet)
- Slider voor "max wedstrijden per blok"
- Preview van waar de knip valt
- Automatisch gegenereerde bloklabels

### Chip Weergave (variabele categorieën)

Format: `{label 1 letter} {min_lft}j {min_kg}kg ({wedstrijden}w)`

Voorbeeld: `M 9j 22kg (10w)`

- **Label**: Eerste letter van leeftijdsklasse prefix (M, V, B voor Beginners, etc.)
- **Min leeftijd**: Jongste judoka in de poule
- **Min gewicht**: Lichtste judoka in de poule
- **Wedstrijden**: Aantal wedstrijden in de poule

Dit maakt de sortering visueel controleerbaar: van jong naar oud, van licht naar zwaar.

### Overzicht Panel (variabele categorieën)

Bij variabele categorieën toont het rechterpanel individuele poules:

```
P1 M 8-9j 20-23kg (6w)    [1]
P2 M 8-9j 23-26kg (10w)   [1]
P3 M 9-10j 24-27kg (6w)   [2]
...
```

- **P{nummer}**: Poule nummer
- **Label**: Eerste letter (M, V, B, etc.)
- **Leeftijd**: Volledige range (8-9j)
- **Gewicht**: Volledige range (20-23kg)
- **Wedstrijden**: Aantal wedstrijden
- **Blok**: Toegewezen bloknummer

Panel is breder (w-72) om alle info te tonen. Gesorteerd op min leeftijd, dan min gewicht.

### Wedstrijddag: Gewichtsmutaties (variabele categorieën)

Bij variabele categorieën is de logica anders dan bij vaste categorieën:

**Vaste categorieën:**
- Judoka buiten gewichtsklasse → individuele oranje markering
- Moet naar andere gewichtsklasse verplaatst worden

**Variabele categorieën:**
- **Geen** individuele markering (gewicht mag binnen poule range variëren)
- Poule header wordt **oranje** als max_kg_verschil overschreden
- Poule titel toont actuele range: `Mini's 7-8j (18.5-22.3kg)`
- Alleen problematische poules vereisen actie (verplaats lichtste of zwaarste)

---

---
