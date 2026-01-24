# Session Handover 24 januari avond - Reverb Fix + Database Reset

## Wat is gedaan

### 1. Reverb Chat Fix (staging + production)
- **Probleem:** "Start Chat (Reverb)" knop gaf 404 error
- **Oorzaak:** `CheckToernooiRol` middleware kreeg string ipv model
- **Fix:** Handmatige model resolution toegevoegd wanneer route binding nog niet is gebeurd
- **Bestand:** `app/Http/Middleware/CheckToernooiRol.php:20-25`

### 2. Supervisor Socket Permissions (server)
- **Probleem:** PHP kon supervisorctl niet aanroepen (permission denied)
- **Oorzaak:** Socket had `chmod=0700`, alleen root kon erbij
- **Fix:** Gewijzigd naar `chmod=0770` + `chown=root:www-data`
- **Bestand:** `/etc/supervisor/supervisord.conf`

### 3. Database FK Issue (KRITIEK - veroorzaakte data verlies)
- **Probleem:** `SQLSTATE: no such table: main.judokas_backup`
- **Oorzaak:** Eerdere migration (`2026_01_23_204738_make_judoka_fields_nullable_for_portal.php`)
  hernoemde `judokas` naar `judokas_backup`, maar FK constraints in andere tabellen
  (`poule_judoka`, `wegingen`, `wedstrijden`) bleven naar `judokas_backup` verwijzen
- **SQLite quirk:** Bij tabel hernoemen worden FK referenties mee hernoemd
- **Fix:** `migrate:fresh` - **ALLE DATA GEWIST**

## Huidige staat

| Item | Status |
|------|--------|
| Local server | Draait op poort 8007 |
| Local database | LEEG (verse migratie) |
| Staging | Werkt (Reverb actief) |
| Production | Werkt (Reverb actief) |

## Te doen morgen

1. **Nieuw test-toernooi aanmaken** (lokale database is leeg)
2. **Judoka's importeren** voor testen
3. **Poules genereren** testen
4. **Coach check-in systeem** testen (zie handover 23-jan-avond)

## Commits

- `42c7af6` - fix: Use correct supervisor process name 'reverb' instead of 'reverb-production'
- (eerder) `b6e9167` - fix: Resolve toernooi model manually in middleware when not bound

## Lessen geleerd

**KRITIEK - Niet herhalen:**
- `migrate:fresh` wist ALLE data - altijd eerst waarschuwen/backup vragen
- Bij SQLite tabel hernoemen in migration: FK constraints in andere tabellen controleren
- SQLite quirk: FK referenties volgen tabel hernoemen

## Gerelateerde handovers
- `2026-01-23-avond-coach-inuitcheck.md` - Coach check-in systeem (nog te testen)
