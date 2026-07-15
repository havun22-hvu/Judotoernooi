---
title: Categorie-configuratie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Categorie-configuratie

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 3. Categorie Configuratie

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - hardcoded waarden
$maxKgVerschil = 3;
$tolerantie = 0.5;

// ❌ NOOIT DOEN - string check voor categorie type
$isDynamisch = !str_starts_with($gewichtsklasse, '-') && !str_starts_with($gewichtsklasse, '+');
```

### GOED (professioneel)

```php
// ✓ Haal waarden uit categorie config
$config = $poule->getCategorieConfig();
$maxKgVerschil = $config['max_kg_verschil'] ?? 0;
$maxLftVerschil = $config['max_leeftijd_verschil'] ?? 0;

// ✓ Haal tolerantie uit toernooi
$tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

// ✓ Check via model methode
$isDynamisch = $poule->isDynamisch();  // Checkt max_kg_verschil > 0
```

### Configuratie structuur

```php
// Categorie config voorbeeld
[
    'label' => "Mini's 4-5j",
    'min_leeftijd' => 4,
    'max_leeftijd' => 5,
    'max_kg_verschil' => 3,      // 0 = vaste klasse, >0 = variabele klasse
    'max_leeftijd_verschil' => 1,
    'gewichtsklassen' => ['-24', '-27', '-30', '+30'],  // Alleen bij vaste klassen
]
```

---

