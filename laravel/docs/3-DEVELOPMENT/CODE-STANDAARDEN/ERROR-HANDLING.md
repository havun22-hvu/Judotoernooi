---
title: Error handling & external service calls
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Error handling & external service calls

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 11. Error Handling

### Exception Classes

Gebruik custom exceptions voor duidelijke error categorisatie:

```
app/Exceptions/
├── JudoToernooiException.php      # Base exception (extends Exception)
├── MollieException.php            # Betalingen (extends JudoToernooiException)
├── ImportException.php            # Import fouten (extends JudoToernooiException)
└── ExternalServiceException.php   # Externe APIs/Python solver (extends JudoToernooiException)
```

### FOUT (amateuristisch)

```php
// ❌ NOOIT DOEN - generieke exception
throw new \Exception('Mollie API error: ' . $response->body());

// ❌ NOOIT DOEN - geen context bij logging
Log::error('Fout!');

// ❌ NOOIT DOEN - technische foutmelding aan gebruiker
return back()->with('error', $e->getMessage());
```

### GOED (professioneel)

```php
// ✓ Gebruik custom exception met context
throw MollieException::apiError('/payments', $response->body(), $response->status());

// ✓ Log met context
Log::error('Payment creation failed', [
    'toernooi_id' => $toernooi->id,
    'user_id' => auth()->id(),
    'error' => $e->getMessage(),
]);

// ✓ Gebruikersvriendelijke melding
return back()->with('error', $e->getUserMessage());
```

### Controller Pattern

```php
try {
    // Business logic
} catch (JudoToernooiException $e) {
    $e->log(); // Logt met juiste level en context
    return back()->with('error', $e->getUserMessage());
} catch (\Exception $e) {
    Log::error('Unexpected error', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    return back()->with('error', 'Er ging iets mis. Probeer opnieuw.');
}
```

### Log Levels

| Level | Wanneer | Voorbeeld |
|-------|---------|-----------|
| `Log::error()` | Systeem errors, crashes | Database connectie faalt |
| `Log::warning()` | Business logic failures | Betaling geannuleerd |
| `Log::info()` | Belangrijke events | Betaling geslaagd, import voltooid |
| `Log::debug()` | Development | Python solver output |

### Context Meegeven

```php
Log::info('Payment completed', [
    'user_id' => auth()->id(),
    'toernooi_id' => $toernooi->id,
    'payment_id' => $payment->id,
    'amount' => $payment->amount,
]);
```

### Database Transactions

```php
// ✓ Gebruik transactions voor meerdere DB writes
DB::transaction(function () use ($data) {
    // Multiple database operations
}, 3); // 3 retries on deadlock
```

---

## 12. External Service Calls

### Timeout Configuratie

```php
// ✓ Altijd timeout instellen
Http::timeout(15)
    ->connectTimeout(5)
    ->post($url, $data);
```

### Retry Logic

```php
// ✓ Retry voor transient errors
$response = Http::retry(2, 500) // 2 retries, 500ms sleep
    ->timeout(15)
    ->get($url);
```

### Fallback Strategie

```php
// ✓ Graceful degradation
try {
    $result = $this->pythonSolver($data);
} catch (ExternalServiceException $e) {
    $e->log();
    $result = $this->simpleFallback($data); // Altijd werkende fallback
}
```

---

