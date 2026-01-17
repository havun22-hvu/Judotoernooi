# Planning: Dynamische Poule Indeling

> **Status:** Fase 1-2 voltooid, Fase 3-4 gepland
> **Laatst bijgewerkt:** 17 jan 2026

## Kernbegrippen

### De 4 Stappen (BELANGRIJK!)

| Stap | Wat | Resultaat |
|------|-----|-----------|
| **1. Categoriseren** | Judoka → categorie (harde criteria) | Elke judoka heeft een categorie |
| **2. Sorteren** | Alle judoka's op prioriteiten | Gesorteerde lijst (jong/licht eerst) |
| **3. Groeperen** | Per categorie groeperen | Gesorteerde lijst PER categorie |
| **4. Poules maken** | Verdelen in poules van 5 | Poules binnen kg/lft limieten |

**Stap 1: Categoriseren** = Welke groep?
- Judoka moet voldoen aan ALLE harde criteria
- Eerste leeftijdsmatch = zijn categorie (NOOIT doorvallen!)
- Harde criteria: max_leeftijd, geslacht, band_filter

**Stap 2-3: Sorteren & Groeperen** = Welke volgorde?
- Sorteer op prioriteiten (leeftijd/gewicht/band)
- Groepeer per categorie → gesorteerde lijst per categorie

**Stap 4: Poules maken** = Verdelen
- Binnen limieten: max_kg_verschil, max_leeftijd_verschil
- Poulegrootte voorkeur: [5, 4, 6, 3]

---

## Algoritme Overzicht

