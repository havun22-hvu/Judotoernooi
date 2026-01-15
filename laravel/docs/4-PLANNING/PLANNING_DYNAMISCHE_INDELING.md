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

> **Sortering bij laden:** Zie `GEBRUIKERSHANDLEIDING.md` sectie "Presets opslaan"

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

### Instelling per categorie

Per categorie in Instellingen is er 1 checkbox:
- [ ] **Toon label in titel**

### Automatische regels

De poule titel bevat altijd:
- **Vaste klasse** (verschil=0) → gewichtscategorie uit preset (bijv. `-26kg`)
- **Actuele range** (verschil>0) → min-max berekend uit judoka's in poule

| Situatie | In titel |
|----------|----------|
| `max_leeftijd_verschil = 0` | Geen leeftijd (zit in label) |
| `max_leeftijd_verschil > 0` | Min-max leeftijd van poule-inhoud |
| `max_kg_verschil = 0` | Vaste gewichtscategorie |
| `max_kg_verschil > 0` | Min-max gewicht van poule-inhoud |

### Voorbeelden

| Poule titel | Label | Lft verschil | Kg verschil | Toelichting |
|-------------|-------|--------------|-------------|-------------|
| `#5 Mini's U7 -26kg` | aan | 0 | 0 | Vaste categorie + vaste gewichtsklasse |
| `#5 Mini's U7 28-32kg` | aan | 0 | >0 | Vaste leeftijd, variabel gewicht |
| `#5 Jeugd 9-10j 28-32kg` | aan | >0 | >0 | Beide variabel |
| `#5 9-10j 28-32kg` | uit | >0 | >0 | Beide variabel, geen label |
| `#5 -26kg` | uit | 0 | 0 | Alleen vaste gewichtsklasse (niet aangeraden) |

**Let op:** Bij vaste categorieën (verschil=0) is het verstandig het label aan te laten staan voor context.

### Live titel update bij verslepen judoka's

Bij variabele indeling (verschil>0) wordt de titel automatisch bijgewerkt wanneer judoka's worden versleept:
- Server berekent nieuwe min-max ranges uit `berekenPouleRanges()`
- Server update titel via `updateDynamischeTitel()`
- Database wordt bijgewerkt (titel veld)
- Client update titel in DOM via JavaScript `updatePouleStats()`

**Belangrijk:** De ranges zitten IN de titel zelf (bijv. "Jeugd 9-10j 28-32kg"), niet als aparte tekst erachter.

**Wat wordt live bijgewerkt na drag & drop:**
- Aantal judoka's per poule
- Aantal wedstrijden per poule
- Poule titel (met nieuwe min-max ranges)
- Totaal statistieken bovenaan pagina

### Automatische blokverdeling (variabel systeem)

Bij variabele categorieën (`max_leeftijd_verschil > 0` of `max_kg_verschil > 0`) worden blokken automatisch ingedeeld:

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
    // Check of er variabele categorieën zijn (verschil > 0)
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
2. Poules krijgen titels op basis van label checkbox + actuele ranges
3. VariabeleBlokVerdelingService groepeert op categorie
4. Binnen groep: trial & error algoritme

**Voorbeeld:** Band-scheiding + variabel binnen bandgroep

1. Poule-aanmaak scheidt op band (t/m oranje vs vanaf groen)
2. Poules krijgen titels: "Beginners 9-10j 28-32kg" of "Gevorderden 9-10j 28-32kg"
3. VariabeleBlokVerdelingService groepeert op categorie
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
     * @param Collection $poules Poules met variabele indeling
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

**Nieuw veld (13 jan 2026):** `categorie_key` op `poules` tabel
- Gebruikt voor groepering bij blokverdeling
- Voorbeelden: `m_variabel`, `v_variabel`, `beginners`, `gevorderden`
- Maakt gemengde scenario's mogelijk (M/V apart + variabel binnen geslacht)

**Algoritme:**
1. Groepeer poules eerst op `categorie_key`
2. Binnen elke categorie: sorteer op MIN leeftijd → MIN gewicht
3. Trial & error met 20 strategieën voor optimale splits
4. Bij leeftijdsgrens: zoek gewichtssplit binnen overlappende leeftijden

