---
title: Laravel-bestanden & app-vereisten
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Laravel-bestanden & app-vereisten

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

## Laravel Bestanden

### Nieuw
| Bestand | Doel |
|---------|------|
| `routes/api.php` | Scoreboard API routes |
| `app/Http/Controllers/Api/ScoreboardController.php` | 5 endpoints |
| `app/Http/Middleware/CheckScoreboardToken.php` | Bearer token auth |
| `app/Events/ScoreboardAssignment.php` | Wedstrijd toewijzing event |
| `app/Events/ScoreboardEvent.php` | Event-based sync naar display |
| `database/migrations/xxxx_add_scoreboard_to_device_toegangen.php` | api_token kolom |
| `resources/views/pages/mat/scoreboard-live.blade.php` | Web display (Blade + Reverb) |

### Gewijzigd
| Bestand | Wijziging |
|---------|-----------|
| `MatController.php` | Dispatch ScoreboardAssignment bij groen zetten |
| `DeviceToegang.php` | api_token field, scoreboard rol |
| `routes/channels.php` | Nieuwe WebSocket channels |
| `routes/web.php` | Route voor scoreboard-live display |
| `bootstrap/app.php` | API middleware registreren |

---

## App Vereisten

### Background service
- Timer MOET doorlopen als app geminimaliseerd wordt (foreground service)
- Sticky notification toont lopende tijd
- Android permissions: `WAKE_LOCK`, `FOREGROUND_SERVICE`, `VIBRATE`

### Offline resilience
- Timer draait ALTIJD door (geen netwerk nodig)
- Uitslag ge-queued als offline → automatisch verstuurd bij reconnect
- Keep-awake tijdens actieve wedstrijd

### Distributie
- APK via eigen server (sideloading, geen Play Store)
- OTA updates voor kleine fixes
- Version check endpoint: `GET /api/scoreboard/version`

