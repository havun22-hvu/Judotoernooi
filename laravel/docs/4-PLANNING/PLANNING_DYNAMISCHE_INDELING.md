# Planning: Dynamische Poule Indeling

> **Status:** In ontwikkeling (Fase 1-2 voltooid, Fase 3-4 gepland)
> **Doel:** Flexibele indeling op basis van gewichtsverschil i.p.v. vaste gewichtsklassen

## Overzicht

Nieuw indelingssysteem waarbij de organisator per leeftijdsgroep kan kiezen tussen:
1. **Vaste gewichtsklassen** (huidige systeem, JBN normen)
2. **Dynamische indeling** (nieuw, op basis van max kg verschil)

## JBN Leeftijdsklassen (referentie)

| Klasse | Leeftijd | Opmerking |
|--------|----------|-----------|
| Mini's | tot 8 jaar | 2 jaar range (7-8) |
| Pupillen A | tot 10 jaar | 2 jaar range (9-10) |
| Pupillen B | tot 12 jaar | 2 jaar range (11-12) |
| U15 | tot 15 jaar | 2 jaar range (13-14) |
| U18 | tot 18 jaar | 3 jaar range (15-17) |
| Senioren | 18+ | |

**Let op:** JBN gebruikt "tot" (exclusief), niet "t/m" (inclusief).

## UI: Categorieën Instelling (NIEUW - jan 2026)

### Hoofdkeuze Bovenaan
```
┌─────────────────────────────────────────────────────────────────┐
│ Categorieën Instelling                                          │
│                                                                 │
│ [○ Geen standaard] [○ JBN 2025] [● JBN 2026] [Preset ▼] [Save] │
└─────────────────────────────────────────────────────────────────┘
```

Drie keuzes:
1. **Geen standaard** - Leeg starten, zelf categorieën opbouwen
2. **JBN 2025** - Officiële JBN 2025 regels (vaste gewichtsklassen)
3. **JBN 2026** - Officiële JBN 2026 regels (vaste gewichtsklassen)

### Sorteer Prioriteit (ALTIJD zichtbaar)

De sorteer prioriteit wordt altijd getoond, ongeacht de preset keuze.
Dit bepaalt de volgorde waarin judokas over poules worden verdeeld binnen een categorie.

```
┌─────────────────────────────────────────────────────────────────┐
│ Sorteer prioriteit: (i)                                         │
│ [1. Band] [2. Gewicht] [3. Groepsgrootte] [4. Club]            │
│ (sleep om te wisselen)                                          │
└─────────────────────────────────────────────────────────────────┘
```

**Belangrijke gedrag:**
- **Band eerst:** Witte banden vullen eerst de poules, dan gele, etc.
- **Gewicht eerst:** Lichtste judoka's eerst in de poules
- Harde criteria (leeftijd, geslacht, gewichtsklasse) blijven ALTIJD gerespecteerd

### Bij "GEEN STANDAARD"

```
┌─────────────────────────────────────────────────────────────────┐
│                        (leeg)                                   │
│                                                                 │
│ [+ Categorie toevoegen]                                         │
└─────────────────────────────────────────────────────────────────┘
```

**Na toevoegen van een categorie:**
```
┌─────────────────────────────────────────────────────────────────┐
│ ≡ Naam: [        ]  Max leeftijd: [  ] jaar                    │
│   Geslacht: [Gemengd ▼]  Systeem: [Poules ▼]              [×]  │
│   Δkg: [0]   Δlft: [0]   Band: [Alle ▼]                        │
│                                                                 │
│ [+ Categorie toevoegen]                                         │
└─────────────────────────────────────────────────────────────────┘
```

**Velden per categorie:**
| Veld | Type | Default | Beschrijving |
|------|------|---------|--------------|
| Naam | text | - | Label voor deze categorie (bijv. "Mini's", "Jeugd") |
| Max leeftijd | number | - | Leeftijdsgrens categorie (exclusief) |
| Geslacht | select | Gemengd | Gemengd / M / V |
| Systeem | select | Poules | Poules / Poules+Kruisfinale / Eliminatie |
| Δkg (max kg verschil) | number | 0 | HARDE limiet gewichtsverschil in poule |
| Δlft (max leeftijd verschil) | number | 0 | HARDE limiet leeftijdsverschil in poule (zie onder) |
| Band filter | select | Alle | Beginners/gevorderden scheiding (zie hieronder) |
| Gewichtsklassen | text | - | Vaste klassen (alleen als Δkg = 0) |

**Max leeftijd verschil (Δlft) uitleg:**
| Waarde | Betekenis |
|--------|-----------|
| 0 | Gebruik categorie limiet (max_leeftijd bepaalt de groep) |
| 1 | Max 1 jaar verschil binnen poule (flexibeler) |
| 2 | Max 2 jaar verschil binnen poule |

**Voorbeeld:** Categorie "Jeugd" met max_leeftijd=12 en Δlft=1:
- Judoka's van 9, 10, 11 jaar komen in deze categorie
- Maar in één poule mogen alleen judoka's met max 1 jaar verschil (bijv. 10+11, niet 9+11)

