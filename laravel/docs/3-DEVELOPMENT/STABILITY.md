# Stability & Error Handling - JudoToernooi

> **Doel**: Zorgen dat de applicatie stabiel blijft, ook bij onverwachte situaties.
> Een toernooi mag NOOIT vastlopen door een bug of externe service failure.

---

## Overzicht

| Pattern | Locatie | Doel |
|---------|---------|------|
| Custom Exceptions | `app/Exceptions/` | Gestructureerde error categorisatie |
| Circuit Breaker | `app/Support/CircuitBreaker.php` | Voorkom cascade failures externe services |
| Result Object | `app/Support/Result.php` | Clean error handling zonder exceptions |
| Guard Clauses | Controllers/Services | Early return bij ongeldige input |
| Error Notifications | `app/Services/ErrorNotificationService.php` | Real-time alerts naar HavunCore |
| Rate Limiting | `AppServiceProvider.php` | Bescherming tegen abuse |
| Health Check | `/health` endpoint | Monitoring & uptime checks |
| Form Requests | `app/Http/Requests/` | Centrale validatie met messages |

---

## 1. Custom Exception Classes

### Hiërarchie

```
Exception
└── JudoToernooiException (base)
    ├── MollieException        # Betalingen
    ├── ImportException        # CSV/Excel import
    └── ExternalServiceException # Python solver, HTTP clients
```

### JudoToernooiException (Base)

```php
use App\Exceptions\JudoToernooiException;

// Aanmaken
throw new JudoToernooiException(
    message: 'Technische foutmelding',
    userMessage: 'Gebruikersvriendelijke melding',
    context: ['toernooi_id' => 123]
);

// Gebruiken in controller
try {
    $this->doSomething();
} catch (JudoToernooiException $e) {
    $e->log(); // Logt automatisch met juiste level
    return back()->with('error', $e->getUserMessage());
}
```

### MollieException

```php
use App\Exceptions\MollieException;

// Factory methods
throw MollieException::apiError($endpoint, $errorMessage);
throw MollieException::timeout($endpoint);
throw MollieException::tokenExpired($organisatorId);
throw MollieException::paymentCreationFailed($details);

// Error codes
MollieException::ERROR_API          // 1001
MollieException::ERROR_TIMEOUT      // 1002
MollieException::ERROR_OAUTH        // 1003
MollieException::ERROR_TOKEN_EXPIRED // 1004
MollieException::ERROR_PAYMENT      // 1005
```

### ImportException

```php
use App\Exceptions\ImportException;

// Factory methods
throw ImportException::fileReadError($filename, $error);
throw ImportException::invalidFormat($expected, $got);
throw ImportException::missingColumns($columns);
throw ImportException::rowError($rowNumber, $error);
throw ImportException::databaseError($error);
throw ImportException::partialImport($imported, $failed, $errors);

// Row-level tracking
$errors = [];
foreach ($rows as $index => $row) {
    try {
        $this->processRow($row);
    } catch (\Exception $e) {
        $errors[] = ['row' => $index + 1, 'error' => $e->getMessage()];
    }
}
if (!empty($errors)) {
    throw ImportException::partialImport(count($rows) - count($errors), count($errors), $errors);
}
```

### ExternalServiceException

```php
use App\Exceptions\ExternalServiceException;

// Factory methods
throw ExternalServiceException::timeout($service, $timeoutSeconds);
throw ExternalServiceException::connectionFailed($service, $error);
throw ExternalServiceException::processError($service, $exitCode, $output);
throw ExternalServiceException::pythonSolverError($error, $exitCode);
```

---

## 2. Circuit Breaker

Voorkomt dat een falende externe service de hele applicatie platlegt.

### Concept

```
CLOSED (normaal) → failures >= 3 → OPEN (block calls)
                                      ↓
                            30 sec → HALF_OPEN (test call)
                                      ↓
                              success → CLOSED
                              fail → OPEN
```

### Gebruik

