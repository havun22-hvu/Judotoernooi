# Planning: Chat functionaliteit met Laravel Reverb

## Status: Gepland (nog niet gestart)

## Overzicht

Realtime chat tussen hoofdjury en PWA's (mat, weging, spreker, dojo) via Laravel Reverb WebSockets.

## Technische keuzes

- **Laravel Reverb** - Officieel Laravel WebSocket server (gratis, self-hosted)
- **Geen polling** - Direct push via WebSockets
- **Kanaal-gebaseerd** - Elk device krijgt eigen kanaal

## Kanalen structuur

```
hoofdjury.{toernooi_id}        - Hoofdjury luistert hier (ontvangt van alle PWA's)
mat.{toernooi_id}.{mat_id}     - Per mat
weging.{toernooi_id}           - Alle weging stations
spreker.{toernooi_id}          - Spreker
dojo.{toernooi_id}             - Dojo scanner
```

## Communicatie flow

- PWA's kunnen alleen naar hoofdjury sturen
- Hoofdjury kan kiezen:
  - Alle matten (broadcast)
  - Specifieke mat (mat 1, mat 2, etc.)
  - Weging
  - Spreker
- PWA's kunnen NIET direct met elkaar communiceren

## UI Componenten

### Alle PWA's + Hoofdjury
1. **Toast notificatie** - Bij nieuw bericht, verschijnt bovenaan scherm
2. **Chat icoontje** - Altijd zichtbaar in hoek, met badge voor ongelezen
3. **Chatvenster** - Opent bij klik op icoontje, toont berichtengeschiedenis

### Hoofdjury extra
- Dropdown om ontvanger te selecteren (alle matten, mat X, weging, spreker)
- Overzicht van alle gesprekken

## Server requirements

- Reverb proces draaiend houden (supervisor/systemd)
- Poort 8080 open in firewall
- SSL voor wss:// op productie

## Installatie stappen

1. `composer require laravel/reverb` (handmatig, na overleg)
2. `php artisan reverb:install`
3. Config in `.env`
4. Supervisor config voor `php artisan reverb:start`
5. Nginx proxy voor wss://

## Database

Berichten worden opgeslagen voor geschiedenis:

```
chat_messages
- id
- toernooi_id
- van_type (hoofdjury, mat, weging, spreker, dojo)
- van_id (mat nummer, etc.)
- naar_type
- naar_id
- bericht
- gelezen_op
- created_at
```

## Prioriteit

Laag - Nice to have, niet essentieel voor eerste toernooi.