```
┌─────────────────────────────────────────────────────────────────┐
│ POULE INDELING ALGORITME (4 stappen)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ STAP 1: CATEGORISEREN                                           │
│   Per judoka → check welke categorie past                       │
│                                                                 │
│   A. Vind eerste categorie waar leeftijd ≤ max_leeftijd         │
│      (categorieën gesorteerd van jong → oud)                    │
│                                                                 │
│   B. Check ALLEEN categorieën met DIE max_leeftijd:             │
│      • geslacht = M/V/Gemengd                                   │
│      • band voldoet aan band_filter (als gezet)                 │
│                                                                 │
│   ⚠️ KRITIEK: Als geslacht/band niet past → NIET GECATEGORISEERD│
│      NOOIT doorvallen naar categorie met hogere max_leeftijd!   │
│      Een 6-jarige in U7 komt NOOIT in U9, ook niet als          │
│      band_filter niet matcht!                                   │
│                                                                 │
│   LET OP: max_kg_verschil is NIET voor categoriseren!           │
│   Dat is voor stap 4 (poules maken binnen de categorie).        │
│                                                                 │
│ STAP 2: SORTEREN                                                │
│   Sorteer ALLE judoka's volgens verdeling_prioriteiten:         │
│   • Leeftijd: jong → oud                                        │
│   • Gewicht: licht → zwaar                                      │
│   • Band: laag → hoog (wit → zwart)                             │
│                                                                 │
│ STAP 3: GROEPEREN                                               │
│   Groepeer per categorie (sortering blijft behouden)            │
│   → Gesorteerde lijst per categorie, klaar voor poule-indeling  │
│                                                                 │
│ STAP 4: POULES MAKEN (greedy, direct optimaal)                  │
│   Gesorteerde groep verdelen in poules van 5 (of 4/6/3):        │
│                                                                 │
│   Voor elke judoka (gesorteerd):                                │
│   1. Probeer toe te voegen aan huidige poule                    │
│   2. Check: gewicht_verschil ≤ max_kg_verschil (uit config)     │
│   3. Check: leeftijd_verschil ≤ max_leeftijd_verschil (config)  │
│   4. Check: poule_grootte < 5 (of voorkeur)                     │
│   5. Alle checks OK → toevoegen, anders → nieuwe poule          │
│                                                                 │
│   Aan einde: merge kleine poules (< 4) als binnen limieten      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Harde vs Zachte Criteria

### Harde Criteria voor CATEGORISEREN (Stap 1)

Deze criteria bepalen in WELKE CATEGORIE een judoka komt.
**Een judoka die qua leeftijd past maar niet qua geslacht/band = NIET GECATEGORISEERD!**

| Criterium | Voorbeeld | Toelichting |
|-----------|-----------|-------------|
| `max_leeftijd` | U7 = max 6 jaar | **HARDE GRENS** - 6-jarige in U7 komt NOOIT in U9 |
| `geslacht` | M / V / Gemengd | Moet matchen binnen de leeftijdscategorie |
| `band_filter` | t/m oranje, vanaf groen | Moet matchen binnen de leeftijdscategorie |

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
| `clubspreiding` | aan/uit | Probeer clubs te verdelen |

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

## UI: Categorieën Instelling

### Preset Keuze

```
┌─────────────────────────────────────────────────────────────────┐
│ Categorieën Instelling                                          │
│                                                                 │
│ [○ Geen standaard] [○ JBN 2025] [● JBN 2026] [Preset ▼] [Save] │
└─────────────────────────────────────────────────────────────────┘
```

### Sorteer Prioriteit (altijd zichtbaar)

```
┌─────────────────────────────────────────────────────────────────┐
│ Sorteer prioriteit: (sleep om te wisselen)                      │
│ [1. Leeftijd] [2. Gewicht] [3. Band]                           │
│                                                                 │
│ [ ] Clubspreiding (probeer zelfde club te verdelen over poules)│
└─────────────────────────────────────────────────────────────────┘
```

### Categorie Velden

| Veld | Type | Beschrijving |
|------|------|--------------|
| Naam | text | Label (bijv. "Mini's", "Jeugd") |
| In titel | checkbox | Toon label in poule titel |
| Max leeftijd | number | Leeftijdsgrens (exclusief) |
| Geslacht | select | Gemengd / M / V |
| Systeem | select | Poules / Kruisfinale / Eliminatie |
| Max kg verschil | number | 0 = vaste klassen, >0 = variabel |
| Max lft verschil | number | Max jaren verschil in poule |
| Band filter | select | Optioneel: t/m X of vanaf X |
| Gewichtsklassen | text | Alleen bij max_kg = 0 |

### Band Filter Opties

```
┌─────────────────────────────────────────────────────────────────┐
│ Band filter: [Alle banden ▼]                                    │
├─────────────────────────────────────────────────────────────────┤
│ • Alle banden        ← geen filter                              │
│ ─────────────────────                                           │
│ • t/m wit            ← alleen witte band                        │
│ • t/m geel           ← wit + geel                               │
│ • t/m oranje         ← wit + geel + oranje (= beginners)        │
│ • t/m groen          ← wit t/m groen                            │
│ ─────────────────────                                           │
│ • vanaf geel         ← geel en hoger                            │
│ • vanaf oranje       ← oranje en hoger                          │
│ • vanaf groen        ← groen en hoger (= gevorderden)           │
│ • vanaf blauw        ← blauw en hoger                           │
└─────────────────────────────────────────────────────────────────┘
```

**Belangrijk:** Band filter is een HARD criterium voor categoriseren, niet voor sorteren!

---

## Presets

### Opslag

| Preset | Locatie |
|--------|---------|
| JBN 2025 | Hardcoded: `Toernooi::getJbn2025Gewichtsklassen()` |
| JBN 2026 | Hardcoded: `Toernooi::getJbn2026Gewichtsklassen()` |
| Eigen presets | Database: `gewichtsklassen_presets` tabel |

### JBN Leeftijdsklassen (referentie)

| Klasse | U-nummer | max_leeftijd | Leeftijden |
|--------|----------|--------------|------------|
| Mini's | U7/U8 | 6/7 | 5-6 / 6-7 jaar |
| Pupillen A | U9/U10 | 8/9 | 7-8 / 8-9 jaar |
| Pupillen B | U11/U12 | 10/11 | 9-10 / 10-11 jaar |
| Aspiranten | U13/U14 | 12/13 | 11-12 / 12-13 jaar |
| Cadetten | U15 | 14 | 13-14 jaar |
| Junioren | U18 | 17 | 15-17 jaar |
| Senioren | Sen | 99 | 18+ |

**Let op:**
- U7 = **max 6 jaar** (want: Under 7)
- `max_leeftijd` in config = hoogste leeftijd die in deze categorie past
- JBN gebruikt 2-jaar ranges binnen elke categorie

---

## Poulegrootte Verdeling

### Voorkeur Algoritme

Gegeven `poule_grootte_voorkeur = [5, 4, 6, 3]`:

| Aantal | Verdeling | Uitleg |
|--------|-----------|--------|
| 8 | [4, 4] | Twee gelijke (niet 5+3) |
| 9 | [5, 4] | Ideaal + goed |
| 10 | [5, 5] | Twee ideale |
| 11 | [6, 5] of [4, 4, 3] | Afhankelijk van 6 vs 3 voorkeur |
| 12 | [4, 4, 4] | Drie gelijke |
| 15 | [5, 5, 5] | Drie ideale |
| 20 | [5, 5, 5, 5] | Vier ideale |

### Harde Constraints

| Constraint | Breekbaar? |
|------------|------------|
| max_kg_verschil | Nee, nooit |
| max_leeftijd_verschil | Nee, nooit |
| Poulegrootte 3-6 | **Ja, poule van 1-2 toegestaan** |
| Geslacht (indien apart) | Nee, nooit |

### Orphan Judoka's (poule van 1)

**Belangrijk:** Een judoka die geen gewichtsmatch heeft met anderen wordt
WEL ingedeeld in de juiste categorie, maar dan in een poule van 1.

Voorbeeld:
- Fleur (11j, 24.7kg) past in categorie "Jeugd" (t/m 14 jaar)
- Geen andere judoka binnen 3kg verschil
- → Fleur komt in poule van 1 binnen categorie "Jeugd"
- → Organisator kan haar handmatig verplaatsen of constraint aanpassen

Dit voorkomt "niet ingedeeld" meldingen voor judoka's die WEL in een
categorie passen maar geen gewichtsmatch hebben.

### Niet-Gecategoriseerde Judoka's (configuratie probleem)

**Belangrijk:** Dit is iets ANDERS dan orphan judoka's!

| Type | Oorzaak | Oplossing |
|------|---------|-----------|
| **Niet gecategoriseerd** | Geen categorie past (leeftijd/geslacht/band) | Config aanpassen |
| **Orphan (poule van 1)** | Wel categorie, geen gewichtsmatch | Handmatig of max_kg aanpassen |

**Melding "Niet gecategoriseerd":**
- Locatie: **Bovenaan Instellingen pagina** (niet bij Poules!)
- Stijl: Knipperende rode melding (10 sec)
- Triggers:
  1. Na opslaan instellingen (categorie config gewijzigd)
  2. Na import/validatie judoka's
  3. Bij laden instellingen pagina (als er niet-gecategoriseerde zijn)
- Inhoud: Aantal + link naar lijst

---

## Poule Titels

Titels worden automatisch samengesteld:

| Situatie | In titel |
|----------|----------|
| `toon_label_in_titel = true` | Categorie naam |
| `max_leeftijd_verschil = 0` | Geen leeftijd (zit in label) |
| `max_leeftijd_verschil > 0` | Min-max leeftijd van poule |
| `max_kg_verschil = 0` | Vaste gewichtsklasse |
| `max_kg_verschil > 0` | Min-max gewicht van poule |

**Voorbeelden:**
```
#5 Mini's U7 -26kg        ← label aan, vast
#5 Mini's U7 28-32kg      ← label aan, variabel gewicht
#5 Jeugd 9-10j 28-32kg    ← label aan, beide variabel
#5 9-10j 28-32kg          ← label uit, beide variabel
```

---

## Database Velden

### judokas tabel

| Veld | Inhoud | Voorbeeld |
|------|--------|-----------|
| `leeftijdsklasse` | Label uit config (weergave) | "Mini's", "U11 Heren" |
| `categorie_key` | Config array key (lookup) | "minis", "u11_h" |
| `sort_categorie` | Volgorde (0, 1, 2...) | 0, 1, 2 |
| `sort_gewicht` | Gewicht in grammen | 30500 (= 30.5kg) |
| `sort_band` | Band niveau (1-7) | 3 (= oranje) |

### poules tabel

| Veld | Inhoud | Voorbeeld |
|------|--------|-----------|
| `leeftijdsklasse` | Label (weergave) | "U7", "U11 Jongens" |
| `gewichtsklasse` | Klasse of range | "-24" of "24-27kg" |
| `categorie_key` | Config array key (lookup) | "u7", "u11_h" |

### categorie_key uitleg

De `categorie_key` is de directe link naar de gewichtsklassen config:

```php
// Config in toernooien.gewichtsklassen
'u7' => ['label' => 'U7', 'max_leeftijd' => 6, ...],
'u11_h' => ['label' => 'U11 Jongens', 'max_leeftijd' => 10, ...],