```php
use App\Support\CircuitBreaker;

class MollieService
{
    private CircuitBreaker $circuitBreaker;

    public function __construct()
    {
        $this->circuitBreaker = new CircuitBreaker('mollie');
    }

    private function makeApiRequest($method, $endpoint, $data, $apiKey)
    {
        return $this->circuitBreaker->call(
            // Primary action
            fn() => $this->executeApiRequest($method, $endpoint, $data, $apiKey),
            // Fallback when circuit is open
            fn() => throw MollieException::apiError($endpoint, 'Service temporarily unavailable')
        );
    }
}
```

### Configuratie

```php
// In CircuitBreaker.php
private const FAILURE_THRESHOLD = 3;  // Open after 3 failures
private const RECOVERY_TIMEOUT = 30;  // Seconds before trying again
```

---

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

## 5. Error Notification Service

Stuurt kritieke errors naar HavunCore voor remote monitoring.

### Configuratie

```env
# .env
ERROR_NOTIFICATION_WEBHOOK=https://havuncore.example.com/webhook/errors
```

```php
// config/services.php
'error_notification' => [
    'webhook_url' => env('ERROR_NOTIFICATION_WEBHOOK'),
],
```

### Automatisch (via bootstrap/app.php)

Kritieke exceptions worden automatisch gemeld:

```php
// bootstrap/app.php
$exceptions->report(function (\Throwable $e) {
    if (!app()->environment('local', 'testing')) {
        // Skip common non-critical exceptions
        $ignoredExceptions = [
            TokenMismatchException::class,
            ModelNotFoundException::class,
            NotFoundHttpException::class,
            ValidationException::class,
        ];
        // Send notification for critical errors
        app(ErrorNotificationService::class)->notifyException($e, [...]);
    }
});
```

### Handmatig

```php
use App\Services\ErrorNotificationService;

// Async notification (fire and forget)
app(ErrorNotificationService::class)->notify(
    title: 'Critical: Backup failed',
    message: 'Daily backup could not complete',
    context: ['server' => gethostname()],
    severity: 'critical'
);

// Synchronous (blocking, for critical)
$success = app(ErrorNotificationService::class)->notifyImmediate(
    title: 'Payment webhook failed',
    message: 'Multiple webhook failures detected',
    context: ['last_error' => $error]
);
```

### Payload Format

```json
{
    "app": "JudoToernooi",
    "environment": "production",
    "server": "server-name",
    "timestamp": "2026-02-02T12:00:00+01:00",
    "severity": "critical",
    "title": "Exception: Error message",
    "message": "Full error message",
    "context": {
        "exception_class": "App\\Exceptions\\MollieException",
        "file": "/var/www/app/Services/MollieService.php",
        "line": 145,
        "trace": "..."
    },
    "url": "https://judotournament.org/...",
    "user_id": 123,
    "ip": "1.2.3.4"
}
```

---

## 6. Rate Limiting

### Configuratie (AppServiceProvider)

```php
// General API: 60/min
RateLimiter::for('api', fn(Request $r) => Limit::perMinute(60)->by($r->ip()));

// Public endpoints: 30/min (favorieten, scan-qr)
RateLimiter::for('public-api', fn(Request $r) => Limit::perMinute(30)->by($r->ip()));

// Form submissions: 10/min
RateLimiter::for('form-submit', fn(Request $r) => Limit::perMinute(10)->by($r->ip()));

// Login attempts: 5/min
RateLimiter::for('login', fn(Request $r) => Limit::perMinute(5)->by($r->ip()));

// Webhooks (Mollie): 100/min
RateLimiter::for('webhook', fn(Request $r) => Limit::perMinute(100)->by($r->ip()));
```

### Routes

```php
// Webhook routes
Route::middleware('throttle:webhook')->group(function () {
    Route::post('mollie/webhook', [MollieController::class, 'webhook']);
});

// Public API
Route::middleware('throttle:public-api')->group(function () {
    Route::post('favorieten', ...);
    Route::post('scan-qr', ...);
});

// Login
Route::middleware('throttle:login')->group(function () {
    Route::post('login', ...);
});
```

