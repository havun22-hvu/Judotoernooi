# Session Handover - JudoToernooi

> **Laatste update:** 29 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 29 maart 2026

### Wat is gedaan:

**Publieke app — Mat expand/collapse:**
- Twee weergavemodi: overzicht (alle matten grid) en detail (1 mat groot)
- Vierkantje-icoon rechtsboven per mat om te vergroten/verkleinen
- Desktop: 2 kolommen, mobiel: onder elkaar
- Docs bijgewerkt in INTERFACES.md

**Staging upgrade korting (50%):**
- FreemiumService: `STAGING_KORTING = 0.5`, `pasKortingToe()`, `isStagingKorting()`
- Halve staffelprijzen op staging om testers niet af te schrikken
- Oranje banner op upgrade pagina: "Staging omgeving: 50% korting"
- Production prijzen ongewijzigd

**Server-side heartbeat broadcast (GROOT):**
- Nieuw: `ToernooiHeartbeat` artisan command — long-running, elke seconde broadcast mat-state via Reverb
- Nieuw: `MatHeartbeat` event — pusht volledige mat-data via WebSocket
- `MatUpdate` event zet cache key `toernooi:{id}:heartbeat_active` (15 min TTL)
- Publieke app ontvangt mat-data direct via WebSocket — geen HTTP polling meer
- Polling fallback volledig verwijderd uit publieke app
- Toggle: CLI (`toernooi:heartbeat-toggle {id} [--off]`) + UI (LIVE knop op wedstrijddag)
- Supervisor config aangemaakt op production: `toernooi-heartbeat` RUNNING
- Reverb production gefixt (stale process killed, nu via supervisor)

### Openstaande items:
- [ ] 5 pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] Staging heartbeat supervisor config nog niet aangemaakt (alleen production)
- [ ] Staging upgrade korting: nog niet op staging gedeployed + getest
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL — niet gerelateerd aan deze sessie

### Belangrijke context:
- Heartbeat process draait op production via supervisor (`toernooi-heartbeat`)
- Auto-start: bij elke mat actie (score/beurt) wordt heartbeat geactiveerd voor 15 min
- Handmatig: LIVE knop op wedstrijddag pagina of CLI command
- Reverb production draait weer correct (was FATAL door stale process op port 8080)

### Server status na sessie:
```
reverb                  RUNNING (production, port 8080)
reverb-staging          RUNNING (staging, port 8081)
toernooi-heartbeat      RUNNING (production)
laravel-worker          FATAL (niet gerelateerd)
laravel-worker-staging  FATAL (niet gerelateerd)
```

### Pending migraties (production):
```
2026_03_21_200000_add_scoreboard_to_device_toegangen
2026_03_26_084039_add_toernooi_type_to_toernooien_table
2026_03_26_230142_add_is_gearchiveerd_to_toernooien_table
2026_03_28_192607_create_club_aanmeldingen_table
2026_03_28_204336_add_zichtbaar_op_agenda_to_toernooien_table
```
