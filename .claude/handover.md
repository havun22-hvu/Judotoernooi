# Session Handover

> **Status:** Production live op https://judotournament.org
> **Coverage:** 89.6% (3215 tests, minimum 82.5%)

## Openstaand

- **Chromecast Cast** — device + receiver URL registreren in henkvu Developer Console, testen met App ID `47CF3728`
- **LCD winnaar-bug** — verifiëren op staging
- **LCD TV-setup handleiding** voor organisatoren
- **LCD proporties vergroten voor TV-leesbaarheid** + LCD link in device-toegangen naar `/tv/{4-char}` formaat (zie `laravel/docs/2-FEATURES/SCOREBORD-APP.md → Openstaand`)

## Bekende aandachtspunten

- Brevo SMTP credits op → alle emails uit, admin panel (`/admin/autofix`) is enige notificatie-kanaal. `ErrorNotificationService` slaat errors op in `autofix_proposals` met status `error`.
- Cast: alles op **henkvu@gmail.com** (Chrome, Google Home, Developer Console). Oude App ID was `C11C3563` op havun22 — niet meer gebruiken.
- `staging_judo_toernooi.jobs` tabel ontbreekt op staging.
- Production `.env` heeft dubbele `MAIL_MAILER` entry (opruimen bij volgende deploy).

## Recente sessies (voor context — git log = bron van waarheid)

**21 apr 2026** Docs cleanup: staging/ duplicate (609 files) + `.claude/archive/` verwijderd; doc-issues 0 open; INSTALLATIE/CLASSIFICATIE anchor gefixt.
**20 apr 2026** Test-herstel + security: NPM audit fix (picomatch, rollup), `SESSION_SECURE_COOKIE=true`, CI storage/framework/views fix; ~2590 regels nieuwe tests (auth, middleware, models, services).
**6-10 apr 2026** Security patches (5 PHP CVEs); email → admin panel migratie; Chromecast account-mismatch root cause; TV koppelsysteem (4-cijferige code + QR); Reverb broadcasting failure fixes (SafelyBroadcasts trait, reverb:health, BroadcastConfigValidator).

Alle code-wijzigingen: `git log --since="..."`.