// Lookup via CategorieClassifier
$config = $classifier->getConfigVoorPoule($poule);
// Gebruikt $poule->categorie_key om juiste config te vinden
```

**Belangrijk:**
- `leeftijdsklasse` = label, alleen voor weergave
- `categorie_key` = array key, voor config lookup
- Nooit zoeken op label! Labels kunnen wijzigen.

### Band Niveaus

| Band | Niveau |
|------|--------|
| wit | 1 |
| geel | 2 |
| oranje | 3 |
| groen | 4 |
| blauw | 5 |
| bruin | 6 |
| zwart | 7 |

---

## Services

### CategorieClassifier

Dedicated class voor categorie-herkenning op basis van harde criteria.

**Waarom een aparte class?**
- Classificatielogica op één plek (niet verspreid over services)
- Makkelijk te testen (unit tests)
- Duidelijke verantwoordelijkheid

**Harde criteria voor categorie-identificatie:**

| Criterium | Niveau | Voorbeeld |
|-----------|--------|-----------|
| `max_leeftijd` | Categorie | U7 = max 6 jaar |
| `geslacht` | Categorie | M / V / gemengd |
| `band_filter` | Categorie | tm_oranje, vanaf_groen |
| `gewichtsklassen` | Categorie (bij vast) | [-21, -24, -27, ...] |

**NIET voor categorie-identificatie (poule-niveau):**

| Criterium | Niveau | Gebruik |
|-----------|--------|---------|
| `max_kg_verschil` | Poule | Verdeling binnen categorie |
| `max_leeftijd_verschil` | Poule | Verdeling binnen categorie |

**Interface:**

```php
class CategorieClassifier
{
    public function __construct(array $gewichtsklassenConfig);

    // Classificeer judoka naar categorie
    public function classificeer(Judoka $judoka): ?CategorieResultaat;

    // Haal config op voor een poule (op basis van opgeslagen categorie_key)
    public function getConfigVoorPoule(Poule $poule): ?array;

    // Check of categorie dynamisch is (max_kg_verschil > 0)
    public function isDynamisch(string $categorieKey): bool;
}
```

**CategorieResultaat:**

```php
[
    'key' => 'u7',                    // Config array key
    'label' => 'U7',                  // Weergavenaam
    'sortCategorie' => 0,             // Sorteervolgorde
    'gewichtsklasse' => '-24',        // Bij vast, anders null
    'isDynamisch' => true,            // max_kg_verschil > 0
]
```

**Locatie:** `app/Services/CategorieClassifier.php`

### PouleIndelingService

Hoofdservice voor poule-indeling:
- `herberkenKlassen()` - Categoriseert judoka's opnieuw (gebruikt CategorieClassifier)
- `genereerPoules()` - Maakt poules aan, roept Python solver aan per categorie
- `updateSorteerVelden()` - Update sorteervelden voor één judoka (gebruikt CategorieClassifier)
- `maakPouleTitel()` - Genereert titel

**Flow:**
1. `CategorieClassifier` → classificeert judoka's naar categorieën
2. `PouleIndelingService` → roept Python solver aan per categorie
3. `poule_solver.py` → maakt poules binnen die categorie

### Python Poule Solver (scripts/poule_solver.py)

**De solver doet ALLEEN poule-verdeling binnen een categorie:**

- **Classificatie**: Gebeurt via `CategorieClassifier` (niet in Python!)
- **Input**: Judoka's van één categorie + constraints (max_kg, max_leeftijd)
- **Output**: Optimale poule-indeling

**Input JSON (per categorie):**

```json
{
  "max_kg_verschil": 3,
  "max_leeftijd_verschil": 2,
  "poule_grootte_voorkeur": [5, 4, 6, 3],
  "judokas": [
    {"id": 1, "leeftijd": 6, "gewicht": 22.5, "band": 2, "club_id": 1},
    {"id": 2, "leeftijd": 6, "gewicht": 23.1, "band": 1, "club_id": 2}
  ]
}
```

**Output JSON:**

```json
{
  "success": true,
  "poules": [
    {
      "categorie_key": "u7",
      "label": "U7",
      "gewichtsklasse": "22-25kg",
      "judoka_ids": [1, 2, 5, 8, 12],
      "gewicht_range": 2.8,
      "leeftijd_range": 1
    }
  ],
  "statistieken": {
    "totaal_judokas": 50,
    "totaal_poules": 12,
    "orphans": 0
  }
}
```

**Voordelen van gecombineerde aanpak:**
- Eén optimalisatie-run over alle judoka's
- Classifier en verdeling in sync
- Python kan globaal optimaliseren (minder orphans)

**Locatie:** `scripts/poule_solver.py`

### DynamischeIndelingService (legacy)

Roept Python solver aan voor dynamische categorieën:
- `berekenIndeling()` - Wrapper rond Python solver
- `getEffectiefGewicht()` - Fallback: gewicht_gewogen → gewicht → gewichtsklasse

#### Algoritme Python Solver (Sliding Window)

```
INPUT:  Judoka's van 1 categorie + config (max_kg, max_lft, poule_grootte_voorkeur)
OUTPUT: Poules binnen constraints

