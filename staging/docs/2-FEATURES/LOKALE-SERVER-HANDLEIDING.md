# Lokale Server Handleiding

> Stap-voor-stap gids voor het redundantie systeem

---

## Snelle Start

### 1. Installeer Laravel Herd (eenmalig)

**Windows:** https://herd.laravel.com/windows
**Mac:** https://herd.laravel.com

Na installatie heb je PHP automatisch beschikbaar.

### 2. Start de Server

**Windows:**
1. Open de `laravel` map
2. Dubbelklik op `start-server.bat`
3. Venster opent met "Server actief op http://127.0.0.1:8000"

**Mac:**
1. Open de `laravel` map
2. Dubbelklik op `start-server.command`
3. Terminal opent met "Server actief op http://127.0.0.1:8000"

### 3. Configureer de Rol

1. Open browser: http://127.0.0.1:8000/local-server/setup
2. Kies **PRIMARY** (Laptop A) of **STANDBY** (Laptop B)
3. Klik "Bevestigen"

---

## Workflow voor Wedstrijddag

### Voorbereiding (1 dag van tevoren)

```
1. Laptop A:
   - Start server (dubbelklik start-server.bat)
   - Ga naar /local-server/setup → kies PRIMARY
   - Ga naar /local-server/preflight → alle checks groen?

2. Laptop B:
   - Start server (dubbelklik start-server.bat)
   - Ga naar /local-server/setup → kies STANDBY
   - Ga naar /local-server/preflight → alle checks groen?

3. Download offline backup:
   - Ga naar Noodplan pagina
   - Klik "Download van server" (JSON backup)
   - Bewaar bestand op USB stick
```

### Op de Wedstrijddag (ochtend)

```
1. Zet Deco mesh units neer (alleen stroom!)
2. Verbind beide laptops met Deco wifi
3. Start server op Laptop A (PRIMARY)
4. Start server op Laptop B (STANDBY)
5. Open /local-server/preflight → run checks
6. Open /local-server/health-dashboard → alles groen?
```

### Tijdens het Toernooi

**Op Laptop A (PRIMARY):**
- Laat /local-server/health-dashboard open
- Toont: Cloud status, Standby status, actieve toernooien

**Op Laptop B (STANDBY):**
- Open /local-server/standby-sync
- Synct automatisch elke 5 seconden
- Toont: Primary status, sync log, data stats

---

## Controleren

### Dashboard bekijken
```
http://[laptop-ip]:8000/local-server
```
Toont: serverrol, IP, toernooien vandaag

### Health check
```
http://[laptop-ip]:8000/local-server/health-dashboard
```
Toont: Cloud sync, Standby status, alle devices

### Pre-flight check
```
http://[laptop-ip]:8000/local-server/preflight
```
Automatische checks voor hardware, software, netwerk, data

### Sync status (standby)
```
http://[laptop-ip]:8000/local-server/standby-sync
```
Real-time sync status, heartbeat, failover knop

---

## Overschakelen bij Crash (Failover)

### Als Primary crasht:

**Optie 1: Via Standby interface**
1. Ga naar Laptop B
2. Open /local-server/standby-sync
3. Als je ziet "PRIMARY OFFLINE" met rode waarschuwing
4. Klik "Activeer als Primary"
5. Ga naar /local-server/setup
6. Kies "PRIMARY"
7. In Deco app: geef Laptop B het IP van Laptop A (192.168.1.100)
8. Tablets blijven werken zonder wijzigingen

**Optie 2: Handmatig**
1. Ga naar Laptop B
2. Open /local-server/setup
3. Kies "PRIMARY"
4. In Deco app: geef Laptop B het IP van Laptop A
5. Test 1 tablet

### Tijdslijn:
- Detectie: 15 seconden (3 gemiste heartbeats)
- Overschakelen: 2-3 minuten
- Data verlies: max 5 seconden

---

## Automatisch vs Handmatig

| Wat | Automatisch? |
|-----|-------------|
| Sync Primary → Standby | ✅ Ja, elke 5 sec |
| Heartbeat monitoring | ✅ Ja, elke 5 sec |
| Waarschuwing bij crash | ✅ Ja, na 15 sec |
| Overschakelen naar Standby | ❌ Nee, handmatig |
| IP wijzigen in Deco | ❌ Nee, handmatig |

**Waarom niet volledig automatisch?**
- Voorkomt "split brain" (twee servers denken dat ze Primary zijn)
- Organisator heeft controle
- Simpeler en betrouwbaarder

---

## Troubleshooting

### "Server draait niet"
```
1. Is PHP geïnstalleerd? (Laravel Herd)
2. Draait het command venster nog?
3. Probeer: php artisan serve --port=8000
```

### "Standby ziet Primary niet"
```
1. Zijn beide laptops op hetzelfde wifi netwerk?
2. Klopt het IP adres in config/local-server.php?
3. Is de firewall uit? (Windows Defender)
```

### "Tablets kunnen niet verbinden"
```
1. Staat de server aan?
2. Klopt het IP? (check /local-server/status)
3. Zijn tablets op Deco wifi?
```

### "Sync werkt niet"
```
1. Check /local-server/standby-sync
2. Is Primary online? (groene status)
3. Klik "Force Sync" knop
```

---

## Belangrijke URLs

| URL | Functie |
|-----|---------|
| `/local-server` | Dashboard |
| `/local-server/setup` | Rol kiezen |
| `/local-server/preflight` | Pre-flight check |
| `/local-server/standby-sync` | Standby monitor |
| `/local-server/health-dashboard` | Systeem overzicht |
| `/local-server/health` | Health JSON (API) |
| `/local-server/sync` | Sync data JSON (API) |

---

## Samenvatting

```
VOOR TOERNOOI:
1. Installeer Herd (eenmalig)
2. Start servers op beide laptops
3. Configureer rollen (Primary/Standby)
4. Run pre-flight check

TIJDENS TOERNOOI:
- Standby synct automatisch
- Monitor via health-dashboard

BIJ CRASH:
1. Zie waarschuwing op Standby
2. Klik "Activeer als Primary"
3. Wijzig IP in Deco app
4. Klaar!
```