**Band filter opties:**
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
│ • t/m blauw          ← wit t/m blauw                            │
│ • t/m bruin          ← wit t/m bruin                            │
│ ─────────────────────                                           │
│ • vanaf geel         ← geel en hoger                            │
│ • vanaf oranje       ← oranje en hoger                          │
│ • vanaf groen        ← groen en hoger (= gevorderden)           │
│ • vanaf blauw        ← blauw en hoger                           │
│ • vanaf bruin        ← bruin en hoger                           │
│ • vanaf zwart        ← alleen zwarte band                       │
└─────────────────────────────────────────────────────────────────┘
```

**Typisch gebruik OWFJ:**
- Mini's beginners: `t/m oranje`
- Mini's gevorderden: `vanaf groen`

### Bij "JBN 2025" (vaste gewichtsklassen)

```
┌─────────────────────────────────────────────────────────────────┐
│ Vaste gewichtsklassen volgens JBN 2025 normen                   │
│ Sortering: op BAND binnen gewichtsklasse                        │
├─────────────────────────────────────────────────────────────────┤
│ Mini's (-8j):        -18, -21, -24, -27, -30, -34, +34 kg      │
│ Pupillen A (-10j):   -21, -24, -27, -30, -34, -38, +38 kg      │
│ Pupillen B (-12j):   -24, -27, -30, -34, -38, -42, +42 kg      │
│ Dames -15:           -32, -36, -40, -44, -48, -52, +52 kg      │
│ Heren -15:           -34, -38, -42, -46, -50, -55, +55 kg      │
│ ...                                                             │
└─────────────────────────────────────────────────────────────────┘
```

### Bij "JBN 2026" (dynamische gewichten + band scheiding)

> **Volledige docs:** `laravel/docs/5-REGLEMENT/JBN-REGLEMENT-2026.md`

JBN 2026 heeft **geen vaste gewichtsklassen** - alleen vaste leeftijdscategorieën.

```
┌─────────────────────────────────────────────────────────────────┐
│ Leeftijdscategorieën: U7, U9, U11, U13, U15 (vast)              │
│ Gewichtsklassen: GEEN (dynamisch op basis van gewicht)          │
├─────────────────────────────────────────────────────────────────┤
│ Per categorie instelbaar:                                       │
│ • Max kg verschil: [3] kg                                       │
│ • t/m band: [Oranje ▼]  ← maakt 2 groepen                      │
├─────────────────────────────────────────────────────────────────┤
│ Band scheiding creëert 2 groepen:                               │
│ • Beginners: t/m geselecteerde band (bijv. wit t/m oranje)     │
│ • Gevorderden: hoger dan geselecteerde band (bijv. groen+)     │
├─────────────────────────────────────────────────────────────────┤
│ Algoritme:                                                      │
│ 1. Splits op band (t/m oranje vs groen+)                       │
│ 2. Sorteer op gewicht binnen band-groep                        │
│ 3. Maak poules van 5 (of 4)                                    │
└─────────────────────────────────────────────────────────────────┘
```

**Verschil presets:**
| Aspect | JBN 2025 | JBN 2026 | Geen standaard |
|--------|----------|----------|----------------|
| Leeftijdsgroepen | Vast (oud) | Vast (nieuw: -7, -9, etc.) | Zelf invullen |
| Gewichtsklassen | Vast (-18, -21, etc.) | **Geen** (dynamisch) | Dynamisch |
| Band scheiding | Nee | **Ja** (instelbaar per cat.) | Nee |
| Sortering | Op band binnen klasse | Op gewicht binnen band-groep | Op prioriteit |
| Max leeftijd verschil | Nodig | **Niet nodig** | Nodig |
| Geslacht | Volgens JBN | Volgens JBN | Zelf kiezen |

### Eigen Presets
Organisator kan huidige configuratie opslaan als eigen preset:
- Klik **Opslaan** → voer naam in
- Preset wordt opgeslagen bij de organisator
- Later laden via dropdown **Preset**

**Database:** `gewichtsklassen_presets` tabel
```
id, organisator_id, naam, configuratie (JSON), timestamps
unique: [organisator_id, naam]
```

## Classificatie Systeem

### Presets

| Preset | Opslag |
|--------|--------|
| **JBN 2025** | Hardcoded in PHP (`Toernooi::getJbn2025Gewichtsklassen()`) |
| **JBN 2026** | Hardcoded in PHP (`Toernooi::getJbn2026Gewichtsklassen()`) |
| **Eigen presets** | Database (`gewichtsklassen_presets` tabel) |

De code volgt de gekozen/actieve preset.

### Harde Criteria (worden NOOIT overschreden)

**Categorie niveau** (bepaalt in welke categorie een judoka valt):

| Criterium | Voorbeeld |
|-----------|-----------|
| `max_leeftijd` | U11 = max 10 jaar |
| `geslacht` | M / V / Gemengd |
| `band_filter` | t/m oranje, vanaf groen |
| `gewichtsklassen` | -24kg, -27kg (bij vaste klassen) |

**Matching:** Categorieën worden doorlopen van jong→oud. Eerste categorie waar judoka aan alle criteria voldoet = zijn categorie.

**Poule niveau** (bepaalt met wie een judoka in een poule mag):

| Criterium | Voorbeeld |
|-----------|-----------|
| `max_kg_verschil` | Max 3 kg verschil binnen poule |
| `max_leeftijd_verschil` | Max 1 jaar verschil binnen poule |

### Zachte Criteria (prioriteiten)

Gelden **alleen** bij grote aantallen binnen een categorie, wanneer poules op meerdere manieren samengesteld kunnen worden. Dan bepalen de prioriteiten de optimale verdeling:

- Gewicht
- Band
- Groepsgrootte
- Clubspreiding

### Opslag

**judokas tabel:**

| Veld | Inhoud | Voorbeeld |
|------|--------|-----------|
| `leeftijdsklasse` | Label uit preset config | "Mini's", "U11 Heren" |
| `categorie_key` | Config key voor lookup | "minis", "u11_h" |
| `sort_categorie` | Volgorde uit config (0, 1, 2, ...) | 0, 1, 2 |
| `sort_gewicht` | Gewicht in grammen | 30500 (= 30.5kg) |
| `sort_band` | Band niveau (1=wit, ..., 7=zwart) | 3 (= oranje) |

**Sortering:**

De volgorde van `sort_gewicht` en `sort_band` is afhankelijk van `verdeling_prioriteiten`:

```sql
-- Als 'band' voor 'gewicht' in prioriteiten:
ORDER BY sort_categorie ASC, sort_band ASC, sort_gewicht ASC