STAP 1: LEEFTIJDSGROEP (sliding window)
────────────────────────────────────────
- Bepaal jongste leeftijd in categorie (bijv. 5 jaar)
- Maak groep: jongste t/m jongste + max_lft_verschil (bijv. 5-6 jarigen)
- Overblijvers uit vorige groep gaan MEE (zolang binnen lft range)

STAP 2: GEWICHTSRANGE (sliding window)
────────────────────────────────────────
- Sorteer leeftijdsgroep op gewicht
- Bepaal lichtste gewicht (bijv. 23kg)
- Maak range: lichtste t/m lichtste + max_kg_verschil (bijv. 23-26kg)

STAP 3: POULE MAKEN (1 poule per keer!)
────────────────────────────────────────
- Sorteer judoka's in gewichtsrange op band (laagste eerst: wit→geel→oranje...)
- Maak 1 poule van max ideale_grootte (eerste voorkeur, meestal 5)
- Geplaatste judoka's zijn "OP"
- NIET alle judoka's in range verdelen, maar 1 poule maken en dan checken!

STAP 4: HERHAAL (BELANGRIJK!)
────────────────────────────────────────
- NA elke poule: check of jongste leeftijd "op" is
- Jongste "op"? → Terug naar stap 1 (nieuwe leeftijdsgroep)
- Jongste niet "op"? → Terug naar stap 2 (nieuwe gewichtsrange)
- Nieuwe gewichtsrange = lichtste OVERBLIJVER + max_kg_verschil

ORPHAN ONTSTAAT ALLEEN ALS:
────────────────────────────────────────
- Geen match in huidige leeftijdsgroep
- EN kan niet mee naar volgende leeftijdsgroep (buiten max_lft_verschil)

⛔ NOOIT: gewichtsrange in poule overschrijden (max_kg_verschil is hard)
```

**Voorbeeld:**

```
Categorie "Jeugd", max_lft=1, max_kg=3

Judoka's: [5j/23kg, 5j/24kg, 5j/25kg, 5j/28kg, 6j/24kg, 6j/26kg, 7j/25kg]

Groep 5-6j, sorteer op gewicht:
  [5j/23kg, 5j/24kg, 6j/24kg, 5j/25kg, 6j/26kg, 5j/28kg]

Range 23-26kg, sorteer op band:
  → Poule 1: [5j/23kg, 5j/24kg, 6j/24kg, 5j/25kg, 6j/26kg]

Overblijvers: [5j/28kg]
Range 28-31kg: alleen 5j/28kg → te weinig

5-jarigen op → Nieuwe groep 6-7j
  Overblijver 5j/28kg kan NIET mee (5j niet in 6-7 range)
  → 5j/28kg = orphan

Groep 6-7j: [7j/25kg]
  → Poule 2 of merge poging
```

**Waarom dit beter werkt:**
- Judoka's van vergelijkbare leeftijd EN gewicht komen samen
- Band-sortering zorgt dat beginners bij beginners komen
- Sliding window voorkomt harde grenzen
- Overblijvers krijgen kans in volgende groep

### VariabeleBlokVerdelingService

Voor blokverdeling bij variabele categorieën:
- `genereerVarianten()` - Trial & error splits
- `groepeerInCategorieen()` - Dynamische headers

---

## Implementatie Status

### Voltooid

- [x] Database & UI (Fase 1)
- [x] Indeling algoritme (Fase 2)
- [x] Eigen presets
- [x] Drag & drop categorieën
- [x] Variabele blokverdeling
- [x] Live titel update bij drag & drop
- [x] Hardcoded categorieën opgeruimd

### Gepland

- [ ] **Python Poule Solver (Fase 3)** - zie hieronder
- [ ] UI varianten weergave (Fase 4)
- [ ] Unit tests (Fase 5)

---

## Fase 3: Python Poule Solver

### Waarom een solver?

**Probleem met huidige greedy aanpak:**

```
Sortering: leeftijd → gewicht

Poule 1: 6j, 25-28kg (grootte=3, orphan!)
...veel judoka's verder in lijst...
Judoka X: 7j, 26kg  ← past qua gewicht, maar staat ver weg
```

- Greedy kijkt alleen "vooruit" in gesorteerde lijst
- Mist goede matches die verder weg staan (andere leeftijd, zelfde gewicht)
- Resulteert in veel orphans en ongelijke poules

**Solver voordelen:**
- Bekijkt ALLE judoka's in categorie
- Zoekt optimale combinaties (niet alleen buren)
- Minimaliseert orphans, maximaliseert poules van 5

### Architectuur

```
┌─────────────────────────────────────────────────────────────────┐
│ FLOW                                                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PHP: PouleIndelingService                                      │
│    │                                                            │
│    ├─► Stap 1: Categoriseren (harde grenzen)                    │
│    │                                                            │
│    ├─► Stap 2-3: Sorteren & Groeperen                           │
│    │                                                            │
│    └─► Stap 4: Poules maken                                     │
│          │                                                      │
│          ▼                                                      │
│        ┌─────────────────────────────────────────┐              │
│        │ Python: poule_solver.py                 │              │
│        │                                         │              │
│        │ Input:  JSON met judoka's per categorie │              │
│        │ Output: JSON met optimale poules        │              │
│        │                                         │              │
│        │ Algoritme:                              │              │
│        │ 1. Score functie (orphans, grootte)     │              │
│        │ 2. Zoek beste combinaties               │              │
│        │ 3. Return poule-toewijzingen            │              │
│        └─────────────────────────────────────────┘              │
│          │                                                      │
│          ▼                                                      │
│  PHP: Sla poules op in database                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Input/Output Format

