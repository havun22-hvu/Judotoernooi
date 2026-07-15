---
title: Services: Classifier & Indeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Services: Classifier & Indeling

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

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
- `genereerPouleIndeling()` - Maakt poules aan, roept Python solver aan per categorie
- `maakPouleTitel()` - Genereert titel
- `verplaatsJudoka()` - Verplaatst judoka naar andere poule

**BELANGRIJK: Altijd verse config lezen!**
Alle services lezen bij elke operatie de actuele config uit de database:
- `genereerPouleIndeling()` → roept `initializeFromToernooi()` + `herberkenKlassen()` aan
- `BlokMatVerdelingService` → leest direct uit `$toernooi->blokken`, `$toernooi->poules()`
- Er wordt NOOIT gecachte config hergebruikt van een vorige run

**Herclassificatie triggers:**
- Bij opslaan categorie-instellingen → `voerValidatieUit()` (JudokaController)
- Bij poule-indeling genereren → `herberkenKlassen()` (PouleIndelingService)
- Beide gebruiken `CategorieClassifier` en updaten: leeftijdsklasse, categorie_key, sort_categorie

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

