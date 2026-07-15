---
title: Mat Updates: real-time score sync
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Mat Updates: real-time score sync

> Onderdeel van [Real-time Communicatie met Laravel Reverb](../CHAT.md).

# 2. Mat Updates (Real-time Score Sync)

Real-time synchronisatie van wedstrijddata tussen Mat PWA → Publiek/Spreker/Jurytafel.

## Waarom?

- **Polling vervelend**: Pagina refresh reset tabs en scrolt naar boven
- **Snellere updates**: Direct ipv elke 15-30 seconden
- **Consistentie**: Alle displays tonen dezelfde data

## Kanalen structuur

```
toernooi.{toernooi_id}                  - Alle updates voor heel toernooi (publiek, spreker)
mat.{toernooi_id}.{mat_id}              - Specifieke mat updates (jurytafel)
```

## Event types

| Type | Wanneer | Data |
|------|---------|------|
| `score` | Score geregistreerd | wedstrijd_id, jp_wit, jp_blauw, winnaar |
| `beurt` | Groen/geel/blauw wijzigt | mat_id, groen_wedstrijd_id, geel_wedstrijd_id, blauw_wedstrijd_id |
| `poule_klaar` | Poule afgerond | poule_id, mat_id |
| `bracket` | Judoka geplaatst/verwijderd in bracket | poule_id, wedstrijd_id, actie |

## Client-side events

Views luisteren naar deze browser events:

```javascript
// Data updates
window.addEventListener('mat-update', (e) => { /* alle updates */ });
window.addEventListener('mat-score-update', (e) => { /* score wijziging */ });
window.addEventListener('mat-beurt-update', (e) => { /* groen/geel/blauw */ });
window.addEventListener('mat-poule-klaar', (e) => { /* poule afgerond */ });
window.addEventListener('mat-bracket-update', (e) => { /* bracket judoka geplaatst/verwijderd */ });

// Connectie status (voor LIVE/OFFLINE indicator)
window.addEventListener('reverb-connected', () => { /* WebSocket verbonden */ });
window.addEventListener('reverb-disconnected', () => { /* WebSocket verbroken */ });
```

## Welke views luisteren?

| View | Luistert naar | Actie |
|------|---------------|-------|
| **Publiek** | score, beurt, poule_klaar | Herlaadt matten + favorieten |
| **Spreker** | poule_klaar | Pagina reload (nieuwe uitslag) |
| **Mat Interface** | score, beurt, poule_klaar, bracket | Herlaadt wedstrijden (vervangt polling) |

## Key files (Mat Updates)

- `app/Events/MatUpdate.php` - Broadcast event (`ShouldBroadcastNow`)
- `app/Http/Controllers/MatController.php` - Dispatcht events bij wijzigingen
- `resources/views/partials/mat-updates-listener.blade.php` - Client-side WebSocket listener

## Gebruik in views

```blade
{{-- Include in views die real-time updates nodig hebben --}}
@if(config('broadcasting.default') === 'reverb')
    @include('partials.mat-updates-listener', [
        'toernooi' => $toernooi,
        'matId' => null  // null = alle matten, of specifiek mat ID
    ])
@endif
```

## Server Setup (staging)

Staging heeft een aparte Reverb instance op port 8081:

```bash
# Supervisor config: /etc/supervisor/conf.d/reverb-staging.conf
supervisorctl status reverb-staging

# Nginx proxy naar /app → 127.0.0.1:8081
```

