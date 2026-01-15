# Planning: Dynamische Poule Indeling

> **Status:** Fase 1-2 voltooid, Fase 3-4 gepland
> **Laatst bijgewerkt:** 15 jan 2026

## Kernbegrippen

### Categoriseren vs Sorteren (BELANGRIJK!)

Dit onderscheid is cruciaal voor het hele systeem:

| Concept | Betekenis | Wanneer |
|---------|-----------|---------|
| **Categoriseren** | Judoka toewijzen aan een categorie | EERST - check ALLE harde criteria |
| **Sorteren** | Volgorde bepalen binnen de groep | DAARNA - binnen 1 categorie |

**Categoriseren** = Welke groep?
- Judoka moet voldoen aan ALLE criteria van een categorie
- Eerste match (van jong naar oud) = zijn categorie
- Harde criteria: max_leeftijd, geslacht, band_filter, gewichtsklasse

**Sorteren** = Welke volgorde binnen de groep?
- Pas NADAT judoka in categorie is geplaatst
- Bepaalt alleen volgorde, niet de groep
- Zachte criteria: prioriteit van leeftijd/gewicht/band

---

## Algoritme Overzicht

```
┌─────────────────────────────────────────────────────────────────┐
│ POULE INDELING ALGORITME (4 stappen)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ STAP 1: CATEGORISEREN                                           │
│   Per judoka → check welke categorie past                       │
│   Criteria (ALLEMAAL moeten matchen):                           │
│   • leeftijd ≤ max_leeftijd                                     │
│   • geslacht = M/V/Gemengd                                      │
│   • band voldoet aan band_filter (als gezet)                    │
│   • gewicht past in gewichtsklasse (vast) of max_kg_verschil   │
│                                                                 │
│   Categorieen worden doorlopen van jong→oud.                    │
│   Eerste match = judoka's categorie.                            │
│                                                                 │
│ STAP 2: GROEPEREN                                               │
│   Alle judoka's in dezelfde categorie = 1 groep                 │
│   Dit zijn de kandidaten voor poules binnen deze categorie      │
│                                                                 │
│ STAP 3: SORTEREN (binnen de groep)                              │
│   Sorteer volgens verdeling_prioriteiten instelling:            │
│   • Leeftijd: jong → oud                                        │
│   • Gewicht: licht → zwaar                                      │
│   • Band: laag → hoog (wit → zwart)                             │
│                                                                 │
│ STAP 4: POULES MAKEN                                            │
│   Gesorteerde groep verdelen in poules                          │
│   Voorkeur: [5, 4, 6, 3] (of andere instelling)                 │
│   Voorbeeld: 20 judoka's → 4 poules van 5                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Harde vs Zachte Criteria

### Harde Criteria (categorie niveau - worden NOOIT overschreden)

| Criterium | Voorbeeld | Waar ingesteld |
|-----------|-----------|----------------|
| `max_leeftijd` | U11 = max 10 jaar | Per categorie |
| `geslacht` | M / V / Gemengd | Per categorie |
| `band_filter` | t/m oranje, vanaf groen | Per categorie (optioneel) |
| `gewichtsklassen` | -24kg, -27kg | Per categorie (bij vast) |
| `max_kg_verschil` | Max 3 kg in poule | Per categorie (bij variabel) |
| `max_leeftijd_verschil` | Max 2 jaar in poule | Per categorie |

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

| Klasse | Leeftijd | Opmerking |
|--------|----------|-----------|
| Mini's | tot 8 jaar | 2 jaar range (7-8) |
| Pupillen A | tot 10 jaar | 2 jaar range (9-10) |
| Pupillen B | tot 12 jaar | 2 jaar range (11-12) |
| U15 | tot 15 jaar | 2 jaar range (13-14) |
| U18 | tot 18 jaar | 3 jaar range (15-17) |
| Senioren | 18+ | |

**Let op:** JBN gebruikt "tot" (exclusief), niet "t/m" (inclusief).

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
