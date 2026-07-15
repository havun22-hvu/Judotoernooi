---
title: Gewichtsklasse-checks & pouletitels
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Gewichtsklasse-checks & pouletitels

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 1. Gewichtsklasse Checks

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - string prefix check
$isVasteKlasse = str_starts_with($gewichtsklasse, '-') || str_starts_with($gewichtsklasse, '+');

// ❌ NOOIT DOEN - regex op gewichtsklasse string
$isPlusKlasse = str_starts_with($klasse, '+');
$limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));
if ($isPlusKlasse) {
    $pastInKlasse = $gewicht >= ($limiet - $tolerantie);
} else {
    $pastInKlasse = $gewicht <= ($limiet + $tolerantie);
}
```

### GOED (professioneel)

```php
// ✓ Gebruik model methode - checkt via categorie config (max_kg_verschil)
$isVasteKlasse = $judoka->isVasteGewichtsklasse();

// ✓ Gebruik model methode voor gewicht check
$pastInKlasse = $judoka->isGewichtBinnenKlasse(null, $tolerantie);
```

### Beschikbare methodes

| Methode | Doel | Bron |
|---------|------|------|
| `Judoka::isVasteGewichtsklasse()` | Check of vaste klasse (max_kg_verschil = 0) | Categorie config via poule |
| `Judoka::isGewichtBinnenKlasse()` | Check of gewicht binnen limiet | Model methode |
| `Judoka::getEffectiefGewicht()` | Haal gewicht op (gewogen > ingeschreven > klasse) | Model methode |
| `Poule::isDynamisch()` | Check of variabele klasse (max_kg_verschil > 0) | Categorie config |

---

## 2. Poule Titels

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - directe database kolom
$titel = $poule->titel;

// ❌ NOOIT DOEN - regex op titel string
preg_match('/(\d+-\d+j)/', $poule->titel, $lftMatch);
preg_match('/([\d.]+)-([\d.]+)kg/', $poule->titel, $kgMatch);
```

### GOED (professioneel)

```php
// ✓ Gebruik model methode - berekent live uit judoka's
$titel = $poule->getDisplayTitel();

// ✓ Gebruik model methodes voor ranges
$leeftijdRange = $poule->getLeeftijdsRange();  // ['min_jaar' => 4, 'max_jaar' => 6, 'range' => 2]
$gewichtRange = $poule->getGewichtsRange();    // ['min_kg' => 16.0, 'max_kg' => 18.6, 'range' => 2.6]
```

### Beschikbare methodes

| Methode | Doel | Bron |
|---------|------|------|
| `Poule::getDisplayTitel()` | Live titel met ranges | Berekend uit judoka's |
| `Poule::getLeeftijdsRange()` | Leeftijdsrange actieve judoka's | Berekend uit judoka's |
| `Poule::getGewichtsRange()` | Gewichtsrange gewogen judoka's | Berekend uit judoka's |
| `Poule::updateTitel()` | Update database titel (bij verplaatsing) | Wordt automatisch aangeroepen |

---

