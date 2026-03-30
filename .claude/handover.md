# Session Handover - JudoToernooi

> **Laatste update:** 30 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 30 maart 2026

### Wat is gedaan:

**Organisator Mobiel (NIEUW):**
- Responsive mobiele view voor organisatoren die door de zaal lopen op wedstrijddag
- 4 tabs: Zoeken, Toevoegen, Matten, Chat
- Tab 1: Judoka zoeken op naam/club → gewicht invullen → poule bekijken → overpoulen
- Tab 2: Nieuwe judoka toevoegen aan poule
- Tab 3: Mat voortgang (resterende wedstrijden per mat + per poule, uitklapbaar)
- Tab 4: Chat widget (bestaand systeem, als hoofdjury)
- "Mobiel" knop op dashboard header
- Hint: "Volledige voorbereiding? Open de app op tablet of PC"
- Nieuwe routes: wedstrijddag/mobiel, wedstrijddag/mat-voortgang, wedstrijddag/poules-api
- Planning doc + INTERFACES.md + KB bijgewerkt

**Staging/Production PWA onderscheid:**
- Dynamisch manifest.json (route i.p.v. statisch bestand)
- Staging: naam "JTStag", oranje theme_color, oranje icoon met "STAGING" banner
- Production: ongewijzigd ("JudoToernooi", blauw)

### Openstaande items:
- [ ] Organisator Mobiel testen op staging (functioneel)
- [ ] 5 pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] Staging heartbeat supervisor config nog niet aangemaakt
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL

---

## Vorige Sessie: 29 maart 2026

**Publieke app — Mat expand/collapse, Staging korting, Heartbeat broadcast**

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
