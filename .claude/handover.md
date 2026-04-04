# Session Handover - JudoToernooi

> **Laatste update:** 5 april 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 4-5 april 2026

### Wat is gedaan:

**1. Production deploy (alle fixes van 3-4 april)**
- Dashboard UI professionalisering, poule solver, mat real-time updates, eliminatie broadcast fix
- Alle 5 pending migraties bleken al gedraaid (batch 74-75)
- Backup gemaakt: `voor-deploy-04apr_2026-04-04_09-42-06.sql.gz`

**2. Mollie knop roze → blauw**
- `edit.blade.php` regel 2463: `bg-pink-500` → `bg-blue-600`

**3. laravel-worker supervisor gefixt**
- Root cause: config wees naar `/var/www/production/artisan` (bestond niet)
- Gefixt naar `/var/www/judotoernooi/laravel/artisan` (prod) en `/var/www/judotoernooi/staging/artisan` (staging)
- Beide workers draaien nu RUNNING

**4. bandNaarNummer "bug" — GEEN BUG**
- DB slaat band op als string ("wit", "blauw") niet als integer
- `bandNaarNummer()` werkt correct — handover was incorrect

**5. Docs bijgewerkt**
- CLAUDE.md: magic link + passkeys bij login sectie
- context.md: betalingen + auth bij core features
- CLASSIFICATIE.md: wachtruimte OBSOLEET → beschrijvend
- MEMORY.md: stale entries opgeschoond

**6. Test coverage opgeschaald (19→397 tests, 941 assertions, alle groen)**
- `ToernooiModelTest.php` (18 tests): slug, codes, relationships, status, casts
- `PaymentResultTest.php` (8 tests): DTO status methods, fromMollie, constructor
- `OrganisatorAuthTest.php` (12 tests): GET pages, MagicLinkToken model, factory states
- `ToernooiControllerTest.php` (7 tests): dashboard auth/permissions, page loads
- `ClubModelTest.php` (11 tests): relationships, portal code/pincode, portal URL, findOrCreate
- `MatModelTest.php` (4 tests): relationships, label, nummer
- `BlokModelTest.php` (9 tests): relationships, casts, attributes, factory states, sluitWeging
- `WedstrijdSchemaServiceTest.php` (12 tests): round-robin, volgorde optimalisatie, punten competitie
- `DynamischeIndelingServiceTest.php` (16 tests): bandNaarNummer, berekenScore, statistieken
- `BlokMatVerdelingServiceTest.php` (13 tests): extractLeeftijd, extractGewicht, hashToewijzingen
- BlokFactory gefixt: verkeerde kolomnamen (naam→removed, label→blok_label, weging_eind→weging_einde)

**7. migrate:fresh safeguard gebouwd**
- `AppServiceProvider`: MigrationsStarted listener blokkeert migrate:fresh op server
- `SafeMigrateFresh` command: backup → fresh → restore (veilig alternatief)
- `BackupService`: `isServerEnvironment()` + `restoreFromBackup()` toegevoegd
- Aanleiding: staging DB gewist door RefreshDatabase in tests (4 apr incident)
- Staging DB hersteld uit dagelijkse backup (03:00)

**8. Avast SSL gefixt**
- Avast Web/Mail Shield Root cert geëxporteerd en toegevoegd aan `C:\laragon\etc\ssl\cacert.pem`
- `composer install --dev` werkt nu lokaal — tests draaien lokaal

**9. Broadcast bescherming structureel opgelost**
- `SafelyBroadcasts` trait overschrijft `dispatch()` met circuit breaker + try-catch + log throttle
- Alle 5 broadcast events beschermd: MatUpdate, ScoreboardEvent, ScoreboardAssignment, NewChatMessage, MatHeartbeat
- Reverb down → geen 500 errors, geen log spam, data altijd in DB
- Supervisor wrapper scripts ruimen zombie processen automatisch op bij herstart
- Docs: STABILITY.md, CLAUDE.md, MEMORY.md bijgewerkt

**10. LCD match_duration fix**
- Hardcoded 240s (4 min) → `Toernooi::getMatchDuration()` default 180s (3 min)
- Gefixt in: ScoreboardController, MatController (2x), scoreboard-live.blade.php (6x)

**11. Per-categorie wedstrijdinstellingen (shiai_time, shime/kansetsu waza)**
- 3 velden per categorie in `gewichtsklassen` JSON (geen migratie)
- Edit form: select (2:00/3:00/4:00/5:00) + 2 checkboxes met Nederlandse tooltips
- `Toernooi::getMatchDurationForCategorie()` + `getMatchRulesForCategorie()`
- MatController + ScoreboardController sturen categorie-specifieke duration + regels mee
- Deployed naar staging, production nog niet

### Openstaande items:
- [ ] **LCD timer/JP/osaekomi** — timer loopt niet, JP niet zichtbaar. Afhankelijk van scoreboard app events.
- [ ] **Deploy production** — categorie wedstrijdinstellingen + broadcast safeguards
- [ ] Coverage naar 60% target (nu ~40% geschat, 397 tests)
- [ ] POST feature tests: staging env mismatch (MySQL + middleware vs SQLite)

### Bekende issues:
- 4 medium PHP security vulnerabilities (league/commonmark 2x, league/flysystem 2x)

---

## Vorige Sessies

### 1 april 2026
- Bug fix: Live Matten tab publieke PWA — ontbrekende `>` op div tag
- Feedback: VSCode syntax errors altijd checken

### 31 maart 2026
- Biometrische login redirect + post-merge hooks

### Migraties (production):
Alle migraties gedraaid t/m batch 75 (4 april 2026).