---

## 7. Health Check Endpoints

### Basic Check: `/health`

```bash
curl https://judotournament.org/health
```

```json
{
    "status": "healthy",
    "timestamp": "2026-02-02T12:00:00+01:00",
    "checks": {
        "database": {"ok": true},
        "disk": {"ok": true},
        "cache": {"ok": true}
    }
}
```

### Detailed Check: `/health/detailed`

```json
{
    "status": "healthy",
    "timestamp": "2026-02-02T12:00:00+01:00",
    "environment": "production",
    "version": "1.0.0",
    "checks": {
        "database": {
            "ok": true,
            "response_time_ms": 2.5,
            "driver": "mysql"
        },
        "disk": {
            "ok": true,
            "free_gb": 45.2,
            "used_percent": 23.5
        },
        "cache": {
            "ok": true,
            "driver": "file"
        },
        "app": {
            "ok": true,
            "debug": false,
            "timezone": "Europe/Amsterdam"
        }
    }
}
```

### Response Codes

| Code | Betekenis |
|------|-----------|
| 200 | Alles healthy |
| 503 | Een of meer checks gefaald |

### Alerts

- **Disk**: Alert bij < 1GB vrij of > 90% gebruikt
- **Database**: Alert bij connection failure
- **Cache**: Alert bij cache test failure

---

## 8. Form Requests

Centrale validatie met Nederlandse foutmeldingen.

### Beschikbare Requests

| Request | Controller | Velden |
|---------|------------|--------|
| `JudokaStoreRequest` | JudokaController@store | naam, club_id, geboortejaar, geslacht, band, gewicht, telefoon |
| `JudokaUpdateRequest` | JudokaController@update | naam, geboortejaar, geslacht, band, gewicht |
| `ClubRequest` | ClubController@store/update | naam, email, email2, contact_naam, telefoon, plaats, website |
| `WegingRequest` | WegingController | gewicht, opmerking |
| `WedstrijdUitslagRequest` | MatController | wedstrijd_id, winnaar_id, score_wit, score_blauw, uitslag_type |
| `ToernooiRequest` | ToernooiController | (alle toernooi velden) |

### Gebruik

```php
// In controller - Form Request automatisch gevalideerd
public function store(JudokaStoreRequest $request, Toernooi $toernooi)
{
    $validated = $request->validated();
    // ...
}
```

### Voorbeeld Request Class

```php
class WedstrijdUitslagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'nullable|exists:judokas,id',
            'score_wit' => 'nullable|integer|in:0,1,2',
            'score_blauw' => 'nullable|integer|in:0,1,2',
            'uitslag_type' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'score_wit.in' => 'Score wit moet 0, 1 of 2 zijn',
            'score_blauw.in' => 'Score blauw moet 0, 1 of 2 zijn',
        ];
    }
}
```

---

## 9. Database Performance

### Indexes (Migration: 2026_02_02_120000)

```php
// poules
$table->index('leeftijdsklasse');
$table->index('gewichtsklasse');
$table->index('type');

// judokas
$table->index('club_id');
$table->index('aanwezigheid');
$table->index('geslacht');

// wedstrijden
$table->index('is_gespeeld');
$table->index('winnaar_id');

// poule_judoka
$table->index('judoka_id');
```

### N+1 Prevention

```php
// ❌ FOUT - N+1 queries
foreach ($judokas as $judoka) {
    echo $judoka->club->naam; // Query per judoka
}

// ✓ GOED - Eager loading
$judokas = Judoka::with('club')->get();
foreach ($judokas as $judoka) {
    echo $judoka->club->naam; // Geen extra query
}
```

---

## 10. Async Import (Optional)

Voor grote imports (> 100 judokas) is er een background job beschikbaar.

### Job: ImportJudokasJob

