---
title: Rate Limiting, Health Checks & Form Requests
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Rate Limiting, Health Checks & Form Requests

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

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