-- Als 'gewicht' voor 'band' in prioriteiten (default):
ORDER BY sort_categorie ASC, sort_gewicht ASC, sort_band ASC
```

**Band niveaus:**
| Band | Niveau |
|------|--------|
| wit | 1 |
| geel | 2 |
| oranje | 3 |
| groen | 4 |
| blauw | 5 |
| bruin | 6 |
| zwart | 7 |

### Legacy

De `App\Enums\Leeftijdsklasse` enum is **deprecated**.
- Bevat hardcoded JBN2025 categorieën
- Wordt niet meer gebruikt voor classificatie
- Nieuwe code moet `toernooi->gewichtsklassen` (uit preset) gebruiken
- `judoka_code` veld is deprecated, gebruik sorteer velden

## Sorteer Prioriteit (bij dynamische indeling)

Bij categorieën met grote aantallen (bijv. 30 judoka's in 8-9 jaar, 34-36 kg)
bepaalt de prioriteit hoe judoka's worden gegroepeerd:

| Prioriteit | Betekenis |
|------------|-----------|
| 1. Gewicht | Eerst groeperen zodat gewichtsverschil ≤ max kg |
| 2. Band | Lagere banden bij elkaar, hogere bij elkaar (wit ≠ bruin) |
| 3. Groepsgrootte | Optimaliseren voor ideale poule grootte (4-5 judoka's) |
| 4. Club | Clubspreiding (vermijd 2 van zelfde club in 1 poule) |

**Voorbeelden:**
- Gewicht > Band: Eerst op gewicht groeperen, dan band als secundaire factor
- Band > Gewicht: Eerst op band groeperen (wit bij wit), dan gewicht

## Algoritme: Dynamische Indeling

### Samenvatting (TL;DR)

```
┌─────────────────────────────────────────────────────────────────┐
│ POULE INDELING ALGORITME                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ STAP 1: HARDE SELECTIE (per categorie)                          │
│   Judoka moet voldoen aan ALLE categorie-criteria:              │
│   • Max leeftijd (U9 = max 8 jaar, U11 = max 10 jaar)           │
│   • Geslacht (M / V / Gemengd)                                  │
│   • Band filter (t/m oranje, vanaf groen, etc.)                 │
│   • Gewichtsklasse (bij vaste klassen: -34kg, -38kg)            │
│   • Max kg verschil per poule (bij dynamisch: Δkg)              │
│                                                                 │
│ STAP 2: SORTEREN (binnen geselecteerde groep)                   │
│   Op basis van prioriteit instelling:                           │
│   • Gewicht eerst → sorteer gewicht, dan band                   │
│   • Band eerst → sorteer band, dan gewicht                      │
│                                                                 │
│ STAP 3: VERDELEN IN POULES                                      │
│   Gesorteerde judoka's van boven naar beneden:                  │
│   • Vul poule tot max kg verschil bereikt zou worden            │
│   • Start nieuwe poule                                          │
│   • Ideale grootte: 4-5 per poule                               │
│                                                                 │
│ STAP 4: VALIDATIE                                               │
│   • Check: zijn alle judoka's ingedeeld?                        │
│   • Zo niet: categorie-configuratie is onvolledig               │
│   • Toon niet-ingedeelde judoka's met reden                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

U-CATEGORIE LEEFTIJDEN:
  U7 = max 6 jaar    U13 = max 12 jaar
  U9 = max 8 jaar    U15 = max 14 jaar
  U11 = max 10 jaar  U18 = max 17 jaar
```

### Gedetailleerde Uitleg

```
═══════════════════════════════════════════════════════════════════
HARDE LIMIETEN (instelbaar, daarna ABSOLUUT)
═══════════════════════════════════════════════════════════════════

De organisator stelt in:
- Max leeftijd verschil (default: 2 jaar)
- Max kg verschil (default: 3 kg)

