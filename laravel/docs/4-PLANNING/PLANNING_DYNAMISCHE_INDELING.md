# Planning: Dynamische Poule Indeling

> **Status:** Gepland voor 2026
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

## Nieuwe Velden per Leeftijdsgroep

| Veld | Type | Opties | Beschrijving |
|------|------|--------|--------------|
| `geslacht` | enum | gemengd / jongens / meisjes | Welke judoka's in deze groep |
| `max_kg_verschil` | decimal | 0-10 | 0 = vaste klassen, >0 = dynamisch |
| `max_leeftijd_verschil` | int | 0-3 | 0 = vaste leeftijdsklassen, >0 = dynamisch |

## UI: Zonder Gewichtsklassen

Wanneer "Gewichtsklassen gebruiken" UIT staat, kan de organisator kiezen:

```
┌─────────────────────────────────────────────────────────────────┐
│ Zonder gewichtsklassen: Judoka's worden alleen per              │
│ leeftijdsgroep ingedeeld. Kies de sorteervolgorde:              │
├─────────────────────────────────────────────────────────────────┤
│ ○ Gewicht → Band                                                │
│   Sorteer op werkelijk gewicht, dan band                        │
│                                                                 │
│ ○ Band → Gewicht                                                │
│   Sorteer op band (laag→hoog), dan gewicht                      │
├─────────────────────────────────────────────────────────────────┤
│ Max kg verschil: [3]  Max leeftijd verschil: [2]                │
└─────────────────────────────────────────────────────────────────┘
```

## Toernooi-niveau Instellingen

| Veld | Database | Default | Beschrijving |
|------|----------|---------|--------------|
| Max kg verschil | `toernooien.max_kg_verschil` | 3.0 | Voor dynamische indeling |
| Max leeftijd verschil | `toernooien.max_leeftijd_verschil` | 2 | Voor dynamische indeling |

## Presets

```
┌─────────────────────────────────────────────────────────────────┐
│ [JBN 2025]  [JBN 2026]  [Eigen preset ▼]  [Opslaan als preset] │
└─────────────────────────────────────────────────────────────────┘
```

### Standaard Presets (JBN)
De knoppen **JBN 2025** en **JBN 2026** laden de officiële JBN indeling:
- Alle leeftijdsklassen met correcte leeftijdsgrenzen
- Vaste gewichtsklassen per categorie (max_kg_verschil = 0)
- Geslacht per categorie: Mini's & Pupillen gemengd, vanaf U15 M/V apart

### Eigen Presets
Organisator kan huidige configuratie opslaan als eigen preset:
- Klik **Opslaan als preset** → voer naam in
- Preset wordt opgeslagen bij de organisator
- Later laden via dropdown **Eigen preset**

**Database:** `gewichtsklassen_presets` tabel
```
id, organisator_id, naam, configuratie (JSON), timestamps
unique: [organisator_id, naam]
```

## Algoritme: Dynamische Indeling

```
═══════════════════════════════════════════════════════════════════
VASTE HIËRARCHIE (veiligheid eerst!)
═══════════════════════════════════════════════════════════════════

1. GESLACHT    - M/V apart (indien niet gemengd)
2. LEEFTIJD    - Max 2 jaar verschil (HARD - veiligheid)
3. GEWICHT     - Max 3 kg verschil (HARD - veiligheid)
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

## Notities

- Leeftijd is ALTIJD eerste filter (veiligheid!)
- Band-sortering is secundair: zorgt voor eerlijke poules
- Clubspreiding blijft werken zoals nu
- Wedstrijdsysteem (poules/kruisfinale/eliminatie) blijft per leeftijdsgroep
