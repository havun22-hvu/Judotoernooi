# Session Handover: 1 februari 2026

## 🚀 Startinstructies voor nieuwe sessie

**Lees deze docs in volgorde:**

1. `.claude/handover.md` ← Dit bestand (startpunt + roadmap)
2. `laravel/docs/3-TECHNICAL/REDUNDANTIE.md` ← Technische specs, architectuur, mockups
3. `laravel/docs/2-FEATURES/NOODPLAN-HANDLEIDING.md` ← Hoe het voor eindgebruiker moet werken

**Optioneel (als context nodig):**
- `laravel/docs/4-PLANNING/PLANNING_NOODPLAN.md` - Bestaande noodplan features
- `CLAUDE.md` - Project regels en conventies

**Start implementatie:**
```
Lees eerst .claude/handover.md, dan laravel/docs/3-TECHNICAL/REDUNDANTIE.md,
en begin met Fase 1 van de implementatie.
```

---

## Context
Gebruiker wil Enterprise Redundantie implementeren voor grote toernooien. Documentatie is COMPLEET, implementatie moet nog gebeuren.

## Documentatie (KLAAR)

| Document | Beschrijving |
|----------|--------------|
| `laravel/docs/3-TECHNICAL/REDUNDANTIE.md` | Technisch plan - architectuur, failover, specs |
| `laravel/docs/2-FEATURES/NOODPLAN-HANDLEIDING.md` | Praktische gids voor leken |
| `laravel/docs/4-PLANNING/PLANNING_NOODPLAN.md` | Sectie 9 verwijst naar beide docs |

## Implementatie Roadmap (TODO)

### Fase 1a: Database Download + Offline Print (MVP - snel waarde) ✅ KLAAR
1. **Database download/export functie** ✅
   - JSON download van server of localStorage
   - JSON bestand laden voor offline gebruik
   - Offline detectie banner

2. **Offline matrix print vanuit localStorage** ✅
   - Live wedstrijd schema's werkt offline
   - JSON backup kan worden ingeladen

### Fase 1b: Lokale Server Launcher ✅ KLAAR
1. **Laravel Herd installatie** ✅
   - Instructies in NOODPLAN-HANDLEIDING.md
   - start-server.bat (Windows)
   - start-server.command (Mac)

2. **Server rol configuratie scherm** ✅
   - `/local-server/setup` - Rol kiezen (Primary/Standby)
   - `/local-server` - Dashboard met status
   - Config opslag in `.env`

3. **Sync API** ✅
   - `/local-server/sync` - Alle toernooi data
   - `/local-server/heartbeat` - Heartbeat endpoint
   - `/local-server/health` - Health check

### Fase 2: Hot Standby ✅ KLAAR
1. **Sync API tussen Primary en Standby** ✅
   - `/local-server/sync` - Alle toernooi data
   - `/local-server/receive-sync` - Standby ontvangt data
   - Elke 5 sec sync

2. **Heartbeat monitoring** ✅
   - `/local-server/heartbeat` - Heartbeat endpoint
   - 3 gemiste pings = alert
   - Failover knop verschijnt

3. **Standby sync UI** ✅
   - `/local-server/standby-sync` - Real-time sync status
   - Toont primary status, sync stats, log
   - "Activeer als Primary" knop

4. **Health dashboard** ✅
   - `/local-server/health-dashboard` - Systeem overzicht
   - Cloud status, standby status, devices

### Fase 3: Enterprise Features ✅ KLAAR
- Health dashboard ✅ `/local-server/health-dashboard`
- Pre-flight check wizard ✅ `/local-server/preflight`
- Automatic failover ✅ "Activeer als Primary" knop in standby-sync

## Architectuurbeslissingen (31 jan 2026)

| Vraag | Beslissing | Reden |
|-------|------------|-------|
| **PHP bundelen** | Laravel Herd | Gratis, cross-platform, makkelijkste voor leken |
| **IP failover** | Deco IP reservation switch | Standby krijgt IP van Primary via Deco app, tablets hoeven niks te wijzigen |
| **Database** | SQLite lokaal, MySQL cloud | Code is al database-agnostic (Eloquent) |
| **Conflict resolution** | Last-write-wins | Wedstrijden worden lokaal ingevoerd, cloud sync is alleen backup |
| **MVP scope** | Splitsen in 1a en 1b | Fase 1a (download+print) geeft snel waarde zonder PHP op client |

## Technische Details

### Hardware vereisten (zie REDUNDANTIE.md sectie 12)
- Windows 10+ of macOS 10.15+
- 4GB RAM minimum
- Elke laptop na 2018 voldoet
- Chromebooks/tablets werken NIET als server

### Netwerk setup
- Deco M4 mesh (3 units)
- Primary IP: 192.168.1.100
- Standby IP: 192.168.1.101
- Geen internet vereist voor lokale werking

### Bestaande code om te gebruiken
- `NoodplanController::syncData()` - JSON export van alle poule data
- localStorage sync al actief in `layouts/app.blade.php`
- Print layouts in `resources/views/pages/noodplan/`

## Belangrijke punten

1. **Gebruiker kiest rol expliciet** - geen automatische detectie
2. **Online modus blijft werken** - lokaal is uitbreiding, geen vervanging
3. **Documentatie is leidend** - implementeer volgens de specs in REDUNDANTIE.md
4. **Leken-vriendelijk** - organisatoren zijn geen IT'ers

## Gemaakte Files

```
# Controllers
app/Http/Controllers/LocalSyncController.php  - Alle sync en setup logica

# Config
config/local-server.php                       - Netwerk en sync instellingen

# Views
resources/views/local/setup.blade.php         - Rol configuratie UI
resources/views/local/dashboard.blade.php     - Server status dashboard
resources/views/local/standby-sync.blade.php  - Standby sync monitor
resources/views/local/health-dashboard.blade.php - Systeem health overzicht
resources/views/local/preflight.blade.php     - Pre-flight check wizard

# Launchers
start-server.bat                              - Windows launcher
start-server.command                          - Mac launcher
```

## URLs

| URL | Functie |
|-----|---------|
| `/local-server` | Dashboard |
| `/local-server/setup` | Rol configureren |
| `/local-server/opstarten` | **Startup wizard (stap-voor-stap)** |
| `/local-server/preflight` | Pre-flight check |
| `/local-server/standby-sync` | Standby sync monitor |
| `/local-server/health-dashboard` | Health overzicht |
| `/local-server/sync` | Sync data (JSON) |
| `/local-server/heartbeat` | Heartbeat endpoint |
| `/local-server/health` | Health check (JSON) |

## Status

**ALLE FASES COMPLEET** - Redundantie systeem is klaar voor gebruik.

---

## Laatste Sessie: 1 februari 2026

### Wat is gedaan:
- Startup wizard voor wedstrijddag (`/local-server/opstarten`)
- Computernaam prominent tonen op alle local server pagina's
- WP invoer: geen auto-fill meer, alleen eigen waarde
- JP invoer: blanco = reset, 0 = gelijkspel
- Groen/geel wedstrijd selectie verbeterd met bevestiging
- Help pagina bijgewerkt met nieuwe logica

### Openstaande items:
- [ ] Testen van redundantie systeem in productie

### Bekende issues/bugs:
- Geen