Wat ingesteld wordt is een ABSOLUTE grens:
→ Judoka's die niet passen mogen NOOIT in dezelfde poule
→ Geen uitzonderingen, geen penalties - gewoon niet toegestaan
→ Sorteer prioriteiten veranderen alleen de VOLGORDE, niet de limieten

═══════════════════════════════════════════════════════════════════
VASTE HIËRARCHIE (veiligheid eerst!)
═══════════════════════════════════════════════════════════════════

1. GESLACHT    - M/V apart (indien niet gemengd)
2. LEEFTIJD    - Max [ingesteld] jaar verschil (HARD)
3. GEWICHT     - Max [ingesteld] kg verschil (HARD)
4. BAND        - Sortering voor eerlijke poules (ZACHT)

═══════════════════════════════════════════════════════════════════
BELANGRIJKE CONSTRAINT: LEEFTIJD
═══════════════════════════════════════════════════════════════════

Een 8-jarige mag NOOIT tegen een 12-jarige!
→ Max 2 jaar verschil is HARDE grens (net als JBN)
→ Dit geldt voor ALLE algoritmes

═══════════════════════════════════════════════════════════════════
TWEE OPTIES NA LEEFTIJDSGROEPERING
═══════════════════════════════════════════════════════════════════

Binnen een leeftijdsgroep (max 2 jaar verschil):

┌─────────────────────────────────────────────────────────────────┐
│ OPTIE 1: GEWICHT → BAND                                         │
├─────────────────────────────────────────────────────────────────┤
│ 1e: Groepering op gewicht (breekpunten bij >3 kg verschil)      │
│     → 30-33kg wordt 1 klasse                                    │
│ 2e: Binnen klasse sorteren op band                              │
│     → Beginners eerst, ervaren later                            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ OPTIE 2: BAND → GEWICHT                                         │
├─────────────────────────────────────────────────────────────────┤
│ 1e: Groepering op band (wit, geel, oranje, etc.)                │
│     → Alle witte banden = 1 klasse                              │
│ 2e: Binnen klasse sorteren op gewicht (+ max kg check!)         │
│     → Lichter eerst, zwaarder later                             │
└─────────────────────────────────────────────────────────────────┘

═══════════════════════════════════════════════════════════════════
VASTE GEWICHTSKLASSEN (bestaand systeem)
═══════════════════════════════════════════════════════════════════

Als max_kg_verschil = 0:
→ Gebruik vaste klassen (-30, -35, -40, etc.)
→ Binnen klasse sorteren op band
→ Dit is identiek aan dynamisch, alleen grenzen zijn vooraf bepaald
```

## Poulegrootte Verdeling

### Twee Instellingen

De organisator bepaalt **twee** dingen:

1. **`poule_grootte_voorkeur`** - Volgorde van voorkeur voor poule groottes
   - Bijv. `[5, 4, 3, 6]` = 5 beste, dan 4, dan 3, dan 6
   - Of `[5, 4, 6, 3]` = 5 beste, dan 4, dan 6, dan 3 (default)

2. **`verdeling_prioriteiten`** - Prioriteit tussen criteria
   - Bijv. `[groepsgrootte, gewicht, band, clubspreiding]`
   - Als groepsgrootte op 1 staat → strikt de voorkeur volgen
   - Als groepsgrootte op 4 staat → flexibeler voor andere criteria

### Voorkeur Volgorde (instelbaar)

| Positie | Penalty | Voorbeeld [5,4,3,6] | Voorbeeld [5,4,6,3] |
|---------|---------|---------------------|---------------------|
| 1e keus | 0 | 5 (ideaal) | 5 (ideaal) |
| 2e keus | laag | 4 (goed) | 4 (goed) |
| 3e keus | medium | 3 (acceptabel) | 6 (acceptabel) |
| 4e keus | hoog | 6 (liever niet) | 3 (liever niet) |

### Voorbeelden Verdeling

**Met voorkeur [5, 4, 3, 6]:**

| Aantal | Verdeling | Uitleg |
|--------|-----------|--------|
| 10 | [5, 5] | Perfect |
| 11 | [5, 3, 3] | Één 5 + twee 3's (beter dan 6+5) |
| 12 | [4, 4, 4] | Drie gelijke poules |
| 13 | [5, 4, 4] | Één 5, twee 4's |
| 14 | [5, 5, 4] | Twee 5's, één 4 |
| 15 | [5, 5, 5] | Perfect |
| 16 | [5, 5, 3, 3] | Twee 5's + twee 3's (beter dan 6+5+5) |
| 17 | [5, 4, 4, 4] | Één 5, drie 4's |
| 20 | [5, 5, 5, 5] | Perfect |

**Met voorkeur [5, 4, 6, 3] (default):**

| Aantal | Verdeling | Uitleg |
|--------|-----------|--------|
| 11 | [6, 5] | Één 6 + één 5 (6 voor 3 in voorkeur) |
| 16 | [6, 5, 5] | Één 6, twee 5's |

### Algoritme Samenvatting

```
STAP 1: PARTITIONERING (harde constraints)
══════════════════════════════════════════
- Splits op geslacht (indien niet gemengd)
- Splits op leeftijd (max X jaar verschil)
- Splits op gewicht (max Y kg verschil)
→ Resultaat: disjuncte gewichtsgroepen