**Dynamische header generatie:**
```
[Label?] + leeftijd range + gewicht range
Voorbeeld: "M 8-10j 30-40kg" of "Beginners 9-11j 25-35kg"
```

**Detectie variabele categorieën:**
```php
// Check categorie config, niet de titel
$config = $toernooi->getPresetConfig();
foreach ($config as $categorie) {
    if ($categorie['max_leeftijd_verschil'] > 0 || $categorie['max_kg_verschil'] > 0) {
        return true; // Heeft variabele categorieën
    }
}
return false;
```

## Classificatie Workflow (14 jan 2026)

### Hoe classificatie werkt

1. **Import** - Judoka's worden geïmporteerd met originele classificatie (bijv. JBN labels)
2. **Herclassificatie** - `PouleIndelingService::herberkenKlassen()` leest toernooi preset en classificeert opnieuw
3. **Poule generatie** - Roept automatisch herclassificatie aan

### Automatische geslacht detectie uit label (14 jan 2026)

Als `geslacht=gemengd` maar het label bevat geslacht-indicatie, wordt dit automatisch afgeleid:

| Label bevat | Wordt behandeld als |
|-------------|---------------------|
| "Dames", "Meisjes", "_d_", "_d" (suffix) | V (vrouw) |
| "Heren", "Jongens", "_h_", "_h" (suffix) | M (man) |

**Voorbeeld:**
```
u15_d_groen_plus: label="U15 Dames Groen+", geslacht=gemengd
→ Code detecteert "Dames" in label → behandelt als geslacht=V
```

Dit voorkomt fouten wanneer organisator vergeet geslacht in te vullen.

### Na kopie van andere database

Als judoka's worden gekopieerd van production naar staging:
- Judoka's behouden hun **oude classificatie** (bijv. JBN2025 labels)
- **Herclassificatie moet draaien** om nieuwe preset labels te krijgen
- Dit gebeurt automatisch bij "Poules genereren", of handmatig:

```bash
# Handmatig herclassificeren (staging)
cd /var/www/staging.judotoernooi/laravel
php artisan tinker --execute="app(App\Services\PouleIndelingService::class)->herberkenKlassen(App\Models\Toernooi::find(5));"
```

### Dashboard categorieën

Het dashboard toont **wat in `judokas.leeftijdsklasse` staat**, NIET hardcoded categorieën.
- Als dit oude labels toont (A-pupillen, Dames -15) → herclassificatie moet draaien
- Na herclassificatie toont het de preset labels (U11 Geel+, U15 Dames Groen+)

---

## Rode Poule Markering (14 jan 2026)

### Probleem
Rode markering voor problematische poules was hardcoded op `< 3 judoka's`.
Dit hield geen rekening met de `poule_grootte_voorkeur` instelling.

### Oplossing
Rode markering nu gebaseerd op instellingen:
- Een poule is **rood** als grootte NIET in `poule_grootte_voorkeur` staat
- Default voorkeur: `[5, 4, 6, 3]` → poules met 1, 2, 7, 8+ judoka's zijn rood
- Lege poules (0) zijn **blauw** (verwijderbaar)

### Voorbeeld
```
poule_grootte_voorkeur = [5, 4, 6, 3]

Poule met 2 judoka's → ROOD (2 niet in [5,4,6,3])
Poule met 3 judoka's → BLAUW (3 in [5,4,6,3])
Poule met 7 judoka's → ROOD (7 niet in [5,4,6,3])
```

### Implementatie
- `poule/index.blade.php`: PHP + JavaScript aangepast
- Melding toont nu toegestane groottes

