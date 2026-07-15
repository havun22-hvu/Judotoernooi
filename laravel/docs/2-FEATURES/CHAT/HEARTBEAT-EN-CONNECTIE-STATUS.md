---
title: Heartbeat-broadcast en connectie-status indicator
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Heartbeat-broadcast en connectie-status indicator

> Onderdeel van [Real-time Communicatie met Laravel Reverb](../CHAT.md).

## Server-side Heartbeat Broadcast (Publiek PWA)

De publieke app ontvangt continu mat-state via een server-side heartbeat. Geen polling.

### Hoe het werkt

1. **Activatie**: Bij elke `MatUpdate` event → cache key `toernooi:{id}:heartbeat_active` (15 min TTL)
2. **Heartbeat command**: `php artisan toernooi:heartbeat` — long-running process (supervisor)
3. **Elke seconde**: broadcast volledige mat-state voor alle actieve toernooien via Reverb
4. **Auto-stop**: stopt broadcast na 15 min zonder activiteit (cache key vervalt)
5. **Publieke app**: ontvangt `mat.heartbeat` event met volledige mat-data, update direct in UI

### Event type: `heartbeat`

```javascript
// Publieke app ontvangt heartbeat met volledige mat-state
window.addEventListener('mat-heartbeat', (e) => {
    // e.detail.matten = volledige mat array, direct toekennen
    this.liveMatten = e.detail.matten;
});
```

### Supervisor config

```ini
[program:toernooi-heartbeat]
command=php /var/www/judotoernooi/laravel/artisan toernooi:heartbeat
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/judotoernooi/laravel/storage/logs/heartbeat.log
```

### Key files

- `app/Console/Commands/ToernooiHeartbeat.php` — long-running heartbeat command
- `app/Events/MatUpdate.php` — zet cache key bij elke actie
- `resources/views/partials/mat-updates-listener.blade.php` — client-side listener

### Geen polling meer

| View | Real-time | Polling |
|------|-----------|---------|
| Publiek | ✓ heartbeat (1s) | Geen |
| Spreker | ✓ (poule_klaar) | 10 sec |
| Mat Interface | ✓ (score, beurt, poule_klaar, bracket) | Geen |

---

# 3. Connectie Status Indicator (Publiek PWA)

De Publiek PWA toont een **LIVE/OFFLINE knop** in de header die de WebSocket verbinding toont.

## Status weergave

| Status | Kleur | Betekenis |
|--------|-------|-----------|
| 🟢 **LIVE** | Groen | Reverb WebSocket verbonden, data is real-time |
| 🔵 **OFFLINE** | Blauw (pulserend) | Geen verbinding, data kan verouderd zijn |

## Klik actie

Bij klik op de knop:
1. Knop wordt tijdelijk blauw (refresh bezig)
2. Alle data wordt opnieuw geladen (matten + favorieten)
3. WebSocket herconnect wordt getriggerd
4. Knop wordt weer groen als verbinding OK

## Technische werking

```javascript
// Events die connectie status updaten
window.addEventListener('reverb-connected', () => { isConnected = true; });
window.addEventListener('reverb-disconnected', () => { isConnected = false; });

// Bij ontvangst van data = verbonden
window.addEventListener('mat-score-update', () => { isConnected = true; });
```

## Waarom?

- Gebruiker weet of data actueel is
- Bij twijfel: klik = forceer refresh
- Visuele feedback bij verbindingsproblemen (blauw pulserend)