STAP 2: POULEGROOTTE BEPALEN (per gewichtsgroep)
══════════════════════════════════════════
- Lees poule_grootte_voorkeur (bijv. [5,4,3,6])
- Bereken alle mogelijke verdelingen (3-6 per poule)
- Score elke verdeling op voorkeur
- Kies verdeling met laagste score

STAP 3: SORTERING (binnen harde constraints!)
══════════════════════════════════════════
Lees verdeling_prioriteiten:

  IF gewicht op positie 1:
    sort(judokas, gewicht ASC, band ASC)
    → Lichtste judoka's in eerste poule

  IF band op positie 1:
    sort(judokas, band ASC, gewicht ASC)
    → Lagere banden in eerste poule

⚠️ Sortering breekt NOOIT harde constraints!
   Alle judoka's in groep voldoen al aan max_kg en max_leeftijd.

STAP 4: VERDEEL OVER POULES
══════════════════════════════════════════
- Verdeel gesorteerde judoka's over poules
- Poule 1 = eerste N judoka's
- Poule 2 = volgende M judoka's
- etc.

STAP 5: CLUBSPREIDING (optimalisatie)
══════════════════════════════════════════
- Swap judoka's tussen poules indien:
  - Verbetert clubspreiding
  - Breekt geen harde constraints

STAP 6: VALIDATIE
══════════════════════════════════════════
- Check alle poules op gewichtslimiet
- Fix indien nodig (split/swap)
```

### Harde vs Zachte Constraints

| Type | Constraint | Breekbaar? |
|------|------------|------------|
| **HARD** | max_kg_verschil | Nee, nooit |
| **HARD** | max_leeftijd_verschil | Nee, nooit |
| **HARD** | Poulegrootte 3-6 | Nee, nooit |
| **HARD** | Geslacht (indien apart) | Nee, nooit |
| **ZACHT** | Poulegrootte voorkeur | Ja, via prioriteit |
| **ZACHT** | Band sortering | Ja, via prioriteit |
| **ZACHT** | Clubspreiding | Ja, best effort |

## Varianten Generatie (zoals Blokverdeling)

Net als bij de blokverdeling kunnen we meerdere indelingen berekenen en de beste presenteren:

```
┌─────────────────────────────────────────────────────────────────┐
│ POULE INDELING - VARIANTEN                                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Variant 1: Score 116.9  ✓ Beste                                 │
│   - Leeftijd max: 2 jaar                                        │
│   - Gewicht max: 3.0 kg                                         │
│   - 86 poules, 398 judoka's                                     │
│                                                                 │
│ Variant 2: Score 121.4                                          │
│   - Leeftijd max: 2 jaar                                        │
│   - Gewicht max: 2.5 kg                                         │
│   - 92 poules, 395 judoka's                                     │
│                                                                 │
│ Variant 3: Score 128.7                                          │
│   ...                                                           │
│                                                                 │
│ [Kies Variant 1]  [Kies Variant 2]  [Kies Variant 3]            │
└─────────────────────────────────────────────────────────────────┘
```

### Score Berekening

| Criterium | Gewicht | Max grens | Penalty bij overschrijding |
|-----------|---------|-----------|---------------------------|
| Leeftijd | 40% | 2 jaar | 10x (HARD) |
| Gewicht | 40% | 3 kg | 10x (HARD) |
| Band | 20% | 2 niveaus | 5x (ZACHT) |

**Lagere score = betere indeling**

## Testresultaten (400 judoka's)

```
                        │ GEWICHT>BAND │ BAND>GEWICHT │ LEEFTIJD>GEWICHT>BAND
────────────────────────┼──────────────┼──────────────┼──────────────────────
Leeftijd max            │     4j ✗     │     4j ✗     │     2j ✓
Gewicht max             │     3kg      │     3kg      │     3kg
Band max                │     4        │     0 ✓      │     4
────────────────────────┼──────────────┼──────────────┼──────────────────────
SCORE                   │   136.3      │   130.9      │   116.9 ✓
```

**Conclusie:** LEEFTIJD > GEWICHT > BAND is het beste algoritme:
- Geen leeftijdsoverschrijdingen (8j vs 12j onmogelijk)
- Beste totaalscore
- Bijna alle judoka's ingedeeld

## Implementatie Stappen

### Fase 1: Database & UI (5 jan 2026) ✓
- [x] Gewichtsklassen JSON structuur uitbreiden
- [x] UI aanpassen: geslacht dropdown per categorie (M/V/Gemengd)
- [x] UI aanpassen: max kg verschil input per categorie
- [x] Gewichtsklassen input verbergen als max_kg > 0
- [x] Controller updaten voor nieuwe velden
- [x] Eigen presets: migration + model
- [x] Eigen presets: controller endpoints (GET/POST/DELETE)
- [x] Eigen presets: UI dropdown + opslaan knop
- [x] Drag & drop voor categorieën
- [x] Verwijderd: "Jongens/meiden gescheiden" toggle (nu per categorie)
- [x] Toernooi-niveau: max_kg_verschil en max_leeftijd_verschil velden

### Fase 2: Indeling Algoritme (6 jan 2026) ✓
- [x] Nieuwe service: `DynamischeIndelingService`
- [x] Leeftijd-eerst algoritme implementeren
- [x] Varianten genereren (zoals blokverdeling)
- [x] Score berekening
- [x] Test command: `php artisan test:dynamische-indeling {aantal}`
- [x] Integreren met bestaande `PouleIndelingService`

### Integratie Details (Fase 2)

De `PouleIndelingService` detecteert automatisch wanneer dynamische indeling nodig is:

```php
// Per leeftijdsgroep: check of dynamische indeling geconfigureerd is
$usesDynamic = !$gebruikGewichtsklassen && $this->usesDynamicGrouping($leeftijdsklasse);

