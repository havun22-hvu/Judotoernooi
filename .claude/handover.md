# Handover - Laatste Sessie

## Datum: 17 januari 2026 (sessie 2)

### Wat is gedaan:

1. **CategorieClassifier class toegevoegd** ✅
   - Dedicated class voor categorie-identificatie op basis van harde criteria
   - Harde criteria: max_leeftijd, geslacht, band_filter, gewichtsklassen
   - NIET voor identificatie: max_kg_verschil, max_leeftijd_verschil (poule-niveau)
   - Locatie: `app/Services/CategorieClassifier.php`

2. **Poule::getCategorieConfig() gefixt** ✅
   - Was: zocht op label match (foutgevoelig)
   - Nu: directe lookup op `categorie_key` via CategorieClassifier
   - Fix voor vals-positieve gewichtsrange warnings

3. **Docs bijgewerkt** ✅
   - CategorieClassifier sectie in PLANNING_DYNAMISCHE_INDELING.md
   - Database velden uitgebreid (poules tabel, categorie_key uitleg)
   - "Nooit zoeken op label!" gedocumenteerd

### De bug die is opgelost:

**Probleem:** Poules met vaste gewichtsklassen (bijv. "21-23kg") werden oranje gemarkeerd alsof de gewichtsrange te groot was.

**Oorzaak:** `getCategorieConfig()` zocht op label match. Als het label niet exact matchte → fallback naar toernooi-brede `max_kg_verschil` → poule werd als "dynamisch" gezien → gewichtsvalidatie triggerde onterecht.

**Oplossing:** Lookup nu via `categorie_key` (directe array key), niet via label.

### Gewijzigde bestanden:

```
laravel/app/Services/CategorieClassifier.php (NIEUW)
  - classificeer() - judoka naar categorie
  - getConfigVoorPoule() - config lookup op key
  - isDynamisch() - check max_kg_verschil > 0

laravel/app/Models/Poule.php
  - getCategorieConfig() - gebruikt nu CategorieClassifier
  - isDynamisch() - gebruikt nu CategorieClassifier
  - getClassifier() - helper methode

laravel/docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md
  - CategorieClassifier sectie toegevoegd
  - Database velden uitgebreid
```

### Commit:

- `feat: Add CategorieClassifier for category lookup by key instead of label`

### Openstaand:

- [ ] PouleIndelingService refactoren om CategorieClassifier te gebruiken (optioneel, werkt al)
- [ ] Live testen of vals-positieve warnings weg zijn

---

## Datum: 17 januari 2026 (sessie 1)

### Wat is gedaan:

1. **Vergrootglas zoek-match icoon** ✅
   - 🔍 knop bij elke judoka in poules EN wachtruimte
   - Klikt naar Zoek Match popup met geschikte poules

2. **Revalidatie na drag naar wachtruimte** ✅
   - `naarWachtruimte()` endpoint retourneert nu `is_problematisch`
   - JS update probeert border te verwijderen als poule OK is

3. **Oranje border bij initiële render** ✅
   - Check `$problematischeGewichtsPoules->has($poule->id)` in PHP render
   - Voorheen werd border alleen via JS gezet

4. **Console.log debugging** ✅
   - Toegevoegd voor troubleshooting gewichtsrange issues

### OPENSTAANDE BUGS (KRITIEK):

#### 1. Vals-positieve gewichtsrange markering
**Probleem:** Poules worden oranje gemarkeerd terwijl gewichtsrange OK is!

**Voorbeelden:**
- **Poule #5 Jeugd 21-23kg**: range 21.5-23.4 = **1.9kg** → zou NIET oranje moeten zijn!
- **Poule #9 Jeugd 26.5-28.8kg**: range 27.3-31.8 = 4.5kg (1 judoka buiten klasse)

**Mogelijke oorzaken:**
1. `Poule::isDynamisch()` retourneert true voor VASTE gewichtsklassen
2. `max_kg_verschil` staat verkeerd in config
3. `$problematischeGewichtsPoules` collectie bevat verkeerde poules

**Te onderzoeken bestanden:**
```
app/Models/Poule.php:
  - isDynamisch() regel 214-218
  - getCategorieConfig() regel 223-239
  - isProblematischNaWeging() regel 275-314

app/Http/Controllers/WedstrijddagController.php:
  - Hoe wordt $problematischeGewichtsPoules opgebouwd?
```

#### 2. Header kleur verkeerd (oranje i.p.v. blauw)
**Probleem:** Headers zijn oranje/bruin bij poules met >= 3 judoka's