---

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
- [x] Aantal judoka's per poule
- [x] Aantal wedstrijden per poule
- [x] Min-max leeftijd per poule
- [x] Min-max gewicht per poule
- [x] Totaal statistieken bovenaan pagina (wedstrijden, judoka's, problemen) ← 14 jan 2026
- [x] Poule titel (bij variabele categorie)

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

---

## DONE: Hardcoded JBN Categorieën Opgeruimd (14 jan 2026)

### Probleem (OPGELOST ✓)

Er stonden op veel plekken hardcoded JBN categorieën ("Mini's", "A-pupillen", "Dames -15", etc.).
Deze mogen **alleen** in de preset definities staan, niet verspreid door de code.

### Wat HARDCODED mag blijven

| Locatie | Reden |
|---------|-------|
| `Models/Toernooi.php` | JBN preset definities (dat is de bron) |
| `Enums/Leeftijdsklasse.php` | Legacy enum (deprecated, niet gebruiken) |

### Wat is aangepast (commit b3b3ef7)

Alle plekken gebruiken nu de preset config uit `toernooi->gewichtsklassen`:

| File | Wat aangepast |
|------|---------------|
| `PouleIndelingService.php` | `leeftijdsklasseToConfigKey()` zoekt nu in config |
| `PouleIndelingService.php` | `getLeeftijdOrder()` gebruikt config key volgorde |
| `BlokMatVerdelingService.php` | `getGroteLeeftijden()` / `getKleineLeeftijden()` nu dynamisch op basis van geslacht |
| `RoleToegang.php` | Gebruikt `toernooi->getCategorieVolgorde()` |
| `PubliekController.php` | Gebruikt `toernooi->getCategorieVolgorde()` |
| `WedstrijddagController.php` | Bouwt label→key mapping dynamisch uit config |
| `blok/index.blade.php` | Geen standaard afkortingen meer |
| `blok/_category_chip.blade.php` | Fallback arrays verwijderd |
| `publiek/index.blade.php` | `kortLeeftijd()` JS gebruikt nu generieke truncatie |
| `coach/judokas.blade.php` | `bepaalLeeftijdsklasse()` JS gebruikt config met max_leeftijd |

### Nieuwe helper methodes in `Toernooi.php`

```php
// Retourneert [label => volgorde_nummer] uit preset config
public function getCategorieVolgorde(): array

// Retourneert config key voor een label
public function getCategorieKeyByLabel(string $label): ?string
```

### Hoe het werkt

**Alle categorie-info komt uit de instellingen (preset config):**

1. **Sortering**: `sort_categorie` field op judokas (gezet bij herclassificatie)
2. **Labels**: `$config[$key]['label']` uit preset
3. **Volgorde**: Volgorde van keys in preset config
4. **Geen hardcoded afkortingen**: Gebruik volledige label uit preset

### Stappen

- [ ] Centrale helper: `Toernooi::getCategorieVolgorde()` → leest volgorde uit preset keys
- [ ] Vervang hardcoded sortering arrays door `sort_categorie` ORDER BY
- [ ] Vervang hardcoded label mappings door preset config lookup
- [ ] Verwijder afkortingen - gebruik volledige label
- [ ] Test met JBN 2025, JBN 2026, en eigen presets (OWFJ2026)

---

## Implementatieplan: Poule Titels Refactoring (14 jan 2026)

> **Doel:** Vervang `lft-kg` placeholder systeem door checkbox-gebaseerd systeem

### Samenvatting

**Oud systeem:**
- Organisator typt `lft-kg` in label veld
- Code vervangt `lft-kg` door actuele ranges
- Detectie via string matching in titel

**Nieuw systeem:**
- Per categorie checkbox: "Toon label in titel"
- Ranges komen automatisch als `verschil > 0`
- Detectie via categorie config velden

### Poule titel regels

| Situatie | In titel |
|----------|----------|
| `toon_label_in_titel = true` | Label uit config |
| `max_leeftijd_verschil = 0` | Geen leeftijd (zit in label) |
| `max_leeftijd_verschil > 0` | Min-max leeftijd van poule-inhoud |
| `max_kg_verschil = 0` | Vaste gewichtscategorie uit preset |
| `max_kg_verschil > 0` | Min-max gewicht van poule-inhoud |

### Voorbeelden

```
#5 Mini's U7 -26kg        ← label aan, lft=0, kg=0 (vaste categorie)
#5 Mini's U7 28-32kg      ← label aan, lft=0, kg>0 (variabel gewicht)
#5 Jeugd 9-10j 28-32kg    ← label aan, lft>0, kg>0 (beide variabel)
#5 9-10j 28-32kg          ← label uit, lft>0, kg>0 (beide variabel)
#5 -26kg                  ← label uit, lft=0, kg=0 (niet aangeraden)
```

### Fase 1: Database & Config

**1.1 Nieuw veld in categorie config:**
```php
// In preset config structuur
'toon_label_in_titel' => true,  // default: true
```

**1.2 Bestanden:**
- `app/Services/DynamischeIndelingService.php` - default waarde toevoegen
- `app/Http/Controllers/ToernooiController.php` - opslaan/laden

**1.3 Bestaande presets updaten:**
- JBN 2025: alle categorieën `toon_label_in_titel = true`
- JBN 2026: alle categorieën `toon_label_in_titel = true`
- Database presets: migration om default toe te voegen

### Fase 2: UI Aanpassingen

**2.1 Instellingen pagina (`edit.blade.php`):**

Verwijderen:
- [ ] `lft-kg` default value in nieuwe categorie
- [ ] Tooltip "Tip: 'lft-kg' wordt vervangen..."

Toevoegen:
- [ ] Checkbox "Toon label in titel" per categorie
- [ ] Checkbox default aan (checked)

**2.2 Nieuwe UI per categorie:**
```
┌─────────────────────────────────────────────────────────────────┐
│ Naam: [Jeugd________]  [☑ Toon in titel]                       │
│ Max lft: [10] jaar   Geslacht: [M&V ▼]                         │
│ Max lft verschil: [2]   Max kg verschil: [3.0]                 │
└─────────────────────────────────────────────────────────────────┘
```

**2.3 JavaScript aanpassingen:**
- `buildDataFromForm()` - nieuw veld uitlezen
- `addNewCategory()` - checkbox meegeven (default true)
- `renderCategory()` - checkbox renderen

### Fase 3: Titel Generatie

**3.1 `PouleIndelingService::maakPouleTitel()` herschrijven:**

```php
private function maakPouleTitel(
    string $leeftijdsklasse,
    string $gewichtsklasse,
    ?string $geslacht,
    int $pouleNr,
    array $pouleJudokas = [],
    ?array $categorieConfig = null
): string {
    $parts = [];

    // 1. Label (optioneel)
    $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
    if ($toonLabel && !empty($categorieConfig['label'])) {
        $parts[] = $categorieConfig['label'];
    }

    // 2. Geslacht (als niet gemengd)
    if ($geslacht && $geslacht !== 'gemengd') {
        $parts[] = $geslacht; // 'M' of 'V'
    }

    // 3. Leeftijd range (als variabel)
    $maxLftVerschil = $categorieConfig['max_leeftijd_verschil'] ?? 0;
    if ($maxLftVerschil > 0 && !empty($pouleJudokas)) {
        $leeftijden = array_filter(array_map(fn($j) => $j->leeftijd, $pouleJudokas));
        if (!empty($leeftijden)) {
            $min = min($leeftijden);
            $max = max($leeftijden);
            $parts[] = $min == $max ? "{$min}j" : "{$min}-{$max}j";
        }
    }

    // 4. Gewicht (vaste klasse OF variabele range)
    $maxKgVerschil = $categorieConfig['max_kg_verschil'] ?? 0;
    if ($maxKgVerschil > 0 && !empty($pouleJudokas)) {
        // Variabel: bereken range uit judoka's
        $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
        if (!empty($gewichten)) {
            $min = min($gewichten);
            $max = max($gewichten);
            $parts[] = $min == $max ? "{$min}kg" : "{$min}-{$max}kg";
        }
    } elseif (!empty($gewichtsklasse)) {
        // Vast: gebruik gewichtsklasse uit preset
        $parts[] = str_contains($gewichtsklasse, 'kg') ? $gewichtsklasse : "{$gewichtsklasse}kg";
    }

    return implode(' ', $parts) ?: 'Onbekend';
}
```

**3.2 Verwijderen:**
- [ ] `lft-kg` string matching logica (regels 1174-1188)
- [ ] ` · ` separator logica

### Fase 4: Detectie Variabele Categorieën

**4.1 `BlokMatVerdelingService::heeftVariabeleCategorieen()`:**

```php
// OUD (verwijderen)
$toernooi->poules()
    ->where('titel', 'like', '%lft-kg%')
    ->orWhere('titel', 'like', '% · %')
    ->exists();

// NIEUW
private function heeftVariabeleCategorieen(Toernooi $toernooi): bool
{
    $config = $toernooi->getPresetConfig();
    foreach ($config as $categorie) {
        if (($categorie['max_leeftijd_verschil'] ?? 0) > 0 ||
            ($categorie['max_kg_verschil'] ?? 0) > 0) {
            return true;
        }
    }
    return false;
}
```

**4.2 Bestanden aanpassen:**
- `app/Services/BlokMatVerdelingService.php`
- `app/Services/VariabeleBlokVerdelingService.php`

### Fase 5: Cleanup

**5.1 Verwijderen uit code:**
- [ ] `lft-kg` string checks in `PouleIndelingService.php`
- [ ] `lft-kg` string checks in `BlokMatVerdelingService.php`
- [ ] `lft-kg` string checks in `VariabeleBlokVerdelingService.php`
- [ ] `lft-kg` string checks in `PouleController.php`
- [ ] `lft-kg` string checks in `WedstrijddagController.php`
- [ ] ` · ` separator logica overal

**5.2 Verwijderen uit views:**
- [ ] `edit.blade.php` - lft-kg tooltip en default
- [ ] `index.blade.php` - lft-kg string checks

### Fase 6: Testen

- [ ] Nieuwe categorie aanmaken met label checkbox aan
- [ ] Nieuwe categorie aanmaken met label checkbox uit
- [ ] Vaste categorie (verschil=0) → titel met label + gewichtsklasse
- [ ] Variabele categorie (verschil>0) → titel met ranges
- [ ] Bestaande poules blijven werken
- [ ] Blokverdeling werkt met nieuwe detectie
- [ ] Live titel update bij verslepen

### Bestanden Overzicht

| Bestand | Actie |
|---------|-------|
| `app/Services/DynamischeIndelingService.php` | Default `toon_label_in_titel` toevoegen |
| `app/Services/PouleIndelingService.php` | `maakPouleTitel()` herschrijven, lft-kg verwijderen |
| `app/Services/BlokMatVerdelingService.php` | Detectie aanpassen |
| `app/Services/VariabeleBlokVerdelingService.php` | lft-kg checks verwijderen |
| `app/Http/Controllers/PouleController.php` | lft-kg checks verwijderen |
| `app/Http/Controllers/WedstrijddagController.php` | lft-kg checks verwijderen |
| `resources/views/pages/toernooi/edit.blade.php` | Checkbox toevoegen, lft-kg verwijderen |
| `resources/views/pages/poule/index.blade.php` | lft-kg checks verwijderen |

### Migratie Bestaande Data

Bestaande poules met `lft-kg` in titel:
- Geen actie nodig - titels worden opnieuw gegenereerd bij "Poules genereren"
- Of: eenmalige migration om titels te updaten

### Risico's

1. **Breaking change:** Bestaande poules met `lft-kg` titel
   - Mitigatie: titels worden bij regeneratie automatisch correct

2. **Backwards compatibility:** Oude presets zonder `toon_label_in_titel`
   - Mitigatie: default `true` als veld ontbreekt

---

## Implementatieplan: Betere Poule Grootte bij Variabele Categorieën (15 jan 2026)

### Probleem

Bij variabele categorieën (`max_kg_verschil > 0`) worden poules niet optimaal verdeeld:
- **Huidige aanpak:** Sequentieel vullen tot gewichtslimiet bereikt (kan 7-8 judoka's worden)
- **Gewenst:** Poules van 5 (ideaal) binnen de harde limieten

### Kernprincipes

1. **Ranges zijn HARD** - leeftijd en gewicht limieten worden NOOIT overschreden
2. **Poule grootte prioriteit:** 5 > 4 > 3 (5 is best, 3 alleen als het niet anders kan)
3. **Geen magic fixes** - als indeling niet goed is, moet organisator ranges aanpassen

### Algoritme

```
1. SORTEER alle judoka's:
   - Primair: leeftijd (jong → oud)
   - Secundair: gewicht (licht → zwaar)

2. GROEPEER op harde limieten:
   - Maak groepen waar ALLE judoka's binnen leeftijd EN gewicht limieten vallen
   - Dit zijn de "kandidaat-poules" die nog verdeeld moeten worden

3. OPTIMALISEER poule grootte per groep:
   - Bereken beste verdeling volgens prioriteit [5, 4, 3]
   - Voorbeeld: 8 judoka's → 4+4 (niet 5+3, want 4 > 3)
   - Voorbeeld: 9 judoka's → 5+4
   - Voorbeeld: 7 judoka's → 4+3 (niet 5+2, want 2 is te klein)
   - Voorbeeld: 11 judoka's → 4+4+3 (niet 5+3+3)

4. RESULTAAT: poules van 5, 4 of 3 judoka's binnen harde limieten
```

### Poule grootte verdeling tabel

| Aantal | Beste verdeling | Waarom |
|--------|----------------|--------|
| 3 | 3 | Enige optie |
| 4 | 4 | Enige optie |
| 5 | 5 | Ideaal |
| 6 | 3+3 | Twee gelijke poules |
| 7 | 4+3 | 4 > 3 |
| 8 | 4+4 | Twee gelijke poules (niet 5+3) |
| 9 | 5+4 | 5 is prioriteit |
| 10 | 5+5 | Twee ideale poules |
| 11 | 4+4+3 | Voorkom 3+3 situatie (niet 5+3+3) |
| 12 | 4+4+4 | Drie gelijke poules |
| 13 | 5+4+4 | 5 waar mogelijk |
| 14 | 5+5+4 | Twee ideale + één goede |
| 15 | 5+5+5 | Drie ideale poules |

### Verschil met huidige code

| Aspect | Huidige code | Nieuwe code |
|--------|--------------|-------------|
| Stop criterium | Bij 5 judoka's | Na optimale groepering |
| 8 judoka's | 5+3 | 4+4 |
| 11 judoka's | 5+3+3 | 4+4+3 |
| Limieten | Hard | Hard (ongewijzigd) |
| Optimalisatie | Geen (sequentieel) | Ja (beste verdeling) |

### Voorbeeld

```
Input: 12 judoka's, max 2j leeftijd, max 3kg gewicht
Gesorteerd: [8j/25kg, 8j/26kg, 8j/28kg, 9j/27kg, 9j/29kg, 9j/30kg,
             10j/31kg, 10j/32kg, 10j/34kg, 11j/33kg, 11j/35kg, 11j/36kg]

Poule 1: [8j/25kg, 8j/26kg, 8j/28kg, 9j/27kg, 9j/29kg] → 5 judoka's, 8-9j, 25-29kg ✓
Poule 2: [9j/30kg, 10j/31kg, 10j/32kg, 10j/34kg] → 4 judoka's, 9-10j, 30-34kg ✓
         (11j/33kg past niet: leeftijd 9-11 = 2j OK, maar gewicht 30-33 = 3kg OK...
          maar poule 2 is al bij 34kg, dus 30-34=4kg zou overschrijden)
Poule 3: [11j/33kg, 11j/35kg, 11j/36kg] → 3 judoka's, 11j, 33-36kg ✓

Resultaat: [5, 4, 3] binnen alle limieten
```

### Implementatie

| Bestand | Wijziging |
|---------|-----------|
| `DynamischeIndelingService.php` | `maakPoules()` aanpassen: stop bij 5 judoka's |

### Code Wijziging

In `maakPoules()`, voeg poule grootte check toe:

```php
// BESTAAND: check gewichtslimiet
if (($nieuwMax - $nieuwMin) > $maxKgVerschil) {
    $pastInPoule = false;
}

// NIEUW: check ook poule grootte (max 5)
if (count($huidigePoule) >= 5) {
    $pastInPoule = false;
}
```

### Wat als indeling niet goed is?

Als er veel kleine poules (2-3) ontstaan:
- **Oorzaak:** Ranges te strak voor de deelnemers
- **Oplossing:** Organisator past `max_kg_verschil` of `max_leeftijd_verschil` aan
- **Geen automatische fix:** Systeem overschrijdt nooit de ingestelde limieten