if ($usesDynamic) {
    // Gebruik DynamischeIndelingService voor deze groep
    $indeling = $this->dynamischeIndelingService->berekenIndeling($judokas, $maxLeeftijd, $maxKg);
    // Maak poules van de resultaten...
}
```

**Voorwaarden voor dynamische indeling:**
1. `gebruik_gewichtsklassen` = false (geen vaste klassen)
2. `max_kg_verschil` > 0 in de categorie config

**Geslacht per categorie:**
- Wordt nu uit de config gelezen per leeftijdsgroep
- `gemengd` = jongens en meisjes samen
- `M` of `V` = alleen dat geslacht in aparte groep

### Fase 3: UI Varianten
- [ ] Varianten weergave in poule-overzicht
- [ ] Kies variant functionaliteit
- [ ] Score visualisatie

### Fase 4: Testen
- [ ] Unit tests voor algoritme
- [ ] Test met 100, 400, 800 judoka's
- [ ] Edge cases: grote gaten, weinig judoka's

## Edge Cases

| Situatie | Oplossing |
|----------|-----------|
| Groep met 1-2 judoka's | Voeg toe aan dichtstbijzijnde groep |
| Alle judoka's binnen max kg | Eén grote groep, verdeel in poules |
| Geen judoka's in leeftijdsgroep | Skip |
| Te groot leeftijdsverschil | Forceer breekpunt |

## Poule Titels

Titels worden automatisch samengesteld bij het genereren van poules.

**De categorie naam uit Instellingen wordt overgenomen als basis voor de poule titel.**

| Type | Voorbeeld titel |
|------|-----------------|
| Voorronde | "Jeugd 9-10j 30-33kg" |
| Eliminatie | "Jeugd M 9-10j 30-33kg - Eliminatie" |
| Kruisfinale | "Kruisfinale Jeugd M -30kg (top 2)" |

### Dynamische placeholder: `lft-kg`

Bij "Geen standaard" categorieën kun je `lft-kg` als naam gebruiken. Dit wordt bij poule generatie automatisch vervangen door de actuele ranges:

| Categorie naam | Poule titel wordt |
|----------------|-------------------|
| `lft-kg` | "8-10j 30-35kg" |
| `Jeugd` | "Jeugd 8-10j 30-35kg" |
| `Beginners lft-kg` | "Beginners 8-10j 30-35kg" |

**Werking:**
- `lft-kg` wordt vervangen door `{min-max leeftijd}j {min-max gewicht}kg`
- Ranges worden berekend uit de judoka's in die specifieke poule
- Werkt ook in combinatie met andere tekst (bijv. "Beginners lft-kg")

**Onderdelen:**
- **Categorie naam** (uit Instellingen → Categorieën → Naam veld)
- Geslacht: M/V (alleen bij niet-gemengde categorieën)
- Leeftijd range: berekend uit judoka's (bijv. "9-10j")
- Gewicht range: berekend uit judoka's (bijv. "30-33kg")

**Let op:** Wijzig de categorie naam in Instellingen VOORDAT je poules genereert.

### Live titel update bij verslepen judoka's

Wanneer judoka's worden versleept tussen poules:

| Label type | Weergave | Bij verslepen |
|------------|----------|---------------|
| `lft-kg` | `#28 9-10j · 30-35kg` | Titel wordt automatisch bijgewerkt |
| Vaste naam | `#5 Jeugd (9-10j, 30-35kg)` | Alleen ranges tussen haakjes bijgewerkt |

**Dynamische titels (lft-kg):**
- Titel bevat " · " als scheidingsteken
- Bij verslepen: server berekent nieuwe ranges en update DB
- Client update titel in DOM

### Automatische blokverdeling (variabel systeem)

Bij "lft-kg" categorieën worden blokken automatisch ingedeeld:

**Algoritme:**
1. Bereken doel: `totaal_wedstrijden / aantal_blokken`
2. Sorteer alle poules op: MIN leeftijd → MIN gewicht
3. Loop door gesorteerde poules, tel wedstrijden op
4. Bij ~doel wedstrijden: zoek optimale split
   - Primair: leeftijdsgrens (bijv. 8-9j | 9-10j)
   - Secundair: gewichtsgrens binnen aangrenzende leeftijden
5. Trial & error op gewichtssplit tot optimale verdeling

**Bij leeftijdsgrens (bijv. 8-9j en 9-10j overlap):**
- Pak poules van beide aangrenzende leeftijden
- Sorteer op gewicht
- Probeer verschillende gewichtssplitpunten
- Kies split waar blok ~doel wedstrijden heeft
- Lichtere poules → blok N
- Zwaardere poules → blok N+1

