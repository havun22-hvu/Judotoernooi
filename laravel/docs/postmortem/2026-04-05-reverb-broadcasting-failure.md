---
title: Post-Mortem: Reverb Broadcasting Failure — LCD Scoreboard Down
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# Post-Mortem: Reverb Broadcasting Failure — LCD Scoreboard Down

**Datum:** 5 april 2026
**Impact:** Alle LCD scoreboard displays ontvingen GEEN events meer (punten, timer, wedstrijd toewijzing)
**Duur:** ~2-3 dagen (3-5 april 2026)
**Omgevingen:** Production + Staging
**Ontdekt door:** Gebruiker tijdens toernooi-test

---

## Samenvatting

Het LCD scoreboard ontving geen WebSocket events meer van de scoreboard bediening-app.
De oorzaak was een **kettingreactie van 5 afzonderlijke fouten** die in 3 dagen tijd zijn geïntroduceerd,
waarbij elke fout de diagnose van de volgende bemoeilijkte.

---

## Tijdlijn

| Wanneer | Wat | Commit | Impact |
|---------|-----|--------|--------|
| 3 apr | LCD werkt nog perfect | — | — |
| 4 apr 22:04 | SafelyBroadcasts trait toegevoegd | `7baed8df` | Alle broadcast errors worden stilletjes opgevangen |
| 4 apr 22:12 | `dispatch()` override in SafelyBroadcasts | `3044e71f` | Alle events gaan door circuit breaker |
| 4 apr 22:28 | Circuit breaker + log throttling | `3be5e7b8` | Na 3 failures → 30s stilte, logs 1x/min |
| 5 apr 11:48 | Fix trait collision | `793efe88` | `insteadof Dispatchable` — events dispatchbaar |
| 5 apr 19:29 | Fix LCD env() → config() | `e108f04b` | LCD Blade view correct na config:cache |
| 5 apr ~17:00 | config:cache op staging | deploy | `env()` calls retourneren null |

---

## De 5 fouten in detail

### Fout 1: `allowed_origins` als string i.p.v. array (ROOT CAUSE)

**Bestand:** `config/reverb.php:85`
**Oud:** `'allowed_origins' => env('REVERB_ALLOWED_ORIGINS', '...')`
**Probleem:** Reverb v1.7.0 verwacht `array`, `env()` retourneert `string`

```
TypeError: Application::__construct(): Argument #6 ($allowedOrigins)
must be of type array, string given
```

Dit was een **sluipmoordenaar**: Reverb startte prima op (`INFO Starting server on 0.0.0.0:8081`),
maar elke event-broadcast POST naar `/apps/{id}/events` crashte met een 500 Internal Server Error.
De Throwable werd gevangen door de HTTP server, niet gelogd naar het Laravel-logbestand.

**Fix:** `explode(',', env('REVERB_ALLOWED_ORIGINS', '...'))`

### Fout 2: SafelyBroadcasts maskeert alle errors

**Bestand:** `app/Events/Concerns/SafelyBroadcasts.php`
**Probleem:** Het `dispatch()` override vangt ALLE exceptions op en logt ze slechts als `debug` met throttling (1x/min).

```php
} catch (\Throwable $e) {
    static::logThrottled('Broadcast failed: ' . class_basename(static::class));
    // $e->getMessage() wordt NIET gelogd!
}
```

De controller retourneert `{"success": true}` ongeacht of de broadcast slaagde.
**Resultaat:** Niemand wist dat er iets mis was — geen errors in logs, geen error response.

### Fout 3: Circuit breaker verergert het probleem

**Bestand:** `app/Support/CircuitBreaker.php`
**Probleem:** Na 3 mislukte broadcasts (3 score-acties) gaat het circuit OPEN.
Daarna worden ALLE broadcasts 30 seconden lang overgeslagen — zonder poging.

Met de `allowed_origins` bug faalt elke broadcast → circuit staat permanent open
(elke 30s 1 test → faalt → opnieuw 30s dicht).

### Fout 4: `env()` na `config:cache`

**Bestanden:** `scoreboard-live.blade.php`, `ScoreboardController.php`
**Probleem:** Na `php artisan config:cache` retourneren alle `env()` calls `null`.

- LCD view kreeg `wssPort: 0`, `forceTLS: false`
- API gaf scoreboard app `port: 0`, `scheme: null`

### Fout 5: Dubbel procesbeheer (Supervisor + Systemd)

**Impact:** Alleen staging
**Probleem:** Zowel Supervisor als Systemd probeerden Reverb op poort 8081 te starten.
Systemd crashte 273.706 keer met `EADDRINUSE`, wat periodiek het Reverb-proces verstoorde.

---

## Waarom het zo lang onopgemerkt bleef

1. **SafelyBroadcasts slokt alle errors op** — controller zegt altijd "success"
2. **Log throttling** — slechts 1 `debug` bericht per minuut, geen error-niveau
3. **Error message ontbreekt** — alleen "Broadcast failed: ScoreboardEvent", niet de REDEN
4. **Reverb logt niets** — de TypeError wordt in de HTTP catch-all gevangen, niet door Laravel's logger
5. **Geen monitoring/health check** — niemand controleert of broadcasts daadwerkelijk aankomen

---

## Fixes toegepast

| Fix | Commit | Beschrijving |
|-----|--------|-------------|
| `allowed_origins` array | `d55b745c` | `explode()` rond env() |
| ScoreboardController config() | `d0dcb7c7` | `config('app.url')` i.p.v. `env()` |
| Systemd disabled | handmatig | `systemctl disable judotoernooi-reverb-staging` |
| Circuit breaker reset | handmatig | Cache keys verwijderd |
| Reverb herstart | handmatig | `supervisorctl restart reverb reverb-staging` |

---

## Structurele verbeteringen nodig

1. **Reverb health check command** — test of broadcast daadwerkelijk aankomt
2. **Post-deploy verificatie** — automatische broadcast test na elke deploy
3. **SafelyBroadcasts moet errors LOGGEN** — de exception message, niet alleen de class name
4. **Config validatie** — check types van kritieke config waarden bij boot
5. **Monitoring endpoint** — circuit breaker status + Reverb bereikbaarheid
6. **Tests** — unit tests voor config correctheid en broadcast flow