**Verwacht:** `bg-blue-700` (blauw) bij >= 3 actieve judoka's
**Realiteit:** Headers lijken oranje/bruin

**Te checken:** Browser cache (Ctrl+F5), CSS overrides

#### 3. Update na drag werkt niet altijd
**Debug stappen:**
1. Open browser console (F12)
2. Sleep judoka naar wachtruimte
3. Check output: `naarWachtruimte response: { van_poule: { is_problematisch: ... } }`

### Hypothese:

Het probleem zit waarschijnlijk in hoe categorieën als "dynamisch" worden geïdentificeerd. `isDynamisch()` checkt `max_kg_verschil > 0`, maar als ALLE categorieën dit hebben (ook vaste zoals 21-23kg), worden ze allemaal gevalideerd.

**Oplossing:** Onderscheid maken tussen:
- Dynamische categorie: variabel gewicht, max_kg_verschil constraint
- Vaste gewichtsklasse: gewicht al bepaald door klasse (21-23kg), geen extra validatie nodig

### Gewijzigde bestanden:

```
app/Http/Controllers/WedstrijddagController.php
  - naarWachtruimte() - is_problematisch response

resources/views/pages/wedstrijddag/poules.blade.php
  - 🔍 zoek-match knoppen
  - $heeftGewichtsprobleem bij initiële render
  - updateGewichtsrangeBox() JS functie
  - Console.log debugging
```

### Commits vandaag:

- `feat: Revalidate poule after dragging judoka to wachtruimte`
- `feat: Add search icon to find suitable poule for each judoka`
- `fix: Add orange border styling at initial render for weight range problems`

---

## Datum: 16 januari 2026 (sessie 2)

### Wat is gedaan:

1. **Python Greedy++ Solver gemaakt** ✅
   - **Doel:** Minder orphans, betere poule verdeling
   - **Locatie:** `laravel/scripts/poule_solver.py`
   - **Algoritme:** Greedy basis → Fix orphans → Merge kleine poules → Swap optimalisatie
   - **Test:** `laravel/scripts/test_poule_solver.py` - score verbeterd van 190 → 55

2. **DynamischeIndelingService.php gerefactored** ✅
   - Roept nu Python solver aan via stdin/stdout JSON
   - Simpele PHP fallback als Python niet beschikbaar is
   - Oude greedy functies verwijderd (maakPoulesGreedy, mergeKleinePoules, etc.)
   - Score functie gebruikt nu `poule_grootte_voorkeur` config correct:
     - Index 0 = 0 punten (eerste voorkeur)
     - Index 1 = 5 punten (tweede voorkeur)
     - Index 2+ = 40 punten (rest)
     - Niet in lijst = 70 punten
     - Orphan = 100 punten

3. **Clubspreiding behouden** ✅
   - Python solver doet geen clubspreiding
   - PHP doet clubspreiding NA Python solver resultaat

### Gewijzigde bestanden:

```
laravel/scripts/poule_solver.py (NIEUW)
  - Greedy++ algoritme met 4 stappen
  - Input/output via JSON stdin/stdout

laravel/scripts/test_poule_solver.py (NIEUW)
  - Test suite voor Python solver

laravel/app/Services/DynamischeIndelingService.php
  - callPythonSolver() - roept Python aan
  - findPython() - cross-platform Python detectie
  - simpleFallback() - PHP fallback
  - Verwijderd: maakPoulesGreedy, mergeKleinePoules, globaalMergeKleinePoules, balanceerPoules
```

---

## Datum: 16 januari 2026 (sessie 1)

### Wat is gedaan:

1. **KRITIEKE BUG: Classificatie doorvallen gefixed** ✅
   - **Probleem:** Judoka's vielen door naar verkeerde leeftijdscategorie als geslacht/band niet paste
   - **Voorbeeld:** 6-jarige meisje met witte band → U7 heeft `band_filter: vanaf_geel` → viel door naar U11 (FOUT!)
   - **Correct:** 6-jarige hoort ALTIJD in U7, als band niet past → "Niet gecategoriseerd"
   - **Oorzaak:** Code gebruikte `continue` en ging door naar volgende categorie i.p.v. te stoppen
   - **Oplossing:** Check nu ALLEEN categorieën met de eerste leeftijdsmatch
   - **Gefixte functies:**
     - `PouleIndelingService::classificeerJudoka()`
     - `Toernooi::bepaalLeeftijdsklasse()`
     - `Toernooi::bepaalGewichtsklasse()`

