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
**Prod = staging = main** (15-07). Security-fixes staan live.

## Open — wacht op Henk

| Wat | Details |
|-----|---------|
| **Prod controleren na deploy 15-07** | Security-fixes + login-herbouw + device-toegangen staan nu **live op prod** (`20ff55bb`), zonder dat jij ze in de browser had gezien. Homepage 200, `/api/scoreboard/event` 401 (geen 500), 0 alerts, geen errors in de log. Backup vóór deploy: `judo_toernooi_handmatig_2026-07-15_16-57-43.sql.gz` |
| **Scoreboard end-to-end testen** | Nooit door jou geverifieerd, en de security-fix raakt precies dat pad (`CheckScoreboardToken`, `DeviceToegang`, `ScoreboardController`). Staat nu op prod |
| **Device-sweep** | Fysieke sweep op je P10 — `docs/3-DEVELOPMENT/DEVICE-TEST-CHECKLIST.md`. Kan Claude niet zelf |
| **Stale blok-1-selecties op prod** | Mat 1 / test-toernooi-2026: wis ze zelf via de amber banner (Blok 2 + Mat 1 → "Wis markeringen") |

## Open — te doen

- **MD-docs ronde 2** — negen docs zitten boven 8k tekens en zijn dus deels onvindbaar via
  `docs:search`, maar bleven onder de 200-regelnorm (`ELIMINATIE/README` 15.7k,
  `PRINTBARE-BRACKETS` 13.8k, `MAT-WEDSTRIJD-SELECTIE`, `DATABASE`, `CHAT`, `URL-STRUCTUUR`,
  `WEDSTRIJDSCHEMA`, `ONTWIKKELAAR`, `ELIMINATIE/FORMULES`). Recept + lijst:
  `.claude/plan-md-splitsing.md`. **Meet in tekens (`wc -c`), niet in regels.**
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

- **15-07 — MD-docs gesplitst tot index + deeldocs** (`34ce77ad`..`dc4684ff`). 13 docs van 355-1465
  regels → index (op de oude bestandsnaam, want code linkt erheen) + deeldocs in een gelijknamige
  map. Docs: 48 → 145. Inhoud verhuisd, niet herschreven — elke kop geverifieerd tegen het
  origineel. **Les:** de norm is tekens, niet regels. De indexer embed de eerste 8000 tekens en
  halveert bij een context-error naar 4000/2000 (`HavunCore DocIndexer:123`); `OVERPOULEN.md` was
  198 regels maar 12.411 tekens en dus grotendeels onvindbaar. Ronde 2 staat bij "Open — te doen".
- **15-07 — prod-deploy** (`20ff55bb`). Prod liep 20 commits achter; de scoreboard-API-security van
  die ochtend stond een dag ongedeployd. Geen migraties, geen dependency-wijzigingen — pull +
  cache-clear. Backup vooraf. Meegegaan zonder browser-goedkeuring: login-herbouw (`140045ab`) en
  de device-toegangen-fix.
- **15-07 — Device Toegangen mat-rij: één label per rij** (`9f728fc0` → `a6d98d3d`, prod). Codes en
  knoppen waren twee losse kolommen naast elkaar, dus een schermregel las
  `Mat interface | HQ6QALCGS9AQ | LCD | Kort Volledig Koppel TV` — twee labels op één regel.
  Eerst probeerde ik de linkerkolom om te draaien; dat zette het label er dubbel op. Fix: codes de
  knoppentabel in, één rij = label + code + knoppen. **Niet weer uit elkaar trekken.** LCD-QR weg
  (TV heeft geen camera). Doc: `docs/2-FEATURES/SCOREBORD/TV-LCD-URLS.md`. Code-kolom links en
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
