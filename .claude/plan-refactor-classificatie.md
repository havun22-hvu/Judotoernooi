# Plan: Refactor Classificatie en Sortering

## Samenvatting

**Doel:** Verwijder enum dependency, gebruik preset config als source of truth, vereenvoudig sortering.

## Huidige Problemen

1. **Leeftijdsklasse Enum** - Hardcoded JBN2025, werkt niet met andere presets
2. **Labels** - Komen uit enum ipv preset config
3. **Judoka codes** - Complex format (`U1234M01`), moeilijk te begrijpen/debuggen
4. **Hardcoded mappings** - Op 3+ plekken dezelfde mapping

## Nieuwe Aanpak

### 1. Labels uit Preset Config

**Source of truth:** `toernooi->gewichtsklassen` (de actieve preset)

```php
// Classificatie
$configKey = $this->findConfigKeyForJudoka($judoka, $toernooi);
$config = $gewichtsklassenConfig[$configKey];
$label = $config['label']; // "Mini's", "U11 Heren", etc.

// Opslaan
$judoka->leeftijdsklasse = $label;
```

### 2. Nieuwe Sorteer Velden

**Verwijder:** `judoka_code` (complex string format)

**Toevoegen aan `judokas` tabel:**

| Veld | Type | Beschrijving |
|------|------|--------------|
| `sort_categorie` | int | Volgorde uit config (0, 1, 2, ...) |
| `sort_gewicht` | int | Gewicht in grammen (30500 = 30.5kg) |
| `sort_band` | int | Band niveau (1=wit, 2=geel, ..., 10=zwart) |
| `categorie_key` | string | Config key voor interne lookup |

**Sortering wordt:**
```sql
ORDER BY sort_categorie ASC, sort_gewicht ASC, sort_band ASC
```

### 3. Band Niveau Mapping

| Band | Niveau |
|------|--------|
| wit | 1 |
| geel | 2 |
| oranje | 3 |
| groen | 4 |
| blauw | 5 |
| bruin | 6 |
| zwart | 7 |

### 4. Classificatie Logica

```
Voor elke judoka:
1. Bereken leeftijd (toernooi jaar - geboortejaar)
2. Loop door config categorieën (in volgorde)
3. Check harde criteria:
   - max_leeftijd: leeftijd <= config.max_leeftijd
   - geslacht: config.geslacht == 'gemengd' OR config.geslacht == judoka.geslacht
   - band_filter: judoka.band voldoet aan filter (t/m X of vanaf X)
4. Eerste match = categorie van judoka
5. Sla op:
   - leeftijdsklasse = config.label
   - categorie_key = config key
   - sort_categorie = index in config array
```

### 5. Gewichtsklasse Bepaling

**Bij vaste klassen (max_kg_verschil = 0):**
```
- Lees gewichten uit config['gewichten']
- Bepaal klasse op basis van gewicht + tolerantie
- Sla op in gewichtsklasse veld
```

**Bij dynamisch (max_kg_verschil > 0):**
```
- Geen vaste gewichtsklasse
- gewichtsklasse = null of leeg
- Indeling gebeurt later bij poule generatie
```

## Database Wijzigingen

### Migration: add_sort_fields_to_judokas

```php
Schema::table('judokas', function (Blueprint $table) {
    $table->unsignedSmallInteger('sort_categorie')->default(0)->after('judoka_code');
    $table->unsignedInteger('sort_gewicht')->default(0)->after('sort_categorie');
    $table->unsignedTinyInteger('sort_band')->default(0)->after('sort_gewicht');
    $table->string('categorie_key', 50)->nullable()->after('sort_band');

    // Index voor sortering
    $table->index(['toernooi_id', 'sort_categorie', 'sort_gewicht', 'sort_band'], 'judokas_sort_index');
});
```

### Optioneel later: Verwijder judoka_code

Na succesvolle migratie kan `judoka_code` verwijderd worden (aparte migration).

## Code Wijzigingen

### PouleIndelingService.php

**Verwijderen:**
- `use App\Enums\Leeftijdsklasse`
- `bepaalGewichtsklasseVoorLeeftijd()` met enum parameter
- Hardcoded `getLeeftijdOrder()` array
- `herberekenJudokaCodes()` (vervangen door nieuwe methode)

**Toevoegen:**
- `classificeerJudoka(Judoka $judoka, Toernooi $toernooi): array`
- `updateSorteerVelden(Judoka $judoka, Toernooi $toernooi): void`
- `getBandNiveau(string $band): int`
- `bepaalGewichtsklasseUitConfig(float $gewicht, array $config): ?string`

**Aanpassen:**
- `herberkenKlassen()` - gebruik nieuwe classificatie
- `groepeerJudokas()` - sorteer op nieuwe velden

### Leeftijdsklasse.php (Enum)

**Markeren als deprecated:**
```php
/**
 * @deprecated Gebruik preset config voor classificatie
 * Deze enum blijft alleen voor legacy compatibility
 */
enum Leeftijdsklasse: string
```

### PouleController.php

**Verwijderen:**
- Hardcoded `$leeftijdsklasseVolgorde` array
- Hardcoded `$leeftijdsklasseToKey` mapping

## Uitvoering Volgorde

1. **Docs bijwerken** - PLANNING_DYNAMISCHE_INDELING.md
2. **Migration maken** - Nieuwe sorteer velden
3. **Helper methodes** - getBandNiveau(), classificeerJudoka()
4. **Refactor herberkenKlassen()** - Nieuwe logica
5. **Refactor groepeerJudokas()** - Gebruik nieuwe velden
6. **Update controller** - Verwijder hardcoded arrays
7. **Markeer enum deprecated**
8. **Testen** - JBN2025, JBN2026, eigen preset
9. **Commit per stap**

## Backwards Compatibility

- `judoka_code` blijft voorlopig bestaan (niet verwijderen)
- Bestaande judoka's worden bijgewerkt bij volgende `herberkenKlassen()`
- Oude sorteer logica kan fallback zijn tot migratie compleet is
