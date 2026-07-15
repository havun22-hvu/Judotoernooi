---
title: JavaScript & Blade-views
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# JavaScript & Blade-views

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 4. JavaScript (Frontend)

### FOUT (amateuristisch)

```javascript
// ❌ NOOIT DOEN - string check in JS
const isVasteKlasse = klasse.startsWith('-') || klasse.startsWith('+');
```

### GOED (professioneel)

```javascript
// ✓ Backend stuurt pre-berekende waarde mee
const isVasteKlasse = judoka.is_vaste_klasse || false;
```

### API Response moet bevatten

```php
// In controller
return response()->json([
    'judoka' => [
        'id' => $judoka->id,
        'naam' => $judoka->naam,
        'gewichtsklasse' => $judoka->gewichtsklasse,
        'is_vaste_klasse' => $judoka->isVasteGewichtsklasse(),  // ← Pre-berekend!
        // ...
    ],
]);
```

---

## 5. Views (Blade Templates)

### FOUT (amateuristisch)

```blade
{{-- ❌ NOOIT DOEN - business logic in view --}}
@php
    $isPlusKlasse = str_starts_with($poule->gewichtsklasse, '+');
    $pouleLimiet = floatval(preg_replace('/[^0-9.]/', '', $poule->gewichtsklasse));
    if ($isPlusKlasse) {
        $isVerkeerdePoule = $judokaGewicht < ($pouleLimiet - $tolerantie);
    } else {
        $isVerkeerdePoule = $judokaGewicht > ($pouleLimiet + $tolerantie);
    }
@endphp
```

### GOED (professioneel)

```blade
{{-- ✓ Gebruik model methode --}}
@php
    $isAfwijkendGewicht = $judoka->isGewichtBinnenKlasse(null, $tolerantie) === false;
@endphp
```

---

