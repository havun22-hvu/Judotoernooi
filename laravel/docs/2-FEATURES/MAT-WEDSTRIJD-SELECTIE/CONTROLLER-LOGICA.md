---
title: Controller-logica en doorschuiving
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Controller-logica en doorschuiving

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## Controller Logica

### MatController@setWedstrijdStatus

```php
public function setWedstrijdStatus(Request $request, Mat $mat)
{
    $validated = $request->validate([
        'actieve_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        'volgende_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        'gereedmaken_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
    ]);

    // Valideer dat wedstrijden bij poules op deze mat horen
    foreach (['actieve', 'volgende', 'gereedmaken'] as $type) {
        $key = "{$type}_wedstrijd_id";
        if ($validated[$key]) {
            $wedstrijd = Wedstrijd::find($validated[$key]);
            if ($wedstrijd->poule->mat_id !== $mat->id) {
                return response()->json(['error' => 'Wedstrijd hoort niet bij deze mat'], 422);
            }
        }
    }

    $mat->update($validated);

    return response()->json(['success' => true, 'mat' => [
        'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
        'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
        'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
    ]]);
}
```

### Doorschuiving na deselectie groen

```php
// Bij deselectie van groen (wedstrijd stoppen)
$mat->update([
    'actieve_wedstrijd_id' => $mat->volgende_wedstrijd_id,    // geel → groen
    'volgende_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id, // blauw → geel
    'gereedmaken_wedstrijd_id' => null,                        // blauw = null
]);
```

### Doorschuiving na deselectie geel

```php
// Bij deselectie van geel
$mat->update([
    'volgende_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id, // blauw → geel
    'gereedmaken_wedstrijd_id' => null,                        // blauw = null
]);
```

---

