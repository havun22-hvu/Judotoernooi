# Planning: Dynamische Poule Indeling

> **Status:** Fase 1-2 voltooid, Fase 3-4 gepland
> **Laatst bijgewerkt:** 15 jan 2026

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
| `poule_grootte_voorkeur` | [5, 4, 6, 3] | Ideale poule groottes |
| `clubspreiding` | aan/uit | Probeer clubs te verdelen |

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
| `leeftijdsklasse` | Label uit config | "Mini's", "U11 Heren" |
| `categorie_key` | Config key | "minis", "u11_h" |
| `sort_categorie` | Volgorde (0, 1, 2...) | 0, 1, 2 |
| `sort_gewicht` | Gewicht in grammen | 30500 (= 30.5kg) |
| `sort_band` | Band niveau (1-7) | 3 (= oranje) |

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

### PouleIndelingService

Hoofdservice voor poule-indeling:
- `herberkenKlassen()` - Categoriseert judoka's opnieuw
- `genereerPoules()` - Maakt poules aan
- `maakPouleTitel()` - Genereert titel

### DynamischeIndelingService

Voor variabele categorieën (max_kg_verschil > 0):
- `berekenIndeling()` - Optimale groepering
- `getEffectiefGewicht()` - Fallback: gewicht_gewogen → gewicht → gewichtsklasse

#### Algoritme `berekenIndeling()` (simpel & effectief)

```php
// Input: judokas (gesorteerd), maxKgVerschil, maxLeeftijdVerschil (uit config!)
// Output: array van poules (elk max 5 judoka's, binnen limieten)

1. Start lege poule
2. Voor elke judoka:
   - Bereken nieuw gewicht_range als judoka toegevoegd wordt
   - Bereken nieuw leeftijd_range als judoka toegevoegd wordt
   - ALS gewicht_range ≤ maxKgVerschil
     EN leeftijd_range ≤ maxLeeftijdVerschil
     EN poule.count < 5:
       → Voeg toe
   - ANDERS:
       → Sla huidige poule op
       → Start nieuwe poule met deze judoka
3. Sla laatste poule op
4. Merge kleine poules (< 4 judoka's) met buren als binnen limieten
```

**Waarom dit werkt:**
- Judoka's zijn al gesorteerd (stap 3), dus buren liggen dicht bij elkaar
- Direct poules van 5 maken → geen complexe herverdeling nodig
- Simpele code, voorspelbaar resultaat

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

- [ ] UI varianten weergave (Fase 3)
- [ ] Unit tests (Fase 4)

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

---

## Legacy

De `App\Enums\Leeftijdsklasse` enum is **deprecated**:
- Bevat hardcoded JBN2025 categorieën
- Wordt niet meer gebruikt
- Nieuwe code moet preset config gebruiken