2. **Handover gecorrigeerd** ✅
   - Fout scenario uit vorige sessie verwijderd
   - Correct onderscheid toegevoegd: Niet gecategoriseerd vs Orphan

3. **PLANNING_DYNAMISCHE_INDELING.md verbeterd** ✅
   - "Harde Criteria" sectie gesplitst: categoriseren vs poule-indeling
   - Expliciete waarschuwing toegevoegd: doorvallen naar andere leeftijdscategorie VERBODEN
   - JBN tabel gecorrigeerd: U7 = max 6 jaar (niet 7)
   - Algoritme overzicht Stap 1 verduidelijkt met A/B substappen

---

## Datum: 15 januari 2026

### Wat is gedaan:

1. **Classificatie bug gefixed** ✅
   - 6-jarigen werden als "Heren" geclassificeerd i.p.v. "Jeugd"
   - Oorzaak: Auto-detect geslacht op key suffix (`_d`, `_h`) overschreef explicit `gemengd`
   - Oplossing: Check of `geslacht` expliciet 'gemengd' is voordat auto-detect triggert
   - 352 judoka's herclassificeerd

2. **Leeftijd range in poule titel gefixed** ✅
   - Na genereren ontbrak leeftijd range, na drag & drop verscheen het wel
   - Oorzaak: Blade template construeerde titel handmatig i.p.v. `$poule->titel` te gebruiken
   - Oplossing: Direct `$poule->titel` gebruiken

3. **Poule grootte optimalisatie** ✅
   - Prioriteit: 5 > 4 > 3 (niet 5+3 voor 8 judoka's, maar 4+4)
   - Nieuw algoritme: `verdeelInOptimalePoules()`, `berekenOptimaleVerdeling()`
   - Gedocumenteerd in PLANNING_DYNAMISCHE_INDELING.md

4. **Fatal error gefixed** ✅
   - `scoreVerdeling()` kwam dubbel voor met verschillende signatures
   - Nieuwe functie hernoemd naar `scorePouleGrootteVerdeling()`

5. **Debug logging verwijderd** ✅
   - Console.log statements uit edit.blade.php verwijderd

### Classificatie logica gecontroleerd:

De classificatie werkt correct:
- Categorieën gesorteerd op max_leeftijd (jongste eerst)
- Eerste match wint
- Geslacht: GEMENGD matcht alles, anders exacte match
- Band filter: `vanaf_X` of `tm_X` wordt gerespecteerd
- Als judoka in GEEN categorie past → "Niet gecategoriseerd" (melding nodig!)

### Belangrijk onderscheid:

| Concept | Niveau | Probleem |
|---------|--------|----------|
| **Niet gecategoriseerd** | Instellingen | Geen categorie past (leeftijd/geslacht/band) → melding |
| **Orphan (poule van 1)** | Poules | Wel categorie, geen gewichtsmatch |

Zie: `PLANNING_DYNAMISCHE_INDELING.md` regel 232-248

### Openstaande items:

- [ ] **Vanavond testen** - gebruiker gaat live testen
- [ ] Unit tests voor dynamische indeling (Fase 4)

### Gewijzigde bestanden:

```
laravel/app/Services/PouleIndelingService.php
  - classificeerJudoka() - fix auto-detect geslacht (regel 710-723)

laravel/app/Services/DynamischeIndelingService.php
  - scoreVerdeling() → scorePouleGrootteVerdeling() (regel 953)
  - verdeelInOptimalePoules() - nieuw algoritme
  - berekenOptimaleVerdeling() - nieuw algoritme

laravel/resources/views/pages/poule/index.blade.php
  - Gebruik $poule->titel direct i.p.v. handmatige constructie

laravel/resources/views/pages/toernooi/edit.blade.php
  - Debug console.log verwijderd

laravel/docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md
  - Classificatie logica fix gedocumenteerd
  - Poule grootte optimalisatie tabel

.claude/smallwork.md
  - Sessie 15 januari toegevoegd
```

### Branch:

`main`

### Commits vandaag:

- `fix: Use label-first lookup for category config in maakPouleTitel`
- `fix: Pass categorieKey to maakPouleTitel for correct config lookup`
- `feat: Limit poule size to max 5 judoka's in dynamic grouping`
- `feat: Simplify drag & drop title update, remove obsolete ranges span`
- `fix: Rename duplicate scoreVerdeling to scorePouleGrootteVerdeling`
