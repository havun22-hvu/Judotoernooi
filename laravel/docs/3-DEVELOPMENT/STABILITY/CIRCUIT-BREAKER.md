---
title: Circuit Breaker
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Circuit Breaker

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

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

### Reverb/Broadcast Bescherming

Alle broadcast events gebruiken de `SafelyBroadcasts` trait die `dispatch()` overschrijft met 3 lagen bescherming:

```
Laag 1: Circuit Breaker  → Na 3 failures: skip broadcasts 30s (fail-fast, geen timeout)
Laag 2: Try-catch         → Exceptions worden gelogd op WARNING niveau met error message
Laag 3: Log throttling    → Max 1 logmelding per minuut per event type (geen spam)
```

**Hoe het werkt:**

```php
// In elk broadcast event — LET OP: insteadof is VERPLICHT!
class MatUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Concerns\SafelyBroadcasts {
        Concerns\SafelyBroadcasts::dispatch insteadof Dispatchable;
    }

    // Normale code — geen speciale aanroep nodig
}

// In controllers — gewoon dispatch() gebruiken:
MatUpdate::dispatch($toernooiId, $matId, 'score', $data);
// ^ Automatisch beschermd. Data is altijd in DB, broadcast is best-effort.
```

> **⚠️ KRITIEK: `insteadof` is VERPLICHT!**
> Zonder `insteadof` botst `SafelyBroadcasts::dispatch()` met `Dispatchable::dispatch()`
> → FatalError → ALLE broadcasts voor dit event crashen stilletjes.
> **Incident 3-5 apr 2026:** LCD scoreboard volledig kapot door deze collision.
> Zie `docs/postmortem/` voor het volledige verslag.

**Beschermde events:**
- `MatUpdate` — score, beurt, poule updates
- `ScoreboardEvent` — scorebord sync
- `ScoreboardAssignment` — wedstrijd toewijzing aan scorebord
- `NewChatMessage` — chat berichten
- `MatHeartbeat` — periodieke mat status (via `broadcast()` helper + eigen try-catch)

**Trait locatie:** `app/Events/Concerns/SafelyBroadcasts.php`

**Belangrijk:** Bij het aanmaken van een nieuw broadcast event ALTIJD `use SafelyBroadcasts;` toevoegen met de `insteadof` syntax hierboven. De trait overschrijft `dispatch()` zodat het automatisch werkt — geen speciale method nodig.

### Waarom geen `ShouldQueue` op broadcast events (besluit 15-07-2026)

Terugkerend voorstel: `ShouldBroadcastNow` → `ShouldQueue` zou retry geven bij Reverb-uitval.
**Niet doen.** Drie redenen, in volgorde van belang:

1. **Latency.** De worker draait `queue:work --sleep=3` (`/etc/supervisor/conf.d/laravel-worker.conf`).
   Bij een lege queue — de normale toestand tussen twee scores — slaapt hij, dus een score komt pas na
   gemiddeld 1,5s en maximaal 3s aan. Op een scorebord is een verouderde score erger dan geen score:
   de volgende update overschrijft 'm toch.
2. **Retry lost het niet op.** De 8 `failed_jobs` van 04-04-2026 wáren queued (`NewChatMessage`,
   `database@default`) en faalden alsnog na 3 pogingen. Ligt Reverb langer plat dan het retry-venster,
   dan helpt de queue niet; ligt hij korter plat, dan is de data al achterhaald.
3. **Het sloopt de circuit breaker.** `SafelyBroadcasts` meet of de broadcast zélf lukt. Met
   `ShouldQueue` meet de breaker nog maar het wegschrijven naar de `jobs`-tabel — dat lukt altijd, ook
   als Reverb dood is. De bescherming wordt betekenisloos en de echte fout verdwijnt naar `failed_jobs`,
   waar niemand kijkt.

Het incident van 04-04 is opgelost met deze trait, niet met een queue. Broadcast is best-effort;
de data staat altijd in de DB.

### Reverb Config Regels

| Regel | Waarom |
|-------|--------|
| `allowed_origins` in `config/reverb.php` MOET een **array** zijn | Reverb v1.7.0 verwacht array → TypeError bij string |
| NOOIT `env()` in Blade views | Na `config:cache` retourneert `env()` NULL → gebruik `config()` |
| `BroadcastConfigValidator` checkt types bij boot | Logt CRITICAL bij foute config types |
| `php artisan reverb:health` na elke deploy | Test config + server + broadcast + circuit breaker reset |

**Tests:** `ReverbConfigTest` (9 tests) + `ReverbHealthCheckTest` (4 tests) bewaken deze regels.

### Reverb Infrastructuur

Supervisor wrapper scripts op de server ruimen automatisch zombie processen op bij (her)start:
- `/usr/local/bin/reverb-prod-start.sh` — killt poort 8080 zombies, start Reverb
- `/usr/local/bin/reverb-staging-start.sh` — killt poort 8081 zombies, start Reverb

### Configuratie

```php
// Circuit Breaker defaults voor Reverb
new CircuitBreaker(
    service: 'reverb',
    failureThreshold: 3,   // Open na 3 failures
    recoveryTimeout: 30,   // 30 sec wachten voor retry
);
```

---

