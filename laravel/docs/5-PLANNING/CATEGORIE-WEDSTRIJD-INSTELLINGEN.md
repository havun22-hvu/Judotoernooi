# Feature: Wedstrijdinstellingen per Categorie

> **Status:** OPEN
> **Prioriteit:** Hoog — nodig voor correcte scoreboard/LCD werking
> **Aanleiding:** LCD toonde 4:00 ipv 3:00, hardcoded waarden vervangen door config

## Doel

Per categorie in `gewichtsklassen` JSON drie extra velden:

| Veld | Type | Default | Beschrijving |
|------|------|---------|-------------|
| `shiai_time` | int (seconden) | 180 | Wedstrijdtijd (bv. 120 voor mini's, 180 voor pupillen, 240 voor senioren) |
| `shime_waza` | bool | false | Wurging toegestaan |
| `kansetsu_waza` | bool | false | Armklem toegestaan |

## IJF Standaard (referentie)

| Categorie | Leeftijd | Wedstrijdtijd | Shime waza | Kansetsu waza |
|-----------|----------|--------------|------------|---------------|
| Mini's | 4-6 | 2:00 (120s) | Nee | Nee |
| Pupillen | 7-9 | 2:00 (120s) | Nee | Nee |
| Aspiranten | 10-11 | 3:00 (180s) | Nee | Nee |
| Cadetten | 12-14 | 3:00 (180s) | Ja (≥13) | Nee |
| Junioren | 15-17 | 4:00 (240s) | Ja | Ja |
| Senioren | 18+ | 4:00 (240s) | Ja | Ja |

## Implementatie

### 1. Edit form (`edit.blade.php`)

In het categorie blok, na de bestaande velden (max kg, max leeftijd, band grens):

```html
<!-- Wedstrijdtijd -->
<select class="shiai-time-select">
    <option value="120">2:00 min</option>
    <option value="180" selected>3:00 min</option>
    <option value="240">4:00 min</option>
    <option value="300">5:00 min</option>
</select>

<!-- Wurging (shime waza) -->
<input type="checkbox" class="shime-waza-checkbox">
<label>Shime waza</label>

<!-- Armklem (kansetsu waza) -->
<input type="checkbox" class="kansetsu-waza-checkbox">
<label>Kansetsu waza</label>
```

### 2. Save logica (`edit.blade.php` JS)

In de `updateGewichtsklassenJson()` functie, toevoegen aan `entry`:
```javascript
entry.shiai_time = parseInt(item.querySelector('.shiai-time-select')?.value) || 180;
entry.shime_waza = item.querySelector('.shime-waza-checkbox')?.checked || false;
entry.kansetsu_waza = item.querySelector('.kansetsu-waza-checkbox')?.checked || false;
```

### 3. Render logica (`edit.blade.php` JS)

In de `renderCategorieItem()` sectie, waarden uitlezen:
```javascript
const shiaiTime = item.shiai_time || 180;
const shimeWaza = item.shime_waza || false;
const kansetsuWaza = item.kansetsu_waza || false;
```

### 4. Toernooi model

`getMatchDuration()` moet per categorie werken:
```php
public function getMatchDurationForCategorie(?string $categorieKey): int
{
    $klassen = $this->gewichtsklassen ?? [];
    return $klassen[$categorieKey]['shiai_time'] ?? 180;
}
```

### 5. Doorvoeren naar scoreboard

De `match_duration` in ScoreboardController en MatController moet de categorie van de wedstrijd meenemen:
```php
'match_duration' => $toernooi->getMatchDurationForCategorie($wedstrijd->poule?->categorie_key),
```

De `shime_waza` en `kansetsu_waza` moeten meegestuurd worden zodat de scoreboard app weet welke technieken zijn toegestaan:
```php
'shime_waza' => $toernooi->gewichtsklassen[$categorieKey]['shime_waza'] ?? false,
'kansetsu_waza' => $toernooi->gewichtsklassen[$categorieKey]['kansetsu_waza'] ?? false,
```

### 6. LCD display

`scoreboard-live.blade.php` ontvangt `match_duration` al — dat werkt automatisch.
Optioneel: toon iconen voor shime/kansetsu waza status op het display.

## Bestanden

| Bestand | Wijziging |
|---------|-----------|
| `resources/views/pages/toernooi/edit.blade.php` | 3 form velden per categorie |
| `app/Models/Toernooi.php` | `getMatchDurationForCategorie()` method |
| `app/Http/Controllers/MatController.php` | Lees categorie-specifieke duration |
| `app/Http/Controllers/Api/ScoreboardController.php` | Idem + shime/kansetsu meesturen |
| `resources/views/pages/mat/scoreboard-live.blade.php` | Optioneel: shime/kansetsu iconen |

## Geen migratie nodig

Alles zit in de bestaande `gewichtsklassen` JSON kolom.