**Input (PHP → Python):**
```json
{
  "categorie": "U7",
  "max_kg_verschil": 3.0,
  "max_leeftijd_verschil": 2,
  "poule_grootte_voorkeur": [5, 4, 6, 3],
  "judokas": [
    {"id": 1, "leeftijd": 6, "gewicht": 25.5, "band": 2, "club_id": 10},
    {"id": 2, "leeftijd": 6, "gewicht": 26.0, "band": 1, "club_id": 11},
    ...
  ]
}
```

**Output (Python → PHP):**
```json
{
  "success": true,
  "poules": [
    {"judoka_ids": [1, 2, 5, 8, 12], "gewicht_range": 2.8, "leeftijd_range": 1},
    {"judoka_ids": [3, 4, 6, 9, 10], "gewicht_range": 2.5, "leeftijd_range": 2},
    ...
  ],
  "orphans": [15],
  "stats": {
    "totaal_judokas": 50,
    "totaal_poules": 10,
    "poules_van_5": 8,
    "poules_van_4": 1,
    "poules_van_3": 1,
    "orphans": 1
  }
}
```

### Score Functie

**BELANGRIJK:** Scores zijn NIET hardcoded! Ze komen uit de config.

```python
def bereken_grootte_penalty(grootte, poule_grootte_voorkeur):
    """
    Score op basis van poule_grootte_voorkeur uit config.

    Voorbeeld: poule_grootte_voorkeur = [5, 4, 6, 3]
    - Index 0 (5) = beste   → penalty 0
    - Index 1 (4) = goed    → penalty 5
    - Index 2 (6) = minder  → penalty 40
    - Index 3 (3) = slecht  → penalty 40
    - Niet in lijst (1,2)   → orphan penalty 70
    - Orphan (0 of alleen)  → penalty 100
    """
    if grootte <= 1:
        return 100  # Orphan

    if grootte in poule_grootte_voorkeur:
        index = poule_grootte_voorkeur.index(grootte)
        # Eerste voorkeur = 0, tweede = 5, rest = 40
        if index == 0:
            return 0
        elif index == 1:
            return 5
        else:
            return 40
    else:
        return 70  # Niet in voorkeurlijst (poule van 2, 7, 8, etc.)


def score_indeling(poules, config):
    """
    Lagere score = betere indeling
    Config bevat: poule_grootte_voorkeur = [5, 4, 6, 3]
    """
    score = 0
    voorkeur = config.get('poule_grootte_voorkeur', [5, 4, 6, 3])

    for poule in poules:
        grootte = len(poule)
        score += bereken_grootte_penalty(grootte, voorkeur)

    return score
```

**Standaard penalties (bij voorkeur [5, 4, 6, 3]):**

| Grootte | Penalty | Reden |
|---------|---------|-------|
| 5 | 0 | Eerste voorkeur |
| 4 | 5 | Tweede voorkeur |
| 6 | 40 | Derde voorkeur |
| 3 | 40 | Vierde voorkeur |
| 2 | 70 | Niet in voorkeur |
| 1 | 100 | Orphan |

### Algoritme Opties

| Optie | Beschrijving | Snelheid | Kwaliteit |
|-------|--------------|----------|-----------|
| **Greedy++** | Greedy + backtrack voor orphans | Snel | Goed |
| **Simulated Annealing** | Random swaps, accepteer soms slechter | Medium | Zeer goed |
| **OR-Tools CP** | Constraint Programming solver | Langzaam | Optimaal |

**Aanbeveling:** Start met Greedy++ (PHP vervanging), upgrade naar SA als nodig.

### Greedy++ Algoritme

```python
def greedy_plus_plus(judokas, max_kg, max_lft):
    """
    1. Sorteer op leeftijd → gewicht
    2. Maak poules greedy (zoals nu)
    3. NIEUW: Voor elke orphan/kleine poule:
       - Zoek in ALLE poules of orphan erbij past
       - Zoek in ALLE judoka's of er een swap mogelijk is
    """

    # Stap 1-2: Greedy basis
    poules = maak_poules_greedy(judokas, max_kg, max_lft)

    # Stap 3: Fix orphans
    for _ in range(MAX_ITERATIES):
        verbeterd = False

        # Probeer orphans toe te voegen aan bestaande poules
        for orphan in get_orphans(poules):
            for poule in poules:
                if kan_toevoegen(orphan, poule, max_kg, max_lft):
                    poule.append(orphan)
                    verbeterd = True
                    break

        # Probeer kleine poules samen te voegen
        for p1, p2 in combinaties(kleine_poules(poules)):
            if kan_samenvoegen(p1, p2, max_kg, max_lft):
                merge(p1, p2)
                verbeterd = True

        # Probeer swaps tussen poules
        for p1, p2 in combinaties(poules):
            if swap_verbetert(p1, p2, max_kg, max_lft):
                doe_swap(p1, p2)
                verbeterd = True

        if not verbeterd:
            break

    return poules
```

### Implementatie Stappen

