# Chat functionaliteit met Laravel Reverb

## Status: Werkend op staging en production

## Overzicht

Realtime chat tussen hoofdjury en PWA's (mat, weging, spreker, dojo) via Laravel Reverb WebSockets.

## Technische keuzes

- **Laravel Reverb** - Officieel Laravel WebSocket server (gratis, self-hosted)
- **Geen polling** - Direct push via WebSockets
- **Kanaal-gebaseerd** - Elk device krijgt eigen kanaal
- **ShouldBroadcastNow** - Directe broadcast zonder queue

## Kanalen structuur

```
chat.{toernooi_id}.hoofdjury       - Hoofdjury
chat.{toernooi_id}.mat.{mat_id}    - Per mat
chat.{toernooi_id}.weging          - Weging
chat.{toernooi_id}.spreker         - Spreker
chat.{toernooi_id}.dojo            - Dojo scanner
chat.{toernooi_id}.alle_matten     - Broadcast naar alle matten
chat.{toernooi_id}.iedereen        - Broadcast naar iedereen
```

## Communicatie flow

### Standaard (vrije chat)
- **Iedereen kan naar iedereen sturen** - PWA's en hoofdjury hebben dezelfde opties:
  - Iedereen (broadcast)
  - Alle matten
  - Specifieke mat (mat 1, mat 2, etc.)
  - Weging
  - Spreker
  - Dojo
  - Hoofdjury

### Beperkte modus (toggle door hoofdjury)
- Hoofdjury kan "vrije chat" uitschakelen via toggle in Instellingen
- Bij uitschakelen: **PWA's kunnen alleen naar hoofdjury sturen**
- Hoofdjury behoudt alle opties
- Nuttig bij misbruik door vrijwilligers

## UI Componenten

### Alle PWA's + Hoofdjury
1. **Toast notificatie** - Bij nieuw bericht, verschijnt bovenaan scherm
2. **Chat icoontje** - Altijd zichtbaar in hoek, met badge voor ongelezen
3. **Chatvenster** - Opent bij klik op icoontje, toont berichtengeschiedenis

### Hoofdjury extra
- Buttons om ontvanger te selecteren (alle matten, mat X, weging, spreker)
- Klik op inkomend bericht om direct te antwoorden aan die afzender

---

## Server Setup (voor beheerder)

### Reverb starten/herstarten

```bash
# Via supervisor (aanbevolen)
supervisorctl restart reverb
supervisorctl status reverb

# Handmatig (voor debugging)
cd /var/www/judotoernooi/laravel
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Supervisor configuratie

Bestand: `/etc/supervisor/conf.d/reverb.conf`

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/judotoernooi/laravel/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/judotoernooi/laravel/storage/logs/reverb.log
```

Na wijzigen:
```bash
supervisorctl reread
supervisorctl update
```

### Nginx configuratie

WebSocket proxy in `/etc/nginx/sites-available/judotoernooi`:

```nginx
# WebSocket proxy for Reverb
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}

location /apps {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### .env instellingen

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=judotoernooi
REVERB_APP_KEY=oixj1bggwjv8qhj3jlpb
REVERB_APP_SECRET=<secret>
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Troubleshooting

**Chat werkt niet / berichten komen niet aan:**
1. Check of Reverb draait: `supervisorctl status reverb`
2. Check logs: `tail -f /var/www/judotoernooi/laravel/storage/logs/reverb.log`
3. Check of poort 8080 luistert: `netstat -tlnp | grep 8080`
4. Herstart Reverb: `supervisorctl restart reverb`

**WebSocket connection errors in browser:**
1. Check nginx config voor `/app` location
2. `nginx -t && systemctl reload nginx`

**Meerdere Reverb processen:**
```bash
pkill -9 -f 'reverb:start'
supervisorctl start reverb
```

---

## Database

Berichten worden opgeslagen in `chat_messages`:

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| van_type | string | hoofdjury, mat, weging, spreker, dojo |
| van_id | int | Mat nummer (alleen bij mat) |
| naar_type | string | hoofdjury, mat, weging, spreker, dojo, alle_matten, iedereen |
| naar_id | int | Mat nummer (alleen bij mat) |
| bericht | text | Inhoud van het bericht |
| gelezen_op | datetime | Wanneer gelezen |
| created_at | datetime | Verzonden op |

## Key files

- `app/Events/NewChatMessage.php` - Broadcast event
- `app/Http/Controllers/ChatController.php` - API endpoints
- `app/Models/ChatMessage.php` - Model met scopes
- `resources/views/partials/chat-widget.blade.php` - PWA widget
- `resources/views/partials/chat-widget-hoofdjury.blade.php` - Hoofdjury widget
