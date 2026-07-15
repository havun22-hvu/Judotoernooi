---
title: JudoToernooi Handover
type: claude
scope: judotoernooi
last_updated: 2026-07-15
---

# JudoToernooi — Handover

> **Één handover, bijwerken — nooit een sessieblok toevoegen.** Levende status, geen logboek.
> Afgerond = weg (git bewaart het). Max ~120 regels. Regel:
> `HavunCore/docs/kb/standards/md-doc-grootte.md`.

**Branch:** main (enige branch, geen open PR's) · **Status:** stabiel, Laravel 12.62, scoreboard 1.1.6.
**Staging = main** (gesynct 15-07). **Prod loopt achter op main** — security-fixes 15-07 nog niet gedeployd.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Prod-deploy** (`f3445e46`, `34bd9549`, `9f728fc0`) | Scoreboard-API-security: tenant-isolatie, token-lek, Reset trekt nu echt in, throttle, CORS. Plus device-toegangen UI-fix. Staat op main + staging, **niet op prod**. Deploy = jouw cue |
| **Staging bekijken/goedkeuren** | Blauw-positie scorebord (in Device Toegangen), gear-icoontje in header, Hantei (W) + Gelijkspel (G) in JP-dropdown, mat-rij Device Toegangen (mat boven, LCD onder, geen LCD-QR) |
| **Scoreboard end-to-end testen** | Nooit door jou geverifieerd na de scoreboard-wijzigingen |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md`. Kan Claude niet zelf |
| **Stale blok-1-selecties op prod** | Mat 1 / test-toernooi-2026: wis ze zelf via de amber banner (Blok 2 + Mat 1 → "Wis markeringen") |

## Open — te doen

- **CSP/HSTS-hardening** — uit de security-sweep van 25-06, bewust uitgesteld: vereist
  browser-verificatie.
- **Login/biometrie-punten (verifiëren, mogelijk achterhaald).** De login is 14-07 herbouwd op de
  KB-standaard `patterns/havun-mobile-login.md` (`140045ab`, staging). Check daarna nog of: passkey
  registreren alleen in account/`setup-pin` zit (niet op het loginscherm), en of de smartphone-PWA
  beperkt moet worden tot QR-scannen + intro (jouw wens, 26-06).
- **ShouldQueue voor MatUpdate/ScoreboardEvent** — optioneel: `ShouldBroadcastNow` → queued
  broadcast geeft retry bij tijdelijke Reverb-uitval. Lage prioriteit.
- **`routes/channels.php` is dode code** — wordt nooit geladen (geen `withBroadcasting()`), en alle
  callbacks doen `return true`. Opruimen, óf activeren als de kanalen ooit privé moeten.
- **`CheckDeviceBinding` gebruikt `$request->merge()`** met het DeviceToegang-model — zelfde
  anti-patroon als het token-lek van 15-07. Lekt nu niet (geen `$request->all()`-broadcast in dat
  pad) en `$hidden` dekt het af, maar het hoort naar `$request->attributes`. Raakt 12+ call-sites
  incl. de `$request->device_toegang` magic getter.

## Recent afgerond (context die nog nut heeft)

- **15-07 — Device Toegangen mat-rij rechtgezet** (`9f728fc0`, staging). Code-kolom links en
  knop-kolom rechts hadden LCD/Mat in omgekeerde volgorde. Nu beide mat-boven; "Interface" heet
  overal "Mat interface"; LCD-QR weg (TV heeft geen camera → koppelen via 4-cijferige code of
  korte URL); LCD-code toont alleen nog bij de mat-rol.
- **15-07 — Scoreboard-API security** (`f3445e46`, `34bd9549`). Vier lekken: `/result` scoopte niet
  op het toernooi van het token (elk token kon élk toernooi schrijven); `/event` broadcastte het hele
  `DeviceToegang`-record incl. `api_token` op een publiek kanaal; **Reset nulde `api_token` niet**
  (gereset apparaat schreef door) → Reset trekt nu token + code in; geen rate limit → 120/min per
  token (niet per IP: één NAT-IP per zaal). Review:
  `HavunCore/docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`.
  **Bewust:** Reverb-kanalen blijven publiek (Henk: "prima, als je de url weet").
- **14-07 — login herbouwd** op `havun-mobile-login` (`140045ab`, staging): breedte-heuristiek →
  `pointer:coarse`, QR nooit op smartphone, bio-timeout 2s.
- **03-07 — HavunClub-koppeling live op prod**: weegkaart-lookup, judoka-upsert, inschrijvingen,
  resultaten, school-portal. Additieve migraties. Contract:
  `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.
- **13-07 — mat-interface amber banner** (`28891b31`, prod): selecties buiten de weergave worden
  benoemd + wisbaar. Oorzaak was niet de kleurlogica maar mat-brede selecties bij een per-blok
  weergave.

## Vaste context voor dit project

- Artisan altijd met `cd laravel &&` prefix.
- Auth guard is `organisator` — **niet** `web`.
- DB: SQLite lokaal, MySQL productie. **Nooit tests draaien op staging/productie.**
- Realtime via Reverb/WebSockets — geen polling.
- Deploy: `git pull` in het repo-pad (`/var/www/judotoernooi/repo-prod`), **niet** in de symlink.
  Migraties alleen bij expliciete input; auto-migrate op prod mag niet.
- **Alpine draait op de `@alpinejs/csp` build:** geen `Alpine.evaluate(el, string)`, wél
  `Alpine.$data(el).method()` of `x-on:event.window`. Geen compound `@click` (`x = 1; method()`)
  → altijd een aparte methode op de component.
- **CSS-bundle meecommitten** bij nieuwe Tailwind-classes: de oude bundle mist ze anders op prod
  (kostte een deploy op 25-06 en 13-07).
- AutoFix kan server-wijzigingen maken vóór sessiestart → altijd `git pull` na een server-push.