1. **Python solver script** (`laravel/scripts/poule_solver.py`)
   - Input: JSON van stdin
   - Output: JSON naar stdout
   - Greedy++ algoritme

2. **PHP integratie** (`DynamischeIndelingService.php`)
   - `callPythonSolver($judokas, $config): array`
   - Fallback naar PHP greedy als Python faalt

3. **Tests**
   - Unit tests Python solver
   - Integratie test PHP ↔ Python

### Bestaand Experiment

Er is al een experiment: `Scripts/python/poule_solver_experiment.py`
- Test 3 algoritmes: GEWICHT>BAND, BAND>GEWICHT, LEEFTIJD>GEWICHT>BAND
- Kan als basis dienen voor productie solver

---

## Technische Details

### Automatische Geslacht Detectie

Als `geslacht` niet is ingevuld maar label bevat indicatie:

| Label bevat | Wordt |
|-------------|-------|
| "Dames", "Meisjes", "_d" | V |
| "Heren", "Jongens", "_h" | M |

**Let op:** Als `geslacht = 'gemengd'` expliciet, dan GEEN auto-detect.

### Gewicht Fallback

Prioriteit voor effectief gewicht:
1. `gewicht_gewogen` (na weging)
2. `gewicht` (ingeschreven)
3. `gewichtsklasse` (extract: "-38" → 38.0)

### Rode Poule Markering

Een poule is rood als grootte NIET in `poule_grootte_voorkeur`:
- Default [5, 4, 6, 3] → 1, 2, 7, 8+ zijn rood
- Lege poules (0) zijn blauw (verwijderbaar)

### Zoek Match - Handmatig Orphan Oplossen

Wanneer de solver een orphan niet kan plaatsen (past nergens binnen limieten),
kan de organisator handmatig een match zoeken met eventuele overschrijding.

**Activeren:** Rechtsklik op judoka → "🔍 Zoek match"

**Popup toont alle poules gesorteerd op compatibiliteit:**

```
┌──────────────────────────────────────────────────────────────────┐
│ Match voor: Jan de Vries (60kg, 8j)                         [X] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ ✅ Poule #65 Jeugd                                              │
│    Nu:  4 judoka's | 7-8j | 57-60kg                             │
│    Na:  5 judoka's | 7-8j | 57-60kg                             │
│                                                                  │
│ ⚠️ Poule #68 Jeugd                                  +2kg over  │
│    Nu:  3 judoka's | 8-9j | 55-58kg                             │
│    Na:  4 judoka's | 8-9j | 55-60kg  ← gewicht verandert        │
│                                                                  │
│ ❌ Poule #75 Jeugd                                  +7kg over  │
│    Nu:  4 judoka's | 9-10j | 50-53kg                            │
│    Na:  5 judoka's | 8-10j | 50-60kg ← leeftijd én gewicht      │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

**Per poule tonen:**
- Poule nummer + categorie
- **Nu:** huidige statistieken (aantal judoka's | leeftijd range | gewicht range)
- **Na:** statistieken na verplaatsing (wat verandert is zichtbaar)
- Status indicator:
  - ✅ Past binnen limieten
  - ⚠️ Kleine overschrijding (acceptabel)
  - ❌ Grote overschrijding (problematisch)

**Actie:** Klik op poule → judoka wordt direct verplaatst, popup sluit

**Sortering poules:**
1. Eerst: past binnen limiet (✅)
2. Dan: minste kg overschrijding (⚠️)
3. Laatst: grote overschrijding (❌)

**Backend endpoint:** `POST /poule/{toernooi}/zoek-match/{judoka}`

Response:
```json
{
  "judoka": { "id": 123, "naam": "Jan", "gewicht": 60, "leeftijd": 8 },
  "matches": [
    {
      "poule_id": 65,
      "poule_titel": "Poule #65 Jeugd",
      "huidige_judokas": 4,
      "huidige_leeftijd": "7-8j",
      "huidige_gewicht": "57-60kg",
      "nieuwe_judokas": 5,
      "nieuwe_leeftijd": "7-8j",
      "nieuwe_gewicht": "57-60kg",
      "kg_overschrijding": 0,
      "lft_overschrijding": 0,
      "status": "ok"
    },
    ...
  ]
}
```

---

## Wedstrijddag: Dynamisch Overpoulen

### TL;DR

```
┌─────────────────────────────────────────────────────────────────────┐
│ DYNAMISCH OVERPOULEN - SAMENVATTING                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ WANNEER: Na sluiten weging van een blok                             │
│                                                                     │
│ PROBLEEM: Poule gewichtsrange > max_kg_verschil (uit config)        │
│           Voorbeeld: poule 27-32kg = 5kg range, max = 3kg → ❌      │
│                                                                     │
│ DETECTIE: Voor elke poule in blok:                                  │
│           range = max(gewogen) - min(gewogen)                       │
│           if range > max_kg_verschil → problematisch                │
│                                                                     │
│ OPLOSSING: Verplaats lichtste OF zwaarste judoka via Zoek Match     │
│                                                                     │
│ DOELPOULES:                                                         │
│   • Zelfde blok → direct in poule plaatsen                          │
│   • Ander blok (weging open) → naar WACHTPOULE van dat blok         │
│   • Ander blok (weging gesloten) → pas dan in echte poule           │
│                                                                     │
│ WACHTPOULE: Parkeerplaats tot gewichten van doelblok bekend zijn    │
│             Judoka hoeft NIET opnieuw te wegen (gewicht al bekend)  │
│                                                                     │
│ NA VERPLAATSEN: Weegkaart + publieke pagina's updaten automatisch   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Flow Diagram

