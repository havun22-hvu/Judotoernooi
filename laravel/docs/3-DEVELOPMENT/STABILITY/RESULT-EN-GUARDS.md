---
title: Result Object Pattern & Guard Clauses
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Result Object Pattern & Guard Clauses

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

## 3. Result Object Pattern

Voor operaties waar exceptions niet gepast zijn (bijv. validation, business rules).

### Gebruik

```php
use App\Support\Result;

// Success
return Result::success($judoka);

// Failure
return Result::failure('Judoka niet gevonden');

// Consuming
$result = $this->findJudoka($id);
if ($result->isSuccess()) {
    $judoka = $result->getValue();
} else {
    return back()->with('error', $result->getError());
}

// Chaining met map()
$result = $this->findJudoka($id)
    ->map(fn($judoka) => $judoka->naam)
    ->map(fn($naam) => strtoupper($naam));

// JSON response
return $result->toResponse();
// Success: {"success": true, "data": ...}
// Failure: {"success": false, "error": "..."}, 422
```

---

## 4. Guard Clauses

Early return bij ongeldige input of state.

### FOUT

```php
public function updateScore($wedstrijd, $score)
{
    if ($wedstrijd) {
        if ($wedstrijd->poule) {
            if ($wedstrijd->poule->blok) {
                $toernooi = $wedstrijd->poule->blok->toernooi;
                if ($toernooi) {
                    // actual logic 5 levels deep
                }
            }
        }
    }
}
```

### GOED

```php
public function updateScore($wedstrijd, $score)
{
    if (!$wedstrijd) {
        return response()->json(['error' => 'Wedstrijd niet gevonden'], 404);
    }

    $toernooi = $wedstrijd->poule?->blok?->toernooi;
    if (!$toernooi) {
        return response()->json(['error' => 'Geen gekoppeld toernooi'], 400);
    }

    // actual logic at top level
}
```

### Null-Safe Operator

```php
// ✓ PHP 8 null-safe operator
$toernooi = $wedstrijd->poule?->blok?->toernooi;

// ✓ Guard clause na null-safe
if (!$toernooi) {
    return back()->with('error', 'Toernooi niet gevonden');
}
```

---

