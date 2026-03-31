# Session Handover - JudoToernooi

> **Laatste update:** 31 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 31 maart 2026

### Wat is gedaan:

**500 error staging opgelost:**
- Oorzaak: `Route [wedstrijddag.mobiel] not defined` — gecachte route tabel na git pull
- Fix: route/config/view cache gecleared op staging

**Git post-merge hook (staging + production):**
- `.git/hooks/post-merge` aangemaakt op beide servers
- Draait automatisch `php artisan optimize:clear && optimize` na elke `git pull`
- Voorkomt verouderde caches na deploy
- Gedocumenteerd in `.claude/deploy.md`

**Biometrische login redirect (staging + production):**
- Probleem: biometrie-knop op login pagina faalde omdat er geen passkeys geregistreerd waren
- Oorzaak: gebruikers werden nooit naar de setup-pin/biometrie pagina geleid
- Fix: na wachtwoord-login, als gebruiker geen passkeys heeft → eenmalige redirect naar `/auth/setup-pin`
- Nieuw veld `biometric_prompted_at` op organisators tabel (wordt gezet bij redirect, voorkomt herhaling)
- Setup-pin pagina biedt PIN + biometrie (vingerafdruk/gezicht) aan op smartphones
- "Overslaan" link blijft beschikbaar → gebruiker kan later via Account Instellingen

**Strategische beslissing (door Henk):**
- Standaard login wordt: magic link → biometrie/QR code voor webapp
- Wordt doorgegeven aan HavunCore als standaard ontwerp voor alle projecten

### Openstaande items:
- [ ] Magic link als primaire login methode (nieuw standaard ontwerp, HavunCore)
- [ ] Biometrie login testen op telefoon (setup-pin flow na wachtwoord-login)
- [ ] 5+ pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] Staging heartbeat supervisor config nog niet aangemaakt
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL

### Belangrijke context voor volgende keer:
- Post-merge hook staat op BEIDE servers — caches worden automatisch gecleared na git pull
- `biometric_prompted_at` is NULL voor alle bestaande gebruikers → zij krijgen de prompt bij volgende login
- Setup-pin pagina (`/auth/setup-pin`) heeft al volledige PIN + biometrie flow
- PasskeyController gebruikt Laragear/WebAuthn package
- Henk wil magic link + biometrie/QR als standaard — dit is een groter herontwerp

---

## Vorige Sessie: 30 maart 2026

**Organisator Mobiel + Staging/Production PWA onderscheid**
Zie archive voor details.

### Pending migraties (production):
```
2026_03_21_200000_add_scoreboard_to_device_toegangen
2026_03_26_084039_add_toernooi_type_to_toernooien_table
2026_03_26_230142_add_is_gearchiveerd_to_toernooien_table
2026_03_28_192607_create_club_aanmeldingen_table
2026_03_28_204336_add_zichtbaar_op_agenda_to_toernooien_table
2026_03_31_233238_add_biometric_prompted_at_to_organisators_table (DONE)
```