```
┌──────────────────┐
│ Weging gesloten  │
│ voor blok X      │
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Check alle poules│
│ in blok X        │
└────────┬─────────┘
         ▼
┌──────────────────┐     Nee      ┌──────────────────┐
│ range > max_kg?  │─────────────►│ Klaar, geen      │
│                  │              │ actie nodig      │
└────────┬─────────┘              └──────────────────┘
         │ Ja
         ▼
┌──────────────────┐
│ Markeer poule    │
│ als problematisch│
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Toon lichtste +  │
│ zwaarste judoka  │
│ met Zoek Match   │
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Org kiest judoka │
│ → Zoek Match     │
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Filter opties:   │
│ • Zelfde blok    │
│ • Andere blokken │
└────────┬─────────┘
         ▼
┌──────────────────┐     Zelfde      ┌──────────────────┐
│ Org kiest doel   │────────────────►│ Direct in poule  │
│                  │                 │ plaatsen         │
└────────┬─────────┘                 └──────────────────┘
         │ Ander blok
         ▼
┌──────────────────┐
│ Naar WACHTPOULE  │
│ van doelblok     │
│ (paarse kleur)   │
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Wacht tot weging │
│ doelblok sluit   │
└────────┬─────────┘
         ▼
┌──────────────────┐
│ Org plaatst in   │
│ echte poule      │
│ (gewichten known)│
└──────────────────┘
```

### Het Probleem

Bij **vaste categorieën** werkt overpoulen zo:
- Judoka te zwaar → uit poule → naar wachtruimte van juiste gewichtsklasse
- Wachtruimte bestaat per vaste categorie (-36kg, -40kg, etc.)

Bij **dynamische poules** is dit anders:
- Geen vaste gewichtsklassen = geen wachtruimtes
- Poules zijn gevormd op basis van werkelijke gewichten
- Na weging kunnen gewichten afwijken → poule range kan te groot worden

### Detectie: Wanneer is overpoulen nodig?

**Na sluiten weging** per blok:

1. **Herbereken min-max kg** per poule op basis van **gewogen gewichten**
2. **Check:** `(max_kg - min_kg) > max_kg_verschil` uit categorie config?
3. **Indien ja:** poule is problematisch → moet opgelost worden

**Voorbeeld:**
```
Poule #42 vóór weging:  28, 29, 30, 31 kg → range 3kg ✅ (max=3)
Poule #42 na weging:    27, 29, 30, 32 kg → range 5kg ❌ (max=3)
→ Probleem: 27kg of 32kg moet verplaatst worden
```

**Belangrijk:** Het gaat om de POULE range, niet om individuele judoka's!
- Als iedereen 1kg zwaarder is → range blijft gelijk → geen probleem
- Alleen als de spreiding te groot wordt → actie nodig

### Oplossing: Zoek Match voor Wedstrijddag

Hergebruik het Zoek Match systeem met extra beperkingen:

**Blok beperkingen voor doelpoule:**

| Blok situatie | Actie | Reden |
|---------------|-------|-------|
| **Zelfde blok** | Direct in poule | Gewichten al bekend |
| **Ander blok (weging open)** | Naar wachtpoule | Gewichten nog niet bekend |
| **Ander blok (weging gesloten)** | Direct in poule | Gewichten al bekend |

### Verschil Lege Poules: Vast vs Dynamisch

```
┌──────────────────────────────────────────────────────────────────┐
│ LEGE POULES OP WEDSTRIJDDAG                                       │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ VASTE CATEGORIEËN (max_kg_verschil = 0):                         │
│   • Lege poules WEL tonen op wedstrijddag                        │
│   • Reden: Wachtruimte per gewichtsklasse nodig                  │
│   • Voorbeeld: -36kg poule leeg → judoka uit -32kg kan erheen    │
│                                                                   │
│ DYNAMISCHE CATEGORIEËN (max_kg_verschil > 0):                    │
│   • Lege poules NIET tonen op wedstrijddag                       │
│   • Reden: We gebruiken wachtpoules per blok                     │
│   • Geen vaste gewichtsklassen = geen wachtruimtes nodig         │
│                                                                   │
├──────────────────────────────────────────────────────────────────┤
│ ⚠️  BELANGRIJK: LEGE POULES NOOIT OP MAT ZETTEN!                 │
│                                                                   │
│ Geldt voor BEIDE systemen (vast én dynamisch):                   │
│   • Lege poule = geen wedstrijden = niet op mat                  │
│   • Bij "naar zaaloverzicht": lege poules overslaan              │
│   • Mat interface toont alleen poules met judoka's               │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### Wachtruimte (per categorie)

```
┌──────────────────────────────────────────────────────────────────┐
│ WACHTRUIMTE                                                       │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ WAT:    Tijdelijke parkeerplaats per categorie (rechts van       │
│         poules in UI)                                             │
│                                                                   │
│ INHOUD: Judoka's die:                                             │
│         - Gewogen zijn                                            │
│         - Nog geen poule hebben OF uit poule gesleept zijn       │
│         - NIET afwezig zijn                                       │
│                                                                   │
│ GEBRUIK:                                                          │
│   1. Organisator sleept judoka UIT poule NAAR wachtruimte        │
│      (om poule gewichtsrange kloppend te maken)                  │
│   2. Organisator sleept judoka UIT wachtruimte NAAR poule        │
│      OF gebruikt "Zoek Match" om geschikte poule te vinden       │
│                                                                   │
│ AFWEZIGEN: Alleen zichtbaar in info tooltip (i) van poule        │
│            NIET in wachtruimte                                    │
│                                                                   │
│ KLEUR:   Oranje (border + achtergrond)                           │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

**Drag & Drop:**
- Van poule → wachtruimte: judoka wordt uit poule gehaald
- Van wachtruimte → poule: judoka wordt in poule geplaatst
- Bidirectioneel slepen mogelijk

### Judoka's met Afwijkend Gewicht

