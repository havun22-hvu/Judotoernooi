---
title: Reverb server-setup en troubleshooting
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Reverb server-setup en troubleshooting

> Onderdeel van [Real-time Communicatie met Laravel Reverb](../CHAT.md).

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