**Categorie headers na berekening:**
- Per groep poules die samen zijn ingedeeld
- Naam = MIN-MAX leeftijd · MIN-MAX gewicht van alle poules in groep

**Voorbeeld:**
```
Blok 1:
├─ Categorie "8-9j · 20-30kg"          ← dynamische header
│  ├─ Poule: 8-9j · 20-23kg (12w)
│  ├─ Poule: 8-9j · 24-27kg (18w)
│  └─ Poule: 8-9j · 28-30kg (15w)

Blok 2:
├─ Categorie "8-10j · 28-35kg"         ← overlap door split
│  ├─ Poule: 8-9j · 31-35kg (10w)
│  ├─ Poule: 9-10j · 28-30kg (20w)
│  └─ Poule: 9-10j · 31-35kg (22w)
```

**Beperkingen:**
- Kruisfinales/KO niet beschikbaar bij variabel systeem
- Later toe te voegen: alleen bij grote homogene groepen (zelfde leeftijd+gewicht)

## Architectuur: Variabele Blokverdeling Service

### Beslissing (13 jan 2026)
**Nieuw bestand:** `app/Services/VariabeleBlokVerdelingService.php`

### Waarom apart bestand?

| Aspect | `BlokMatVerdelingService` | `VariabeleBlokVerdelingService` |
|--------|---------------------------|----------------------------------|
| **Groepering** | Per leeftijdsklasse label | Trial & error op gewichtssplit |
| **Headers** | Vaste labels (Mini's, U11) | Dynamisch: min-max lft · min-max kg |
| **Algoritme** | Categorie → blok toewijzen | Poules sorteren → splits zoeken |
| **Kruisfinales** | Ondersteund | Niet ondersteund |

### Delegatie patroon

```php
// BlokMatVerdelingService.php
public function genereerVarianten(Toernooi $toernooi, ...): array
{
    // Check of er variabele categorieën zijn (lft-kg labels)
    if ($this->heeftVariabeleCategorieen($toernooi)) {
        return app(VariabeleBlokVerdelingService::class)
            ->genereerVarianten($toernooi, $userVerdelingGewicht);
    }

    // Bestaande logica voor vaste categorieën
    // ...
}
```

### Gemengde scenario's

**Voorbeeld:** M en V apart + variabel binnen geslacht

1. Poule-aanmaak scheidt al op geslacht (harde constraint)
2. Poules krijgen labels: `V lft-kg` en `M lft-kg`
3. VariabeleBlokVerdelingService groepeert op label-prefix
4. Binnen groep: trial & error algoritme

**Voorbeeld:** Band-scheiding + variabel binnen bandgroep

1. Poule-aanmaak scheidt op band (t/m oranje vs vanaf groen)
2. Poules krijgen labels: `Beginners lft-kg` en `Gevorderden lft-kg`
3. VariabeleBlokVerdelingService groepeert op label-prefix
4. Binnen groep: trial & error algoritme

### Interface

```php
class VariabeleBlokVerdelingService
{
    /**
     * Genereer blokverdeling voor variabele categorieën
     *
     * @param Toernooi $toernooi
     * @param int $userVerdelingGewicht 0-100 (gewicht voor gelijke verdeling)
     * @return array ['varianten' => [...], 'huidige' => current state]
     */
    public function genereerVarianten(Toernooi $toernooi, int $userVerdelingGewicht = 50): array;

    /**
     * Groepeer poules in categorieën op basis van leeftijd/gewicht proximity
     *
     * @param Collection $poules Poules met lft-kg labels
     * @return Collection Gegroepeerde poules met dynamische headers
     */
    public function groepeerInCategorieen(Collection $poules): Collection;

    /**
     * Zoek optimale split op leeftijdsgrens
     *
     * @param Collection $poules Gesorteerd op leeftijd, gewicht
     * @param int $doelWedstrijden Gewenste wedstrijden per blok
     * @return array ['split_index' => int, 'split_type' => 'leeftijd'|'gewicht']
     */
    private function zoekOptimaleSplit(Collection $poules, int $doelWedstrijden): array;
}
```

### Implementatie stappen (13 jan 2026) ✓

1. [x] Maak `VariabeleBlokVerdelingService.php`
2. [x] Implementeer `genereerVarianten()` met trial & error
3. [x] Implementeer `berekenCategorieGroepen()` voor dynamische headers
4. [x] Update `BlokMatVerdelingService` met delegatie check
5. [ ] Test met gemengde scenario's (M/V + variabel)

### Technische details

**Key format:** `leeftijdsklasse|gewichtsklasse` (compatibel met bestaande view)

**Algoritme:**
1. Sorteer poules op MIN leeftijd → MIN gewicht
2. Groepeer per leeftijdsrange
3. Trial & error met 20 strategieën voor optimale splits
4. Bij leeftijdsgrens: zoek gewichtssplit binnen overlappende leeftijden

**Detectie variabele categorieën:**
```php
$toernooi->poules()
    ->where('titel', 'like', '%lft-kg%')
    ->orWhere('titel', 'like', '% · %')
    ->exists();
```

## Vereenvoudiging Instellingen (7 jan 2026)

### Probleem
Er waren twee overlappende instellingen:
1. `verdeling_prioriteiten` - drag & drop met groepsgrootte/bandkleur/clubspreiding
2. `judoka_code_volgorde` - gewicht_band of band_gewicht (bij groepen)

Dit was verwarrend voor gebruikers.

### Oplossing
**Verplaatsen:** drag & drop prioriteiten naar groepsindeling sectie

**Nieuwe UI bij groepsindeling (zonder gewichtsklassen):**
```
┌─────────────────────────────────────────────────────────────────┐
│ Zonder gewichtsklassen: Judoka's worden alleen per              │
│ leeftijdsgroep ingedeeld.                                       │
├─────────────────────────────────────────────────────────────────┤
│ Prioriteit: (sleep om te wisselen)                              │
│ [1. 🏋️ Gewicht] [2. 🥋 Band] [3. 👥 Groepsgrootte] [4. 🏠 Club] │
└─────────────────────────────────────────────────────────────────┘
```

**Reden:**
- Alle indelings-instellingen op één plek
- Verwijdert verwarring tussen twee aparte instellingen
- Drag & drop geeft flexibiliteit

### Implementatie (7 jan 2026) ✓
- [x] Verwijder `verdeling_prioriteiten` uit bovenste sectie (Poule instellingen)
- [x] Verplaats drag & drop naar groepsindeling sectie (bij "Zonder gewichtsklassen")
- [x] Voeg "Gewicht" toe als prioriteit item (vervangt `judoka_code_volgorde`)
- [x] Update PouleIndelingService: lees volgorde uit `verdeling_prioriteiten`
- [x] Verwijder `judoka_code_volgorde` radio buttons (niet meer nodig)

**Nieuwe prioriteit keys:** `gewicht`, `band`, `groepsgrootte`, `clubspreiding`
**Oude keys (deprecated):** `bandkleur` → `band`
**Info popup:** (i) icoon met uitleg over sorteer volgorde

### Drag & Drop Poule Statistieken (7 jan 2026) ✓
Bij verslepen van judoka's tussen poules worden nu ook bijgewerkt:
- [x] Aantal judoka's
- [x] Aantal wedstrijden
- [x] Min-max leeftijd
- [x] Min-max gewicht

### Bugfix: Clubspreiding respecteert prioriteiten (8 jan 2026) ✓
**Probleem:** Bij clubspreiding werden judoka's met groot gewichtsverschil (20kg vs 26kg)
door elkaar gehusseld, ook als gewicht prioriteit 1 had.

**Oorzaak:** `pasClubspreidingToe()` checkte alleen band-compatibiliteit bij swaps.

**Oplossing:**
- Als gewicht hogere prioriteit heeft dan clubspreiding → max kg verschil bij swap
- Swap wordt geblokkeerd als gewichtsverschil groter is dan `max_kg_verschil` (default 3kg)
- Prioriteiten worden nu volledig gerespecteerd

### Auto-herberekening judoka codes (8 jan 2026) ✓
Bij wijziging van `verdeling_prioriteiten` (drag & drop volgorde) worden judoka codes
automatisch herberekend bij opslaan van instellingen.

### Import onvolledige judoka's (7 jan 2026) ✓
- Judoka's zonder geboortejaar worden nu geïmporteerd (niet meer overgeslagen)
- Nieuw veld `is_onvolledig` om te markeren
- Filter knop "Onvolledig" in judoka lijst
- Gewicht wordt afgeleid van gewichtsklasse als die wel is ingevuld (bv. "-34" → 34 kg)

### Bugfix: Gewicht fallback naar gewichtsklasse (10 jan 2026) ✓
**Probleem:** Harde gewichtsconstraint (max 3kg verschil) werd genegeerd - poules hadden 30kg verschil!

**Oorzaak:** `DynamischeIndelingService` gebruikte `$judoka->gewicht` direct, maar dit veld is vaak `null`.
Veel judoka's hebben alleen `gewichtsklasse` (bijv. "-38") ingevuld, niet `gewicht`.

**Oplossing:** `getEffectiefGewicht()` helper methode met fallback prioriteit:
1. `gewicht_gewogen` - meest nauwkeurig (na weging op wedstrijddag)
2. `gewicht` - ingeschreven gewicht
3. `gewichtsklasse` - extract getal uit "-38" of "+73" → 38.0 of 73.0

**Gewijzigde bestanden:**
- `app/Services/DynamischeIndelingService.php` - helper + 20+ plekken
- `app/Http/Controllers/PouleController.php` - berekenPouleRanges()
- `resources/views/pages/poule/index.blade.php` - range berekening + gewicht display

**UI impact:**
- Judoka's tonen nu gewicht in poule overzicht (≤38kg als fallback)
- Min-max range in poule header werkt nu ook zonder `gewicht` veld

## Notities

- Leeftijd is ALTIJD eerste filter (veiligheid!)
- Band-sortering is secundair: zorgt voor eerlijke poules
- Clubspreiding als aan/uit optie bij groepsindeling
- Wedstrijdsysteem (poules/kruisfinale/eliminatie) blijft per leeftijdsgroep
