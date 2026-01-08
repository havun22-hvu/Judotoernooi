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

### Bij "GEEN STANDAARD" (dynamische indeling)

```
┌─────────────────────────────────────────────────────────────────┐
│ Sorteer prioriteit: (i)  - bij categorieën met grote aantallen │
│ [1. Gewicht] [2. Band] [3. Groepsgrootte] [4. Club]            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
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
│   Max kg verschil: [3] kg   Max leeftijd verschil: [1] jaar    │
│                                                                 │
│ [+ Categorie toevoegen]                                         │
└─────────────────────────────────────────────────────────────────┘
```

**Velden per categorie:**
| Veld | Type | Default | Beschrijving |
|------|------|---------|--------------|
| Naam | text | - | Label voor deze categorie (bijv. "Mini's", "Jeugd") |
| Max leeftijd | number | - | Leeftijdsgrens (exclusief) |
| Geslacht | select | Gemengd | Gemengd / Jongens / Meisjes |
| Systeem | select | Poules | Poules / Poules+Kruisfinale / Eliminatie |
| Max kg verschil | number | 3 | HARDE limiet voor gewichtsverschil |
| Max leeftijd verschil | number | 1 | HARDE limiet voor leeftijdsverschil |

### Bij "JBN 2025" of "JBN 2026" (vaste gewichtsklassen)

```
┌─────────────────────────────────────────────────────────────────┐
│ Vaste gewichtsklassen volgens JBN normen                        │
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

**Verschil JBN vs Geen standaard:**
| Aspect | JBN 2025/2026 | Geen standaard |
|--------|---------------|----------------|
| Gewichtsklassen | Vast (-18, -21, etc.) | Dynamisch (max kg verschil) |
| Sortering | Op BAND binnen klasse | Op prioriteit (gewicht/band) |
| Leeftijdsgroepen | Vast per JBN | Zelf invullen |
| Geslacht | Volgens JBN | Zelf kiezen |

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

Titels zijn nu dynamisch op basis van werkelijke waarden:

| Type | Oud formaat | Nieuw formaat |
|------|-------------|---------------|
| Voorronde | "A-pupillen -30 kg" | "Jeugd 9-10j 30-33kg" |
| Eliminatie | "A-pupillen -30 kg - Eliminatie" | "Jeugd M 9-10j 30-33kg - Eliminatie" |
| Kruisfinale | "Kruisfinale A-pupillen Jongens -30 kg" | "Kruisfinale Jeugd M -30kg (top 2)" |

**Onderdelen:**
- Categorie label (uit instellingen: "Mini's", "Jeugd", etc.)
- Geslacht: M/V (kort, ipv "Jongens"/"Meisjes")
- Leeftijd range: berekend uit judoka's (bijv. "9-10j")
- Gewicht range: berekend uit judoka's (bijv. "30-33kg")

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

## Notities

- Leeftijd is ALTIJD eerste filter (veiligheid!)
- Band-sortering is secundair: zorgt voor eerlijke poules
- Clubspreiding als aan/uit optie bij groepsindeling
- Wedstrijdsysteem (poules/kruisfinale/eliminatie) blijft per leeftijdsgroep