```
┌──────────────────────────────────────────────────────────────────┐
│ BELANGRIJK: AFWIJKEND GEWICHT ≠ AUTOMATISCH VERWIJDEREN          │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ Afwezig:           - Automatisch uit poule verwijderd            │
│                    - Alleen zichtbaar in info tooltip (i)        │
│                    - NIET in wachtruimte                          │
│                                                                   │
│ Afwijkend gewicht: - BLIJFT in poule staan                       │
│                    - Oranje markering (achtergrond + ⚠ icoon)    │
│                    - Organisator SLEEPT handmatig naar           │
│                      wachtruimte indien gewenst                   │
│                                                                   │
│ Flow bij te grote range:                                          │
│   1. Poule wordt rood gemarkeerd (range > max_kg_verschil)       │
│   2. Org ziet lichtste (blauw) en zwaarste (rood) judoka         │
│   3. Org sleept gewenste judoka naar wachtruimte                 │
│   4. Org sleept vanuit wachtruimte naar andere poule             │
│      OF gebruikt "Zoek Match" knop                               │
│                                                                   │
│ Telling:           Afwijkend gewicht telt WEL mee voor:          │
│                    - Poule grootte                               │
│                    - Aantal wedstrijden                          │
│                    - Min/max gewicht range berekening            │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### UI: Problematische Poules na Weging

Op **Wedstrijddag Poules** pagina:

```
┌──────────────────────────────────────────────────────────────────┐
│ ⚠️ Poule #42 Jeugd 9-10j                         Range: 5kg ❌  │
│    Huidige judoka's: 27-32kg (max toegestaan: 3kg)              │
│                                                                  │
│    [Toon details ▼]                                             │
│                                                                  │
│    27kg - Piet Jansen      [🔍 Zoek match] ← lichtste           │
│    29kg - Jan de Vries                                          │
│    30kg - Kees Bakker                                           │
│    32kg - Tom Smit         [🔍 Zoek match] ← zwaarste           │
│                                                                  │
│    💡 Verplaats de lichtste of zwaarste om range te verkleinen  │
└──────────────────────────────────────────────────────────────────┘
```

**Weergave:**
- Markeer poules waar range > max_kg_verschil
- Toon huidige range en max toegestaan
- Highlight lichtste EN zwaarste judoka (organisator kiest)
- Zoek Match knop alleen bij lichtste en zwaarste

### Zoek Match Popup (Wedstrijddag variant)

Extra informatie t.o.v. voorbereiding:
- Blok van doelpoule tonen
- Beschikbaarheid indicator (blok status)
- Sortering: zelfde blok eerst, dan volgend, dan vorig

```
┌──────────────────────────────────────────────────────────────────┐
│ Match voor: Piet Jansen (27kg, 9j)                          [X] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ 🟢 BLOK 2 (huidig blok)                                         │
│ ─────────────────────────────────────────────────────────────── │
│ ✅ Poule #38 Jeugd                                              │
│    Nu:  4 judoka's | 9-10j | 26-28kg                            │
│    Na:  5 judoka's | 9-10j | 26-28kg                            │
│                                                                  │
│ 🔵 BLOK 3 (volgend blok)                                        │
│ ─────────────────────────────────────────────────────────────── │
│ ⚠️ Poule #55 Jeugd                                   +1kg over  │
│    Nu:  3 judoka's | 8-9j | 24-26kg                             │
│    Na:  4 judoka's | 8-9j | 24-27kg                             │
│                                                                  │
│ 🟡 BLOK 1 (vorig blok - weging nog open)                        │
│ ─────────────────────────────────────────────────────────────── │
│ ✅ Poule #12 Jeugd                                              │
│    Nu:  4 judoka's | 9j | 26-29kg                               │
│    Na:  5 judoka's | 9j | 26-29kg                               │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Nieuwe Poule Maken

Als geen geschikte match:
- Organisator kan nieuwe poule aanmaken
- Nieuwe poule komt in zelfde blok (of kies blok)
- **Let op:** Lege poules niet op mat zetten!

### Implementatie Stappen

1. **Detectie problematische poules** ✅
   - Na `sluitWeging()`: check alle poules in blok
   - Bereken range op basis van gewogen gewichten
   - Markeer poules waar range > max_kg_verschil

2. **UI aanpassing Wedstrijddag Poules** ✅
   - Toon problematische poules met waarschuwing
   - Zoek Match knop bij lichtste/zwaarste judoka
   - Blok status indicator

3. **Zoek Match uitbreiden** ✅
   - Parameter: `wedstrijddag=true` voor extra blok-filtering
   - Groepeer resultaten per blok
   - Check blok status (gestart/weging open/gesloten)

4. **Validatie bij verplaatsen** ✅
   - Check of doelblok beschikbaar is
   - Blokkeer verplaatsen naar eerder blok met gesloten weging

5. **Wachtruimte bidirectioneel** 🚧 TODO
   - Drag van poule → wachtruimte (judoka uit poule halen)
   - Drag van wachtruimte → poule (judoka in poule plaatsen)
   - Zoek Match vanuit wachtruimte
   - Afwezigen alleen in info tooltip (i), niet in wachtruimte

6. **Data updates na verplaatsen** ✅
   - **Weegkaarten:** Dynamisch, blok/mat info update automatisch
   - **Publieke pagina's:** Deelnemer zoeken, poule overzichten, etc. tonen actuele data
   - **QR-code:** Blijft zelfde (gebaseerd op judoka ID, niet poule)
   - Alle views lezen live uit database → geen cache invalidatie nodig

---

## Legacy

De `App\Enums\Leeftijdsklasse` enum is **deprecated**:
- Bevat hardcoded JBN2025 categorieën
- Wordt niet meer gebruikt
- Nieuwe code moet preset config gebruiken
