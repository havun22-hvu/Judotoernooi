---
title: Stabiliteitspatronen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Stabiliteitspatronen

> De patronen die de app overeind houden als er iets stukgaat: exceptions, circuit breaker,
> guard clauses, rate limiting, health checks. Bouw je een feature? De checklist onderaan is
> het minimum. **Index-doc** — de patronen zelf staan in [`STABILITY/`](STABILITY/).

## Overzicht

| Pattern | Locatie | Doel |
|---------|---------|------|
| Custom Exceptions | `app/Exceptions/` | Gestructureerde error categorisatie |
| Circuit Breaker | `app/Support/CircuitBreaker.php` | Voorkom cascade failures externe services |
| SafelyBroadcasts | `app/Events/Concerns/SafelyBroadcasts.php` | Broadcast nooit crasht app (Reverb down) |
| migrate:fresh blokkade | `AppServiceProvider.php` | Voorkom data verlies op server |
| Result Object | `app/Support/Result.php` | Clean error handling zonder exceptions |
| Guard Clauses | Controllers/Services | Early return bij ongeldige input |
| Error Notifications | `app/Services/ErrorNotificationService.php` | Real-time alerts naar HavunCore |
| Rate Limiting | `AppServiceProvider.php` | Bescherming tegen abuse |
| Health Check | `/health` endpoint | Monitoring & uptime checks |
| Form Requests | `app/Http/Requests/` | Centrale validatie met messages |

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [EXCEPTIONS](STABILITY/EXCEPTIONS.md) | Je roept iets externs aan en moet een eigen exception gooien in plaats van `\Exception`. |
| [CIRCUIT-BREAKER](STABILITY/CIRCUIT-BREAKER.md) | Nieuwe externe dienst (Mollie, Reverb, HavunClub) — verplicht vóór je live gaat. |
| [RESULT-EN-GUARDS](STABILITY/RESULT-EN-GUARDS.md) | Een service die kan falen zonder dat het een exception is; en hoe je nesting weghaalt. |
| [ERROR-NOTIFICATION](STABILITY/ERROR-NOTIFICATION.md) | Er moet iemand gewaarschuwd worden als het misgaat. |
| [RATE-LIMIT-HEALTH-FORMS](STABILITY/RATE-LIMIT-HEALTH-FORMS.md) | Nieuw API-endpoint, login of webhook (throttle verplicht), health-endpoint, of user-input die een Form Request nodig heeft. |
| [PERFORMANCE-LOGGING](STABILITY/PERFORMANCE-LOGGING.md) | N+1 en indexen, zware import naar een queue, of een kritieke actie die in het audit log moet. |

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