```php
use App\Jobs\ImportJudokasJob;

// Dispatch job
$job = new ImportJudokasJob($toernooi, $rows, $mapping, $header);
$importId = $job->getImportId();
dispatch($job);

// Check progress
$progress = ImportJudokasJob::getProgress($importId);
// {processed: 50, total: 100, percentage: 50, status: 'processing'}
```

### Progress Endpoint

```
GET /organisator/{organisator}/toernooi/{toernooi}/judoka/import/progress?import_id=xxx
```

```json
{
    "processed": 50,
    "total": 100,
    "percentage": 50,
    "status": "processing",
    "error": null,
    "updated_at": "2026-02-02T12:00:00+01:00"
}
```

### Status Values

| Status | Betekenis |
|--------|-----------|
| `starting` | Job gestart, nog geen rows verwerkt |
| `processing` | Bezig met importeren |
| `completed` | Import succesvol afgerond |
| `failed` | Import gefaald (zie error) |

---

## 11. Activity Logging

Elke belangrijke actie in de applicatie wordt gelogd in de `activity_logs` tabel. Dit biedt een audit trail zodat je altijd kunt achterhalen wie, wat, wanneer deed.

### Tabel: `activity_logs`

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| `toernooi_id` | FK | Gekoppeld toernooi |
| `actie` | string(50) | `verplaats_judoka`, `registreer_uitslag`, etc. |
| `model_type` | string(50) | `Judoka`, `Poule`, `Wedstrijd` |
| `model_id` | uint/null | ID van betreffende record |
| `beschrijving` | string | Leesbaar: "Jevi van Bussel verplaatst naar Poule 7" |
| `properties` | json/null | `{old: {...}, new: {...}, meta: {...}}` |
| `actor_type` | string(30) | `organisator`, `rol_sessie`, `device`, `systeem` |
| `actor_id` | uint/null | organisator_id of device_toegang_id |
| `actor_naam` | string(100) | "Henk (admin)" of "Weging (sessie)" |
| `ip_adres` | string(45) | Client IP |
| `interface` | string(30) | `dashboard`, `mat`, `weging`, `hoofdjury` |

### Service: `ActivityLogger`

```php
use App\Services\ActivityLogger;

// Basis logging
ActivityLogger::log($toernooi, 'verplaats_judoka', "Jevi verplaatst naar Poule 7", [
    'model' => $judoka,
    'properties' => ['van_poule' => 3, 'naar_poule' => 7],
]);
```

### Actor detectie (automatisch)

1. `auth('organisator')->user()` → organisator
2. `request()->get('device_toegang')` → device (via CheckDeviceBinding middleware)
3. `session('rol_type')` → rol_sessie
4. Fallback → 'systeem'

### Welke acties worden gelogd

**Wedstrijddag:** verplaats_judoka, nieuwe_judoka, meld_af, herstel, naar_wachtruimte, verwijder_uit_poule
**Mat:** registreer_uitslag, plaats_judoka, verwijder_judoka, poule_klaar
**Weging:** registreer_gewicht, markeer_aanwezig, markeer_afwezig
**Poules:** genereer_poules, maak_poule, verwijder_poule, verplaats_judoka
**Blokken:** sluit_weging, activeer_categorie, reset_categorie, reset_alles, reset_blok
**Toernooi:** update_instellingen, afsluiten, verwijder

### View

Route: `/{slug}/toernooi/{toernooi}/activiteiten`
Filterable tabel met actie, model type, zoekterm. Paginering 50 per pagina.

---

## Checklist voor Nieuwe Features

- [ ] Exceptions: Gebruik custom exception classes, geen generieke `\Exception`
- [ ] Logging: Context meegeven (`toernooi_id`, `user_id`, etc.)
- [ ] Guard clauses: Early return bij ongeldige input
- [ ] Null-safety: Gebruik `?->` operator voor chains
- [ ] External calls: Timeout instellen, retry logic overwegen
- [ ] Validation: Form Request gebruiken bij form submissions
- [ ] Database: Eager loading voor relations

---

*Laatst bijgewerkt: 2 februari 2026*
